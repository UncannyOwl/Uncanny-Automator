<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

use Uncanny_Automator\Recipe;

/**
 * Class WA_MESSAGE_NOT_DELIVERED
 *
 * @package Uncanny_Automator
 */
class WA_MESSAGE_NOT_DELIVERED {

	use Recipe\Triggers;

	/**
	 * Constant TRIGGER_CODE.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'WA_MESSAGE_NOT_DELIVERED';

	/**
	 * Constant TRIGGER_META.
	 *
	 * @var string
	 */
	const TRIGGER_META = 'WA_MESSAGE_NOT_DELIVERED_META';

	public function __construct() {

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
		$this->add_action( 'automator_whatsapp_message_delivery_failed' );

		$this->set_uses_api( true );

		// The number of arguments that the action hook accepts.
		$this->set_action_args_count( 1 );

		$this->set_sentence(
			sprintf(
				/* Translators: Trigger sentence */
				esc_html__( 'A message to a recipient was not delivered', 'uncanny-automator' )
			)
		);

		$this->set_readable_sentence(
			/* Translators: Trigger sentence */
			esc_html__( 'A message to a recipient was not delivered', 'uncanny-automator' )
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

		$name = 'automator_whatsapp_' . $response['wamid'] . '_incoming_failure_message';

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
