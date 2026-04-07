<?php
/**
 * Handshake handler for app.uncannyagent.com site connection.
 *
 * Detects a connect_token on the Uncanny Agent settings page, validates it
 * server-to-server, and shows an approval card. On approval, generates a
 * WP application password and sends it back server-to-server.
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator\Api\Application\Mcp\Handshake;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\OAuth\Token_Manager;

class Handshake_Handler {

	/** Production base URL. */
	const PRODUCTION_URL = 'https://app.uncannyagent.com';

	/** AJAX action name. */
	const AJAX_ACTION = 'uoa_handshake_approve';

	/**
	 * Get the handshake base URL.
	 *
	 * Defaults to production. Override via UNCANNY_AGENT_APP_URL in wp-config.php for dev.
	 *
	 * @return string
	 */
	public static function get_base_url(): string {
		if ( defined( 'UNCANNY_AGENT_APP_URL' ) && ! empty( UNCANNY_AGENT_APP_URL ) ) {
			return rtrim( UNCANNY_AGENT_APP_URL, '/' );
		}

		return self::PRODUCTION_URL;
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_approval' ) );
	}

	/**
	 * Check if a handshake connect_token is present on the current page.
	 *
	 * @return bool
	 */
	public function has_connect_token(): bool {
		return ! empty( $_GET['connect_token'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Get the raw connect token from the URL.
	 *
	 * @return string
	 */
	public function get_connect_token(): string {
		return sanitize_text_field( wp_unslash( $_GET['connect_token'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/** Default bearer token TTL fallback (30 days). */
	const DEFAULT_BEARER_TTL = 2592000;

	/** Maximum bearer token TTL (1 year). */
	const MAX_BEARER_TTL = YEAR_IN_SECONDS;

	/**
	 * Validate the token server-to-server with app.uncannyagent.com.
	 *
	 * @param string $token Raw connect token.
	 *
	 * @return array{valid: bool, requester_email?: string, site_url?: string, session_ttl?: int, error?: string}
	 */
	public function validate_token( string $token ): array {
		$response = wp_remote_post(
			self::get_base_url() . '/api/handshake/validate',
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'connect_token' => $token ) ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'valid' => false,
				'error' => $response->get_error_message(),
			);
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status || empty( $body['valid'] ) ) {
			return array(
				'valid' => false,
				'error' => $body['message'] ?? 'Invalid token.',
			);
		}

		return $body;
	}

	/**
	 * Handle the AJAX approval request.
	 *
	 * Generates a WP application password and sends it server-to-server.
	 *
	 * @return void
	 */
	public function handle_approval(): void {
		check_ajax_referer( self::AJAX_ACTION, '_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		$token  = sanitize_text_field( wp_unslash( $_POST['connect_token'] ?? '' ) );
		$action = sanitize_text_field( wp_unslash( $_POST['approval_action'] ?? '' ) );

		if ( empty( $token ) ) {
			wp_send_json_error( array( 'message' => 'Missing token.' ), 400 );
		}

		if ( 'deny' === $action ) {
			wp_send_json_success( array( 'denied' => true ) );
		}

		// Re-validate the token to get session_ttl from the standalone app.
		$validation = $this->validate_token( $token );

		if ( empty( $validation['valid'] ) ) {
			wp_send_json_error( array( 'message' => $validation['error'] ?? 'Token validation failed.' ), 400 );
		}

		// Use session_ttl from OAuth provider (via standalone app) as bearer token lifetime.
		$bearer_ttl = self::DEFAULT_BEARER_TTL;
		if ( isset( $validation['session_ttl'] ) && is_numeric( $validation['session_ttl'] ) && (int) $validation['session_ttl'] > 0 ) {
			$bearer_ttl = min( (int) $validation['session_ttl'], self::MAX_BEARER_TTL );
		}

		// Generate a Bearer token via Automator's Token_Manager.
		$token_manager = new Token_Manager();
		$token_result  = $token_manager->generate_token(
			get_current_user_id(),
			array( 'mcp' ),
			$bearer_ttl,
			'Uncanny Agent Standalone (' . gmdate( 'Y-m-d H:i' ) . ')'
		);

		if ( false === $token_result || empty( $token_result['token'] ) ) {
			wp_send_json_error( array( 'message' => 'Failed to generate bearer token.' ), 500 );
		}

		// Determine MCP URL.
		$mcp_url = rest_url( 'automator/v1/mcp' );

		// Send bearer token server-to-server to app.uncannyagent.com.
		$response = wp_remote_post(
			self::get_base_url() . '/api/handshake/complete',
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'connect_token' => $token,
						'bearer_token'  => $token_result['token'],
						'mcp_url'       => $mcp_url,
					)
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => 'Failed to complete handshake: ' . $response->get_error_message() ), 500 );
		}

		$status = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			wp_send_json_error( array( 'message' => $body['message'] ?? 'Handshake completion failed.' ), 500 );
		}

		wp_send_json_success( array( 'connected' => true ) );
	}
}
