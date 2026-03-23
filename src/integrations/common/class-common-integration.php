<?php

namespace Uncanny_Automator\Integrations\Common;

use Uncanny_Automator\Integration;

/**
 * Class Common_Integration
 *
 * @package Uncanny_Automator
 */
class Common_Integration extends Integration {

	use \Uncanny_Automator\Integration_Manifest;

	public $tokens;

	/**
	 * Setup integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->set_integration( 'COMMON' );
		$this->set_name( 'Common' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/common-icon.svg' );

		// Set manifest data for built-in integration.
		$this->set_integration_type( 'built-in' );
		$this->set_integration_tier( 'lite' );
		$this->set_integration_version( AUTOMATOR_PLUGIN_VERSION );
		$this->set_short_description( esc_html_x( 'Common tokens for site information, user details, and recipe data.', 'Common', 'uncanny-automator' ) );
		$this->set_full_description( esc_html_x( 'Provides common tokens including site information (name, URL, tagline), user details (name, email, role, IP address), and recipe data (ID, name) for use in automation workflows.', 'Common', 'uncanny-automator' ) );
	}

	/**
	 * load
	 *
	 * @return void
	 */
	protected function load() {
		new Tokens\Admin_Email();
		new Tokens\Current_Blog_Id();
		new Tokens\Recipe_Id();
		new Tokens\Recipe_Name();
		new Tokens\Reset_Pass_Link();
		new Tokens\Site_Name();
		new Tokens\Site_Tagline();
		new Tokens\Site_Url();
		new Tokens\User_Displayname();
		new Tokens\User_Email();
		new Tokens\User_Firstname();
		new Tokens\User_Id();
		new Tokens\User_Ip_Address();
		new Tokens\User_Lastname();
		new Tokens\User_Locale();
		new Tokens\User_Reset_Pass_Url();
		new Tokens\User_Role();
		new Tokens\User_Username();
		new Tokens\User_Registration_Date();
	}
}
