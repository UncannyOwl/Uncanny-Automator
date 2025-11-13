<?php

namespace Uncanny_Automator\Integrations\Thrive_Leads;

use Uncanny_Automator\Integration;

/**
 * Class Thrive_Leads_Integration
 *
 * @package Uncanny_Automator
 */
class Thrive_Leads_Integration extends Integration {

	/**
	 * Setup the integration.
	 */
	protected function setup() {
		$this->helpers = new Thrive_Leads_Helpers();
		$this->set_integration( 'THRIVELEADS' );
		$this->set_name( 'Thrive Leads' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/thrive-leads-icon.svg' );
	}

	/**
	 * Load the integration.
	 */
	public function load() {
		// Load triggers
		new ANON_TL_FORM_SUBMITTED( $this->helpers );
		new TL_FORM_SUBMITTED( $this->helpers );
		new TL_REGISTRATION_FORM_SUBMITTED( $this->helpers );
	}

	/**
	 * Check if the Thrive Leads plugin is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'TVE_LEADS_PATH' );
	}
}
