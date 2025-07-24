<?php

namespace Uncanny_Automator;

/**
 * Class Add_GP_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Gp_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Gp_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'GP' );
		$this->set_name( 'GamiPress' );
		$this->set_icon( 'gamipress-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'gamipress/gamipress.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		$is_active = class_exists( 'GamiPress' );

		if ( true === $is_active ) {
			include_once __DIR__ . '/triggers/gp-deduct-user-points.php';
			new \Uncanny_Automator\GP_DEDUCT_USER_POINTS();

			return true;
		}

		return false;
	}
}
