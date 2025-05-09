<?php
namespace Uncanny_Automator\Integrations\Discord;

use Exception;

/**
 * Class DISCORD_UPDATE_MEMBER
 *
 * @package Uncanny_Automator
 */
class DISCORD_UPDATE_MEMBER extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Prefix for action code / meta.
	 *
	 * @var string
	 */
	public $prefix = 'DISCORD_UPDATE_MEMBER';

	/**
	 * Server meta key.
	 *
	 * @var string
	 */
	private $server_key;

	/**
	 * Set up action.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->helpers    = array_shift( $this->dependencies );
		$this->server_key = $this->helpers->get_constant( 'ACTION_SERVER_META_KEY' );

		$this->set_integration( 'DISCORD' );
		$this->set_action_code( $this->prefix . '_CODE' );
		$this->set_action_meta( $this->prefix . '_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/discord/' ) );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				// translators: %1$s Member name, %2$s Channel name
				esc_attr_x( 'Update {{a member:%1$s}}', 'Discord', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Update {{a member}}', 'Discord', 'uncanny-automator' ) );
		$this->set_action_tokens(
			array(
				'SERVER_ID'   => array(
					'name' => esc_html_x( 'Server ID', 'Discord', 'uncanny-automator' ),
					'type' => 'text',
				),
				'SERVER_NAME' => array(
					'name' => esc_html_x( 'Server name', 'Discord', 'uncanny-automator' ),
					'type' => 'text',
				),
				'USERNAME'    => array(
					'name' => esc_html_x( 'Username', 'Discord', 'uncanny-automator' ),
					'type' => 'text',
				),
				'ROLE_NAMES'  => array(
					'name' => esc_html_x( 'Role name(s)', 'Discord', 'uncanny-automator' ),
					'type' => 'text',
				),
			),
			$this->get_action_code()
		);
	}

	/**
	 * Define options
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_server_select_config( $this->server_key ),
			$this->helpers->get_server_members_select_config( $this->get_action_meta(), $this->server_key ),
			array(
				'option_code' => 'NICKNAME',
				'label'       => esc_html_x( 'Nickname', 'Discord', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
			array(
				'option_code' => 'DISABLE_COMMUNICATIONS',
				'label'       => esc_html_x( 'Disable communications until (in days)', 'Discord', 'uncanny-automator' ),
				'input_type'  => 'text',
				'max_number'  => 28,
				'required'    => false,
				'description' => esc_html_x( "Amount of days when the user's timeout will expire and the user will be able to communicate in the server again ( up to 28 days in the future ). To remove an existing timeout, set to [DELETE] including square brackets.", 'Discord', 'uncanny-automator' ),
			),
			$this->helpers->get_server_roles_select_config(
				'ROLES',
				$this->server_key,
				array(
					'required'                 => false,
					'supports_multiple_values' => true,
				)
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 * @throws Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		// Required fields - throws error if not set and valid.
		$server_id = $this->helpers->get_server_id_from_parsed( $parsed, $this->server_key );
		$member_id = $this->helpers->get_member_id_from_parsed( $parsed, $this->get_action_meta() );

		// Optional fields for update.
		$update = array();

		// Nickname.
		$nick = sanitize_text_field( $this->get_parsed_meta_value( 'NICKNAME', '' ) );
		if ( ! empty( $nick ) ) {
			$update['nick'] = $nick;
		}

		// Timeout days.
		$timeout = sanitize_text_field( $this->get_parsed_meta_value( 'DISABLE_COMMUNICATIONS', '' ) );
		if ( ! empty( $timeout ) ) {
			$timeout = '[DELETE]' === strtoupper( $timeout ) ? null : absint( $timeout );
			if ( ! is_null( $timeout ) ) {
				// Check for max timeout.
				if ( $timeout > 28 ) {
					throw new Exception( esc_html_x( 'Timeout can not be more than 28 days.', 'Discord', 'uncanny-automator' ) );
				}
				// Convert # of days to ISO8601 timestamp.
				$timeout = gmdate( 'c', strtotime( "+{$timeout} days" ) );
			}
			$update['communication_disabled_until'] = $timeout;
		}

		// Roles.
		$role_names = '';
		$roles      = sanitize_text_field( $this->get_parsed_meta_value( 'ROLES', array() ) );

		if ( ! empty( $roles ) ) {

			$role_names = $this->get_parsed_meta_value( 'ROLES_readable', array() );

			// User manually selected options.
			if ( is_array( $roles ) ) {
				$update['roles'] = $roles;
			} else {
				// User used a custom value.
				$update['roles'] = array( $roles );
				// Get role name using helper method
				$role_names = $this->helpers->get_role_name_token_value(
					$role_names, // Custom value text
					$roles, // role ID
					$server_id
				);
			}
		}

		if ( empty( $update ) ) {
			throw new Exception( esc_html_x( 'No update fields provided.', 'Discord', 'uncanny-automator' ) );
		}

		// Prepare the body.
		$body = array(
			'action'    => 'update_member',
			'member_id' => $member_id,
			'args'      => wp_json_encode( $update ),
		);

		// Make request
		$response = $this->helpers->api()->api_request( $body, $action_data, $server_id );

		// Check for errors.
		$status_code = isset( $response['statusCode'] ) ? absint( $response['statusCode'] ) : 0;
		if ( 200 !== $status_code ) {
			throw new Exception( esc_html_x( 'Error updating member.', 'Discord', 'uncanny-automator' ) );
		}

		// Hydrate tokens.
		$this->hydrate_tokens(
			array(
				'SERVER_ID'   => $server_id,
				'SERVER_NAME' => $parsed[ $this->server_key . '_readable' ],
				'USERNAME'    => $this->helpers->get_member_username_token_value(
					$parsed[ $this->get_action_meta() . '_readable' ],
					$member_id,
					$server_id
				),
				'ROLE_NAMES'  => $role_names,
			)
		);

		return true;
	}
}
