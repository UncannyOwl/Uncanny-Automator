<?php
namespace Uncanny_Automator\Integrations\Discord;

use Exception;

/**
 * Class DISCORD_SEND_MESSAGE_TO_CHANNEL
 *
 * @package Uncanny_Automator
 */
class DISCORD_SEND_DIRECT_MESSAGE_TO_MEMBER extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Prefix for action code / meta.
	 *
	 * @var string
	 */
	public $prefix = 'DISCORD_SEND_DIRECT_MESSAGE_TO_MEMBER';

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
				// translators: %1$s Channel name
				esc_attr_x( 'Send a direct message to {{a Discord member:%1$s}}', 'Discord', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Send a direct message to {{a Discord member}}', 'Discord', 'uncanny-automator' ) );
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
			$this->helpers->get_verified_members_select_config( $this->get_action_meta() ),
			array(
				'option_code' => 'MESSAGE',
				'label'       => esc_html_x( 'Message', 'Discord', 'uncanny-automator' ),
				'input_type'  => 'textarea',
				'required'    => true,
				'description' => esc_html_x( 'Enter the message you want to send.', 'Discord', 'uncanny-automator' ),
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
		$message   = $this->helpers->get_message_from_parsed( $parsed, 'MESSAGE' );

		// Prepare the body.
		$body = array(
			'action'    => 'send_direct_message_to_member',
			'member_id' => $member_id,
			'message'   => $message,
		);

		// Send the message.
		$response = $this->helpers->api()->api_request( $body, $action_data, $server_id );

		// REVIEW - Check for errors?

		// Hydrate tokens.
		$this->hydrate_tokens(
			array(
				'SERVER_ID'   => $server_id,
				'SERVER_NAME' => $parsed[ $this->server_key . '_readable' ],
				'USERNAME'    => $this->helpers->get_member_username_token_value( $member_id ),
			)
		);

		return true;
	}
}
