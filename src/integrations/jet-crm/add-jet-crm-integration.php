<?php

namespace Uncanny_Automator;

/**
 * Class Add_Jet_Crm_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Jet_Crm_Integration {
	use Recipe\Integrations;

	/**
	 * Add_Edd_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'JETCRM' );
		$this->set_name( 'Jetpack CRM' );
		$this->set_icon( 'jetpack-crm-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'ZeroBSCRM' );
	}
}
