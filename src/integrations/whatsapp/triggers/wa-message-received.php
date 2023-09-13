<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

use Uncanny_Automator\Recipe;

/**
 * Class WA_MESSAGE_RECEIVED
 *
 * @package Uncanny_Automator
 */
class WA_MESSAGE_RECEIVED {

	use Recipe\Triggers;

	/**
	 * Constant TRIGGER_CODE.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'WA_MESSAGE_RECEIVED';

	/**
	 * Constant TRIGGER_META.
	 *
	 * @var string
	 */
	const TRIGGER_META = 'WA_MESSAGE_RECEIVED_META';

	/**
	 * The WhatsApp tokens.
	 *
	 * @var Wa_Message_Received_Tokens $whatsapp_tokens.
	 */
	public $whatsapp_tokens = array();

	public function __construct() {

		$this->whatsapp_tokens = new Wa_Message_Received_Tokens();

		$this->setup_trigger();

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

		$this->set_is_login_required( false );

		$this->set_trigger_type( 'anonymous' );

		// The action hook to attach this trigger into.
		$this->add_action( 'automator_whatsapp_webhook_message_received' );

		$this->set_uses_api( true );

		// The number of arguments that the action hook accepts.
		$this->set_action_args_count( 1 );

		/* Translators: Trigger sentence */
		$this->set_sentence( sprintf( esc_html__( 'A message is received', 'uncanny-automator' ) ) );

		/* Translators: Trigger sentence */
		$this->set_readable_sentence( esc_html__( 'A message is received', 'uncanny-automator' ) );

		$this->set_tokens(
			$this->whatsapp_tokens->message_received_tokens()
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

		$name = 'automator_whatsapp_' . $response['wamid'] . '_incoming';

		if ( false !== get_transient( $name ) ) {
			return false;
		}

		set_transient( $name, 'yes', 60 ); // Expire in 1 minute.

		// Flush the transient after 60s.
		wp_schedule_single_event( time() + 60, 'automator_whatsapp_flush_transient', array( $name ) );

		return true;

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
