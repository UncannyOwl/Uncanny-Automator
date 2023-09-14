<?php

namespace Uncanny_Automator\Integrations\Common;

/**
 * Class Common_Integration
 *
 * @package Uncanny_Automator
 */
class Common_Integration extends \Uncanny_Automator\Integration {

	public $tokens;

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'COMMON' );
		$this->set_name( 'Common' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/common-icon.svg' );
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
	}
}
