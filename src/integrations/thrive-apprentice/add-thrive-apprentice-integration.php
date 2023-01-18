<?php
namespace Uncanny_Automator;

class Add_Thrive_Apprentice_Integration {

	use Recipe\Integrations;

	public function __construct() {

		$this->setup();

	}

	/**
	 * Sets up Thrive Apprentice integration.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->set_integration( 'THRIVE_APPRENTICE' );

		$this->set_name( 'Thrive Apprentice' );

		$this->set_icon( 'thrive-apprentice-icon.svg' );

		$this->set_icon_path( __DIR__ . '/img/' );

	}

	/**
	 * Determines whether the integration should be loaded or not.
	 *
	 * Checks whether an existing depencency condition is satisfied.
	 *
	 * @return bool Returns true if \TVA_Manager class is active. Returns false, othwerwise.
	 */
	public function plugin_active() {

		return class_exists( '\TVA_Manager' );

	}

}
