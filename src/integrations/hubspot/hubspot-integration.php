<?php

namespace Uncanny_Automator\Integrations\HubSpot;

use Uncanny_Automator\App_Integrations\App_Integration;
use Exception;

/**
 * Class HubSpot_Integration
 *
 * @package Uncanny_Automator
 */
class HubSpot_Integration extends App_Integration {

	/**
	 * The integration config.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'HUBSPOT',
			'name'         => 'HubSpot',
			'api_endpoint' => 'v2/hubspot',
			'settings_id'  => 'hubspot-api',
		);
	}

	/**
	 * Setup the integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new HubSpot_App_Helpers( self::get_config() );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/hubspot-icon.svg' );
		$this->setup_app_integration( self::get_config() );
	}

	/**
	 * Load the integration.
	 *
	 * @return void
	 */
	public function load() {

		// Credentials migration.
		new HubSpot_Credentials_Migration( 'hubspot_vault_credentials', $this->dependencies );

		// Settings.
		new HubSpot_Settings( $this->dependencies, $this->get_settings_config() );

		// Actions.
		new HUBSPOT_CREATE_CONTACT( $this->dependencies );
		new HUBSPOT_ADD_USER( $this->dependencies );
		new HUBSPOT_ADDUSERTOLIST( $this->dependencies );
		new HUBSPOT_ADDCONTACTTOLIST( $this->dependencies );
		new HUBSPOT_REMOVECONTACTFROMLIST( $this->dependencies );
		new HUBSPOT_REMOVEUSERFROMLIST( $this->dependencies );

		// Deprecated actions (kept for backwards compatibility).
		new HUBSPOT_CREATECONTACT( $this->dependencies );
		new HUBSPOT_ADDUSER( $this->dependencies );
	}

	/**
	 * Check if the integration is connected.
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		try {
			$credentials = $this->helpers->get_credentials();
			return ! empty( $credentials['hubspot_id'] );
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Recipe options AJAX handlers.
		add_action( 'wp_ajax_automator_hubspot_get_list_options', array( $this->helpers, 'get_list_options_ajax' ) );
		add_action( 'wp_ajax_automator_hubspot_get_fields', array( $this->helpers, 'get_fields_ajax' ) );
	}
}
