<?php
//phpcs:disable PHPCompatibility.Operators.NewOperators.t_coalesceFound

namespace Uncanny_Automator\Integrations\Threads;

use Exception;
use Uncanny_Automator\Api_Server;
use Uncanny_Automator\Automator_Helpers_Recipe;

/**
 * Class Threads_Helpers
 *
 * @package Uncanny_Automator
 */
class Threads_Helpers {

	/**
	 * Settings tab id
	 *
	 * @var string|object
	 */
	public $settings_tab = 'threads';

	/**
	 * The public API edge.
	 *
	 * @var string
	 */
	const API_ENDPOINT = 'v2/threads';

	/**
	 * The nonce.
	 *
	 * @var string
	 */
	const NONCE = 'automator_threads_api_authentication';

	/**
	 * Credentials wp_options key.
	 *
	 * @var string
	 */
	const CREDENTIALS = 'automator_threads_credentials';

	/**
	 * Get settings page url.
	 *
	 * @return string
	 */
	public function get_settings_page_url() {
		return add_query_arg(
			array(
				'post_type'   => 'uo-recipe',
				'page'        => 'uncanny-automator-config',
				'tab'         => 'premium-integrations',
				'integration' => $this->settings_tab,
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Create and retrieve a disconnect url for Threads Integration.
	 *
	 * @return string The disconnect uri.
	 */
	public static function get_disconnect_url() {

		return add_query_arg(
			array(
				'action' => 'automator_threads_disconnect_account',
				'nonce'  => wp_create_nonce( self::NONCE ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * Authentication handler.
	 *
	 * @return never
	 */
	public function authenticate() {

		$access_token = automator_filter_input( 'access_token' );
		$user_id      = automator_filter_input( 'user_id' );
		$nonce        = automator_filter_input( 'state' );
		$expires_in   = automator_filter_input( 'expires_in' );

		if ( ! wp_verify_nonce( $nonce, self::NONCE ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You are not allowed to do this.' );
		}

		if ( empty( $access_token ) || empty( $user_id ) ) {
			wp_safe_redirect( $this->get_settings_page_url() . '&error=empty_required_credentials' );
		}

		// Make sure to renew the option.
		automator_delete_option( self::CREDENTIALS );

		$credentials = array(
			'access_token' => $access_token,
			'token_type'   => 'bearer',
			'expires_in'   => $expires_in,
			'expiration'   => time() + absint( $expires_in ),
			'user_id'      => $user_id,
		);

		automator_add_option( self::CREDENTIALS, $credentials );

		// Then redirect to settings page. Flag as connected with success=yes.
		wp_safe_redirect( $this->get_settings_page_url() . '&success=yes' );

		die;
	}

	/**
	 * Retrieve the credentials from the options table.
	 *
	 * @return array
	 */
	public static function get_credentials() {
		return (array) automator_get_option( self::CREDENTIALS, array() );
	}

	/**
	 * Integration status.
	 *
	 * @return string
	 */
	public function integration_status() {
		$credentials = self::get_credentials();
		return isset( $credentials['access_token'] ) ? 'success' : '';
	}

	/**
	 * Disconnect Threads integration.
	 *
	 * @return void
	 */
	public function disconnect() {

		if ( wp_verify_nonce( filter_input( INPUT_GET, 'nonce', FILTER_UNSAFE_RAW ), self::NONCE ) ) {

			$this->remove_credentials();
		}

		wp_safe_redirect( $this->get_settings_page_url() );

		exit;
	}

	/**
	 * Remove credentials.
	 *
	 * @return void
	 */
	public function remove_credentials() {
		automator_delete_option( self::CREDENTIALS );
	}

	/**
	 * Sends an HTTP Requests to the API.
	 *
	 * @param mixed $body
	 * @param mixed $action_data
	 * @return array
	 * @throws Exception
	 */
	public function api_request( $body = null, $action_data = null, $refresh = false ) {

		// Make sure to request the credentials just after the refresh token.
		$credentials  = $this->get_credentials();
		$access_token = $credentials['access_token'] ?? '';

		$body['access_token'] = $access_token;

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => $body,
			'action'   => $action_data,
		);

		$response = Api_Server::api_call( $params );

		$data = $response['data'] ?? array();

		if ( isset( $data['credentials'] ) ) {

			$current_credentials = $this->get_credentials();

			$expires_in = $data['credentials']['expires_in'];

			$data['credentials']['user_id']    = $current_credentials['user_id'];
			$data['credentials']['expiration'] = time() + absint( $expires_in );

			automator_update_option( self::CREDENTIALS, $data['credentials'] );
		}

		$this->check_for_errors( $response );

		return $response;
	}

	/**
	 * Check response for errors.
	 *
	 * @param  mixed $response
	 *
	 * @return void
	 */
	public function check_for_errors( $response ) {

		if ( 201 !== $response['statusCode'] && 200 !== $response['statusCode'] && 209 !== $response['statusCode'] ) {
			$message = sprintf(
				/* translators: %d: HTTP status code */
				esc_html_x( 'API Exception (status code: %d). An error has occurred while performing the action. Please try again later.', 'Threads', 'uncanny-automator' ),
				absint( $response['statusCode'] )
			);
			throw new \Exception( esc_html( $message ), absint( $response['statusCode'] ) );
		}
	}

	/**
	 * @return string
	 */
	public static function get_authorization_url() {
		return add_query_arg(
			array(
				'action'     => 'authorization',
				'wp_site'    => rawurlencode( get_bloginfo( 'url' ) ),
				'state'      => wp_create_nonce( self::NONCE ),
				'plugin_ver' => AUTOMATOR_PLUGIN_VERSION,
			),
			AUTOMATOR_API_URL . 'v2/threads'
		);
	}

	/**
	 * Get the media URL from either a URL or a media library ID.
	 *
	 * If the input is a valid URL, it will return the URL. If the input is numeric,
	 * it will fetch the media URL using the attachment ID from the WordPress media library.
	 *
	 * @param string|int $input The input, either a URL or a numeric media library ID.
	 *
	 * @return string The media URL, or an empty string if no valid media is found.
	 */
	public static function get_media_url( $input ) {

		// Check if the input is a valid URL
		if ( filter_var( $input, FILTER_VALIDATE_URL ) ) {
			return $input;
		}

		// Check if the input is numeric (assumed to be a media ID)
		if ( is_numeric( $input ) ) {
			// Get the URL of the media item using the media ID
			$media_url = wp_get_attachment_url( intval( $input ) );

			// Return the media URL if it exists
			if ( $media_url ) {
				return $media_url;
			}
		}

		// Return an empty string if the input is invalid or no URL was found
		return '';
	}

	/**
	 * Check if the account is connected.
	 *
	 * @return bool True if the account is connected, false otherwise.
	 */
	public static function is_account_connected() {

		// Get account credentials.
		$credentials = self::get_credentials();

		$required_keys = array( 'access_token', 'token_type', 'expires_in', 'expiration', 'user_id' );

		// Validate required keys are present.
		return empty( array_diff_key( array_flip( $required_keys ), (array) $credentials ) );
	}
}
