<?php

namespace Uncanny_Automator\Integrations\Wordfence;

/**
 * Class Wordfence_Integration
 *
 * @package Uncanny_Automator
 */
class Wordfence_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Wordfence_Helpers();
		$this->set_integration( 'WORDFENCE' );
		$this->set_name( 'Wordfence Security' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/wordfence-icon.svg' );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		// Triggers.
		new Wordfence_Ip_Locked_Out( $this->helpers );
		new Wordfence_Breached_Password_Login( $this->helpers );
		new Wordfence_Ip_Blocked_Throttled( $this->helpers );
		new Wordfence_Block_Deleted( $this->helpers );
		new Wordfence_2fa_Activated( $this->helpers );
		new Wordfence_2fa_Deactivated( $this->helpers );
		new Wordfence_User_Logs_In( $this->helpers );

		// Actions.
		new Wordfence_Block_Ip( $this->helpers );
		new Wordfence_Unblock_Ip( $this->helpers );
		new Wordfence_Unlock_Ip( $this->helpers );
	}

	/**
	 * Check if Wordfence Security is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'WORDFENCE_VERSION' );
	}
}
