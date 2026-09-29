<?php

namespace Uncanny_Automator\Integrations\Eventin;

use Eventin\Order\OrderModel;
use Uncanny_Automator\Recipe\Action;

/**
 * Class Eventin_Update_Order_Status
 *
 * Sets an Eventin order's status.
 *
 * This is deliberately more than a one-line `OrderModel::update()`. That method
 * writes the meta and nothing else (base/post-model.php:156) — it enforces none
 * of the transition rules Eventin's own route applies, and it fires none of the
 * lifecycle hooks the rest of Eventin listens on. Calling it bare leaves
 * attendee payment statuses and coupon redemption counts stale, and silently
 * permits transitions the Eventin UI refuses. So this action reproduces both
 * halves of `OrderController::update_item()`'s `update_booking_status` branch
 * (core/Order/OrderController.php:809-880):
 *
 * 1. The three refused transitions (:837-859), via
 *    Eventin_Helpers::get_status_transition_error().
 * 2. The lifecycle hook that matches the new status —
 *    `eventin_order_status_completed` (:885), `eventin_order_refund` (:898/:909),
 *    `eventin_order_status_failed` (:929). Those are what drive
 *    OrderAttendee's attendee-status sync (core/Order/OrderAttendee.php:18-20)
 *    and CouponRedemptionHandler (core/Coupon/CouponRedemptionHandler.php:37-38).
 *
 * The status list is the four values Eventin's own REST route accepts (:811).
 * `pending`, `waiting` and `cancelled` exist in the stored vocabulary (:1420)
 * but Eventin refuses to let anyone set them directly, so neither does this.
 *
 * @package Uncanny_Automator\Integrations\Eventin
 *
 * @property Eventin_Helpers $item_helpers
 */
class Eventin_Update_Order_Status extends Action {

	/**
	 * Option code of the status field.
	 */
	const STATUS = 'EVENTIN_ORDER_STATUS';

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'EVENTIN' );
		$this->set_action_code( 'EVENTIN_UPDATE_ORDER_STATUS' );
		$this->set_action_meta( 'EVENTIN_ORDER' );
		// Writes order post meta; no recipe user is involved.
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: 1: Order, 2: Status */
				esc_html_x( 'Update the status of {{an order:%1$s}} to {{a status:%2$s}}', 'Eventin', 'uncanny-automator' ),
				$this->get_action_meta(),
				// Secondary options are addressed as OPTION_CODE:ACTION_META. A
				// bare code does not bind to the field, so the builder renders
				// the placeholder as inert text instead of an editable token.
				self::STATUS . ':' . $this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'Update the status of {{an order}} to {{a status}}', 'Eventin', 'uncanny-automator' ) );
	}

	/**
	 * Options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => esc_html_x( 'Order', 'Eventin', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => true,
				'options'     => array(),
				'remote_data' => $this->item_helpers->remote_data_load_config( 'orders_strict' ),
			),
			array(
				'option_code'           => self::STATUS,
				'label'                 => esc_html_x( 'Status', 'Eventin', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options_show_id'       => false,
				'supports_custom_value' => false,
				'default_value'         => 'completed',
				'options'               => $this->item_helpers->get_order_status_options(),
			),
		);
	}

	/**
	 * Define output tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'EVENTIN_UPDATED_ORDER_ID'      => array(
				'name' => esc_html_x( 'Order ID', 'Eventin', 'uncanny-automator' ),
				'type' => 'int',
			),
			'EVENTIN_ORDER_NEW_STATUS'      => array(
				'name' => esc_html_x( 'New status', 'Eventin', 'uncanny-automator' ),
				'type' => 'text',
			),
			'EVENTIN_ORDER_PREVIOUS_STATUS' => array(
				'name' => esc_html_x( 'Previous status', 'Eventin', 'uncanny-automator' ),
				'type' => 'text',
			),
			'EVENTIN_ORDER_EVENT_ID'        => array(
				'name' => esc_html_x( 'Event ID', 'Eventin', 'uncanny-automator' ),
				'type' => 'int',
			),
			'EVENTIN_ORDER_EVENT_TITLE'     => array(
				'name' => esc_html_x( 'Event title', 'Eventin', 'uncanny-automator' ),
				'type' => 'text',
			),
			'EVENTIN_ORDER_CUSTOMER_EMAIL'  => array(
				'name' => esc_html_x( 'Customer email', 'Eventin', 'uncanny-automator' ),
				'type' => 'email',
			),
			'EVENTIN_ORDER_UPDATED_TOTAL'   => array(
				'name' => esc_html_x( 'Order total', 'Eventin', 'uncanny-automator' ),
				'type' => 'float',
			),
		);
	}

	/**
	 * Process action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action data.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        The args.
	 * @param array $parsed      The parsed options.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		if ( ! class_exists( '\Eventin\Order\OrderModel' ) ) {
			$this->add_log_error( 'Eventin is not active.' );
			return false;
		}

		$order_id = absint( $parsed[ $this->get_action_meta() ] ?? 0 );

		if ( 0 === $order_id || Eventin_Helpers::ORDER_POST_TYPE !== get_post_type( $order_id ) ) {
			$this->add_log_error( sprintf( 'Order not found: [%d].', $order_id ) );
			return false;
		}

		$status = (string) ( $parsed[ self::STATUS ] ?? '' );

		if ( ! in_array( $status, Eventin_Helpers::SETTABLE_ORDER_STATUSES, true ) ) {
			$this->add_log_error( sprintf( 'Unsupported order status: [%s].', $status ) );
			return false;
		}

		$previous = (string) get_post_meta( $order_id, 'status', true );

		$transition_error = $this->item_helpers->get_status_transition_error( $previous, $status );

		if ( '' !== $transition_error ) {
			$this->add_log_error( $transition_error );
			return false;
		}

		$order = new OrderModel( $order_id );

		if ( false === $order->update( array( 'status' => $status ) ) ) {
			$this->add_log_error( sprintf( 'The status of order [%d] could not be updated.', $order_id ) );
			return false;
		}

		$this->fire_status_hook( $status, $order );

		$event_id = absint( get_post_meta( $order_id, 'event_id', true ) );

		$this->hydrate_tokens(
			array(
				'EVENTIN_UPDATED_ORDER_ID'      => $order_id,
				'EVENTIN_ORDER_NEW_STATUS'      => $status,
				'EVENTIN_ORDER_PREVIOUS_STATUS' => $previous,
				'EVENTIN_ORDER_EVENT_ID'        => $event_id,
				'EVENTIN_ORDER_EVENT_TITLE'     => 0 === $event_id ? '' : (string) get_the_title( $event_id ),
				'EVENTIN_ORDER_CUSTOMER_EMAIL'  => (string) get_post_meta( $order_id, 'customer_email', true ),
				'EVENTIN_ORDER_UPDATED_TOTAL'   => (string) get_post_meta( $order_id, 'total_price', true ),
			)
		);

		return true;
	}

	/**
	 * Re-fire the Eventin lifecycle hook that matches the new status.
	 *
	 * OrderModel::update() fires none of these itself. Without them the order's
	 * attendees keep their old payment status and coupon redemption counts never
	 * move — the write would be visible in the orders table and nowhere else.
	 *
	 * @param string     $status The new status.
	 * @param OrderModel $order  The order model.
	 *
	 * @return void
	 */
	private function fire_status_hook( $status, $order ) {

		$hook = $this->item_helpers->get_status_hook( $status );

		if ( '' === $hook ) {
			return;
		}

		do_action( $hook, $order );
	}
}
