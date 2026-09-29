<?php

namespace Uncanny_Automator\Integrations\Edd_Recurring_Integration;

use Uncanny_Automator\Integration;

/**
 * Class Edd_Recurring_Integration
 *
 * @package Uncanny_Automator\Integrations\Edd_Recurring_Integration
 */
class Edd_Recurring_Integration extends Integration {

	/**
	 * Set up the integration.
	 */
	protected function setup() {
		$this->helpers = new Edd_Recurring_Helpers();
		$this->set_integration( 'EDD_RECURRING' );
		$this->set_name( 'EDD - Recurring Payments' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/easy-digital-downloads-icon.svg' );

		$this->set_plugin_file_path( 'edd-recurring/edd-recurring.php' );
		$this->set_developer_name( 'Easy Digital Downloads' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'commercial' );
		$this->set_plugin_required( 'easy-digital-downloads/easy-digital-downloads.php' );
		$this->set_integration_required( 'EDD' );
	}

	/**
	 * Load the triggers and actions.
	 */
	protected function load() {
		// Load migration script
		require_once __DIR__ . '/migrations/migrate-edd-to-eddr.php';

		// Initialize the migration
		new EDD_To_EDDR_Migration();

		// Load tokens

		//triggers
		new EDD_USER_SUBSCRIBES_TO_DOWNLOAD( $this->helpers );

		//actions
		new EDD_CANCEL_USERS_SUBSCRIPTION( $this->helpers );
	}



	/**
	 * @return bool
	 */
	public function plugin_active() {
		return function_exists( 'EDD' ) && class_exists( 'EDD_Recurring' );
	}
}
