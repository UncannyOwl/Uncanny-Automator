<?php

namespace Uncanny_Automator\Integrations\Facebook_Lead_Ads;

use Uncanny_Automator\App_Integrations\App_Integration;
use Exception;

use function Uncanny_Automator\App\Infrastructure\automator_connection_registry;
use function Uncanny_Automator\App\Infrastructure\automator_disconnect_service;
use function Uncanny_Automator\App\Infrastructure\automator_platform_connections_api;
use function Uncanny_Automator\App\Infrastructure\automator_platform_site_registration;

/**
 * Class Facebook_Lead_Ads_Integration
 *
 * @package Uncanny_Automator
 */
class Facebook_Lead_Ads_Integration extends App_Integration {

	/**
	 * Get the integration config.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'FACEBOOK_LEAD_ADS',    // Integration code.
			'name'         => 'Facebook Lead Ads',    // Integration name.
			'api_endpoint' => 'v2/facebook-lead-ads', // Automator API server endpoint.
			'settings_id'  => 'facebook_lead_ads',    // Settings ID ( Settings url / tab id ).
		);
	}

	/**
	 * Integration Set-up.
	 */
	protected function setup() {

		// Define helpers with common config values.
		$this->helpers = new Facebook_Lead_Ads_App_Helpers( self::get_config() );

		// Set the icon URL.
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/facebook-lead-ads-icon.svg' );

		// Finalize setup via the parent class with the common config.
		$this->setup_app_integration( self::get_config() );

		$this->set_developer_name( 'Meta' );
		$this->set_integration_type( 'app' );
		$this->set_distribution_type( 'saas' );
	}

	/**
	 * Load Integration Classes.
	 */
	public function load() {
		// Load settings page.
		new Facebook_Lead_Ads_Settings(
			$this->dependencies,
			$this->get_settings_config()
		);

		// Load triggers.
		new Lead_Created( $this->dependencies );
	}

	/**
	 * Is App connected.
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		try {
			return $this->helpers->has_connection();
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Build the parts that move each connected page to signed lead delivery.
	 *
	 * @return void
	 */
	protected function register_dependencies() {

		$connections  = new Facebook_Lead_Ads_Page_Connections( automator_connection_registry() );
		$subscription = new Facebook_Lead_Ads_Page_Subscription( $this->dependencies->api, $this->helpers, $connections, automator_disconnect_service() );

		$this->set_dependency( 'page_connections', $connections );
		$this->set_dependency( 'page_subscription', $subscription );
		$this->set_dependency( 'lead_dedup', new Facebook_Lead_Ads_Lead_Dedup() );
		$this->set_dependency(
			'page_adoption',
			new Facebook_Lead_Ads_Page_Adoption( automator_platform_connections_api(), automator_platform_site_registration(), $this->helpers, $connections, $subscription )
		);
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {

		// Subscribes a page's connection that got a new connection id (R6).
		$this->dependencies->page_subscription->register();

		// Moves each connected page to signed lead delivery, owed until done.
		$this->dependencies->page_adoption->register();

		// Turns each signed lead the platform delivers into the trigger's hook.
		// Attached whether or not the integration is connected, as the inbox
		// may drain a lead delivered before a disconnect.
		( new Facebook_Lead_Ads_Event_Fanout( $this->dependencies->lead_dedup, $this->dependencies->page_adoption ) )->register();

		// Removes expired lead claims daily.
		$this->dependencies->lead_dedup->register();

		// Register AJAX handlers for recipe builder.
		add_action(
			'wp_ajax_automator_facebook_lead_ads_forms_handler',
			array( $this->helpers, 'forms_handler_ajax' )
		);

		// Cache the picked form's questions when the trigger is added or its page or form changes.
		add_action( 'automator_event_trigger_created', array( $this->helpers, 'cache_form_fields' ) );
		add_action( 'automator_event_trigger_fields_changed', array( $this->helpers, 'cache_form_fields' ) );
		add_action( 'automator_event_trigger_status_changed', array( $this->helpers, 'cache_form_fields' ) );

		// Register legacy endpoints.
		add_action(
			'rest_api_init',
			array( $this->dependencies->webhooks, 'register_legacy_endpoints' ),
			10,
			0
		);
	}
}
