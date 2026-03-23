<?php
namespace Uncanny_Automator\Integrations\Google_Sheet;

use Uncanny_Automator\App_Integrations\App_Integration;

/**
 * Google Sheets App Integration
 *
 * @package Uncanny_Automator\Integrations\Google_Sheet
 * @since 5.0
 *
 * @property Google_Sheet_Helpers $helpers
 * @property Google_Sheet_Api_Caller $api
 */
class Google_Sheet_Integration extends App_Integration {

	/**
	 * Get the integration configuration.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'GOOGLESHEET',
			'name'         => 'Google Sheets',
			'api_endpoint' => 'v2/google',
			'settings_id'  => 'google-sheet',
		);
	}

	/**
	 * Setup the integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Google_Sheet_Helpers( self::get_config() );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/google-sheet-icon.svg' );
		$this->setup_app_integration( self::get_config() );
	}

	/**
	 * Load the integration components.
	 *
	 * @return void
	 */
	public function load() {
		// Load settings.
		new Google_Sheet_Settings( $this->dependencies, $this->get_settings_config() );

		// Load actions.
		new GOOGLESHEET_ADD_ROW_V2( $this->dependencies );
		new GOOGLESHEET_UPDATERECORD_V2( $this->dependencies );
	}

	/**
	 * Register AJAX hooks - called automatically by App Integration framework.
	 * Following App Integration migration guidelines - AJAX hooks in register_hooks method.
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Recipe builder AJAX endpoints.
		add_action( 'wp_ajax_automator_fetch_googlesheets_spreadsheets', array( $this->helpers, 'fetch_spreadsheets_ajax' ) );
		add_action( 'wp_ajax_automator_fetch_googlesheets_worksheets', array( $this->helpers, 'fetch_worksheets_ajax' ) );
		add_action( 'wp_ajax_automator_fetch_googlesheets_worksheets_columns', array( $this->helpers, 'fetch_worksheets_columns_ajax' ) );
		add_action( 'wp_ajax_automator_fetch_googlesheets_worksheets_columns_search', array( $this->helpers, 'fetch_worksheets_columns_search_ajax' ) );
	}

	/**
	 * Check if the app is connected.
	 *
	 * @return bool
	 */
	public function is_app_connected() {
		try {
			// Check if we have credentials and no missing scopes (same logic as legacy)
			$google_client = $this->helpers->get_credentials();
			if ( ! $google_client ) {
				return false;
			}

			// Check for missing scopes
			if ( $this->helpers->has_missing_scope() ) {
				return false;
			}

			// Show as disconnected if the user has generic drive scope
			if ( $this->helpers->has_generic_drive_scope() ) {
				return false;
			}

			return true;

		} catch ( \Exception $e ) {
			return false;
		}
	}
}
