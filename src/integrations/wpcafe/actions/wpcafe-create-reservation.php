<?php

namespace Uncanny_Automator\Integrations\Wpcafe;

use Exception;
use Uncanny_Automator\Recipe\Action;
use WpCafe\Models\Reservation_Model;

/**
 * Class Wpcafe_Create_Reservation
 *
 * Books a table from any recipe — a form submission, a purchase, a CRM event.
 *
 * Writes through `Reservation_Model::create()`, then re-fires
 * `wpcafe_after_reservation_create` itself. The model fires nothing; WPCafe's
 * REST controller is what fires the lifecycle hooks, so a booking created here
 * would otherwise be invisible to WPCafe's own reservation emails and to WP
 * Cafe Pro's outbound webhooks.
 *
 * That re-fire is also the hook Wpcafe_Table_Booked listens on, so a booking
 * made by this action announces itself to that trigger — deliberately, and the
 * same shape as WordPress' own "a post is published" trigger firing for a post
 * the "Create a post" action wrote. Suppressing it would make Automator-created
 * bookings invisible to WPCafe itself, which is the larger harm. Only a recipe
 * authored to feed its own trigger loops, and no such loop is silent: every pass
 * writes a reservation and a recipe log entry.
 *
 * @package Uncanny_Automator\Integrations\Wpcafe
 *
 * @property Wpcafe_Helpers $item_helpers
 */
