<?php

namespace Uncanny_Automator\Integrations\Edd_SL;

use Uncanny_Automator\Integration;

/**
 * Class Edd_Software_Licensing_Integration
 *
 * @package Uncanny_Automator
 */
class Edd_Software_Licensing_Integration extends Integration {

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Edd_Sl_Helpers();
		$this->set_integration( 'EDD_SL' );
		$this->set_name( 'EDD Software Licensing' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/easy-digital-downloads-icon.svg' );

		$this->set_plugin_file_path( 'edd-software-licensing/edd-software-licenses.php' );
		$this->set_developer_name( 'Easy Digital Downloads' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'commercial' );
		$this->set_plugin_required( 'easy-digital-downloads/easy-digital-downloads.php' );
		$this->set_integration_required( 'EDD' );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		// Load triggers.
		new EDD_SL_LICENSE_CREATED_FOR_DOWNLOAD( $this->helpers );
		new EDD_SL_LICENSE_EXPIRED_FOR_DOWNLOAD( $this->helpers );

		new EDD_SL_USERS_LICENSE_CREATED_FOR_DOWNLOAD( $this->helpers );
		new EDD_SL_USERS_LICENSE_EXPIRED_FOR_DOWNLOAD( $this->helpers );
	}

	/**
	 * Check if Plugin is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'EDD_SL_Requirements_Check' );
	}
}
