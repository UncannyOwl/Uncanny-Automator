<?php

namespace Uncanny_Automator\Integrations\Sucuri;

/**
 * Class Sucuri_Integration
 *
 * @package Uncanny_Automator
 */
class Sucuri_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Sucuri_Helpers();
		$this->set_integration( 'SUCURI' );
		$this->set_name( 'Sucuri Security' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/sucuri-security-icon.svg' );

		$this->set_plugin_file_path( 'sucuri-scanner/sucuri.php' );
		$this->set_developer_name( 'Sucuri Inc.' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'wp_org' );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		// Actions.
		new Sucuri_Run_Malware_Scan( $this->helpers );
		new Sucuri_Apply_Hardening( $this->helpers );
		new Sucuri_Remove_Hardening( $this->helpers );
		new Sucuri_Log_Security_Event( $this->helpers );
		new Sucuri_Reset_User_Password( $this->helpers );
	}

	/**
	 * Check if Sucuri Security is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'SUCURISCAN_INIT' );
	}
}