class Wpcafe_Create_Reservation extends Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'WPCAFE' );
		$this->set_action_code( 'WPCAFE_CREATE_RESERVATION' );
		$this->set_action_meta( 'RESERVATION_EMAIL' );
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: 1: Guest email */
				esc_html_x( 'Create {{a reservation:%1$s}}', 'WPCafe', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Create {{a reservation}}', 'WPCafe', 'uncanny-automator' ) );
	}

	/**
	 * Define action tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'RESERVATION_ID'     => array(
				'name' => esc_html_x( 'Reservation ID', 'WPCafe', 'uncanny-automator' ),
				'type' => 'int',
			),
			'RESERVATION_STATUS' => array(
				'name' => esc_html_x( 'Reservation status', 'WPCafe', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Action options.
	 *
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => esc_html_x( 'Guest email', 'WPCafe', 'uncanny-automator' ),
				'input_type'  => 'email',
				'required'    => true,
			),
			array(
				'option_code' => 'RESERVATION_NAME',
				'label'       => esc_html_x( 'Guest name', 'WPCafe', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
			),
			array(
				'option_code' => 'RESERVATION_PHONE',
				'label'       => esc_html_x( 'Guest phone', 'WPCafe', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
			array(
				'option_code' => 'RESERVATION_DATE',
				'label'       => esc_html_x( 'Date', 'WPCafe', 'uncanny-automator' ),
				'input_type'  => 'date',
				'required'    => true,
			),
			array(
				'option_code' => 'RESERVATION_START_TIME',
				'label'       => esc_html_x( 'Start time', 'WPCafe', 'uncanny-automator' ),
				'input_type'  => 'time',
				'required'    => true,
			),
			array(
				'option_code' => 'RESERVATION_END_TIME',
				'label'       => esc_html_x( 'End time', 'WPCafe', 'uncanny-automator' ),
				'input_type'  => 'time',
				'required'    => false,
			),
			array(
				'option_code' => 'RESERVATION_TOTAL_GUEST',
				'label'       => esc_html_x( 'Number of guests', 'WPCafe', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
			),
			array(
				'option_code' => 'RESERVATION_STATUS',
				'label'       => esc_html_x( 'Status', 'WPCafe', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => true,
				'options'     => $this->item_helpers->get_status_options_strict(),
			),
			array(
				'option_code' => 'RESERVATION_TABLE_NAME',
				'label'       => esc_html_x( 'Table name', 'WPCafe', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
			array(
				'option_code'           => 'RESERVATION_BRANCH_ID',
				'label'                 => esc_html_x( 'Location', 'WPCafe', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => false,
				'options'               => array(),
				// Kept token-friendly: the value is a location term ID, so a recipe
				// can still pass one through from an earlier trigger or action.
				'supports_custom_value' => true,
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'locations_strict' ),
				'description'           => esc_html_x( 'Leave empty when the restaurant runs a single location.', 'WPCafe', 'uncanny-automator' ),
			),
			array(
				'option_code' => 'RESERVATION_NOTES',
				'label'       => esc_html_x( 'Notes', 'WPCafe', 'uncanny-automator' ),
				'input_type'  => 'textarea',
				'required'    => false,
			),
		);
	}

	/**
	 * Process action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action data.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        The trigger args.
	 * @param array $parsed      The parsed field values.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		if ( ! class_exists( '\WpCafe\Models\Reservation_Model' ) ) {
			$this->add_log_error( 'WPCafe is not available.' );

			return false;
		}

		$email = sanitize_email( $parsed[ $this->get_action_meta() ] ?? '' );

		if ( ! is_email( $email ) ) {
			$this->add_log_error( 'A valid guest email is required.' );

			return false;
		}

		$status = sanitize_text_field( $parsed['RESERVATION_STATUS'] ?? '' );

		if ( ! $this->item_helpers->is_reservation_status( $status ) ) {
			$this->add_log_error( sprintf( 'Invalid reservation status "%s".', $status ) );

			return false;
		}

		$date = sanitize_text_field( $parsed['RESERVATION_DATE'] ?? '' );

		if ( empty( $date ) ) {
			$this->add_log_error( 'A reservation date is required.' );

			return false;
		}

		$start_time = $this->item_helpers->to_timestamp( $date, $parsed['RESERVATION_START_TIME'] ?? '' );

		if ( '' === $start_time ) {
			$this->add_log_error( 'The start time could not be read. Use a format like "7:00 PM".' );

			return false;
		}

		// Empty is legitimate — a single-location restaurant has no location terms
		// at all. A value that is not one has to be caught here: `create()`
		// validates attribute KEYS, never values, so an ID pointing at nothing is
		// otherwise stored as meta and the booking belongs to no location with no
		// error anywhere.
		$branch_id = sanitize_text_field( $parsed['RESERVATION_BRANCH_ID'] ?? '' );

		if ( '' !== $branch_id && ! $this->item_helpers->is_location( $branch_id ) ) {
			$this->add_log_error( sprintf( 'No WPCafe location with ID %s.', $branch_id ) );

			return false;
		}

		// Only keys in Reservation_Model::$fillable may be passed —
		// validate_attributes() throws on anything else.
		$attributes = array(
			'name'        => sanitize_text_field( $parsed['RESERVATION_NAME'] ?? '' ),
			'email'       => $email,
			'phone'       => sanitize_text_field( $parsed['RESERVATION_PHONE'] ?? '' ),
			'date'        => $date,
			'start_time'  => $start_time,
			'end_time'    => $this->item_helpers->to_timestamp( $date, $parsed['RESERVATION_END_TIME'] ?? '' ),
			'total_guest' => (string) absint( $parsed['RESERVATION_TOTAL_GUEST'] ?? 0 ),
			'table_name'  => sanitize_text_field( $parsed['RESERVATION_TABLE_NAME'] ?? '' ),
			'branch_id'   => $branch_id,
			'notes'       => sanitize_textarea_field( $parsed['RESERVATION_NOTES'] ?? '' ),
			'status'      => $status,
		);

		try {
			$reservation = Reservation_Model::create( $attributes );
		} catch ( Exception $e ) {
			// create() throws rather than returning WP_Error.
			$this->add_log_error( sprintf( 'WPCafe rejected the reservation: %s', $e->getMessage() ) );

			return false;
		}

		if ( null === $reservation ) {
			$this->add_log_error( 'Failed to create the reservation.' );

			return false;
		}

		$reservation_id = absint( $reservation->id );

		// The model is silent; the controller is what normally announces a new
		// booking. Re-fire so WPCafe's reservation emails and WP Cafe Pro's
		// webhooks see this reservation too.
		do_action( 'wpcafe_after_reservation_create', $reservation );

		$this->hydrate_tokens(
			array(
				'RESERVATION_ID'     => $reservation_id,
				'RESERVATION_STATUS' => $this->item_helpers->get_status_label( $status ),
			)
		);

		return true;
	}
}
