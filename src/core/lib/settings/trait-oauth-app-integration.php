<?php

namespace Uncanny_Automator\Settings;

use Exception;

/**
 * Trait OAuth_App_Integration
 *
 * Common OAuth patterns for app integrations.
 *
 * @package Uncanny_Automator\Settings
 */
trait OAuth_App_Integration {

	/**
	 * The OAuth action name.
	 * Currently different integrations have different action names
	 * It will be up to the integrations themselves to set this correctly.
	 * - TODO normalize at the API
	 *
	 * @var string
	 */
	protected $oauth_action = 'authorization_request';

	/**
	 * The redirect URL parameter name
	 * Currently different integrations have different parameter names
	 * It will be up to the integrations themselves to set this correctly.
	 * - TODO normalize at the API
	 *
	 * @var string
	 */
	protected $redirect_param = 'redirect_url';

	/**
	 * The error message parameter name returned from the API server.
	 * Currently different integrations have different parameter names ( error, connect, auth_error, etc. )
	 * It will be up to the integrations themselves to set this correctly.
	 * - TODO normalize at the API
	 *
	 * @var string
	 */
	protected $error_param = 'error';

	/**
	 * Handle OAuth initiation
	 *
	 * @param array $data
	 * @return array
	 */
	protected function handle_oauth_init( $data, $manager ) {
		// Build the args for the OAuth request.
		$args = array(
			'action'              => $this->oauth_action,
			'nonce'               => $manager->get_oauth_key( $this->get_id() ),
			'plugin_ver'          => AUTOMATOR_PLUGIN_VERSION,
			$this->redirect_param => rawurlencode( $manager->get_oauth_callback_url( $this->get_id() ) ),
		);

		// Allow integrations to filter their OAuth args ( Example : Discord server ID )
		if ( method_exists( $this, 'maybe_filter_oauth_args' ) ) {
			$args = $this->maybe_filter_oauth_args( $args, $data );
		}

		// Return the redirect URL for the frontend to handle
		return array(
			'success'      => true,
			'redirect_url' => add_query_arg(
				$args,
				AUTOMATOR_API_URL . $this->helpers->get_api_endpoint()
			),
		);
	}

	/**
	 * Process OAuth authentication
	 * - Handles OAuth callback from external services via REST
	 * - Returns response data for REST API
	 *
	 * @param array|null $credentials The decoded credentials from REST manager, or null for error cases
	 * @return array Response data
	 */
	public function process_oauth_authentication( $manager ) {

		// Validate session and retrieve decoded credentials.
		$credentials = $manager->get_validated_oauth_credentials( $this->get_id() );

		try {
			if ( empty( $credentials ) ) {
				// Handle error cases - check for specific error parameters
				$error_params = array( 'error', 'connect', 'auth_error' );
				foreach ( $error_params as $param ) {
					if ( automator_filter_has_var( $param ) ) {
						$error_message = automator_filter_input( $param );
						$this->register_oauth_error_alert( $error_message );
						throw new Exception( $error_message );
					}
				}

				// Generic error if no specific error parameter found
				$error_message = esc_html_x( 'Invalid response, please try again.', 'Integration settings', 'uncanny-automator' );
				$this->register_oauth_error_alert( $error_message );
				throw new Exception( $error_message );
			}

			// Validate integration-specific credentials
			$credentials = $this->validate_integration_credentials( $credentials );

			// Store the credentials.
			$this->store_credentials( $credentials );

			$response = array(
				'success'      => true,
				'redirect_url' => $this->get_settings_page_url(),
			);

			// Check if authorize_account method exists for account verification
			if ( method_exists( $this, 'authorize_account' ) ) {
				$response = $this->authorize_account( $credentials, $response );
			}

			// Register success alert.
			$this->register_oauth_success_alert( $credentials );

			// Return success response
			return $response;

		} catch ( Exception $e ) {
			// Re-throw for REST manager to handle
			throw $e;
		}
	}

	/**
	 * Validate integration-specific credentials
	 * Override this in the integration class to add custom validation
	 *
	 * @param array $credentials
	 * @return array
	 */
	protected function validate_integration_credentials( $credentials ) {
		// Check for vault signature - required for all newer OAuth integrations.
		$this->validate_vault_signature( $credentials );
		// TODO : Normalize vault ID name and add common check.
		return $credentials;
	}

	/**
	 * Validate vault signature
	 *
	 * @param array $credentials
	 * @return void
	 */
	protected function validate_vault_signature( array $credentials ) {
		$signature = $credentials['vault_signature'] ?? '';
		if ( empty( $signature ) ) {
			throw new Exception(
				esc_html_x( 'Missing credentials', 'Integration settings', 'uncanny-automator' )
			);
		}
	}

	/**
	 * Register success message alert
	 * - override this in the integration class to provide custom content.
	 *
	 * @param array $credentials
	 *
	 * @return void
	 */
	public function register_oauth_success_alert( $credentials = array() ) {
		$this->register_alert( $this->get_connected_alert() );
	}

	/**
	 * Register error message alert
	 * - override this in the integration class to provide custom content.
	 *
	 * @return void
	 */
	public function register_oauth_error_alert( $message ) {
		$this->register_alert( $this->get_error_alert( $message ) );
	}

	/**
	 * Store credentials
	 * This method satisfies the App_Integration_Settings abstract class requirement
	 *
	 * @param array $credentials
	 * @return void
	 */
	protected function store_credentials( $credentials ) {
		if ( ! method_exists( $this->helpers, 'store_credentials' ) ) {
			throw new Exception(
				esc_html_x(
					'App helpers is missing the store_credentials method',
					'Integration settings',
					'uncanny-automator'
				)
			);
		}

		$this->helpers->store_credentials( $credentials );
	}

	////////////////////////////////////////////////////////////
	// OAuth Templating Methods
	////////////////////////////////////////////////////////////

	/**
	 * Display - Bottom left disconnected content.
	 *
	 * Override this method in the extending class to provide custom content.
	 *
	 * @return void - Outputs HTML directly
	 */
	public function output_bottom_left_disconnected_content() {
		$this->output_oauth_connect_button();
	}

	/**
	 * Display - Output OAuth connect button
	 *
	 * @param string $label
	 * @param array $url_args
	 *
	 * @return void - Outputs HTML directly
	 */
	public function output_oauth_connect_button( $label = '', $url_args = array() ) {
		$label = empty( $label )
			? $this->get_connect_button_label()
			: $label;

		$this->output_action_button(
			'oauth_init',
			$label,
			array(
				'color' => 'primary',
			)
		);
	}
}
