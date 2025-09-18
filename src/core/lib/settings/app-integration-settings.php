<?php
/**
 * Abstract class used to create the setting pages of the premium App integrations
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator\Settings;

use Exception;

/**
 * Class to create settings pages for App Integrations.
 *
 * @package Uncanny_Automator\Settings
 */
abstract class App_Integration_Settings {
	// Add templating trait.
	use Premium_Integration_Templating;
	// Add items trait to get available actions and triggers.
	use Premium_Integration_Items;
	// Add alerts trait to get available alerts.
	use Premium_Integration_Alerts;
	// Add options trait to handle registered options.
	use Settings_Options;
	// Add REST processing trait for common REST functionality.
	use Premium_Integration_Rest_Processing;
	// Add setup trait for shared setup functionality.
	use App_Integration_Settings_Setup;

	/**
	 * Whether the integration requires credits
	 *
	 * @var boolean
	 */
	public $requires_credits = true;

	/**
	 * Whether the integration is a third party integration
	 *
	 * @var null\boolean - null if not set
	 */
	public $is_third_party = null;

	/**
	 * The status of the integration
	 * This expects a valid <uo-tab> status
	 * Check the Design Guidelines to see the list of valid statuses
	 *
	 * @var String
	 */
	public $status;

	/**
	 * The HTML output of the tab
	 *
	 * @var String
	 */
	public $content;

	/**
	 * The preload setting of the integration
	 * This defines whether the content should be loaded even if the tab
	 * is not selected
	 *
	 * @var String
	 */
	public $preload = false;

	/**
	 * App Helpers instance for this integration.
	 *
	 * @var App_Helpers
	 */
	public $helpers;

	/**
	 * API instance for this integration.
	 *
	 * @var Api_Caller
	 */
	public $api;

	/**
	 * Webhooks instance for this integration.
	 *
	 * @var App_Webhooks|null
	 */
	public $webhooks;

	/**
	 * Integration dependencies.
	 *
	 * @var object
	 * @property App_Helpers helpers
	 * @property Api_Caller api
	 * @property App_Webhooks webhooks|null
	 */
	public $dependencies;

	/**
	 * Settings configuration for this integration.
	 *
	 * @var array
	 */
	public $settings = array();

	/**
	 * Constructor.
	 *
	 * @param  object $dependencies Dependencies object
	 * @param  array $settings     Optional settings array
	 *
	 * @return void
	 */
	final public function __construct( $dependencies, $settings = array() ) {

		if ( ! empty( $dependencies ) ) {
			$this->dependencies = $dependencies;

			// Set direct properties for clean access
			if ( is_object( $this->dependencies ) ) {
				$this->helpers  = $this->dependencies->helpers ?? null;
				$this->api      = $this->dependencies->api ?? null;
				$this->webhooks = $this->dependencies->webhooks ?? null;
			}
		}

		// Store settings for later use.
		$this->settings = $settings;

		// Setup the settings page properties.
		$this->setup_settings_properties();

		// Register the hooks.
		$this->register_settings_hooks();
	}

	////////////////////////////////////////////////////////////
	// Required methods
	////////////////////////////////////////////////////////////

	/**
	 * Get formatted account information for connected user info display
	 * This is a required abstract method that integrations must implement
	 * to provide account information for the UI via output_connected_user_info
	 *
	 * @return array Formatted account information for UI display
	 *               Should include: avatar_type, avatar_value, main_info, additional (optional)
	 */
	abstract protected function get_formatted_account_info();

	////////////////////////////////////////////////////////////
	// Setup methods
	////////////////////////////////////////////////////////////

