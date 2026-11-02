<?php

namespace Uncanny_Automator\Integrations\Facebook_Lead_Ads;

use Exception;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Http;
use Uncanny_Automator\App\Infrastructure\Connections\Connection;
use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;
use Uncanny_Automator\App_Integrations\Api_Caller;
use WP_Error;

/**
 * Facebook Lead Ads API Caller
 *
 * Extends the Api_Caller framework class to handle Facebook Lead Ads API communication.
 * Handles vault signature authentication for per-page API requests.
 *
 * @package Uncanny_Automator\Integrations\Facebook_Lead_Ads
 *
 * @property Facebook_Lead_Ads_App_Helpers $helpers
 * @property Facebook_Lead_Ads_Webhooks $webhooks
 */
class Facebook_Lead_Ads_Api_Caller extends Api_Caller {

	/**
	 * The /v3 surface of Facebook Lead Ads. The integration's other calls
	 * still go to its /v2 endpoint; a page's connection is subscribed here.
	 *
	 * @var string
	 */
	const V3_ENDPOINT = 'v3/facebook-lead-ads';

	////////////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////////////

	/**
	 * Set additional properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Register Facebook-specific error messages.
		$this->register_error_messages(
			array(
				'session has expired' => array(
					// translators: %s: Settings page URL.
					'message'   => esc_html_x( 'Your Facebook session has expired. Please [reconnect your account](%s).', 'Facebook Lead Ads', 'uncanny-automator' ),
					'help_link' => $this->helpers->get_settings_page_url(),
				),
				'invalid oauth'       => array(
					// translators: %s: Settings page URL.
					'message'   => esc_html_x( 'Your Facebook authorization is invalid. Please [reconnect your account](%s).', 'Facebook Lead Ads', 'uncanny-automator' ),
					'help_link' => $this->helpers->get_settings_page_url(),
				),
			)
		);
	}

	/**
	 * Check for errors in the response.
	 *
	 * @param array $response The API response.
	 * @param array $args     Additional arguments.
	 *
	 * @return void
	 * @throws Exception If an error is detected.
	 */
	public function check_for_errors( $response, $args = array() ) {
		// Check for standard 400-level errors.
		parent::check_for_errors( $response, $args );

		// Check for Facebook-specific error structure.
		if ( isset( $response['data']['error'] ) ) {
			$error = $response['data']['error'];

			if ( is_array( $error ) && isset( $error['message'] ) ) {
				throw new Exception( esc_html( $error['message'] ) );
			}

			if ( is_string( $error ) ) {
				throw new Exception( esc_html( $error ) );
			}
		}
	}

	////////////////////////////////////////////////////////////
	// Core request method
	////////////////////////////////////////////////////////////

	/**
	 * Send a request to the Facebook Lead Ads API.
	 *
	 * This is the core method that handles vault signature injection. Unlike most
	 * integrations that store credentials locally, Facebook Lead Ads uses a "vault
	 * signature" pattern where the API server stores actual page access tokens and
	 * returns only a reference key (vault signature). We store the signature locally
	 * and send it back on requests, allowing the API server to retrieve the real
	 * token from its secure vault. This ensures page access tokens never leave the
	 * API server.
	 *
	 * Uses the framework's api_request() method with exclude_credentials since
	 * Facebook Lead Ads uses per-page vault signatures instead of standard credentials.
	 *
	 * @param array      $body    The request body.
	 * @param int|string $page_id Optional. The page ID for vault signature lookup.
	 *
	 * @return array
	 *
	 * @throws Exception If an error occurs.
	 */
	public function send_request( array $body = array(), $page_id = 0 ) {
		$account_info = $this->helpers->get_account_info();

		// Add user ID to request.
		$body['user']     = $account_info['id'] ?? '';
		$body['site_url'] = $this->webhooks->get_webhook_url();

		// Add page-specific vault signature if page_id provided.
		if ( ! empty( $page_id ) ) {
			$body['page_vault_signature'] = $this->get_page_vault_signature( $page_id );
		}

		// Use framework's api_request with exclude_credentials since we handle
		// credentials manually via vault signatures.
		return $this->api_request(
			$body,
			null,
			array(
				'exclude_credentials' => true,
				'include_timeout'     => 30,
			)
		);
	}

	////////////////////////////////////////////////////////////
	// Data fetch methods
	////////////////////////////////////////////////////////////

	/**
	 * Get page access tokens for the user.
	 *
	 * @return array|WP_Error
	 */
	public function get_page_access_tokens() {
		$args = array(
			'action'       => 'get_user_pages',
			'access_token' => $this->get_user_access_token(),
		);

		try {
			$response = $this->send_request( $args );
		} catch ( Exception $e ) {
			return new WP_Error( 'page_access_token_exception', $e->getMessage() );
		}

		return $response;
	}

