<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\WhatsApp;

use Uncanny_Automator\Recipe\App_Action;
use Exception;

/**
 * Class WHATSAPP_SEND_MESSAGE
 *
 * @package Uncanny_Automator
 * @property WhatsApp_Helpers $helpers
 * @property WhatsApp_Api_Caller $api
 * @property WhatsApp_Webhooks $webhooks
 */
class WHATSAPP_SEND_MESSAGE extends App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WHATSAPP' );
		$this->set_action_code( 'WHATSAPP_SEND_MESSAGE_CODE' );
		$this->set_action_meta( 'WHATSAPP_SEND_MESSAGE_META' );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/whatsapp/' ) );
		$this->set_is_pro( false );
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				// translators: %1$s is the action meta for the phone number field.
				esc_html_x( 'Send a WhatsApp message to {{a number:%1$s}}', 'WhatsApp', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'Send a WhatsApp message to {{a number}}', 'WhatsApp', 'uncanny-automator' )
		);
	}

	/**
	 * Load the options for the action.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'           => $this->get_action_meta(),
				'label'                 => esc_attr_x( 'To', 'WhatsApp', 'uncanny-automator' ),
				'description'           => esc_attr_x( 'The recipient must have opted-in to receive text messages from your number.', 'WhatsApp', 'uncanny-automator' ),
				'input_type'            => 'text',
				'placeholder'           => esc_attr_x( '+1 123 345 6789', 'WhatsApp', 'uncanny-automator' ),
				'required'              => true,
				'supports_token'        => true,
				'supports_custom_value' => true,
			),
			array(
				'option_code'           => $this->get_action_meta() . '_body',
				'label'                 => esc_attr_x( 'Body', 'WhatsApp', 'uncanny-automator' ),
				'input_type'            => 'textarea',
				'required'              => true,
				'supports_token'        => true,
				'supports_custom_value' => true,
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int $user_id The user id.
	 * @param array $action_data The action data.
	 * @param int $recipe_id The recipe id.
	 * @param array $args The args.
	 * @param array $parsed The parsed data.
	 *
	 * @return bool
	 * @throws Exception If the API request fails.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$to      = sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' );
		$message = sanitize_textarea_field( $parsed[ $this->get_action_meta() . '_body' ] ?? '' );

		// Prepare the body.
		$body = array(
			'action'   => 'send_message',
			'to'       => $to,
			'message'  => $message,
			'phone_id' => $this->helpers->get_phone_number_id(),
		);

		try {
			$response                           = $this->api->api_request( $body, $action_data );
			$this->action_data['args']['await'] = array(
				'whatsapp_response' => $response,
			);
			wp_schedule_single_event( time() + 60, 'automator_whatsapp_webhook_noresponse_closure', array( $response ) );
			return true;
		} catch ( Exception $e ) {
			throw new Exception( esc_html( $e->getMessage() ) );
		}
	}
}
