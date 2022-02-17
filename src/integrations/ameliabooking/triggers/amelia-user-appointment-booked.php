<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

use Uncanny_Automator\Recipe;

/**
 * Class AMELIA_USER_APPOINTMENT_BOOKED
 *
 * @package Uncanny_Automator
 */
class AMELIA_USER_APPOINTMENT_BOOKED {

	use Recipe\Triggers;

	/**
	 * Trigger code.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'AMELIA_USER_APPOINTMENT_BOOKED';

	/**
	 * Trigger meta.
	 *
	 * @var string
	 */
	const TRIGGER_META = 'AMELIA_USER_APPOINTMENT_BOOKED_META';

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
		$this->set_is_login_required( true );

		/* Translators: Trigger sentence */
		$this->set_sentence( esc_html__( 'A user books an appointment', 'uncanny-automator' ) );

		/* Translators: Trigger sentence */
		$this->set_readable_sentence( esc_html__( 'A user books an appointment', 'uncanny-automator' ) ); // Non-active state sentence to show

		$this->add_action( 'AmeliaBookingAddedBeforeNotify' ); // which do_action() fires this trigger

		$this->register_trigger(); // Registering this trigger

	}

	public function validate_trigger( ...$args ) {

		// First element of the $args which is the data of the booking should not be empty.
		if ( empty( array_shift( $args[0] ) ) ) {
			return false;
		}

		return true;

	}

	public function prepare_to_run( $data ) {
		$appointment = $data[0];
	}

}
