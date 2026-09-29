<?php

namespace Uncanny_Automator\Integrations\Wpcafe;

use Uncanny_Automator\Recipe\Trigger;
use WpCafe\Models\Reservation_Model;

/**
 * Class Wpcafe_Table_Booked
 *
 * Fires on `wpcafe_after_reservation_create`, which the reservation controller
 * fires once per booking. It is the only place `Reservation_Model::create()` is
 * called, so every reservation created through WPCafe passes through here.
 *
 * Anonymous by design: the booking form is a public REST route open to
 * logged-out visitors, so most bookings have no logged-in user. A customer who
 * booked while logged in is attached to the run; the typed booking email never
 * binds an account.
 *
 * @package Uncanny_Automator\Integrations\Wpcafe
 *
 * @property Wpcafe_Helpers $item_helpers
 */
class Wpcafe_Table_Booked extends Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'WPCAFE_TABLE_BOOKED', 'WPCAFE' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'WPCAFE_RESERVATION' )
			->hook( 'wpcafe_after_reservation_create', 10, 1 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {

		// integration / code / trigger_meta / trigger_type are auto-applied from definition().
		$this->set_is_pro( false );
		$this->set_is_login_required( false );

		$this->set_sentence( esc_html_x( 'A reservation is made', 'WPCafe', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A reservation is made', 'WPCafe', 'uncanny-automator' ) );
	}

	/**
	 * No options — every new reservation fires this.
	 *
	 * The hook carries the full reservation but there is nothing a builder
	 * would want to filter on at build time. Location is exposed as an output
	 * token instead; most installs run a single location.
	 *
	 * @return array
	 */
	public function options() {
		return array();
	}

	/**
	 * Validate trigger.
	 *
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		$reservation_id = $this->get_reservation_id( $hook_args );

		if ( 0 === $reservation_id ) {
			return false;
		}

		if ( null === $this->item_helpers->get_reservation_post( $reservation_id ) ) {
			return false;
		}

		$user_id = $this->item_helpers->resolve_user_id( $reservation_id );

		if ( $user_id > 0 ) {
			$this->set_user_id( $user_id );
		}

		return true;
	}

	/**
	 * Define tokens.
	 *
	 * @param array $trigger The trigger settings.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge( $tokens, $this->item_helpers->get_reservation_tokens_config() );
	}

	/**
	 * Hydrate tokens.
	 *
	 * @param array $trigger   The completed trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		return $this->item_helpers->hydrate_reservation_tokens( $this->get_reservation_id( $hook_args ) );
	}

	/**
	 * Read the reservation ID off the hook payload.
	 *
	 * The hook passes a `Reservation_Model`. Its `id` is a real attribute (set
	 * by `load_attributes()`), unlike `status`, so it is safe to read directly.
	 *
	 * @param array $hook_args The hook arguments.
	 *
	 * @return int 0 when the payload is not a reservation.
	 */
	private function get_reservation_id( $hook_args ) {

		list( $reservation ) = array_pad( $hook_args, 1, null );

		if ( ! is_a( $reservation, Reservation_Model::class ) ) {
			return 0;
		}

		return absint( $reservation->id );
	}
}