	/**
	 * Setup the settings page properties from the config.
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function setup_settings_properties() {
		// Set settings if provided.
		if ( ! empty( $this->settings ) ) {
			$this->set_settings( $this->settings );
		}

		// Set the status.
		$this->set_status( $this->is_connected ? 'success' : '' );

		// Maybe set third party integration and credits required property.
		$this->maybe_set_third_party_integration( get_called_class() );

		// Allow extending class to set additional properties.
		$this->set_properties();

		// Connection status specific properties.
		if ( $this->is_connected ) {
			$this->set_connected_properties();
		} else {
			$this->set_disconnected_properties();
		}
	}

	/**
	 * Register the settings hooks.
	 *
	 * @return void
	 */
	public function register_settings_hooks() {
		// Add the tab using the filter
		add_filter(
			'automator_settings_premium_integrations_tabs',
			array( $this, 'add_tab' )
		);

		// Enqueue the assets
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Register the options for dynamic sanitization & saving.
		$this->option_registration();

		// Check if the webhook settings trait is used.
		if ( method_exists( $this, 'register_webhook_hooks' ) ) {
			/** @disregard P1013 Undefined method */
			$this->register_webhook_hooks();
		}

		// Register the hooks.
		$this->register_hooks();

		// Register this integration with the Action manager via filter.
		// This will return the Settings instance for validation and processing.
		add_filter(
			'automator_integration_settings_instance_' . sanitize_key( $this->get_id() ),
			function () {
				return $this;
			}
		);
	}

	/**
	 * Returns whether the integration requires credits
	 *
	 * @return boolean TRUE if the integration requires credits
	 */
	public function get_requires_credits() {
		return $this->requires_credits;
	}

	/**
	 * Sets whether the integration requires credits
	 *
	 * @param boolean $requires_credits TRUE if the integration requires credits
	 */
	public function set_requires_credits( $requires_credits = true ) {
		$this->requires_credits = $requires_credits;
	}

	/**
	 * Sets the integration status.
	 *
	 * @param String 'success' or an empty string
	 *
	 * @return void
	 */
	public function set_status( $status ) {
		$this->status = $status;
	}

	/**
	 * Returns the integration status
	 *
	 * @return String The integration status
	 */
	public function get_status() {
		return $this->status;
	}

	/**
	 * Sets the output HTML of the setting tab
	 *
	 * @param String
	 */
	public function set_content( $content ) {
		$this->content = $content;
	}

	/**
	 * Sets the preload setting
	 * This defines whether the content should be loaded even if the tab
	 * is not selected
	 *
	 * @param boolean $preload TRUE if Automator should load the content even if the tab not selected
	 */
	public function set_preload( $preload = false ) {
		$this->preload = $preload;
	}

	/**
	 * Returns the preload setting
	 *
	 * @return boolean TRUE if Automator should load the content even if the tab is not selected
	 */
	public function get_preload() {
		return ! empty( $this->preload ) ? $this->preload : false;
	}

	/**
	 * Returns the URL to the Settings page of this integration
	 *
	 * @param array $params Optional. Additional parameters to add to the URL.
	 *
	 * @return String The URL
	 */
	public function get_settings_page_url( $params = array() ) {
		return $this->helpers->get_settings_page_url( $params );
	}

	/**
	 * Adds the tab and the function that outputs the content to the Settings page
	 *
	 * @param array $tabs The tabs
	 * @return array The tabs
	 */
	public function add_tab( $tabs ) {

		// Check if the required data is defined
		if ( empty( $this->get_id() ) || empty( $this->get_name() ) ) {
			throw new Exception( 'Premium Integration: Define the ID and name of the integration' );
		}

		// Check if the ID is defined
		// Create the tab
		$tabs[ $this->get_id() ] = array(
			'name'             => $this->get_name(),
			'icon'             => $this->get_icon(),
			'status'           => $this->get_status(),
			'preload'          => $this->get_preload(),
			'requires_credits' => $this->get_requires_credits(),
			'is_third_party'   => $this->get_is_third_party(),
			'function'         => array( $this, 'output_wrapper' ),
		);

		return $tabs;
	}

	/**
	 * Maybe set third party integration and credits required property
	 *
	 * @param  mixed $this_class
	 * @return void
	 */
	protected function maybe_set_third_party_integration( $this_class ) {
		// Check if not set yet.
		if ( is_null( $this->is_third_party ) ) {
			$this->set_is_third_party( Automator()->is_third_party_integration_by_class( $this_class ) );
		}

		// Disable requires credits.
		if ( $this->get_is_third_party() ) {
			$this->set_requires_credits( false );
		}
	}
}
