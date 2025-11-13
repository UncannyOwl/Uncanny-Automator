<?php

namespace Uncanny_Automator\Integrations\Active_Campaign;

use Uncanny_Automator\App_Integrations\App_Integration;
use Exception;

/**
 * Class Active_Campaign_Integration
 *
 * @package Uncanny_Automator
 */
class Active_Campaign_Integration extends App_Integration {

	/**
	 * Get the integration config.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'ACTIVE_CAMPAIGN',    // Integration code.
			'name'         => 'ActiveCampaign',     // Integration name.
			'api_endpoint' => 'v2/active-campaign', // Automator API server endpoint.
			'settings_id'  => 'active-campaign',    // Settings ID ( Settings url / tab id ).
		);
	}

	/**
	 * Integration Set-up.
	 */
	protected function setup() {

		// Define helpers with common config values.
		$this->helpers = new Active_Campaign_App_Helpers( self::get_config() );

		// Set the icon URL.
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/activecampaign-icon.svg' );

		// Finalize setup via the parent class with the common config.
		$this->setup_app_integration( self::get_config() );
	}

	/**
	 * Load Integration Classes.
	 */
	public function load() {

		// Load settings page.
		new Active_Campaign_Settings(
			$this->dependencies,
			$this->get_settings_config()
		);

		// Load actions.
		new AC_ANNON_ADD( $this->dependencies );
		new AC_ANNON_ADDTAG( $this->dependencies );
		new AC_ANNON_LIST_ADD( $this->dependencies );
		new AC_ANNON_LISTREMOVE( $this->dependencies );
		new AC_ANNON_REMOVETAG( $this->dependencies );
		new AC_USER_ADD_TAG( $this->dependencies );
		new AC_USER_ADD( $this->dependencies );
		new AC_USER_LIST_ADD( $this->dependencies );
		new AC_USER_LIST_REMOVE( $this->dependencies );
		new AC_USER_REMOVE_TAG( $this->dependencies );
		new AC_ANNON_CONTACT_DELETE( $this->dependencies );

		// Load triggers.
		new AC_CONTACT_TAG_ADDED( $this->dependencies );
		new AC_CONTACT_TAG_REMOVED( $this->dependencies );
	}

	/**
	 * Is App connected.
	 *
	 * @return bool
	 */
	public function is_app_connected() {
		try {
			$this->helpers->get_credentials();
			// Check if we have user info.
			$account = $this->helpers->get_account_info();
			return ! empty( $account['email'] );
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
		// Recipe UI option AJAX handlers.
		add_action( 'wp_ajax_active-campaign-list-tags', array( $this->helpers, 'list_tags_ajax' ) );
		add_action( 'wp_ajax_active-campaign-list-retrieve', array( $this->helpers, 'list_retrieve_ajax' ) );
	}
}
