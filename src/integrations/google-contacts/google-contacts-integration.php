<?php

namespace Uncanny_Automator\Integrations\Google_Contacts;

use Uncanny_Automator\App_Integrations\App_Integration;

/**
 * @package Uncanny_Automator\Integrations\Google_Contacts
 */
class Google_Contacts_Integration extends App_Integration {

	/**
	 * Define configuration.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'GOOGLE_CONTACTS',
			'name'         => 'Google Contacts',
			'api_endpoint' => 'v2/google-contacts',
			'settings_id'  => 'google-contacts',
		);
	}

	/**
	 * Setup the integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$config = self::get_config();

		// Create helpers with config
		$this->helpers = new Google_Contacts_Helpers( $config );

		// Set icon URL.
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/google-contacts-icon.svg' );

		// Setup app integration with same config.
		$this->setup_app_integration( $config );
	}

	/**
	 * Load the integration.
	 *
	 * @return void
	 */
	public function load() {
		// Settings page.
		new Google_Contacts_Settings( $this->dependencies, $this->get_settings_config() );

		// Actions.
		new CREATE( $this->dependencies );

		// Contact group add.
		new CONTACT_GROUP_ADD_TO( $this->dependencies );

		// Contact create.
		new CONTACT_GROUP_CREATE( $this->dependencies );
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Fetch labels.
		add_action( 'wp_ajax_automator_google_contacts_fetch_labels', array( $this->helpers, 'ajax_fetch_labels' ) );
	}

	/**
	 * Check if the app is connected
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		$credentials = $this->helpers->get_credentials();
		if ( ! is_array( $credentials ) || ! isset( $credentials['access_token'] ) ) {
			return false;
		}

		return true;
	}
}
