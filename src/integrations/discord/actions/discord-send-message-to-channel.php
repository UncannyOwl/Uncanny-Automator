<?php
namespace Uncanny_Automator\Integrations\Discord;

use Exception;

/**
 * Class DISCORD_SEND_MESSAGE_TO_CHANNEL
 *
 * @package Uncanny_Automator
 */
class DISCORD_SEND_MESSAGE_TO_CHANNEL extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Prefix for action code / meta.
	 *
	 * @var string
	 */
	public $prefix = 'DISCORD_SEND_MESSAGE_TO_CHANNEL';

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
				esc_attr_x( 'Send a message to {{a channel:%1$s}}', 'Discord', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Send a message to {{a channel}}', 'Discord', 'uncanny-automator' ) );
		$this->set_action_tokens(
			array(
				'SERVER_ID'    => array(
					'name' => _x( 'Server ID', 'Discord', 'uncanny-automator' ),
					'type' => 'text',
				),
				'SERVER_NAME'  => array(
					'name' => _x( 'Server name', 'Discord', 'uncanny-automator' ),
					'type' => 'text',
				),
				'CHANNEL_NAME' => array(
					'name' => _x( 'Channel name', 'Discord', 'uncanny-automator' ),
					'type' => 'text',
				),
				'CHANNEL_ID'   => array(
					'name' => _x( 'Channel ID', 'Discord', 'uncanny-automator' ),
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
			$this->helpers->get_server_channel_select_config( $this->get_action_meta(), $this->server_key ),
			array(
				'option_code' => 'MESSAGE',
				'label'       => _x( 'Message', 'Discord', 'uncanny-automator' ),
				'input_type'  => 'textarea',
				'required'    => true,
				'description' => _x( 'Enter the message you want to send.', 'Discord', 'uncanny-automator' ),
			),
			array(
				'option_code'     => 'TTS',
				'label'           => _x( 'TTS', 'Discord', 'uncanny-automator' ),
				'input_type'      => 'checkbox',
				'is_toggle'       => true,
				'description'     => _x( 'Enable text to speech.', 'Discord', 'uncanny-automator' ),
				'relevant_tokens' => array(),
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
		$server_id  = $this->helpers->get_server_id_from_parsed( $parsed, $this->server_key );
		$channel_id = $this->helpers->get_channel_id_from_parsed( $parsed, $this->get_action_meta(), $server_id );
		$message    = $this->helpers->get_message_from_parsed( $parsed, 'MESSAGE' );
		// Optional fields.
		$tts = $this->helpers->get_bool_value( $this->get_parsed_meta_value( 'TTS', false ) );

		// Prepare the body.
		$body = array(
			'action'     => 'send_message_to_channel',
			'channel_id' => $channel_id,
			'message'    => $message,
			'tts'        => $tts,
		);

		// Send the message.
		$response = $this->helpers->api()->api_request( $body, $action_data, $server_id );

		// REVIEW - Check for errors.

		// Hydrate tokens.
		$this->hydrate_tokens(
			array(
				'SERVER_ID'    => $server_id,
				'SERVER_NAME'  => $parsed[ $this->server_key . '_readable' ],
				'CHANNEL_ID'   => $channel_id,
				'CHANNEL_NAME' => $parsed[ $this->get_action_meta() . '_readable' ],
			)
		);

		return true;
	}

}
