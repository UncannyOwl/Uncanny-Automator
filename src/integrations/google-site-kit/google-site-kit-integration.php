<?php

namespace Uncanny_Automator\Integrations\Google_Site_Kit;

/**
 * Class Google_Site_Kit_Integration
 *
 * @package Uncanny_Automator
 */
class Google_Site_Kit_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Google_Site_Kit_Helpers();
		$this->set_integration( 'GOOGLE_SITE_KIT' );
		$this->set_name( 'Site Kit' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/google-site-kit-icon.svg' );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		// Triggers.
		new Google_Site_Kit_User_Connects( $this->helpers );
		new Google_Site_Kit_Module_Activated( $this->helpers );
		new Google_Site_Kit_Module_Deactivated( $this->helpers );

		// Actions.
		new Google_Site_Kit_Activate_Module( $this->helpers );
		new Google_Site_Kit_Deactivate_Module( $this->helpers );
	}

	/**
	 * Check if Site Kit by Google is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'GOOGLESITEKIT_VERSION' );
	}
}
