<?php
namespace Uncanny_Automator\Integrations\Facebook_Lead_Ads;

use Uncanny_Automator\Services\Html_Partial_Renderer;
use Uncanny_Automator\Integrations\Facebook_Lead_Ads\Helpers\Facebook_Lead_Ads_Helpers;
use Uncanny_Automator\Integrations\Facebook_Lead_Ads\Triggers\Lead_Created;
use Uncanny_Automator\Integrations\Facebook_Lead_Ads\Utilities\Credentials_Manager;
use Uncanny_Automator\Integrations\Facebook_Lead_Ads\Utilities\Rest_Api;
use Uncanny_Automator\Integrations\Facebook_Lead_Ads\Utilities\Tokens_Handler;

/**
 * Integration class for Facebook Lead Ads.
 *
 * Handles integration setup, hooks registration, and initialization.
 *
 * @package Uncanny_Automator\Integrations\Facebook_Lead_Ads
 */
class Facebook_Lead_Ads_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Set up the integration details and register settings.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->set_integration( 'FACEBOOK_LEAD_ADS' );
		$this->set_name( 'Facebook Lead Ads' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/facebook-lead-ads-icon.svg' );
		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'facebook_lead_ads' ) );

		$connections = Facebook_Lead_Ads_Helpers::create_connection_manager();
		$this->set_connected( $connections->has_connection() );

		$this->register_hooks();
	}

	/**
	 * Register WordPress hooks for AJAX and REST API handlers.
	 *
	 * @return void
	 */
	protected function register_hooks() {

		// Captures token from OAuth flow.
		add_action( 'wp_ajax_automator_integration_facebook_lead_ads_capture_token', array( Facebook_Lead_Ads_Helpers::class, 'capture_token_handler' ) );

		// Disconnect handler.
		add_action( 'wp_ajax_automator_integration_facebook_lead_ads_disconnect', array( Facebook_Lead_Ads_Helpers::class, 'disconnect_handler' ) );

		// Forms selection handler.
		add_action( 'wp_ajax_automator_facebook_lead_ads_forms_handler', array( Facebook_Lead_Ads_Helpers::class, 'forms_handler' ) );

		// General connection check (external, unauthenticated).
		add_action( 'wp_ajax_nopriv_facebook_lead_ads_check_connection', array( Facebook_Lead_Ads_Helpers::class, 'check_connection_handler' ) );

		// General connection check (internal, authenticated).
		add_action( 'wp_ajax_facebook_lead_ads_check_connection', array( Facebook_Lead_Ads_Helpers::class, 'check_connection_handler' ) );

		// Verifies page connection.
		add_action( 'wp_ajax_facebook_lead_verify_page_connection', array( Facebook_Lead_Ads_Helpers::class, 'check_page_connection_handler' ) );

		// Analyzes tokens.
		add_action( 'automator_recipe_before_update', array( new Tokens_Handler(), 'analyze_tokens' ), 10, 2 );

		// Endpoint registration.
		add_action( 'rest_api_init', array( new Rest_Api(), 'register_endpoint' ) );
	}


	/**
	 * Initialize triggers, settings, and utilities for the integration.
	 *
	 * @return void
	 */
	protected function load() {

		new Lead_Created();

		$credentials   = new Credentials_Manager();
		$html_renderer = new Html_Partial_Renderer();
		$connections   = Facebook_Lead_Ads_Helpers::create_connection_manager();

		new Facebook_Lead_Ads_Settings( $connections, $html_renderer, $credentials );
	}
}
