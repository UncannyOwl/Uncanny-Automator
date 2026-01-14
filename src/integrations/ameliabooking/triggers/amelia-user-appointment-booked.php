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
		$this->set_sentence( esc_html_x( 'A user books an appointment', 'Amelia Booking', 'uncanny-automator' ) );

		/* Translators: Trigger sentence */
		$this->set_readable_sentence( esc_html_x( 'A user books an appointment', 'Amelia Booking', 'uncanny-automator' ) ); // Non-active state sentence to show

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

		$normalized_data = array_shift( $args[0] );

		// Check if we have a valid normalized data structure
		if ( empty( $normalized_data ) || ! is_array( $normalized_data ) ) {
			return false;
		}

		// Check if customer data exists and has an ID
		if ( empty( $normalized_data['customer'] ) || empty( $normalized_data['customer']['id'] ) ) {
			return false;
		}

		// Only run for appointments. Don't run for events.
		if ( empty( $normalized_data['type'] ) || 'appointment' !== $normalized_data['type'] ) {
			return false;
		}

		return true;
	}
	/**
	 * Prepare to run.
	 *
	 * @param mixed $data The data.
	 */
	public function prepare_to_run( $data ) {

		$normalized_data = $data[0];

		// Extract customer information to get WordPress user context
		if ( ! empty( $normalized_data['customer'] ) && ! empty( $normalized_data['customer']['email'] ) ) {
			$customer_email = $normalized_data['customer']['email'];
			$wp_user        = get_user_by( 'email', $customer_email );
			if ( $wp_user ) {
				$this->set_user_id( $wp_user->ID );
			}
		}
	}
}
