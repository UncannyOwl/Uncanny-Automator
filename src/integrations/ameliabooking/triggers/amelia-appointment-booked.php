<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

use Uncanny_Automator\Recipe;

/**
 * Class AMELIA_APPOINTMENT_BOOKED
 *
 * @package Uncanny_Automator
 */
class AMELIA_APPOINTMENT_BOOKED {

	use Recipe\Triggers;

	/**
	 * Trigger code.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'AMELIA_APPOINTMENT_BOOKED';

	/**
	 * Trigger meta.
	 *
	 * @var string
	 */
	const TRIGGER_META = 'AMELIA_APPOINTMENT_BOOKED_META';

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->setup_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function setup_trigger() {

		$this->set_integration( 'AMELIABOOKING' );
		$this->set_trigger_code( self::TRIGGER_CODE );
		$this->set_trigger_meta( self::TRIGGER_META );
		$this->set_trigger_type( 'anonymous' );
		$this->set_is_login_required( false );

		/* Translators: Trigger sentence */
		$this->set_sentence( esc_html__( 'An appointment is booked', 'uncanny-automator' ) );

		/* Translators: Trigger sentence */
		$this->set_readable_sentence( esc_html__( 'An appointment is booked', 'uncanny-automator' ) ); // Non-active state sentence to show

		$this->add_action( 'AmeliaBookingAddedBeforeNotify' ); // which do_action() fires this trigger

		$this->register_trigger(); // Registering this trigger

	}

	public function validate_trigger( ...$args ) {

		return Automator()->helpers->recipe->ameliabooking->options->validate_trigger( $args );

	}

	public function prepare_to_run( $data ) {

		$appointment = $data[0];
	}

	public function do_continue_anon_trigger( ...$args ) {

		return true;

	}

}
