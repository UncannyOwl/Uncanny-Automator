<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\WhatsApp;

use Uncanny_Automator\Recipe\App_Trigger;

/**
 * Class WA_MESSAGE_STATUS_UPDATED
 *
 * @package Uncanny_Automator
 * @property WhatsApp_Helpers $helpers
 * @property WhatsApp_Api_Caller $api
 * @property WhatsApp_Webhooks $webhooks
 */
class WA_MESSAGE_STATUS_UPDATED extends App_Trigger {

	/**
	 * Static trigger definition for lazy-loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'WA_MESSAGE_STATUS_UPDATED', 'WHATSAPP' )
			->trigger_meta( 'WA_MESSAGE_STATUS_UPDATED_META' )
			->trigger_type( 'anonymous' )
			->hook( 'automator_whatsapp_message_status', 10, 2 );
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
			sprintf(
				// translators: %1$s is the trigger meta.
				esc_html_x( 'A message to a recipient is set to {{a specific:%1$s}} status', 'WhatsApp', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'A message to a recipient is set to {{a specific}} status', 'WhatsApp', 'uncanny-automator' )
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
			'tokenId'   => 'SENDER',
			'tokenName' => esc_html_x( 'Sender', 'WhatsApp', 'uncanny-automator' ),
			'tokenType' => 'text',
		);
		$tokens[] = array(
			'tokenId'   => 'RECIPIENT',
			'tokenName' => esc_html_x( 'Recipient', 'WhatsApp', 'uncanny-automator' ),
			'tokenType' => 'text',
		);
		$tokens[] = array(
			'tokenId'   => 'STATUS',
			'tokenName' => esc_html_x( 'Status', 'WhatsApp', 'uncanny-automator' ),
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
			'SENDER'    => sanitize_text_field( $response['from'] ?? '' ),
			'RECIPIENT' => sanitize_text_field( $response['to'] ?? '' ),
			'STATUS'    => sanitize_text_field( $hook_args[1] ?? '' ),
		);
	}

	/**
	 * Load options for the trigger.
	 *
	 * @return array The options.
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Status', 'WhatsApp', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(
					array(
						'text'  => esc_html_x( 'Sent', 'WhatsApp', 'uncanny-automator' ),
						'value' => 'sent',
					),
					array(
						'text'  => esc_html_x( 'Delivered', 'WhatsApp', 'uncanny-automator' ),
						'value' => 'delivered',
					),
					array(
						'text'  => esc_html_x( 'Read', 'WhatsApp', 'uncanny-automator' ),
						'value' => 'read',
					),
				),
				'relevant_tokens' => array(),
			),
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
		$status = $hook_args[1] ?? '';
		if ( empty( $status ) ) {
			return false;
		}

		// Check if the incoming status matches the trigger's configured status.
		$selected_status = $trigger['meta'][ $this->get_trigger_meta() ] ?? '';
		if ( $status !== $selected_status ) {
			return false;
		}

		$response = $hook_args[0] ?? array();

		return $this->webhooks->is_unique_webhook_event( $response['wamid'] ?? '', $status );
	}
}
