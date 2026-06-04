<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\WhatsApp;

use Uncanny_Automator\Recipe\App_Trigger;

/**
 * Class WA_MESSAGE_NOT_DELIVERED
 *
 * @package Uncanny_Automator
 * @property WhatsApp_Helpers $helpers
 * @property WhatsApp_Api_Caller $api
 * @property WhatsApp_Webhooks $webhooks
 */
class WA_MESSAGE_NOT_DELIVERED extends App_Trigger {

	/**
	 * Static trigger definition for lazy-loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'WA_MESSAGE_NOT_DELIVERED', 'WHATSAPP' )
			->trigger_meta( 'WA_MESSAGE_NOT_DELIVERED_META' )
			->trigger_type( 'anonymous' )
			->hook( 'automator_whatsapp_message_delivery_failed' );
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
			esc_attr_x( 'A message to a recipient was not delivered', 'WhatsApp', 'uncanny-automator' )
		);

		$this->set_readable_sentence(
			esc_attr_x( 'A message to a recipient was not delivered', 'WhatsApp', 'uncanny-automator' )
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

		return $this->webhooks->is_unique_webhook_event( $response['wamid'] ?? '', 'incoming_failure_message' );
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
		// Token IDs are intentionally lowercase for backwards compatibility with legacy token class.
		$tokens[] = array(
			'tokenId'   => 'recipient_number',
			'tokenName' => esc_attr_x( 'Recipient number', 'WhatsApp', 'uncanny-automator' ),
			'tokenType' => 'text',
		);
		$tokens[] = array(
			'tokenId'   => 'message',
			'tokenName' => esc_attr_x( 'Message', 'WhatsApp', 'uncanny-automator' ),
			'tokenType' => 'text',
		);
		$tokens[] = array(
			'tokenId'   => 'delivery_error',
			'tokenName' => esc_attr_x( 'Delivery error', 'WhatsApp', 'uncanny-automator' ),
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
			'recipient_number' => sanitize_text_field( $response['to'] ?? '' ),
			'message'          => sanitize_text_field( $response['errors']['message'] ?? '' ),
			'delivery_error'   => sanitize_text_field( $response['errors']['code'] ?? '' ),
		);
	}
}
