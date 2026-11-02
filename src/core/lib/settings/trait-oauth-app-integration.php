<?php

namespace Uncanny_Automator\Settings;

use Exception;

use function Uncanny_Automator\App\Infrastructure\automator_license_manager;

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
		// Build OAuth callback URL using settings page URL with callback parameter.
		$callback_url = $this->get_settings_page_url(
			array(
				$manager->get_oauth_callback_param() => '1',
			)
		);

		// Build the args for the OAuth request.
		$args = array(
			'action'              => $this->oauth_action,
			'nonce'               => $manager->get_oauth_key( $this->get_id() ),
			'plugin_ver'          => AUTOMATOR_PLUGIN_VERSION,
			'license'             => automator_license_manager()->get_key(),
			$this->redirect_param => rawurlencode( $callback_url ),
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

		try {
			// Explicit error params win over the encrypted-payload decode
			// path. When the provider (or the API server) bounces back with
			// `error=access_denied` / `connect=access_denied` / etc., there
			// is no `automator_api_message` to decode — attempting the
			// decode first just masks the real reason with a generic
			// "Authorization failed, please try again." The prior ordering
			// placed this check AFTER `get_validated_oauth_credentials()`,
			// which throws on an empty payload before the branch could run.
			//
			// UX: register the specific error as an admin alert and
			// return the settings-page redirect shape the happy path uses.
			// A throw here would route to wp_die() in the REST manager and
			// dump the user on a WordPress 500 page for what is usually an
			// ordinary "user cancelled OAuth consent" event — bad for
			// normal provider errors.
			//
			// array_unique() collapses overlap when an integration's custom
			// error_param matches one of the defaults (e.g. the default 'error').
			$error_params = array_unique( array( 'error', 'connect', 'auth_error', $this->error_param ) );
			foreach ( $error_params as $param ) {
				if ( automator_filter_has_var( $param ) ) {
					$error_code    = sanitize_text_field( automator_filter_input( $param ) );
					$error_detail  = automator_filter_has_var( 'error_message' )
						? sanitize_text_field( automator_filter_input( 'error_message' ) )
						: '';
					$error_message = ( '' !== $error_detail && $error_detail !== $error_code )
						? sprintf( '%s: %s', $error_code, $error_detail )
						: $error_code;
					$this->register_oauth_error_alert( $error_message );

					return array(
						'success'      => false,
						'redirect_url' => $this->get_settings_page_url(),
					);
				}
			}

			// No explicit error param — expect a decoded credentials payload.
			$credentials = $manager->get_validated_oauth_credentials( $this->get_id() );

			if ( empty( $credentials ) ) {
				$error_message = esc_html_x( 'Invalid response, please try again.', 'Integration settings', 'uncanny-automator' );
				$this->register_oauth_error_alert( $error_message );
				throw new Exception( $error_message );
			}

			// Validate integration-specific credentials.
			$credentials = $this->validate_integration_credentials( $credentials );

			// Store the credentials.
			$this->store_credentials( $credentials );

			$response = array(
				'success'      => true,
				'redirect_url' => $this->get_settings_page_url(),
			);

			// Check if authorize_account method exists for account verification.
			if ( method_exists( $this, 'authorize_account' ) ) {
				$response = $this->authorize_account( $response, $credentials );
			}

			// Register success alert.
			$this->register_oauth_success_alert( $credentials );

			// Return success response.
			return $response;

		} catch ( Exception $e ) {
			// Re-throw for REST manager to handle.
			throw $e;
		}
	}

	/**
	 * Validate integration-specific credentials.
	 * Override this in the integration class to add custom validation.
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
	 * Validate vault signature.
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
	 * Register success message alert.
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
	 * Register error message alert.
	 * - override this in the integration class to provide custom content.
	 *
	 * @return void
	 */
	public function register_oauth_error_alert( $message ) {
		$this->register_alert( $this->get_error_alert( $message ) );
	}

	/**
	 * Store credentials.
	 * This method satisfies the App_Integration_Settings abstract class requirement.
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
	 * Display - Output OAuth connect button.
	 *
	 * @param string $label
	 * @param array $button_args
	 *
	 * @return void - Outputs HTML directly
	 */
	public function output_oauth_connect_button( $label = '', $button_args = array() ) {
		$label = empty( $label )
			? $this->get_connect_button_label()
			: $label;

		$default_args = array(
			'color' => 'primary',
		);

		$this->output_action_button(
			'oauth_init',
			$label,
			wp_parse_args( $button_args, $default_args )
		);
	}

	/**
	 * Display - Output OAuth reconnect button (MVP plan E.4).
	 *
	 * Triggers the `reconnect` REST action which composes
	 * `handle_disconnect` + `handle_oauth_init` server-side: a
	 * `DELETE /app/v1/connections/{id}` (via `Reconnect_Service`) marks the
	 * platform's connection disconnected, the local `uap_connections` row is
	 * deleted once the platform confirms, local options are wiped, and the
	 * response carries a fresh `redirect_url` to
	 * `/app/v1/oauth/{integration}/start`. When the platform does not
	 * confirm, the action fails before OAuth starts and before any registered
	 * option, credential, account info or the local connection row is
	 * removed; only the integration's pre-disconnect cleanup has run
	 * (see `handle_disconnect()`).
	 *
	 * Use this on connected-state settings panels when the connection
	 * needs an OAuth scope-upgrade (post-promotion to /v3, scope
	 * insufficient, or vendor revoked the token).
	 *
	 * @since 8.0.0 E.4.
	 *
	 * @param string $label       Button label; defaults to a translated "Reconnect {integration}".
	 * @param array  $button_args Button rendering args; `color` defaults to `secondary` so the
	 *                            CTA reads as a recovery action rather than the primary connect.
	 *
	 * @return void
	 */
	public function output_oauth_reconnect_button( $label = '', $button_args = array() ) {

		if ( empty( $label ) ) {
			$label = sprintf(
				/* translators: %s: integration name (e.g. "Dropbox") */
				esc_html_x( 'Reconnect %s', 'OAuth reconnect button', 'uncanny-automator' ),
				$this->get_name()
			);
		}

		$default_args = array(
			'color' => 'secondary',
		);

		$this->output_action_button(
			'reconnect',
			$label,
			wp_parse_args( $button_args, $default_args )
		);
	}
}
