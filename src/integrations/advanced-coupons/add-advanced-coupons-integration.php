<?php

namespace Uncanny_Automator;

/**
 * Class Add_Advanced_Coupons_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Advanced_Coupons_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Advanced_Coupons constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 * Initial plugin setup method
	 */
	protected function setup() {
		$this->set_integration( 'ACFWC' );
		$this->set_name( 'Advanced Coupons' );
		$this->set_icon( 'advanced-coupons-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'advanced-coupons-for-woocommerce-free/advanced-coupons-for-woocommerce-free.php' );
	}

	/**
	 * Checks if parent plugin is active or not.
	 *
	 * @return bool
	 */
	public function plugin_active() {

		$plugin_list = get_option( 'active_plugins' );
		$plugin      = 'advanced-coupons-for-woocommerce-free/advanced-coupons-for-woocommerce-free.php';

		if ( in_array( $plugin, $plugin_list, true ) && class_exists( 'ACFWF' ) ) {
			return true;
		}

		return false;
	}

}
