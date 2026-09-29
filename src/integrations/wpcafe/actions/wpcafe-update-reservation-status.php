<?php

namespace Uncanny_Automator\Integrations\Wpcafe;

use Exception;
use Uncanny_Automator\Recipe\Action;

/**
 * Class Wpcafe_Update_Reservation_Status
 *
 * Confirms, cancels or refunds a booking from any recipe.
 *
 * Writes through `Reservation_Model::update()` and then re-fires the matching
 * WPCafe lifecycle hook, because the model fires none — only the REST
 * controller does. Unlike the controller, the real previous status is passed
 * along: the controller sources it from `$reservation->status`, a model
 * property that is always an empty string.
 *
 * The write itself reaches `wp_update_post()`, so core emits
 * `transition_post_status` — what Wpcafe_Reservation_Status_Changed and
 * Wpcafe_Reservation_Cancelled listen on. A recipe that both triggers on a
 * status change and calls this action therefore re-enters, but cannot spin: the
 * already-in-that-status guard below returns false on the second pass, since by
 * then the reservation holds the status the action would set.
 *
 * @package Uncanny_Automator\Integrations\Wpcafe
 *
 * @property Wpcafe_Helpers $item_helpers
 */
class Wpcafe_Update_Reservation_Status extends Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'WPCAFE' );
		$this->set_action_code( 'WPCAFE_UPDATE_RESERVATION_STATUS' );
		$this->set_action_meta( 'WPCAFE_RESERVATION_ID' );
		$this->set_requires_user( false );

		// Typographic apostrophe (U+2019), not the straight one, and only
		// because it sits inside a {{ }} pill. `esc_html_x()` turns a straight
		// apostrophe into `&#039;`, and the builder's sentence parser assigns
		// pill text with `innerText`, which does not decode entities — so the
		// pill would read "a reservation&#039;s". Text outside the braces is
		// written with `innerHTML` and decodes fine, so an apostrophe outside
		// a pill needs no such treatment.
		$this->set_sentence(
			sprintf(
				/* translators: 1: Reservation ID 2: Status */
				esc_html_x( 'Update {{a reservation’s:%1$s}} status to {{a status:%2$s}}', 'WPCafe', 'uncanny-automator' ),
				$this->get_action_meta(),
				'RESERVATION_STATUS:' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Update {{a reservation’s}} status to {{a status}}', 'WPCafe', 'uncanny-automator' ) );
	}

	/**
	 * Define action tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'RESERVATION_ID'              => array(
				'name' => esc_html_x( 'Reservation ID', 'WPCafe', 'uncanny-automator' ),
				'type' => 'int',
			),
			'RESERVATION_STATUS'          => array(
				'name' => esc_html_x( 'Reservation status', 'WPCafe', 'uncanny-automator' ),
				'type' => 'text',
			),
			'RESERVATION_PREVIOUS_STATUS' => array(
				'name' => esc_html_x( 'Previous reservation status', 'WPCafe', 'uncanny-automator' ),
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
				'option_code'           => $this->get_action_meta(),
				'label'                 => esc_html_x( 'Reservation', 'WPCafe', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => array(),
				// Custom values stay on, and this field is why: the intended use is
				// still a Reservation ID token from an earlier WPCafe trigger,
				// which is the only way to act on a booking made after the recipe
				// was built. The picker serves the other case — an admin acting on
				// a booking that already exists.
				'supports_custom_value' => true,
				'supports_tokens'       => true,
				// Searched server-side rather than loaded in one page: a busy
				// restaurant passes the picker's row cap, and the bookings a
				// search reaches for are the older ones a capped page drops.
				'remote_data'           => $this->item_helpers->remote_data_search_config( 'reservations_strict' ),
				'description'           => esc_html_x( 'Search by guest name, email, date or reservation ID, or pass the Reservation ID token from a WPCafe trigger.', 'WPCafe', 'uncanny-automator' ),
			),
			array(
				'option_code' => 'RESERVATION_STATUS',
				'label'       => esc_html_x( 'Status', 'WPCafe', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => true,
				'options'     => $this->item_helpers->get_status_options_strict(),
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

		$reservation_id = absint( $parsed[ $this->get_action_meta() ] ?? 0 );
		$new_status     = sanitize_text_field( $parsed['RESERVATION_STATUS'] ?? '' );

		if ( ! $this->item_helpers->is_reservation_status( $new_status ) ) {
			$this->add_log_error( sprintf( 'Invalid reservation status "%s".', $new_status ) );

			return false;
		}

		$reservation = $this->item_helpers->get_reservation_model( $reservation_id );

		if ( null === $reservation ) {
			$this->add_log_error( sprintf( 'Reservation %d not found.', $reservation_id ) );

			return false;
		}

		// Read the real previous status before the write — post_status is the
		// only place a reservation's status actually lives.
		$old_status = $this->item_helpers->get_reservation_status( $reservation_id );

		if ( $old_status === $new_status ) {
			$this->add_log_error( sprintf( 'Reservation %1$d is already "%2$s".', $reservation_id, $new_status ) );

			return false;
		}

		try {
			$reservation->update( array( 'status' => $new_status ) );
		} catch ( Exception $e ) {
			$this->add_log_error( sprintf( 'WPCafe rejected the update: %s', $e->getMessage() ) );

			return false;
		}

		if ( $new_status !== $this->item_helpers->get_reservation_status( $reservation_id ) ) {
			$this->add_log_error( sprintf( 'Failed to update reservation %d.', $reservation_id ) );

			return false;
		}

		$this->item_helpers->fire_status_change_hooks( $reservation, $old_status, $new_status );

		$this->hydrate_tokens(
			array(
				'RESERVATION_ID'              => $reservation_id,
				'RESERVATION_STATUS'          => $this->item_helpers->get_status_label( $new_status ),
				'RESERVATION_PREVIOUS_STATUS' => $this->item_helpers->get_status_label( $old_status ),
			)
		);

		return true;
	}
}
