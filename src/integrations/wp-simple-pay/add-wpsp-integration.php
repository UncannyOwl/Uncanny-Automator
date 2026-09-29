<?php

namespace Uncanny_Automator;

/**
 * Class Add_Wpsp_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Wpsp_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Wpsp_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'WPSIMPLEPAY' );
		$this->set_name( 'WP Simple Pay' );
		$this->set_icon( 'wp-simple-pay-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		// WP Simple Pay Lite ships from the 'stripe' folder, not 'wp-simple-pay'.
		$this->set_plugin_file_path( 'stripe/stripe-checkout.php' );

		$this->set_developer_name( 'WP Simple Pay' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'wp_org' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'SIMPLE_PAY_VERSION' );
	}
}
