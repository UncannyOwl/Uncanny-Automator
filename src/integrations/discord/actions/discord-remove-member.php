<?php
namespace Uncanny_Automator\Integrations\Discord;

use Exception;

/**
 * Class DISCORD_REMOVE_MEMBER
 *
 * @package Uncanny_Automator
 */
class DISCORD_REMOVE_MEMBER extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Prefix for action code / meta.
	 *
	 * @var string
	 */
	public $prefix = 'DISCORD_REMOVE_MEMBER';

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
				// translators: %1$s Member name
				esc_attr_x( 'Remove {{a member:%1$s}}', 'Discord', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Remove {{a member}}', 'Discord', 'uncanny-automator' ) );
		$this->set_action_tokens(
			array(
				'SERVER_ID'   => array(
					'name' => _x( 'Server ID', 'Discord', 'uncanny-automator' ),
					'type' => 'text',
				),
				'SERVER_NAME' => array(
					'name' => _x( 'Server name', 'Discord', 'uncanny-automator' ),
					'type' => 'text',
				),
				'USERNAME'    => array(
					'name' => _x( 'Username', 'Discord', 'uncanny-automator' ),
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

		// Prepare the body.
		$body = array(
			'action'    => 'remove_member',
			'member_id' => $member_id,
		);

		// Send the message.
		$response = $this->helpers->api()->api_request( $body, $action_data, $server_id );

		// Check for errors.
		$status_code = isset( $response['statusCode'] ) ? absint( $response['statusCode'] ) : 0;
		if ( 204 !== $status_code ) {
			throw new Exception( esc_html_x( 'Error removing member.', 'Discord', 'uncanny-automator' ) );
		}

		// Remove the member from the cached list.
		$this->remove_member_from_cache( $server_id, $member_id );

		// Hydrate tokens.
		$this->hydrate_tokens(
			array(
				'SERVER_ID'   => $server_id,
				'SERVER_NAME' => $parsed[ $this->server_key . '_readable' ],
				'USERNAME'    => $parsed[ $this->get_action_meta() . '_readable' ],
			)
		);

		return true;
	}

	/**
	 * Remove a member from the cached option data.
	 *
	 * @param string $server_id
	 * @param string $member_id
	 *
	 * @return void
	 */
	private function remove_member_from_cache( $server_id, $member_id ) {

		$key     = 'DISCORD_MEMBERS_' . $server_id;
		$members = automator_get_option( $key, array() );

		if ( ! empty( $members ) ) {
			$members = array_filter(
				$members,
				function ( $member ) use ( $member_id ) {
					return $member['value'] !== $member_id;
				}
			);
			automator_update_option( $key, $members, false );
		}
	}
}
