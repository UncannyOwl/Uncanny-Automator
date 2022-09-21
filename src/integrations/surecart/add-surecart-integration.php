<?php

namespace Uncanny_Automator;

/**
 * Class Add_SureCart_Integration
 *
 * @package Uncanny_Automator
 */
class Add_SureCart_Integration {

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

		$this->set_integration( 'SURECART' );

		$this->set_name( 'SureCart' );

		$this->set_icon( __DIR__ . '/img/surecart-icon.svg' );

	}

	/**
	 * Explicitly return true because it doesn't depend on any 3rd-party plugin.
	 *
	 * @return bool
	 */
	public function plugin_active() {

		return defined( 'SURECART_PLUGIN_FILE' );

	}
}
