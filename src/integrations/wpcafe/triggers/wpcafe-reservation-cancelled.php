<?php

namespace Uncanny_Automator\Integrations\Wpcafe;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Wpcafe_Reservation_Cancelled
 *
 * Cancellation gets its own picker entry rather than leaning on the status
 * trigger: WPCafe deliberately excludes `cancelled` from its status-changed
 * hook, and "a booking was cancelled" is the event most recipes are built
 * around — free the table, alert the waitlist, start a refund.
 *
 * Listens on core's `transition_post_status` for the same reason as
 * Wpcafe_Reservation_Status_Changed: WPCafe's own
 * `wpcafe_after_reservation_cancelled` never fires when a WooCommerce order is
 * cancelled, refunded or fails, because that path writes through
 * `Reservation_Model` directly and skips the controller. It also re-fires for a
 * booking that was already cancelled, since the guard compares against a model
 * property that is always empty.
 *
 * @package Uncanny_Automator\Integrations\Wpcafe
 *
 * @property Wpcafe_Helpers $item_helpers
 */
class Wpcafe_Reservation_Cancelled extends Trigger {

	/**
	 * The status this trigger fires on.
	 *
	 * @var string
	 */
	const CANCELLED_STATUS = 'cancelled';

	/**
	 * Opt this trigger into the lazy loading path.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'WPCAFE_RESERVATION_CANCELLED', 'WPCAFE' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'WPCAFE_CANCELLED_RESERVATION' )
			->hook( 'transition_post_status', 10, 3 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_is_pro( false );
		$this->set_is_login_required( false );

		$this->set_sentence( esc_html_x( 'A reservation is cancelled', 'WPCafe', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A reservation is cancelled', 'WPCafe', 'uncanny-automator' ) );
	}

	/**
	 * No options — every cancellation fires this.
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

		list( $new_status, $old_status, $post ) = array_pad( $hook_args, 3, null );

		if ( ! is_a( $post, 'WP_Post' ) || Wpcafe_Helpers::POST_TYPE !== $post->post_type ) {
			return false;
		}

		if ( self::CANCELLED_STATUS !== (string) $new_status ) {
			return false;
		}

		// Only a real reservation moving into cancellation counts. This keeps
		// the trigger from re-firing when an already-cancelled booking is
		// re-saved, and from firing on a draft or trashed row.
		if ( ! $this->item_helpers->is_reservation_status( $old_status ) ) {
			return false;
		}

		if ( $old_status === $new_status ) {
			return false;
		}

		$user_id = $this->item_helpers->resolve_user_id( $post->ID );

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

		return array_merge(
			$tokens,
			$this->item_helpers->get_reservation_tokens_config(),
			array(
				array(
					'tokenId'   => 'RESERVATION_PREVIOUS_STATUS',
					'tokenName' => esc_html_x( 'Previous reservation status', 'WPCafe', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
			)
		);
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

		list( $new_status, $old_status, $post ) = array_pad( $hook_args, 3, null );

		$tokens = $this->item_helpers->hydrate_reservation_tokens( $post->ID );

		$tokens['RESERVATION_STATUS']          = $this->item_helpers->get_status_label( $new_status );
		$tokens['RESERVATION_PREVIOUS_STATUS'] = $this->item_helpers->get_status_label( $old_status );

		return $tokens;
	}
}
