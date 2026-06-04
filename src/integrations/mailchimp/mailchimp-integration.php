<?php

namespace Uncanny_Automator\Integrations\Mailchimp;

use Uncanny_Automator\App_Integrations\App_Integration;
use Exception;

/**
 * Class Mailchimp_Integration
 *
 * @package Uncanny_Automator
 */
class Mailchimp_Integration extends App_Integration {

	/**
	 * Get the integration config.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'MAILCHIMP',     // Integration code.
			'name'         => 'Mailchimp',     // Integration name.
			'api_endpoint' => 'v2/mailchimp',  // Automator API server endpoint.
			'settings_id'  => 'mailchimp_api', // Settings ID ( Settings url / tab id ).
		);
	}

	/**
	 * Integration Set-up.
	 */
	protected function setup() {

		// Define helpers with common config values.
		$this->helpers = new Mailchimp_App_Helpers( self::get_config() );

		// Set the icon URL.
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/mailchimp-icon.svg' );

		// Finalize setup via the parent class with the common config.
		$this->setup_app_integration( self::get_config() );
	}

	/**
	 * Load Integration Classes.
	 */
	public function load() {

		// Load settings page.
		new Mailchimp_Settings(
			$this->dependencies,
			$this->get_settings_config()
		);

		// Load user-based actions (require logged-in user).
		new AUDIENCE_ADDAUSER( $this->dependencies );
		new AUDIENCE_ADDUSERNOTE( $this->dependencies );
		new AUDIENCE_ADDUSERTAG( $this->dependencies );
		new AUDIENCE_REMOVEUSERTAG( $this->dependencies );
		new AUDIENCE_UNSUBSCRIBEAUSER( $this->dependencies );

		// Load everyone/anonymous actions (work with any email).
		new MC_EVERYONE_ADD_CONTACT( $this->dependencies );
		new MC_EVERYONE_CONTACT_REMOVE( $this->dependencies );
		new MC_EVERYONE_USER_ADD_TAG( $this->dependencies );
		new MC_EVERYONE_USER_REMOVE_TAG( $this->dependencies );
		new MC_EVERYONE_USER_ADD_NOTE( $this->dependencies );

		// Load campaign action.
		new CAMPAIGN_CREATEANDSEND( $this->dependencies );

		// Load triggers.
		new ANON_MAILCHIMP_CONTACT_ADDED( $this->dependencies );
		new ANON_MAILCHIMP_CONTACT_EMAIL_CHANGED( $this->dependencies );
		new ANON_MAILCHIMP_CONTACT_UNSUBSCRIBED( $this->dependencies );
	}

	/**
	 * Is App connected.
	 *
	 * @return bool
	 */
	public function is_app_connected() {
		try {
			$credentials = $this->helpers->get_credentials();
			// Check if we have valid credentials
			return ! empty( $credentials['access_token'] );
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
		// Register trigger token hooks (legacy approach for backwards compatibility).
		$tokens = new Mailchimp_Tokens();
		$tokens->register_hooks( $this->helpers );
	}
}
