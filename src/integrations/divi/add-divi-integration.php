<?php

namespace Uncanny_Automator;

/**
 * Add Divi Integration
 */
class Add_Divi_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Divi_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 * Set up integration
	 */
	protected function setup() {
		$this->set_integration( 'DIVI' );
		$this->set_name( 'Divi' );
		$this->set_icon( 'divi-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( '' );
	}

	/**
	 * Check if Divi theme is active
	 *
	 * @return bool
	 */
	public function plugin_active() {
		$theme = wp_get_theme(); // gets the current theme
		if ( 'Divi' === $theme->get_template() ) {
			return true;
		}

		return false;
	}
}
