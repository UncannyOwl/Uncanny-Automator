<?php

namespace Uncanny_Automator;

/**
 * Class Add_Make_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Make_Integration {

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

		$this->set_integration( 'MAKE' );

		$this->set_name( 'Make' );

		$this->set_icon( __DIR__ . '/img/make-icon.svg' );

	}

	/**
	 * Explicitly return true because it doesn't depend on any 3rd-party plugin.
	 *
	 * @return bool
	 */
	public function plugin_active() {

		return true;

	}
}
