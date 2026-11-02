<?php
namespace Uncanny_Automator\Settings;

use Exception;

/**
 * Trait for common REST processing functionality
 * Extracted from App_Integration_Settings to allow reuse in Pro integrations
 *
 * @package Uncanny_Automator\Settings
 */
trait Premium_Integration_Rest_Processing {

	/**
	 * Process a REST request
	 *
	 * @param WP_REST_Request $request
	 * @param Action_Manager $manager
	 *
	 * @return array
	 * @throws Exception if any errors encountered
	 */
	public function process_rest_request( $request, $manager ) {
		// Revalidate the request in case it's called directly.
		if ( ! $manager->check_rest_permissions( $request ) ) {
			throw new Exception( 'Forbidden' );
		}

		// Get the action
		$action = $request->get_param( 'action' );
		$data   = $request->get_param( 'data' )
			? $request->get_param( 'data' )
			: array();

		// Common actions handled by framework
		switch ( $action ) {
			case 'authorize':
				return $this->handle_authorize( $data );
			case 'disconnect':
				return $this->handle_disconnect( $data );
			case 'save_settings':
				return $this->handle_save_settings( $data );
			case 'oauth_init':
				$manager->validate_action_exists( $this, 'handle_oauth_init' );
				return $this->handle_oauth_init( $data, $manager );
			case 'reconnect':
				$manager->validate_action_exists( $this, 'handle_oauth_init' );
				return $this->handle_reconnect( $data, $manager );
			default:
				// Dynamic action handling
				$method_name = 'handle_' . sanitize_key( $action );
				$manager->validate_action_exists( $this, $method_name );
				$response = array(
					'success' => true,
					'reload'  => false,
				);
				return call_user_func_array( array( $this, $method_name ), array( $response, $data ) );
		}
	}

	/**
	 * Format array data
	 *
	 * @param string $key - The array key to format
	 * @param array $data - The REST request data
	 *
	 * @return array
	 */
	protected function get_array_data( $key, $data ) {
		$results = array();
		$pattern = '/^' . preg_quote( $key, '/' ) . '\\[([^\\]]+)\\]$/';
		foreach ( $data as $i => $value ) {
			if ( preg_match( $pattern, $i, $matches ) ) {
				$results[ $matches[1] ] = $value;
			}
		}
		return $results;
	}

	/**
	 * Handle authorization action.
	 *
	 * @param array $data Optional data passed from REST request
	 *
	 * @return array - formatted response for the REST request.
	 * @throws Exception If authorization fails
	 */
	private function handle_authorize( $data = array() ) {
		// Set default response
		$response = array(
			'success' => true,
			'reload'  => true,
		);

		// Allow integration to perform pre-authorization actions and modify response
		$response = $this->before_authorization( $response, $data );

		// For API key integrations, validate and save registered options.
		$options = $this->validate_registered_options( $data );
		$this->store_registered_options( $options );

		// Check if authorize_account method exists
		if ( method_exists( $this, 'authorize_account' ) ) {
			$response = $this->authorize_account( $response, $options );
		}

		// Allow integration to perform any additional setup after authorization and modify response
		$response = $this->after_authorization( $response, $data );

		return $response;
	}

	/**
	 * Called before successful authorization
	 * Override this method in the integration class to perform additional setup
	 *
	 * @param array $response The current response array
	 * @param array $data The posted data
	 *
	 * @return array Modified response array
	 * @throws Exception If pre-authorization setup fails
	 */
	protected function before_authorization( $response = array(), $data = array() ) {
		// Default implementation does nothing
		return $response;
	}

	/**
	 * Called after successful authorization
	 * Override this method in the integration class to perform additional setup
	 *
	 * @param array $response The current response array
	 * @param array $data The posted data
	 *
	 * @return array Modified response array
	 * @throws Exception If post-authorization setup fails
	 */
	protected function after_authorization( $response = array(), $data = array() ) {
		// Default implementation does nothing
		return $response;
	}