	/**
	 * Get forms for a Facebook page.
	 *
	 * @param int $page_id The Facebook page ID.
	 *
	 * @return array|WP_Error
	 */
	public function get_forms( int $page_id ) {
		$args = array(
			'action'            => 'get_forms',
			'page_id'           => $page_id,
			'page_access_token' => $this->get_page_access_token( $page_id ),
		);

		try {
			$response = $this->send_request( $args, $page_id );
		} catch ( Exception $e ) {
			return new WP_Error( 'get_forms_exception', $e->getMessage() );
		}

		return $response;
	}

	/**
	 * Get form fields for a specific form.
	 *
	 * @param int $page_id The Facebook page ID.
	 * @param int $form_id The form ID.
	 *
	 * @return array|WP_Error
	 */
	public function get_form_fields( int $page_id, int $form_id ) {
		$args = array(
			'action'            => 'get_form_fields',
			'form_id'           => $form_id,
			'page_id'           => $page_id,
			'page_access_token' => $this->get_page_access_token( $page_id ),
		);

		try {
			$response = $this->send_request( $args, $page_id );
		} catch ( Exception $e ) {
			return new WP_Error( 'get_form_fields_exception', $e->getMessage() );
		}

		return $response;
	}

	/**
	 * Get lead data by lead ID.
	 *
	 * @param int $page_id The Facebook page ID.
	 * @param int $lead_id The lead ID.
	 *
	 * @return array|WP_Error
	 */
	public function get_lead( int $page_id, int $lead_id ) {
		$args = array(
			'action'            => 'get_lead',
			'lead_id'           => $lead_id,
			'page_access_token' => $this->get_page_access_token( $page_id ),
		);

		try {
			$response = $this->send_request( $args, $page_id );
		} catch ( Exception $e ) {
			return new WP_Error( 'get_lead_exception', $e->getMessage() );
		}

		return (array) ( $response['data'] ?? array() );
	}

	////////////////////////////////////////////////////////////
	// Signed delivery subscription
	////////////////////////////////////////////////////////////

	/**
	 * Subscribe one page's connection to signed lead events.
	 *
	 * Events arrive at the plugin's shared platform receiver, not at the
	 * Facebook Lead Ads route; the platform sends every integration's events
	 * there.
	 *
	 * Sent without an Idempotency-Key, as is the unregister: each only sets
	 * the connection's subscription, so a repeat changes nothing. A key would
	 * let the platform replay an earlier "subscribed" answer for 24 hours,
	 * after the connection was unsubscribed in between (a disconnect and
	 * reconnect reactivate the same connection), and the page would read as
	 * subscribed while the platform delivers nothing.
	 *
	 * @since 8.0.0
	 *
	 * @param Connection $connection The page's v3 connection.
	 *
	 * @return array The answer's data — `{status, webhook_revision}`.
	 *
	 * @throws Action_Error_Exception When the platform did not subscribe the connection.
	 */
	public function register_site_for_webhooks( Connection $connection ) {

		return $this->send_subscription_request(
			'register-site-for-webhooks',
			$connection,
			array( 'webhook_url' => rest_url( AUTOMATOR_PLATFORM_WEBHOOK_RECEIVER_ROUTE ) ),
			'subscribed'
		);
	}

	/**
	 * Stop signed lead events for one page's connection. Idempotent.
	 *
	 * @since 8.0.0
	 *
	 * @param Connection $connection The page's v3 connection.
	 *
	 * @return array The answer's data — `{status}`.
	 *
	 * @throws Action_Error_Exception When the platform did not unsubscribe the connection.
	 */
	public function unregister_site_for_webhooks( Connection $connection ) {

		return $this->send_subscription_request( 'unregister-site-for-webhooks', $connection, array(), 'unsubscribed' );
	}

	/**
	 * Send a /v3 subscription request for one connection, authenticated by the
	 * connection's own id and signature in the body.
	 *
	 * @param string     $action     The route under the /v3 surface.
	 * @param Connection $connection The page's v3 connection.
	 * @param array      $body       Fields besides the connection's.
	 * @param string     $status     The `data.status` a success answers with.
	 *
	 * @return array The answer's data.
	 *
	 * @throws Action_Error_Exception When the connection cannot be sent, or the answer is not that status.
	 */
	private function send_subscription_request( $action, Connection $connection, array $body, $status ) {

		$signature = (string) $connection->connection_signature();

		if ( '' === $signature ) {
			throw Action_Error_Exception::validation_failed(
				esc_html_x( 'This Facebook page has no Automator App Platform connection to subscribe. Reconnect Facebook Lead Ads.', 'Facebook Lead Ads', 'uncanny-automator' )
			);
		}

		$response = $this->make_api_call(
			array(
				'endpoint' => self::V3_ENDPOINT . '/' . $action,
				'body'     => array_merge(
					array(
						'connection_id'        => $connection->connection_id(),
						'connection_signature' => $signature,
					),
					$body
				),
				'timeout'  => Platform_Http::TIMEOUT_SECONDS,
			)
		);

		$code     = (int) ( $response['statusCode'] ?? 0 );
		$data     = isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : array();
		$answered = isset( $data['status'] ) && is_string( $data['status'] ) ? $data['status'] : '';

		if ( 200 !== $code || $answered !== $status ) {
			throw Action_Error_Exception::execution_failed(
				esc_html_x( 'The Automator App Platform did not confirm the Facebook page subscription.', 'Facebook Lead Ads', 'uncanny-automator' )
			);
		}

		return $data;
	}

