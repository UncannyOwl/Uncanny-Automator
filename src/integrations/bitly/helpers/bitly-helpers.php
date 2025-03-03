<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Bitly;

use Uncanny_Automator\Api_Server;
use Uncanny_Automator\Automator_Helpers_Recipe;

/**
 * Class Bitly_Helpers
 *
 * @package Uncanny_Automator
 */
class Bitly_Helpers {

	/**
	 * Settings tab id
	 *
	 * @var string|object
	 */
	public $settings_tab = 'bitly';

	/**
	 * The public API edge.
	 *
	 * @var string
	 */
	const API_ENDPOINT = 'v2/bitly';

	/**
	 * The nonce.
	 *
	 * @var string
	 */
	const NONCE = 'automator_bitly_api_authentication';

	/**
	 * Credentials wp_options key.
	 *
	 * @var string
	 */
	const ACCOUNT_DETAILS = 'automator_bitly_credentials';

	/**
	 *
	 */
	const ACCESS_TOKEN = 'automator_bitly_access_token';
	/**
	 * The invalid key message.
	 *
	 * @var string
	 */
	public $invalid_key_message = '';
	/**
	 * @var string[]
	 */
	private $account_details = array(
		'login' => '',
		'email' => '',
		'name'  => '',
	);

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
	 * Create and retrieve a disconnect url for Bitly Integration.
	 *
	 * @return string The disconnect uri.
	 */
	public static function get_disconnect_url() {

		return add_query_arg(
			array(
				'action' => 'automator_bitly_disconnect_account',
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

		$data  = automator_filter_input( 'automator_api_message' );
		$nonce = automator_filter_input( 'nonce' );

		$credentials = Automator_Helpers_Recipe::automator_api_decode_message( $data, $nonce );

		// Handle errors.
		if ( false === $credentials ) {
			// Redirect to settings page with error message.
			wp_safe_redirect( $this->get_settings_page_url() . '&error_message=' . _x( 'Unable to decode credentials with the secret provided', 'Bitly', 'uncanny-automator' ) );
			die;
		}

		automator_add_option( 'automator_bitly_credentials', $credentials, false );

		// Then redirect to settings page. Flag as connected with success=yes.
		wp_safe_redirect( $this->get_settings_page_url() . '&success=yes' );
		die;
	}

	/**
	 * Get class const.
	 *
	 * @param string $const
	 *
	 * @return string
	 */
	public function get_const( $const ) {
		return constant( 'self::' . $const );
	}

	/**
	 * Retrieve the credentials from the options table.
	 *
	 * @return array
	 */
	public static function get_account_details() {
		return (array) automator_get_option( self::ACCOUNT_DETAILS, array() );
	}

	/**
	 * Integration status.
	 *
	 * @return string
	 */
	public function integration_status() {
		$credentials = self::get_account_details();

		return isset( $credentials['login'] ) ? 'success' : '';
	}

	/**
	 * Disconnect Bitly integration.
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
		automator_delete_option( self::ACCOUNT_DETAILS );
		automator_delete_option( self::ACCESS_TOKEN );
	}

	/**
	 * Make API request.
	 *
	 * @param string $action
	 * @param mixed $body
	 * @param mixed $action_data
	 * @param bool $check_for_errors
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function api_request( $action, $body = null ) {

		$body                 = is_array( $body ) ? $body : array();
		$body['action']       = $action;
		$body['access_token'] = $this->get_access_token();

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => $body,
		);

		return Api_Server::api_call( $params );
	}

	/**
	 * Check response for errors.
	 *
	 * @param mixed $response
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function check_for_errors( $response ) {

		if ( $response['statusCode'] !== 201 || $response['statusCode'] !== 200 ) {
			$message = isset( $response['data']['message'] ) ? $response['data']['message'] : _x( 'Bitly API Error', 'Bitly', 'uncanny-automator' );
			throw new \Exception( esc_html( $message ), absint( $response['statusCode'] ) );
		}
	}

	/**
	 * Get API Key.
	 *
	 * @return string
	 */
	public function get_access_token() {
		return automator_get_option( self::ACCESS_TOKEN, '' );
	}

	/**
	 * Get Account Details.
	 *
	 * @return array
	 */
	public function get_saved_account_details() {

		// No API key set return defaults.
		$access_token = $this->get_access_token();
		if ( empty( $access_token ) ) {
			return array();
		}

		// Get account details.
		$account = automator_get_option( self::ACCOUNT_DETAILS, false );

		// Legacy check.
		if ( empty( $account['login'] ) ) {
			$account = $this->get_account();
		}

		return $account;
	}

	/**
	 * @return string[]
	 */
	public function get_account() {

		// Set defaults.
		$account = $this->account_details;

		// Validate api key.
		$access_token = $this->get_access_token();

		if ( empty( $access_token ) ) {
			return $account;
		}

		// Get account.
		try {
			$response = $this->api_request( 'get_user' );

		} catch ( \Exception $e ) {
			$error            = $e->getMessage();
			$account['error'] = ! empty( $error ) ? $error : _x( 'Bitly API Error', 'Bitly', 'uncanny-automator' );

			automator_update_option( self::ACCOUNT_DETAILS, $account );

			return $account;
		}

		// Success.
		if ( ! empty( $response['data'] ) ) {
			$account['login'] = $response['data']['login'];
			$account['name']  = $response['data']['name'];
			if ( ! empty( $response['data']['emails'] ) ) {
				$emails            = array_shift( $response['data']['emails'] );
				$account['email']  = $emails['email'];
				$account['status'] = 'connected';
			}
		}

		// Check for invalid key.
		if ( empty( $response['data']['login'] ) ) {
			$account['status'] = '';
			$account['error']  = $this->invalid_key_message;
		}

		automator_update_option( self::ACCOUNT_DETAILS, $account );

		return $account;
	}
}
