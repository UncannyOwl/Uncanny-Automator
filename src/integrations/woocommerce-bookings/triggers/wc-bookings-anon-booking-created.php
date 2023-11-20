<?php

namespace Uncanny_Automator\Integrations\WooCommerce_Bookings;

/**
 * Class WC_BOOKINGS_ANON_BOOKING_CREATED
 *
 * @package Uncanny_Automator
 */
class WC_BOOKINGS_ANON_BOOKING_CREATED extends \Uncanny_Automator\Recipe\Trigger {

	protected $helpers;

	/**
	 * @return mixed|void
	 */
	protected function setup_trigger() {
		$this->helpers = array_shift( $this->dependencies );
		$this->set_integration( 'WC_BOOKINGS' );
		$this->set_trigger_code( 'WC_BOOKINGS_NEW_BOOKING' );
		$this->set_trigger_meta( 'WC_BOOKING_CREATED' );
		$this->set_trigger_type( 'anonymous' );
		$this->set_sentence( esc_attr_x( 'A booking is created', 'WooCommerce Bookings', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_attr_x( 'A booking is created', 'WooCommerce Bookings', 'uncanny-automator' ) );
		$this->add_action( 'woocommerce_booking_unpaid_to_paid', 10, 2 );
	}

	/**
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		if ( ! isset( $hook_args[0] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * define_tokens
	 *
	 * @param mixed $tokens
	 * @param mixed $trigger - options selected in the current recipe/trigger
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		$booking_tokens = $this->helpers->wcb_booking_common_tokens();

		return array_merge( $tokens, $booking_tokens );
	}

	/**
	 * hydrate_tokens
	 *
	 * @param $trigger
	 * @param $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		//	\WC_Booking $booking    Booking object
		list( $booking_id, $booking ) = $hook_args;
		$product                      = get_wc_product_booking( $booking->get_product_id() );
		$booked_data                  = $this->helpers->get_booked_details_token_value( $product, $booking );
		$order_token_values           = $this->helpers->get_wc_order_tokens( $booking->get_order_id(), $booking->get_product_id() );
		$trigger_token_values         = array(
			'WCB_BOOKING_START'    => $booking->get_start_date(),
			'WCB_BOOKING_END'      => $booking->get_end_date(),
			'WCB_BOOKING_ORDER_ID' => $booking->get_order_id(),
			'WCB_BOOKING_ID'       => $booking_id,
			'WCB_CUSTOMER_EMAIL'   => $booking->get_customer()->email,
			'WCB_CUSTOMER_NAME'    => $booking->get_customer()->name,
			'WCB_PRODUCT_URL'      => get_permalink( $booking->get_product_id() ),
			'WCB_PRODUCT_TITLE'    => $booking->get_product()->get_title(),
			'WCB_PRODUCT_DETAILS'  => $booked_data,
			'WCB_PRODUCT_PRICE'    => wc_price( $booking->get_cost() ),
			'WCB_BOOKING_STATUS'   => $booking->get_status(),
		);

		return array_merge( $order_token_values, $trigger_token_values );
	}
}
