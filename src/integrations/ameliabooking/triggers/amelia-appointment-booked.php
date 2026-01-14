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
		$this->set_sentence( esc_html_x( 'An appointment is booked', 'Amelia Booking', 'uncanny-automator' ) );

		/* Translators: Trigger sentence */
		$this->set_readable_sentence( esc_html_x( 'An appointment is booked', 'Amelia Booking', 'uncanny-automator' ) ); // Non-active state sentence to show

		$this->add_action( 'automator_amelia_appointment_booked' ); // which do_action() fires this trigger

		$this->register_trigger(); // Registering this trigger
	}
	/**
	 * Validate trigger.
	 *
	 * @param mixed $args The arguments.
	 * @return mixed
	 */
	public function validate_trigger( ...$args ) {

		// Bailout if args is empty.
		if ( empty( $args ) ) {
			return false;
		}

		$booking = array_shift( $args[0] );

		if ( empty( $booking['type'] ) ) {
			return false;
		}

		// Only run for appointments. Dont run for events.
		if ( 'appointment' === $booking['type'] ) {
			return true;
		}

		return false;
	}
	/**
	 * Prepare to run.
	 *
	 * @param mixed $data The data.
	 */
	public function prepare_to_run( $data ) {

		$normalized_data = $data[0];
	}
	/**
	 * Do continue anon trigger.
	 *
	 * @param mixed $args The arguments.
	 * @return mixed
	 */
	public function do_continue_anon_trigger( ...$args ) {

		return true;
	}
}
