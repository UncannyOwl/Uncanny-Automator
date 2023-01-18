<?php

namespace Uncanny_Automator;

/**
 * Class Add_Thrive_Leads_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Thrive_Leads_Integration {
	use Recipe\Integrations;

	/**
	 * Add_Advanced_Ads_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'THRIVELEADS' );
		$this->set_name( 'Thrive Leads' );
		$this->set_icon( 'thrive-leads-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'thrive-leads/thrive-leads.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'TVE_LEADS_PATH' );
	}
}
