<?php

namespace Uncanny_Automator\Integrations\Wp_Event_Manager;

/**
 * Class Wp_Event_Manager_Integration
 *
 * @package Uncanny_Automator
 */
class Wp_Event_Manager_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Integration Set-up.
	 */
	protected function setup() {
		$this->helpers = new Wp_Event_Manager_Helpers();
		$this->set_integration( 'WP_EVENT_MANAGER' );
		$this->set_name( 'WP Event Manager' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/wp-event-manager-icon.svg' );
	}

	/**
	 * Bootstrap actions, triggers, settings page, etc.
	 *
	 * @return void
	 */
	public function load() {
		// Triggers
		new Wp_Event_Manager_Attendee_Registered( $this->helpers );

		// Actions
		new Wp_Event_Manager_Register_Attendee( $this->helpers );
	}

	/**
	 * Check if the plugin is active.
	 *
	 * @return bool Returns true if WP Event Manager and Registrations are active. Returns false, otherwise.
	 */
	public function plugin_active() {
		return class_exists( '\WP_Event_Manager' );
	}
}
