<?php

namespace Uncanny_Automator\Integrations\WooCommerce_Bookings;

use Uncanny_Automator\Integration;

/**
 * Class Woocommerce_Bookings_Integration
 *
 * @package Uncanny_Automator
 */
class Woocommerce_Bookings_Integration extends Integration {

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Wc_Bookings_Helpers();
		$this->set_integration( 'WC_BOOKINGS' );
		$this->set_name( 'Woo Bookings' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/woocommerce-bookings-icon.svg' );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		// Load triggers.
		new WC_BOOKINGS_ANON_BOOKING_CREATED( $this->helpers );
	}

	/**
	 * Check if Plugin is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'WooCommerce' ) && class_exists( 'WC_Bookings' );
	}
}
