<?php

namespace Uncanny_Automator;

/**
 * Class Add_Airtable_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Airtable_Integration {

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

		$this->set_integration( 'AIRTABLE' );

		$this->set_name( 'Airtable' );

		$this->set_icon( __DIR__ . '/img/airtable-icon.svg' );

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