	////////////////////////////////////////////////////////////
	// Verification methods
	////////////////////////////////////////////////////////////

	/**
	 * Verify the connection status of a specific page.
	 *
	 * @param int|string $page_id The page ID.
	 *
	 * @return string|WP_Error 'Ready' on success, WP_Error on failure.
	 */
	public function verify_page_status( $page_id ) {
		$access_token = $this->get_page_access_token( $page_id );

		if ( empty( $access_token ) ) {
			return new WP_Error(
				'connection_error',
				esc_html_x( 'No access token found for the provided page ID.', 'Facebook Lead Ads', 'uncanny-automator' )
			);
		}

		$body = array(
			'action'            => 'verify_page_connection',
			'page_id'           => $page_id,
			'page_access_token' => $access_token,
		);

		try {
			$this->send_request( $body, $page_id );
			return esc_html_x( 'Ready', 'Facebook Lead Ads', 'uncanny-automator' );
		} catch ( Exception $e ) {
			return new WP_Error( 'connection_error', $e->getMessage() );
		}
	}

	/**
	 * Verify the webhook connection with the API server.
	 *
	 * @param string $url The URL to verify.
	 *
	 * @return array|WP_Error
	 */
	public function verify_connection( $url ) {
		if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return new WP_Error(
				'invalid_url',
				esc_html_x( 'The provided URL is invalid.', 'Facebook Lead Ads', 'uncanny-automator' )
			);
		}

		$body = array(
			'site_url' => $url,
			'action'   => 'verify_connection',
			'nonce'    => wp_create_nonce( 'wp_rest' ),
		);

		// Add basic auth if configured.
		$basic_auth = $this->get_basic_auth_credentials();
		if ( $basic_auth ) {
			$body = array_merge( $body, $basic_auth );
		}

		try {
			$result = $this->send_request( $body );
		} catch ( Exception $e ) {
			return new WP_Error( 'connection_error', $e->getMessage() );
		}

		return $result;
	}

	////////////////////////////////////////////////////////////
	// Credential helpers
	////////////////////////////////////////////////////////////

	/**
	 * Get user access token.
	 *
	 * @return string
	 */
	public function get_user_access_token() {
		$credentials = $this->helpers->get_credentials();

		return $credentials['user_access_token'] ?? '';
	}

	/**
	 * Get page access token for a specific page ID.
	 *
	 * @param int|string $page_id The Facebook page ID.
	 *
	 * @return string The page access token or empty string.
	 */
	public function get_page_access_token( $page_id ) {
		$pages = $this->helpers->get_pages_credentials();

		foreach ( $pages as $page ) {
			if ( isset( $page['id'] ) && absint( $page['id'] ) === absint( $page_id ) ) {
				return $page['access_token'] ?? '';
			}
		}

		return '';
	}

	/**
	 * Get vault signature for a specific page ID.
	 *
	 * The vault signature is a reference key that allows the API server to
	 * retrieve the real page access token from secure storage. See send_request()
	 * for full documentation on the vault signature pattern.
	 *
	 * @param int|string $page_id The Facebook page ID.
	 *
	 * @return string The vault signature or empty string.
	 */
	public function get_page_vault_signature( $page_id ) {
		$credentials = $this->helpers->get_credentials();

		return $credentials['vault_signatures'][ $page_id ] ?? '';
	}

	/**
	 * Check if the site has basic auth configured.
	 *
	 * @return bool
	 */
	public function site_has_basic_auth() {
		return defined( 'AUTOMATOR_FACEBOOK_LEAD_ADS_BASIC_AUTH_USERNAME' )
			&& defined( 'AUTOMATOR_FACEBOOK_LEAD_ADS_BASIC_AUTH_PASSWORD' );
	}

	/**
	 * Get basic auth credentials if configured.
	 *
	 * @return array|null
	 */
	public function get_basic_auth_credentials() {
		if ( ! $this->site_has_basic_auth() ) {
			return null;
		}

		return array(
			'basic_auth_username' => constant( 'AUTOMATOR_FACEBOOK_LEAD_ADS_BASIC_AUTH_USERNAME' ),
			'basic_auth_password' => constant( 'AUTOMATOR_FACEBOOK_LEAD_ADS_BASIC_AUTH_PASSWORD' ),
		);
	}
}
