<?php

namespace Uncanny_Automator\Integrations\URL;

/**
 * Class URL_Integration
 *
 * @package Uncanny_Automator
 */
class URL_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new URL_Helpers();
		$this->set_integration( 'URL' );
		$this->set_name( 'URL' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/url-icon.svg' );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		// Load tokens
		new URL_Tokens();
		// Load triggers
		new URL_HAS_PARAM( $this->helpers );
		new URL_HAS_PARAM_LOGGED_IN( $this->helpers );
	}

	/**
	 * Check if Plugin is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return true;
	}
}