	/**
	 * Handle disconnect action.
	 *
	 * Order: before_disconnect() and the `automator_before_disconnect_{id}`
	 * filter; then, when the integration has a default v3 connection, the
	 * platform disconnect; then after_platform_disconnect() and the
	 * `automator_after_platform_disconnect_{id}` filter — the vendor-side
	 * revocation (handoff R8, L197); then the local wipe (registered options,
	 * credentials, account info) and the after-disconnect hooks.
	 *
	 * The pre-disconnect cleanup runs ahead of the platform disconnect on
	 * purpose: it may call the vendor (delete webhooks, revoke a token) with
	 * the connection that the platform disconnect ends, and running it after
	 * would orphan vendor-side state for good. So when the platform does not
	 * disconnect, this throws with that cleanup already done and nothing else:
	 * the disconnect removes no registered option, credential, account info or
	 * local connection row, and a reconnect does not start OAuth. Whatever the
	 * cleanup itself removed stays that way. The user retries the disconnect
	 * and the cleanup runs again, so every before_disconnect() override and
	 * every `automator_before_disconnect_{id}` callback must be safe to run
	 * twice — and must not revoke anything vendor-side: a revoked vendor
	 * credential behind a connection the platform kept leaves the integration
	 * "connected" and unusable. Vendor-side revocation belongs to
	 * after_platform_disconnect(), which runs only once the platform confirmed
	 * (for an integration still on /v2, where the revocation always ran).
	 *
	 * @param array $data Optional data passed from REST request
	 *
	 * @return array - formatted response for the REST request.
	 * @throws Exception If disconnect fails — a hook threw, or the platform did
	 *                   not disconnect the v3 connection.
	 */
	private function handle_disconnect( $data = array() ) {
		// Set default response
		$response = array(
			'success' => true,
			'reload'  => true,
		);

		// Allow integration to perform any cleanup before default options are cleared and modify response
		$response = $this->before_disconnect( $response, $data );

		/**
		 * Filter to allow integrations to perform additional cleanup before default options are cleared
		 *
		 * Runs before the platform disconnect of a v3 connection as well. If the
		 * platform does not disconnect, the action stops after this ran and the
		 * user's retry runs it again: a callback must be safe to run twice.
		 *
		 * @param array $response The current response array
		 * @param array $data The posted data
		 * @param object $this The integration settings object
		 *
		 * @return array Modified response array
		 *
		 * @example
		 *
		 * ```php
		 * add_filter( 'automator_before_disconnect_github', function( $response, $data, $settings_object ) {
		 *     // Register additional options that should be deleted
		 *     $settings_object->register_option( 'github_webhook_manager' );
		 *     return $response;
		 * }, 10, 3 );
		 * ```
		 */
		$response = apply_filters( 'automator_before_disconnect_' . $this->get_id(), $response, $data, $this );

		// MVP plan G.9 — platform-side disconnect for v3 rows. Deletes the
		// local registry row in lockstep with the platform
		// `DELETE /app/v1/connections/{id}` response (Disconnect_Service owns
		// the rule table) and throws when the platform did not disconnect, so
		// nothing below runs for a connection that is still live. The
		// pre-disconnect cleanup above has run by then, and runs again on the
		// retry. No-op when the helper has no integration slug or no default
		// v3 row exists.
		$this->maybe_disconnect_v3_connection();

		// Handoff R8 (L197) — vendor-side revocation: only once the platform
		// confirmed the disconnect (200/404), and before the local wipe, so the
		// credentials it may need are still on file. For an integration still
		// on /v2 there is no platform disconnect, and this runs where the
		// revocation always ran.
		$response = $this->after_platform_disconnect( $response, $data );

		/**
		 * Filter for vendor-side cleanup that must wait for the platform
		 * disconnect: revoking a credential, deleting state that is not a cache.
		 * Runs once the platform confirmed the disconnect of a v3 connection,
		 * before the local wipe. A failed attempt reaches it again on the
		 * user's retry: a callback must be safe to run twice.
		 *
		 * @param array  $response The current response array
		 * @param array  $data     The posted data
		 * @param object $this     The integration settings object
		 *
		 * @return array Modified response array
		 */
		$response = apply_filters( 'automator_after_platform_disconnect_' . $this->get_id(), $response, $data, $this );

		// Delete all registered options.
		//
		// MVP plan E.5a — webhook-toggle reset on disconnect/reconnect:
		// `Premium_Integration_Webhook_Settings::register_webhook_options()`
		// registers `enable_{slug}_webhooks` as a tracked option. This
		// sweep deletes it alongside everything else, so the user
		// re-opting into trigger reception requires an explicit toggle
		// flip after reconnect — no implicit re-enable. Same path
		// covers G.6 reconnect-after-revocation because the reconnect
		// REST action composes handle_disconnect + handle_oauth_init.
		$this->delete_all_registered_options();

		// Check if the helper class has delete_credentials method
		if ( method_exists( $this->helpers, 'delete_credentials' ) ) {
			$this->helpers->delete_credentials();
		}

		// Delete account info using helper method
		if ( method_exists( $this->helpers, 'delete_account_info' ) ) {
			$this->helpers->delete_account_info();
		}

		// Allow integration to perform any additional cleanup and modify response
		$response = $this->after_disconnect( $response, $data );

		/**
		 * Filter to allow integrations to perform additional cleanup after default options are cleared
		 *
		 * @param array $response The current response array
		 * @param array $data The posted data
		 * @param object $this The integration settings object
		 *
		 * @return array Modified response array
		 *
		 * @example
		 *
		 * ```php
		 * add_filter( 'automator_after_disconnect_github', function( $response, $data, $settings_object ) {
		 *     // Perform additional cleanup
		 *     automator_delete_option( 'github_webhook_manager' );
		 *     return $response;
		 * }, 10, 3 );
		 */
		return apply_filters( 'automator_after_disconnect_' . $this->get_id(), $response, $data, $this );
	}

