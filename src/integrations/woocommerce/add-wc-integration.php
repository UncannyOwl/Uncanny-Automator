<?php

namespace Uncanny_Automator;

/**
 * Class Add_Wc_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Wc_Integration {
	use Recipe\Integrations;

	public function __construct() {
		$this->setup();
	}

	/**
	 * @return mixed
	 */
	protected function setup() {
		$this->set_integration( 'WC' );
		$this->set_name( 'WooCommerce' );
		$this->set_icon( 'woocommerce-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'woocommerce/woocommerce.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'WooCommerce' );
	}
}
