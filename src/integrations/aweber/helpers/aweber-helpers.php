<?php
//phpcs:disable PHPCompatibility.Operators.NewOperators.t_coalesceFound

namespace Uncanny_Automator\Integrations\Aweber;

use Exception;
use Uncanny_Automator\Api_Server;
use Uncanny_Automator\Automator_Helpers_Recipe;

/**
 * Class Aweber_Helpers
 *
 * @package Uncanny_Automator
 */
class Aweber_Helpers {

	/**
	 * Settings tab id
	 *
	 * @var string|object
	 */
	public $settings_tab = 'aweber';

	/**
	 * The public API edge.
	 *
	 * @var string
	 */
	const API_ENDPOINT = 'v2/aweber';

	/**
	 * The nonce.
	 *
	 * @var string
	 */
	const NONCE = 'automator_aweber_api_authentication';

	/**
	 * Credentials wp_options key.
	 *
	 * @var string
	 */
	const CREDENTIALS = 'automator_aweber_credentials';

	/**
	 * Fetches all accounts.
	 *
	 * Callback from "wp_ajax_automator_aweber_accounts_fetch".
	 *
	 * @return void
	 */
	public function accounts_fetch() {

		Automator()->utilities->verify_nonce();

		try {

			$accounts = $this->api_request(
				array(
					'action' => 'get_accounts',
				),
				null
			);

			$entries = $accounts['data']['entries'] ?? array();

			foreach ( (array) $entries as $entry ) {
				$options[] = array(
					'value' => $entry['id'],
					'text'  => $entry['company'],
				);
			}

			$response = array(
				'success' => true,
				'options' => $options,
			);

		} catch ( Exception $e ) {

			$response = array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}

		wp_send_json( $response );
	}

	/**
	 *
	 * @return void
	 */
	public function lists_fetch() {

		Automator()->utilities->verify_nonce();

		// Ignore nonce, already handled above.
		$account_id = isset( $_POST['values']['ACCOUNT'] ) ? sanitize_text_field( wp_unslash( $_POST['values']['ACCOUNT'] ) ) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Missing

		try {

			$accounts = $this->api_request(
				array(
					'action'     => 'get_lists',
					'account_id' => $account_id,
				),
				null
			);

			$entries = $accounts['data']['entries'] ?? array();

			foreach ( (array) $entries as $entry ) {
				$options[] = array(
					'value' => $entry['id'],
					'text'  => $entry['name'],
				);
			}

			$response = array(
				'success' => true,
				'options' => $options,
			);

		} catch ( Exception $e ) {

			$response = array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}

		wp_send_json( $response );
	}

	/**
	 * Fetch all custom fields.
	 *
	 * @return void
	 */
	public function custom_fields_fetch() {

		$account_id = isset( $_POST['values']['ACCOUNT'] ) ? sanitize_text_field( wp_unslash( $_POST['values']['ACCOUNT'] ) ) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Missing
		$list       = isset( $_POST['values']['LIST'] ) ? sanitize_text_field( wp_unslash( $_POST['values']['LIST'] ) ) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Missing
		$rows       = array();

		try {

			$accounts = $this->api_request(
				array(
					'action'     => 'get_custom_fields',
					'account_id' => $account_id,
					'list_id'    => $list,
				),
				null
			);

			$entries = $accounts['data']['entries'] ?? array();

			foreach ( (array) $entries as $entry ) {
				$rows[] = array(
					'FIELD_ID'    => $entry['id'],
					'FIELD_NAME'  => $entry['name'],
					'FIELD_VALUE' => '',
				);
			}

			$response = array(
				'success' => true,
				'rows'    => $rows,
			);

		} catch ( Exception $e ) {

			$response = array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}

		wp_send_json( $response );

	}

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
	 * Create and retrieve a disconnect url for Aweber Integration.
	 *
	 * @return string The disconnect uri.
	 */
	public static function get_disconnect_url() {

		return add_query_arg(
			array(
				'action' => 'automator_aweber_disconnect_account',
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
			wp_safe_redirect( $this->get_settings_page_url() . '&error_message=' . _x( 'Unable to decode credentials with the secret provided', 'AWeber', 'uncanny-automator' ) );
			die;
		}

		automator_add_option( self::CREDENTIALS, array_merge( $credentials['data'], array( 'date_added' => time() ) ), false );

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
	 * Disconnect Aweber integration.
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

		// Only refresh the access token if the request is not coming from refresh token itself.
		if ( false === $refresh && $this->is_access_token_expired() ) {
			$this->refresh_access_token();
		}

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

		$this->check_for_errors( $response );

		return $response;
	}

	/**
	 * Determines whether the access token has expired or not.
	 *
	 * @return bool
	 */
	public function is_access_token_expired() {

		$credentials = $this->get_credentials();

		if ( empty( $credentials ) ) {
			throw new Exception( 'Your AWeber integration is currently disconnected. Please navigate to the settings page to establish a connection with your AWeber account.', 500 );
		}

		$date_authenticated = $credentials['date_added'] ?? 0;
		$expires_in         = $credentials['expires_in'] ?? 0;

		if ( empty( $date_authenticated ) ) {
			throw new Exception( 'Invalid authentication date detected. Please reconnect.', 500 );
		}

		if ( empty( $expires_in ) ) {
			throw new Exception( 'Invalid access token expiration data detected. Please reconnect.', 500 );
		}

		$expiry = $date_authenticated + $expires_in;

		return ( $expiry - time() ) <= 0;

	}

	/**
	 * Refresh the access token.
	 *
	 * @return true
	 */
	public function refresh_access_token() {

		$credentials = $this->get_credentials();

		$response = $this->api_request(
			array(
				'action'        => 'refresh_access_token',
				'refresh_token' => $credentials['refresh_token'] ?? '',
			),
			null, // Not an action.
			true // Flag as refresh request.
		);

		if ( empty( $response['data'] ) ) {
			throw new Exception( 'Refresh access token endpoint returns empty credentials. Please re-authenticate', 400 );
		}

		// Assign the data to credentials.
		$credentials = $response['data'];

		// Flag the new time.
		$credentials['date_added'] = time();

		// Finally, update the credentials.
		automator_update_option( self::CREDENTIALS, $credentials );

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
			$message = isset( $response['data']['error_description'] )
				? '(' . $response['statusCode'] . ') [' . $response['data']['error'] . '] ' . $response['data']['error_description']
				: _x( 'API Exception (status code: ' . $response['statusCode'] . '). An error has occured while performing the action. Please try again later.', 'AWeber', 'uncanny-automator' );
			throw new \Exception( $message, $response['statusCode'] );
		}

	}

	/**
	 * @return string
	 */
	public static function get_authorization_url() {
		return add_query_arg(
			array(
				'action'   => 'authorize',
				'user_url' => rawurlencode( get_bloginfo( 'url' ) ),
				'nonce'    => wp_create_nonce( self::NONCE ),
			),
			AUTOMATOR_API_URL . 'v2/aweber'
		);
	}

}
