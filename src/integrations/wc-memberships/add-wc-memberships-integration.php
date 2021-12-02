<?php

namespace Uncanny_Automator;

/**
 * Class Add_Wc_Memberships_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Wc_Memberships_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Wc_Memberships_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'WCMEMBERSHIPS' );
		$this->set_name( 'Woo Memberships' );
		$this->set_icon( 'woocommerce-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'woocommerce-memberships/woocommerce-memberships.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'WC_Memberships_Loader' );
	}
}
