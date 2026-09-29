<?php

namespace Uncanny_Automator\Integrations\Wpcafe;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Wpcafe_Reservation_Status_Changed
 *
 * Listens on core's `transition_post_status` rather than WPCafe's own
 * `wpcafe_after_reservation_status_changed`, deliberately.
 *
 * A reservation's status IS its `post_status`, and every WPCafe write path goes
 * through `wp_insert_post()` / `wp_update_post()` inside `Post_Model`, so core's
 * hook sees all of them. WPCafe's own hook does not: the WooCommerce payment
 * path writes through the model directly
 * (`checkout-process.php` — `handle_payment_complete()` and
 * `handle_order_status_changed()` both call `$reservation->update()`), bypassing
 * the REST controller that fires the lifecycle hooks. A booking confirmed by a
 * customer paying would never reach a recipe.
 *
 * Core's hook also supplies a real previous status. WPCafe's does not — the
 * controller reads `$reservation->status`, a model property that is always an
 * empty string, so its `$old_status` argument is unusable and its guards fire
 * on unchanged statuses.
 *
 * @package Uncanny_Automator\Integrations\Wpcafe
 *
 * @property Wpcafe_Helpers $item_helpers
 */
class Wpcafe_Reservation_Status_Changed extends Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'WPCAFE_RESERVATION_STATUS_CHANGED', 'WPCAFE' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'WPCAFE_RESERVATION_STATUS' )
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

		$this->set_sentence(
			sprintf(
				/* translators: 1: Reservation status */
				esc_html_x( 'A reservation changes to {{a status:%1$s}}', 'WPCafe', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'A reservation changes to {{a status}}', 'WPCafe', 'uncanny-automator' ) );
	}

	/**
	 * Trigger options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code' => $this->get_trigger_meta(),
				'label'       => esc_html_x( 'Status', 'WPCafe', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => true,
				'options'     => $this->item_helpers->get_status_options(),
			),
		);
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

		// transition_post_status fires for every post on the site — bail first
		// and cheaply on anything that is not a reservation.
		if ( ! is_a( $post, 'WP_Post' ) || Wpcafe_Helpers::POST_TYPE !== $post->post_type ) {
			return false;
		}

		// Both ends must be real reservation statuses. This rejects the `new`
		// pseudo-status of a fresh booking (that is WPCAFE_TABLE_BOOKED's
		// event, and its meta is not written yet at transition time) as well as
		// core statuses like `trash`, `draft` and `auto-draft`.
		if ( ! $this->item_helpers->is_reservation_status( $old_status ) ) {
			return false;
		}

		if ( ! $this->item_helpers->is_reservation_status( $new_status ) ) {
			return false;
		}

		if ( $old_status === $new_status ) {
			return false;
		}

		$selected_status = $trigger['meta'][ $this->get_trigger_meta() ] ?? '-1';

		if ( '-1' !== (string) $selected_status && (string) $selected_status !== (string) $new_status ) {
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

		// The status is read from the transition rather than from the post,
		// because `Post_Model::update()` writes the remaining meta only after
		// wp_update_post() returns — i.e. after this hook has already fired.
		$tokens['RESERVATION_STATUS']          = $this->item_helpers->get_status_label( $new_status );
		$tokens['RESERVATION_PREVIOUS_STATUS'] = $this->item_helpers->get_status_label( $old_status );
		$tokens[ $this->get_trigger_meta() ]   = $this->item_helpers->get_status_label( $new_status );

		return $tokens;
	}
}