	/**
	 * v3 connection disconnect — fires the platform DELETE + removes the
	 * local `uap_connections` row when the helper exposes an integration slug
	 * and a default v3 row exists.
	 *
	 * When the platform did not disconnect the connection (no answer, a
	 * throttle, a refused request), the connection is still live there and
	 * the local row was kept. Throwing stops `handle_disconnect()` before it
	 * wipes the integration's registered options, credentials and account
	 * info, and stops `handle_reconnect()` before it starts a second
	 * connection beside the live one; the admin sees why and can try again.
	 * The pre-disconnect cleanup has already run by then
	 * ({@see handle_disconnect()}).
	 *
	 * @since 8.0.0 G.9 wiring.
	 *
	 * @return void
	 *
	 * @throws Exception When the platform did not disconnect the connection.
	 */
	private function maybe_disconnect_v3_connection(): void {

		if ( ! is_object( $this->helpers ) || ! method_exists( $this->helpers, 'get_integration' ) ) {
			return;
		}

		$integration = (string) $this->helpers->get_integration();
		if ( '' === $integration ) {
			return;
		}

		$result = \Uncanny_Automator\App\Infrastructure\automator_reconnect_service()->reconnect( $integration );
		if ( null === $result || ! $result->is_failure() ) {
			return;
		}

		throw new Exception( sprintf( $this->v3_disconnect_failure_message( $result ), absint( $result->http_status() ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The message is escaped, the status is an integer.
	}

	/**
	 * What the admin is told when the platform did not disconnect a v3
	 * connection. Every message says the account is still connected:
	 *
	 *  - 401: the platform did not accept this site's request — its license
	 *    (an inactive or invalid license is refused on every attempt, so
	 *    waiting does not help) or its site registration (which re-registers
	 *    on its own). Names both, instead of promising that waiting helps.
	 *  - any other failure a retry can clear: try again in a few minutes.
	 *  - a refusal the same request will meet again: contact support.
	 *
	 * @since 8.0.0
	 *
	 * @param \Uncanny_Automator\App\Infrastructure\Connections\Disconnect_Result $result The failed disconnect.
	 *
	 * @return string Escaped, with one `%d` for the HTTP status.
	 */
	private function v3_disconnect_failure_message( \Uncanny_Automator\App\Infrastructure\Connections\Disconnect_Result $result ): string {

		if ( 401 === $result->http_status() ) {
			/* translators: %d: HTTP status code */
			return esc_html_x( 'The Automator App Platform did not accept this site\'s request (its license or its site registration), so this account is still connected. If your license is not active, re-activate it; otherwise, please try again shortly. (status %d)', 'Integration disconnect', 'uncanny-automator' );
		}

		return $result->should_retry()
			/* translators: %d: HTTP status code */
			? esc_html_x( 'The Automator App Platform did not disconnect this account, so it is still connected. Please try again in a few minutes. (status %d)', 'Integration disconnect', 'uncanny-automator' )
			/* translators: %d: HTTP status code */
			: esc_html_x( 'The Automator App Platform did not disconnect this account, so it is still connected. Please contact support if this keeps happening. (status %d)', 'Integration disconnect', 'uncanny-automator' );
	}

	/**
	 * Handle reconnect — MVP plan G.6.
	 *
	 * Composes the existing disconnect + oauth_init flows into a single
	 * REST action so the settings-page button can issue one request and
	 * receive a `redirect_url` pointing at the platform OAuth start.
	 *
	 * Sequence:
	 *  1. {@see handle_disconnect()} — fires `Disconnect_Service`
	 *     (platform DELETE, local row removed only once the platform
	 *     disconnected it or says it is gone), wipes registered options,
	 *     clears credentials + account info, fires before/after disconnect
	 *     filters. Throws when the platform did not disconnect — with only
	 *     the pre-disconnect cleanup done — so step 2 never starts a second
	 *     connection beside one still live.
	 *  2. {@see handle_oauth_init()} — builds the standard OAuth-start
	 *     URL with license_key + plugin_ver + signed callback URL.
	 *
	 * The disconnect leg's `reload: true` is overridden so the frontend
	 * follows the redirect rather than refreshing the disconnected
	 * settings page first.
	 *
	 * @param array $data    Optional data passed from REST request.
	 * @param mixed $manager Action_Manager for permissions + nonce.
	 *
	 * @return array { success, redirect_url } in the oauth_init shape.
	 *
	 * @throws Exception If disconnect or oauth_init fails.
	 */
	private function handle_reconnect( $data = array(), $manager = null ) {

		$this->handle_disconnect( $data );

		return $this->handle_oauth_init( $data, $manager );
	}

	/**
	 * Called before options are cleared during disconnect
	 * Override this method in the integration class to perform cleanup
	 *
	 * Runs before the platform disconnect of a v3 connection as well. If the
	 * platform does not disconnect, the action stops after this ran: the
	 * registered options, credentials, account info and the connection are
	 * kept, but whatever this removed is not restored. The user's retry runs
	 * it again, so it must be safe to run twice. It must not revoke anything
	 * vendor-side — that is {@see after_platform_disconnect()}'s (handoff R8).
	 *
	 * @param array $response The current response array
	 * @param array $data The posted data
	 *
	 * @return array Modified response array
	 * @throws Exception If cleanup fails
	 */
	protected function before_disconnect( $response = array(), $data = array() ) {
		// Default implementation returns response unchanged
		return $response;
	}

	/**
	 * Vendor-side revocation on disconnect (handoff R8, L197): revoke the API
	 * proxy's credential, delete state that is not a cache. Runs once the
	 * platform confirmed the disconnect of a v3 connection — never while that
	 * connection may still be live — and before the local wipe, so the
	 * credentials are still on file. For an integration still on /v2 it runs
	 * where the revocation always ran. Must be safe to run twice: a disconnect
	 * that failed after it is retried.
	 *
	 * @param array $response The current response array
	 * @param array $data The posted data
	 *
	 * @return array Modified response array
	 * @throws Exception If the revocation fails
	 */
	protected function after_platform_disconnect( $response = array(), $data = array() ) {
		// Default implementation returns response unchanged
		return $response;
	}

	/**
	 * Called after options are cleared during disconnect
	 * Override this method in the integration class to perform additional cleanup
	 *
	 * @param array $response The current response array
	 * @param array $data The posted data
	 *
	 * @return array Modified response array
	 * @throws Exception If cleanup fails
	 */
	protected function after_disconnect( $response = array(), $data = array() ) {
		// Default implementation returns response unchanged
		return $response;
	}

	/**
	 * Delete all registered options
	 *
	 * @return void
	 */
	protected function delete_all_registered_options() {
		$all_options = $this->get_all_registered_options();
		if ( ! empty( $all_options ) ) {
			foreach ( $all_options as $option_name => $args ) {
				automator_delete_option( $option_name );
			}
		}
	}

	/**
	 * Handle save settings action.
	 *
	 * This method provides a way to save additional settings outside of the main connection flow.
	 * It can be used to:
	 * - Save settings that are only available after connection
	 * - Update configuration that can be changed at any time
	 * - Handle settings that are independent of the connection method
	 *
	 * To use this:
	 * 1. Register your options using register_option() in your integration class
	 * 2. Override before_save_settings() or after_save_settings() if needed
	 * 3. Call this method from your integration's form submission handler
	 *
	 * @param array $data Optional data passed from REST request
	 * @throws Exception If save settings fails
	 */
	private function handle_save_settings( $data = array() ) {
		// Set default response
		$response = array(
			'success' => true,
			'reload'  => false,
		);

		// Allow integration to perform validation and setup before saving settings
		$response = $this->before_save_settings( $response, $data );

		// Only process options that are available in the current context
		$options = $this->validate_registered_options( $data );
		$this->store_registered_options( $options );

		// Allow integration to perform additional setup and modify response after saving settings
		$response = $this->after_save_settings( $response, $options );

		return $response;
	}

	/**
	 * Called before saving settings
	 * Override this method in the integration class to perform validation and setup
	 *
	 * @param array $response The current response array
	 * @param array $data The posted data
	 *
	 * @return array Modified response array
	 * @throws Exception If pre-save validation fails
	 */
	protected function before_save_settings( $response = array(), $data = array() ) {
		// Default implementation returns response unchanged
		return $response;
	}

	/**
	 * Called after saving settings
	 * Override this method in the integration class to perform additional setup
	 *
	 * @param array $response The current response array
	 * @param array $options The saved options
	 *
	 * @return array Modified response array
	 * @throws Exception If post-save setup fails
	 */
	protected function after_save_settings( $response = array(), $options = array() ) {
		// Default implementation returns response unchanged
		return $response;
	}

	/**
	 * Delete all option data matching the given prefix.
	 *
	 * Performs a bulk DELETE on the uap_options table for all option names
	 * matching the supplied prefix (e.g. 'automator_mailchimp_%').
	 *
	 * This will remove all options under the prefix, including credentials and account data.
	 * Use in after_disconnect() to ensure credentials remain available for any API cleanup
	 * operations that run during the disconnect flow (e.g. external vault disconnection).
	 *
	 * Example usage:
	 * ```php
	 * protected function after_disconnect( $response = array(), $data = array() ) {
	 *     $this->delete_option_data( $this->helpers->get_option_prefix() );
	 *     return $response;
	 * }
	 * ```
	 *
	 * @param string $prefix The option name prefix to match (e.g. 'automator_mailchimp_').
	 *
	 * @return int|false The number of rows deleted, or false on error.
	 */
	protected function delete_option_data( $prefix ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to Automator's own table.
		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}uap_options WHERE option_name LIKE %s",
				$wpdb->esc_like( $prefix ) . '%'
			)
		);
	}
}
