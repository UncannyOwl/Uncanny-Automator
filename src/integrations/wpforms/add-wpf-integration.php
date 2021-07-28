<?php

namespace Uncanny_Automator;

/**
 * Class Add_Wpf_Integration
 * @package Uncanny_Automator
 */
class Add_Wpf_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Wpf_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'WPF' );
		$this->set_name( 'WP Forms' );
		$this->set_icon( 'wpforms-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		if ( class_exists( 'WPForms' ) ) {
			return true;
		}

		return false;
	}
}
