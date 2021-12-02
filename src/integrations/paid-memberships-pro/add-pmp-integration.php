<?php

namespace Uncanny_Automator;

/**
 * Class Add_Pmp_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Pmp_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Pmp_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'PMP' );
		$this->set_name( 'Paid Memberships Pro' );
		$this->set_icon( 'paid-memberships-pro-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'paid-memberships-pro/paid-memberships-pro.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'PMPRO_BASE_FILE' );
	}
}
