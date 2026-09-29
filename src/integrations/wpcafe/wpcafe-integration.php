<?php

namespace Uncanny_Automator\Integrations\Wpcafe;

/**
 * Class Wpcafe_Integration
 *
 * @package Uncanny_Automator
 */
class Wpcafe_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->helpers = new Wpcafe_Helpers();
		$this->set_integration( 'WPCAFE' );
		$this->set_name( 'WPCafe' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/wpcafe-icon.svg' );

		$this->set_plugin_file_path( 'wp-cafe/wpcafe.php' );
		$this->set_developer_name( 'Themewinter' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'wp_org' );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {

		// Triggers.
		new Wpcafe_Table_Booked( $this->helpers );
		new Wpcafe_Reservation_Status_Changed( $this->helpers );
		new Wpcafe_Reservation_Cancelled( $this->helpers );

		// Actions.
		new Wpcafe_Create_Reservation( $this->helpers );
		new Wpcafe_Update_Reservation_Status( $this->helpers );
	}

	/**
	 * Check if WPCafe is active.
	 *
	 * `WPCAFE_VERSION` is defined by the base (free) plugin. Every item in this
	 * integration runs on base WPCafe — none of them require WP Cafe Pro.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'WPCAFE_VERSION' );
	}
}
