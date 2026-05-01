<?php

namespace Uncanny_Automator\Integrations\Aweber;

/**
 * Class Aweber_Integration
 *
 * @package Uncanny_Automator
 */
class Aweber_Integration extends \Uncanny_Automator\App_Integrations\App_Integration {

	/**
	 * Get the integration config.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'AWEBER',
			'name'         => 'AWeber',
			'api_endpoint' => 'v2/aweber',
			'settings_id'  => 'aweber',
		);
	}

	/**
	 * Spin up the integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$config = self::get_config();

		// Create helpers with config.
		$this->helpers = new Aweber_App_Helpers( $config );

		// Set icon URL.
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/aweber-icon.svg' );

		// Setup app integration with same config.
		$this->setup_app_integration( $config );
	}

	/**
	 * Load the integration components.
	 *
	 * @return void
	 */
	public function load() {
		// Settings page.
		new Aweber_Settings( $this->dependencies, $this->get_settings_config() );

		// Actions.
		new AWEBER_SUBSCRIBER_ADD( $this->dependencies );
		new AWEBER_SUBSCRIBER_UPDATE( $this->dependencies );
		new AWEBER_SUBSCRIBER_TAG_ADD( $this->dependencies );
	}

	/**
	 * Check if the app is connected
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		try {
			$credentials = $this->helpers->get_credentials();
			return ! empty( $credentials['access_token'] );
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Register AJAX hooks for dynamic field loading
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Register field loading handlers.
		add_action( 'wp_ajax_automator_aweber_accounts_fetch', array( $this->helpers, 'accounts_fetch' ) );
		add_action( 'wp_ajax_automator_aweber_list_fetch', array( $this->helpers, 'lists_fetch' ) );
		add_action( 'wp_ajax_automator_aweber_custom_fields_fetch', array( $this->helpers, 'custom_fields_fetch' ) );
	}
}
