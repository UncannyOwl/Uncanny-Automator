<?php

namespace Uncanny_Automator;

/**
 * Class Add_Optinmonster_Integration
 */
class Add_Optinmonster_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Optinmonster_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 * setup
	 *
	 * @return void
	 */
	protected function setup() {
		$this->set_integration( 'OPTINMONSTER' );
		$this->set_name( 'OptinMonster' );
		$this->set_icon( 'optinmonster-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'optinmonster/optin-monster-wp-api.php' );
	}

	/**
	 * plugin_active
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'OMAPI' );
	}

}
