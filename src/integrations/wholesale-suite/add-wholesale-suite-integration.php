<?php

namespace Uncanny_Automator;

/**
 * Class Add_Wholesale_Suite_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Wholesale_Suite_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 * Integration Set-up.
	 */
	protected function setup() {
		$this->set_integration( 'WHOLESALESUITE' );
		$this->set_name( 'Wholesale Suite' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_icon( 'wholesale-suite.svg' );

		$this->set_plugin_file_path( 'woocommerce-wholesale-prices/woocommerce-wholesale-prices.bootstrap.php' );
		$this->set_developer_name( 'Wholesale Suite' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'wp_org' );
	}

	/**
	 * Method plugin_active
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'WooCommerceWholeSalePrices' );
	}

}
