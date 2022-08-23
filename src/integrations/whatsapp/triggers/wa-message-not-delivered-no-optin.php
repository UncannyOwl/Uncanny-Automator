<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

use Uncanny_Automator\Recipe;

/**
 * Class WA_MESSAGE_NOT_DELIVERED_NO_OPTIN
 *
 * @package Uncanny_Automator
 */
class WA_MESSAGE_NOT_DELIVERED_NO_OPTIN {

	use Recipe\Triggers;

	/**
	 * Constant TRIGGER_CODE.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'WA_MESSAGE_NOT_DELIVERED_NO_OPTIN';

	/**
	 * Constant TRIGGER_META.
	 *
	 * @var string
	 */
	const TRIGGER_META = 'WA_MESSAGE_NOT_DELIVERED_NO_OPTIN_META';

	/**
	 * Constant NO_OPTIN_ERR_CODE
	 *
	 * @see https://developers.facebook.com/docs/whatsapp/cloud-api/support/error-codes/
	 *
	 * @var integer
	 */
	const NO_OPTIN_ERR_CODE = 131047;

	public function __construct() {

		if ( class_exists( '\Uncanny_Automator\WhatsApp_Helpers' ) ) {

			$this->setup_trigger();

		}

	}

	/**
	 * Define and register the trigger by pushing it into the Automator object.
	 *
	 * @return void.
	 */
	public function setup_trigger() {

		$this->set_integration( 'WHATSAPP' );

		$this->set_trigger_code( self::TRIGGER_CODE );

		$this->set_trigger_meta( self::TRIGGER_META );

		$this->set_is_pro( false );

		$this->set_is_login_required( false );

		$this->set_trigger_type( 'anonymous' );

		// The action hook to attach this trigger into.
		$this->add_action( 'automator_whatsapp_message_delivery_failed' );

		$this->set_uses_api( true );

		// The number of arguments that the action hook accepts.
		$this->set_action_args_count( 1 );

		$this->set_sentence(
			sprintf(
				/* Translators: Trigger sentence */
				esc_html__( 'A message to a recipient is not delivered because they have not opted in', 'uncanny-automator' )
			)
		);

		$this->set_readable_sentence(
			/* Translators: Trigger sentence */
			esc_html__( 'A message to a recipient is not delivered because they have not opted in', 'uncanny-automator' )
		);

		// Register the trigger.
		$this->register_trigger();

	}

	/**
	 * Validate the trigger.
	 *
	 * @return boolean True.
	 */
	public function validate_trigger( ...$args ) {

		$response = $args[0][0];

		$error_code = isset( $response['errors']['code'] ) ? $response['errors']['code'] : null;

		return ! empty( $error_code ) && self::NO_OPTIN_ERR_CODE === $error_code;

	}

	/**
	 * Prepare to run.
	 *
	 * Sets the conditional trigger to true.
	 *
	 * @return void.
	 */
	public function prepare_to_run( $data ) {

		$this->set_conditional_trigger( false );

	}

	/**
	 * Continue trigger process even for logged-in user.
	 *
	 * @return boolean True.
	 */
	public function do_continue_anon_trigger( ...$args ) {

		return true;

	}

}
