<?php

namespace Uncanny_Automator\Integrations\Sg_Security;

/**
 * Class Sg_Security_Integration
 *
 * @package Uncanny_Automator
 */
class Sg_Security_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Sg_Security_Helpers();
		$this->set_integration( 'SG_SECURITY' );
		$this->set_name( 'SG Security' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/sg-security-icon.svg' );
	}

	/**
	 * Load actions.
	 *
	 * @return void
	 */
	public function load() {
		new Sg_Block_Ip( $this->helpers );
		new Sg_Unblock_Ip( $this->helpers );
		new Sg_Block_User( $this->helpers );
		new Sg_Unblock_User( $this->helpers );
		new Sg_Force_Logout_All();
		new Sg_Force_Password_Reset_All();
	}

	/**
	 * Check if SG Security is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( '\SG_Security\Helper\Helper' );
	}
}
