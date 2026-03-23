<?php

namespace Uncanny_Automator\Services\App_Integrations;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use Exception;
use WP_REST_Server;
use Uncanny_Automator\Automator_Helpers_Recipe;

/**
 * Class Action_Manager
 *
 * Handles all settings actions via REST API
 * Handles OAuth callbacks via settings page load hook: load-uo-recipe_page_uncanny-automator-config
 * Handles webhook requests from external services
 *
 * @package Uncanny_Automator\Services\App_Integrations
 */
final class Action_Manager {

	/**
	 * The action for the OAuth callback
	 *
	 * @var string
	 */
	const OAUTH_CALLBACK_ACTION = 'automator_oauth_callback';

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		// Empty constructor
	}

	/**
	 * Register the hooks for the action manager.
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Rest services.
		add_action(
			'rest_api_init',
			array( $this, 'register_routes' )
		);

		// OAuth callback handler on settings page.
		add_action(
			'load-uo-recipe_page_uncanny-automator-config',
			array( $this, 'maybe_handle_oauth_callback' )
		);
	}

	////////////////////////////////////////////////////////////
	// Registration
	////////////////////////////////////////////////////////////

	/**
	 * Register the routes for the REST manager.
	 *
	 * @return void
	 */
	public function register_routes() {
		// Integration settings endpoint.
		$this->register_integration_settings_route();
		// Webhook endpoint.
		$this->register_webhook_routes();
	}

	/**
	 * Register the integration settings routes for App Integrations.
	 * Main intent is to handle the integration settings actions.
	 *
	 * @return void
	 */
	private function register_integration_settings_route() {
		// Integration settings endpoint.
		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/integration-settings/(?P<integration_id>[a-zA-Z0-9_-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'handle_integration_action' ),
					'permission_callback' => array( $this, 'check_rest_permissions' ),
					'args'                => array(
						'integration_id' => $this->sanitize_text_field_args(),
						'action'         => $this->sanitize_text_field_args(),
						'data'           => array(
							'required' => false,
							'type'     => 'object',
						),
					),
				),
			)
		);
	}

	/**
	 * Register the webhook routes for App Integrations to support triggers.
	 *
	 * @return void
	 */
	private function register_webhook_routes() {
		// Get all registered webhook endpoints via filter.
		$endpoints = apply_filters( 'automator_app_integrations_endpoints', array() );

		// Bail nothing to do.
		if ( empty( $endpoints ) || ! is_array( $endpoints ) ) {
			return;
		}

		// Filter endpoints into common patterns and legacy / custom.
		$common   = array_filter( $endpoints, array( $this, 'is_common_webhook_pattern' ) );
		$filtered = array_diff( $endpoints, $common );

		// Register common pattern.
		if ( ! empty( $common ) ) {
			$this->register_webhook_route( '/webhooks/(?P<endpoint>[a-zA-Z0-9_-]+)' );
		}

		// Register filtered routes.
		foreach ( $filtered as $endpoint ) {
			$this->register_webhook_route( $endpoint, true );
		}
	}

	/**
	 * Register a webhook route.
	 *
	 * @param string $endpoint The route endpoint.
	 * @param bool   $is_filtered Add filtered args to the route.
	 *
	 * @return void
	 */
	private function register_webhook_route( $endpoint, $is_filtered = false ) {
		$args = array(
			'methods'             => array( WP_REST_Server::CREATABLE, WP_REST_Server::READABLE ),
			'callback'            => array( $this, 'handle_webhook_request' ),
			'permission_callback' => '__return_true',
		);

		// Add endpoint arg if it's a filtered endpoint.
		if ( $is_filtered ) {
			$args['args'] = array(
				'endpoint' => array(
					'default' => $endpoint,
				),
			);
		}

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			$endpoint,
			array( $args )
		);
	}

	////////////////////////////////////////////////////////////
	// Action handlers
	////////////////////////////////////////////////////////////

	/**
	 * Handle the integration settings actions.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_integration_action( WP_REST_Request $request ) {
		$integration_id = $request->get_param( 'integration_id' );
		$action         = $request->get_param( 'action' );
		try {
			// Get integration instance via filter
			$integration = $this->get_integration_settings_instance( $integration_id );

			// Check if the integration supports the action.
			$this->validate_action_exists( $integration, 'handle_' . $action );

			// Process the request at the integration level.
			$result = $integration->process_rest_request( $request, $this );

			return new WP_REST_Response( $result, 200 );

		} catch ( Exception $e ) {
			return new WP_Error(
				'integration_action_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Maybe handle OAuth callback via load hook.
	 *
	 * @return void
	 */
	public function maybe_handle_oauth_callback() {

		// Early bail if not our OAuth callback request.
		// Note: While the callback URL is generated with the parameter value set to '1',
		// any truthy value will trigger OAuth callback processing.
		if ( ! automator_filter_has_var( self::OAUTH_CALLBACK_ACTION ) ) {
			return;
		}

		// Check user capabilities / logged in status.
		if ( ! $this->check_user_capabilities() ) {
			wp_die( 'Invalid request', 'OAuth Error', array( 'response' => 401 ) );
		}

		// Get the integration ID.
		$integration_id = automator_filter_has_var( 'integration' )
			? sanitize_text_field( automator_filter_input( 'integration' ) )
			: null;

		try {
			// Get the integration instance via filter.
			$integration = $this->get_integration_settings_instance( $integration_id );

			// Check if integration has the process_oauth_authentication method.
			$this->validate_action_exists( $integration, 'process_oauth_authentication' );

			// Process the OAuth authentication.
			$result = $integration->process_oauth_authentication( $this );

			// Redirect to success page.
			if ( isset( $result['redirect_url'] ) ) {
				wp_safe_redirect( $result['redirect_url'] );
				exit;
			}

			throw new Exception( 'Error processing OAuth authentication' );

		} catch ( Exception $e ) {
			wp_die(
				esc_html( $e->getMessage() ),
				'Authorization Error',
				array( 'response' => 500 )
			);
		}
	}

	/**
	 * Handle webhook requests from external services.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function handle_webhook_request( WP_REST_Request $request ) {
		$endpoint = sanitize_key( $request->get_param( 'endpoint' ) );

		try {
			// Handle webhook request via endpoint filter.
			$response = apply_filters(
				"automator_handle_webhook_request_by_endpoint_{$endpoint}",
				$request
			);

			// Validate if valid integration was found and filter ran.
			if ( is_a( $response, 'WP_REST_Request' ) ) {
				throw new Exception( 'Webhook handler not found.' );
			}

			// Validate response is a valid WP_REST_Response.
			if ( ! is_a( $response, 'WP_REST_Response' ) ) {
				throw new Exception( 'Webhook handler response not valid.' );
			}

			return $response;
		} catch ( Exception $e ) {
			// Return error response as WP_REST_Response
			return new WP_REST_Response(
				array(
					'message' => $e->getMessage(),
				),
				200
			);
		}
	}

	////////////////////////////////////////////////////////////
	// Helpers
	////////////////////////////////////////////////////////////

	/**
	 * Get the integration settings instance via filter.
	 *
	 * @param string $integration_id
	 *
	 * @return object - integration instance
	 * @throws Exception If integration not found
	 */
	private function get_integration_settings_instance( $integration_id ) {

		// Check if the integration ID is valid
		if ( empty( $integration_id ) ) {
			throw new Exception( 'Invalid request' );
		}

		// Get integration via filter
		$integration = apply_filters(
			'automator_integration_settings_instance_' . sanitize_key( $integration_id ),
			null
		);

		if ( ! $integration ) {
			throw new Exception(
				esc_html_x(
					"Integration not found",
					'Integration settings',
					'uncanny-automator'
				)
			);
		}

		return $integration;
	}

	/**
	 * Check if a webhook endpoint matches our common pattern.
	 *
	 * @param string $endpoint
	 *
	 * @return bool
	 */
	private function is_common_webhook_pattern( $endpoint ) {
		// Remove leading slash for comparison
		$endpoint = ltrim( $endpoint, '/' );
		// Check if the endpoint starts with 'webhooks/'
		return 0 === strpos( $endpoint, 'webhooks/' );
	}

	////////////////////////////////////////////////////////////
	// Sanitation and validation
	////////////////////////////////////////////////////////////

	/**
	 * Sanitize the text field args
	 *
	 * @return array
	 */
	private function sanitize_text_field_args() {
		return array(
			'required'          => true,
			'validate_callback' => 'sanitize_text_field',
		);
	}

	/**
	 * Check user capabilities
	 *
	 * @return bool
	 */
	private function check_user_capabilities() {
		return current_user_can( automator_get_capability() ); // phpcs:ignore WordPress.WP.Capabilities.Undetermined
	}

	/**
	 * Check permissions for REST endpoints
	 *
	 * @param WP_REST_Request $request
	 * @return bool
	 */
	public function check_rest_permissions( $request ) {
		// Check nonce for security
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return false;
		}

		// Check user capabilities
		return $this->check_user_capabilities( $request );
	}

	/**
	 * Validate if the action exists in the integration
	 *
	 * @param object $integration
	 * @param string $action
	 * @return void
	 * @throws Exception If action not found
	 */
	public function validate_action_exists( $integration, $action ) {
		if ( ! method_exists( $integration, $action ) ) {
			throw new Exception(
				esc_html_x(
					"Requested action is not supported by this integration",
					'Integration settings',
					'uncanny-automator'
				)
			);
		}
	}

	/**
	 * Get the OAuth callback parameter name
	 *
	 * @return string The OAuth callback parameter name
	 */
	public function get_oauth_callback_param() {
		return self::OAUTH_CALLBACK_ACTION;
	}

	/**
	 * Set the OAuth key for the integration
	 *
	 * @param string $integration_id
	 * @return string The encryption key
	 */
	public function get_oauth_key( $integration_id ) {
		// Generate a secure random key for encryption (16 bytes for AES-128)
		$encryption_key = bin2hex( random_bytes( 16 ) );
		// Use the user ID to store the key in a transient.
		$transient_key = sprintf( 'automator_oauth_key_%s_%d', $integration_id, get_current_user_id() );
		set_transient( $transient_key, $encryption_key, MINUTE_IN_SECONDS * 5 );
		return $encryption_key;
	}

	/**
	 * Validate the OAuth session and return the decoded credentials
	 *
	 * @param string $integration_id
	 * @return array decoded credentials
	 * @throws Exception If OAuth session is not found
	 */
	public function get_validated_oauth_credentials( $integration_id ) {
		// Check user capabilities / logged in status.
		if ( ! $this->check_user_capabilities() ) {
			throw new Exception( 'Invalid request' );
		}

		// Get the encryption key.
		$transient_key  = sprintf( 'automator_oauth_key_%s_%d', $integration_id, get_current_user_id() );
		$encryption_key = get_transient( $transient_key );
		if ( empty( $encryption_key ) ) {
			throw new Exception( 'OAuth session expired, please try again.' );
		}

		// Get the OAuth message
		$automator_message = automator_filter_has_var( 'automator_api_message' )
			? sanitize_text_field( automator_filter_input( 'automator_api_message' ) )
			: null;

		return $this->decode_oauth_response( $automator_message, $encryption_key );
	}

	/**
	 * Decode OAuth response from API server
	 * - Centralized OAuth response processing
	 *
	 * @param string $automator_message The OAuth message from API server
	 * @param string $encryption_key The encryption key used for decoding
	 * @return array The decoded credentials
	 * @throws Exception If decoding fails
	 */
	private function decode_oauth_response( $automator_message, $encryption_key ) {
		// Decode the message using the encryption key
		$decoded = Automator_Helpers_Recipe::automator_api_decode_message(
			$automator_message,
			$encryption_key
		);

		if ( empty( $decoded ) ) {
			throw new Exception(
				esc_html_x( 'Authorization failed, please try again.', 'Integration settings', 'uncanny-automator' )
			);
		}

		return (array) $decoded;
	}
}
