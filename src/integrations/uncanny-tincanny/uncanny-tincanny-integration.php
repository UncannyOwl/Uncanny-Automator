<?php

namespace Uncanny_Automator\Integrations\Uncanny_Tincanny;

/**
 * Class Uotc_Integration
 *
 * @package Uncanny_Automator
 */
class Uotc_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Uotc_Helpers();
		$this->set_integration( 'UOTC' );
		$this->set_name( 'Tin Canny Reporting' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/uncanny-owl-icon.svg' );

		// Deprecated shim -- Pro condition may use the singleton chain.
		\Automator()->helpers->recipe->uncanny_tincanny = $this->helpers;
	}

	/**
	 * Load triggers.
	 *
	 * @return void
	 */
	public function load() {
		// Legacy token class for backward compatibility.
		new \Uncanny_Automator\UOTC_Tokens();

		// Triggers.
		new UOTC_MODULEINTERACTION( $this->helpers );
		new UOTC_USERATTAINSSCORE( $this->helpers );
	}

	/**
	 * Check if Tin Canny Reporting and LearnDash are active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'LEARNDASH_VERSION' ) && defined( 'UNCANNY_REPORTING_VERSION' );
	}

}
