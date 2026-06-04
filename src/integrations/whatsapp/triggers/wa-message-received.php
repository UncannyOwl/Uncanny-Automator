<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\WhatsApp;

use Uncanny_Automator\Recipe\App_Trigger;

/**
 * Class WA_MESSAGE_RECEIVED
 *
 * @package Uncanny_Automator
 * @property WhatsApp_Helpers $helpers
 * @property WhatsApp_Api_Caller $api
 * @property WhatsApp_Webhooks $webhooks
 */
class WA_MESSAGE_RECEIVED extends App_Trigger {

	/**
	 * Static trigger definition for lazy-loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'WA_MESSAGE_RECEIVED', 'WHATSAPP' )
			->trigger_meta( 'WA_MESSAGE_RECEIVED_META' )
			->trigger_type( 'anonymous' )
			->hook( 'automator_whatsapp_webhook_message_received' );
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type / hook are auto-applied from definition().
		$this->set_is_login_required( false );
		$this->set_uses_api( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_trigger_code(), 'knowledge-base/whatsapp/' ) );

		$this->set_sentence(
			esc_html_x( 'A message is received', 'WhatsApp', 'uncanny-automator' )
		);

		$this->set_readable_sentence(
			esc_html_x( 'A message is received', 'WhatsApp', 'uncanny-automator' )
		);
	}

	/**
	 * Define tokens for the trigger.
	 *
	 * @param array $trigger The trigger data.
	 * @param array $tokens The existing tokens.
	 *
	 * @return array The tokens.
	 */
	public function define_tokens( $trigger, $tokens ) {
		$tokens[] = array(
			'tokenId'   => 'MESSAGE',
			'tokenName' => esc_html_x( 'Message', 'WhatsApp', 'uncanny-automator' ),
			'tokenType' => 'text',
		);
		$tokens[] = array(
			'tokenId'   => 'SENDER',
			'tokenName' => esc_html_x( 'Sender', 'WhatsApp', 'uncanny-automator' ),
			'tokenType' => 'text',
		);
		$tokens[] = array(
			'tokenId'   => 'SENDER_PROFILE_NAME',
			'tokenName' => esc_html_x( 'Sender profile name', 'WhatsApp', 'uncanny-automator' ),
			'tokenType' => 'text',
		);
		return $tokens;
	}

	/**
	 * Hydrate tokens with their values.
	 *
	 * @param array $trigger The trigger data.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array The token values.
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		$response = $hook_args[0] ?? array();
		return array(
			'MESSAGE'             => sanitize_text_field( $response['body'] ?? '' ),
			'SENDER'              => sanitize_text_field( $response['from'] ?? '' ),
			'SENDER_PROFILE_NAME' => sanitize_text_field( $response['_response']['entry'][0]['changes'][0]['value']['contacts'][0]['profile']['name'] ?? '' ),
		);
	}

	/**
	 * Validate the trigger.
	 *
	 * @param array $trigger The trigger data.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool True if trigger should fire, false otherwise.
	 */
	public function validate( $trigger, $hook_args ) {
		$response = $hook_args[0] ?? array();

		return $this->webhooks->is_unique_webhook_event( $response['wamid'] ?? '', 'incoming' );
	}
}
