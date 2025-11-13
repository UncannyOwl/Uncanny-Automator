<?php

namespace Uncanny_Automator\Integrations\WooCommerce_Bookings;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class WC_BOOKINGS_ANON_BOOKING_CREATED
 *
 * @package Uncanny_Automator
 * @method Wc_Bookings_Helpers get_item_helpers()
 */
class WC_BOOKINGS_ANON_BOOKING_CREATED extends Trigger {

	/**
	 * @return mixed|void
	 */
	protected function setup_trigger() {
		add_action(
			'admin_init',
			function () {
				if ( 'yes' === automator_get_option( 'woo_booking_created_migrated', 'no' ) ) {
					return;
				}
				$searialize = array( 'woocommerce_booking_confirmed', 'woocommerce_booking_unpaid_to_paid' );
				global $wpdb;
				$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->postmeta SET meta_value = %s WHERE meta_value = %s AND meta_key LIKE %s", serialize( $searialize ), 'woocommerce_booking_unpaid_to_paid', 'add_action' ) );
				automator_update_option( 'woo_booking_created_migrated', 'yes' );
			},
			99
		);

		$this->set_integration( 'WC_BOOKINGS' );
		$this->set_trigger_code( 'WC_BOOKINGS_NEW_BOOKING' );
		$this->set_trigger_meta( 'WC_BOOKING_CREATED' );
		$this->set_trigger_type( 'anonymous' );
		$this->set_sentence( esc_attr_x( 'A booking is created', 'WooCommerce Bookings', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_attr_x( 'A booking is created', 'WooCommerce Bookings', 'uncanny-automator' ) );
		$this->add_action( array( 'woocommerce_booking_confirmed', 'woocommerce_booking_unpaid_to_paid' ), 10, 2 );
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
		$booking_tokens = $this->get_item_helpers()->wcb_booking_common_tokens();

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
		/** @var \WC_Booking $booking */
		list( $booking_id, $booking ) = $hook_args;
		$product                      = get_wc_product_booking( $booking->get_product_id() );
		$booked_data                  = $this->get_item_helpers()->get_booked_details_token_value( $product, $booking );
		$order_token_values           = $this->get_item_helpers()->get_wc_order_tokens( $booking->get_order_id(), $booking->get_product_id() );
		$order                        = wc_get_order( $booking->get_order_id() );

		$trigger_token_values = array(
			'WCB_BOOKING_START'         => $booking->get_start_date( null, null, true ),  // Local timezone
			'WCB_BOOKING_END'           => $booking->get_end_date( null, null, true ),    // Local timezone
			'WCB_BOOKING_START_DATE'      => $this->get_booking_start_date( $booking ),
			'WCB_BOOKING_START_TIME'      => $this->get_booking_start_time( $booking ),
			'WCB_BOOKING_END_DATE'        => $this->get_booking_end_date( $booking ),
			'WCB_BOOKING_END_TIME'        => $this->get_booking_end_time( $booking ),
			'WCB_BOOKING_ORDER_ID'        => $booking->get_order_id(),
			'WCB_BOOKING_ID'              => $booking_id,
			'WCB_CUSTOMER_EMAIL'          => $booking->get_customer()->email,
			'WCB_CUSTOMER_NAME'           => $booking->get_customer()->name,
			'WCB_PRODUCT_URL'             => get_permalink( $booking->get_product_id() ),
			'WCB_PRODUCT_TITLE'           => $booking->get_product()->get_title(),
			'WCB_PRODUCT_ID'              => $booking->get_product_id(),
			'WCB_BOOKING_ORDER_ITEM_ID'   => $booking->get_order_item_id(),
			'WCB_PRODUCT_DETAILS'         => $booked_data,
			'WCB_PRODUCT_PRICE'           => wc_price( $booking->get_cost() ),
			'WCB_BOOKING_STATUS'          => $booking->get_status(),
			'WCB_BOOKING_ORDER_LINK'      => $order instanceof \WC_Order ? $order->get_edit_order_url() : '',
			'WCB_BOOKING_DURATION_TYPE'   => $this->get_booking_duration_type( $product ),
			'WCB_BOOKING_DURATION'        => $this->get_booking_duration( $product, $booking ),
			'WCB_BOOKING_DURATION_UNIT'   => $this->get_booking_duration_unit( $product ),
			'WCB_BOOKING_DURATION_MINUTES' => $this->get_booking_duration_minutes( $product, $booking ),
			'WCB_BOOKING_TIMEZONE'        => $this->get_booking_timezone( $booking ),
			'WCB_PRODUCT_TAGS'            => $this->get_booking_product_tags( $product ),
			'WCB_PRODUCT_CATEGORIES'      => $this->get_booking_product_categories( $product ),
		);

		return array_merge( $order_token_values, $trigger_token_values );
	}

	/**
	 * Get booking duration type (fixed or customer)
	 *
	 * @param $product
	 * @return string
	 */
	private function get_booking_duration_type( $product ) {
		if ( ! $product ) {
			return esc_html_x( 'Unknown', 'Woocommerce Bookings', 'uncanny-automator' );
		}

		$duration_type = $product->get_duration_type();

		switch ( $duration_type ) {
			case 'fixed':
				return esc_html_x( 'Fixed', 'Woocommerce Bookings', 'uncanny-automator' );
			case 'customer':
				return esc_html_x( 'Customer', 'Woocommerce Bookings', 'uncanny-automator' );
			default:
				return $duration_type;
		}
	}

	/**
	 * Get booking duration as configured in the product settings
	 *
	 * @param $product
	 * @param $booking
	 * @return int
	 */
	private function get_booking_duration( $product, $booking ) {
		if ( ! $product ) {
			return 0;
		}

		// Get the configured duration from the product settings
		$duration = $product->get_duration();

		// If duration is not set or is 0, try to calculate from booking times
		if ( empty( $duration ) || 0 === $duration ) {
			if ( ! $booking ) {
				return 0;
			}

			// Calculate duration from start and end times (using local timezone)
			$start_time = $booking->get_start_date( null, null, true );
			$end_time   = $booking->get_end_date( null, null, true );

			if ( empty( $start_time ) || empty( $end_time ) ) {
				return 0;
			}

			// Convert to timestamps and calculate difference
			$start_timestamp = strtotime( $start_time );
			$end_timestamp   = strtotime( $end_time );

			if ( $start_timestamp && $end_timestamp ) {
				$duration_seconds = $end_timestamp - $start_timestamp;
				$duration_minutes = intval( $duration_seconds / 60 );

				// Convert to the product's configured duration unit
				$duration_unit = $product->get_duration_unit();
				switch ( $duration_unit ) {
					case 'hour':
						return intval( $duration_minutes / 60 );
					case 'day':
						return intval( $duration_minutes / ( 60 * 24 ) );
					case 'week':
						return intval( $duration_minutes / ( 60 * 24 * 7 ) );
					case 'month':
						return intval( $duration_minutes / ( 60 * 24 * 30 ) ); // Approximate
					case 'year':
						return intval( $duration_minutes / ( 60 * 24 * 365 ) ); // Approximate
					default: // 'minute'
						return $duration_minutes;
				}
			}

			return 0;
		}

		return intval( $duration );
	}

	/**
	 * Get booking duration unit
	 *
	 * @param $product
	 * @return string
	 */
	private function get_booking_duration_unit( $product ) {
		if ( ! $product ) {
			return esc_html_x( 'Unknown', 'Woocommerce Bookings', 'uncanny-automator' );
		}

		$duration_unit = $product->get_duration_unit();

		return ! empty( $duration_unit ) ? $duration_unit : esc_html_x( 'Unknown', 'Woocommerce Bookings', 'uncanny-automator' );
	}

	/**
	 * Get booking timezone
	 *
	 * @param $booking
	 * @return string
	 */
	private function get_booking_timezone( $booking ) {
		if ( ! $booking ) {
			return wp_timezone_string(); // Fallback to site timezone
		}

		// Use WooCommerce Bookings' built-in timezone method
		$local_timezone = $booking->get_local_timezone();

		if ( ! empty( $local_timezone ) ) {
			return $local_timezone;
		}

		// Fallback to site timezone if no local timezone stored
		return wp_timezone_string();
	}

	/**
	 * Get booking duration in minutes
	 *
	 * @param $product
	 * @param $booking
	 * @return int
	 */
	private function get_booking_duration_minutes( $product, $booking ) {
		if ( ! $product || ! $booking ) {
			return 0;
		}

		// Calculate duration from start and end times (using local timezone)
		$start_time = $booking->get_start_date( null, null, true );
		$end_time   = $booking->get_end_date( null, null, true );

		if ( empty( $start_time ) || empty( $end_time ) ) {
			return 0;
		}

		// Convert to timestamps and calculate difference
		$start_timestamp = strtotime( $start_time );
		$end_timestamp   = strtotime( $end_time );

		if ( $start_timestamp && $end_timestamp ) {
			$duration_seconds = $end_timestamp - $start_timestamp;
			return intval( $duration_seconds / 60 ); // Convert to minutes
		}

		return 0;
	}

	/**
	 * Get booking start date formatted according to WordPress date format
	 *
	 * @param $booking
	 * @return string
	 */
	private function get_booking_start_date( $booking ) {
		if ( ! $booking ) {
			return '';
		}

		$start_date = $booking->get_start_date( null, null, true );
		if ( empty( $start_date ) ) {
			return '';
		}

		try {
			$datetime = new \DateTime( $start_date );
			return $datetime->format( get_option( 'date_format', 'F j, Y' ) );
		} catch ( \Exception $e ) {
			return '';
		}
	}

	/**
	 * Get booking start time formatted according to WordPress time format
	 *
	 * @param $booking
	 * @return string
	 */
	private function get_booking_start_time( $booking ) {
		if ( ! $booking ) {
			return '';
		}

		$start_date = $booking->get_start_date( null, null, true );
		if ( empty( $start_date ) ) {
			return '';
		}

		try {
			$datetime = new \DateTime( $start_date );
			return $datetime->format( get_option( 'time_format', 'g:i a' ) );
		} catch ( \Exception $e ) {
			return '';
		}
	}

	/**
	 * Get booking end date formatted according to WordPress date format
	 *
	 * @param $booking
	 * @return string
	 */
	private function get_booking_end_date( $booking ) {
		if ( ! $booking ) {
			return '';
		}

		$end_date = $booking->get_end_date( null, null, true );
		if ( empty( $end_date ) ) {
			return '';
		}

		try {
			$datetime = new \DateTime( $end_date );
			return $datetime->format( get_option( 'date_format', 'F j, Y' ) );
		} catch ( \Exception $e ) {
			return '';
		}
	}

	/**
	 * Get booking end time formatted according to WordPress time format
	 *
	 * @param $booking
	 * @return string
	 */
	private function get_booking_end_time( $booking ) {
		if ( ! $booking ) {
			return '';
		}

		$end_date = $booking->get_end_date( null, null, true );
		if ( empty( $end_date ) ) {
			return '';
		}

		try {
			$datetime = new \DateTime( $end_date );
			return $datetime->format( get_option( 'time_format', 'g:i a' ) );
		} catch ( \Exception $e ) {
			return '';
		}
	}

	/**
	 * Get booking product tags as comma-separated string
	 *
	 * @param $product
	 * @return string
	 */
	private function get_booking_product_tags( $product ) {
		if ( ! $product ) {
			return '';
		}

		$product_id = $product->get_id();
		$terms      = get_the_terms( $product_id, 'product_tag' );

		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}

		$tag_names = array();
		foreach ( $terms as $term ) {
			$tag_names[] = $term->name;
		}

		return implode( ', ', $tag_names );
	}

	/**
	 * Get booking product categories as comma-separated string
	 *
	 * @param $product
	 * @return string
	 */
	private function get_booking_product_categories( $product ) {
		if ( ! $product ) {
			return '';
		}

		$product_id = $product->get_id();
		$terms      = get_the_terms( $product_id, 'product_cat' );

		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}

		$category_names = array();
		foreach ( $terms as $term ) {
			$category_names[] = $term->name;
		}

		return implode( ', ', $category_names );
	}
}
