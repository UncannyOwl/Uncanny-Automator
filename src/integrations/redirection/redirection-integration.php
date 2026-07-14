<?php

namespace Uncanny_Automator\Integrations\Redirection;

/**
 * Class Redirection_Integration
 *
 * @package Uncanny_Automator
 */
class Redirection_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Redirection_Helpers();
		$this->set_integration( 'REDIRECTION' );
		$this->set_name( 'Redirection' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/redirection-icon.svg' );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		// Triggers.
		new Redirection_Redirect_Matched( $this->helpers );
		new Redirection_Redirect_Deleted( $this->helpers );
		new Redirection_404_Logged( $this->helpers );
		new Redirection_Monitor_Created( $this->helpers );
	}

	/**
	 * Check if Redirection is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		// REDIRECTION_VERSION is defined in the plugin's main file at load,
		// so it is the most reliable "is the plugin active" signal (the
		// Red_Item model class is loaded later on init).
		return defined( 'REDIRECTION_VERSION' );
	}
}
