<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

use Uncanny_Automator\Api_Server;
/**
 * Class Helpscout_Helpers
 *
 * @package Uncanny_Automator
 */
class Helpscout_Helpers {

	/**
	 * The API endpoint address.
	 *
	 * @var API_ENDPOINT The endpoint adress.
	 */
	const API_ENDPOINT = 'v2/helpscout';

	const NONCE_KEY = 'automator_helpscout_auth_nonce';

	const CLIENT = 'automator_helpscout_client';

	const TRANSIENT_MAILBOXES = 'automator_helpscout_mailboxes';

	const TRANSIENT_EXPIRES_TIME = 3600; // 1 hour in seconds.

	/**
	 * The setting's ID.
	 *
	 * @var string $setting_tab
	 */
	public $setting_tab = 'helpscout';

	protected $webhook_endpoint = null;

	public function __construct( $load_hooks = true ) {

		if ( $load_hooks ) {
			// Capture OAuthentication credentials.
			add_action(
				'wp_ajax_automator_helpscout_capture_tokens',
				function() {
					$this->capture_tokens();
				}
			);

			// Diconnect.
			add_action(
				'wp_ajax_automator_helpscout_disconnect',
				function() {
					$this->disconnect();
				}
			);

			// Fetch tags.
			add_action( 'wp_ajax_helpscout_fetch_tags', array( $this, 'fetch_tags' ) );

			// Regenerate key.
			add_action( 'wp_ajax_helpscout_regenerate_secret_key', array( $this, 'helpscout_regenerate_secret_key' ) );

			// Fetch coversation ajax handler.
			add_action( 'wp_ajax_helpscout_fetch_conversations', array( $this, 'fetch_conversations' ) );

			// Fetch mailbox users.
			add_action( 'wp_ajax_automator_helpscout_fetch_mailbox_users', array( $this, 'fetch_mailbox_users' ) );

			// Fetch properties' fields.
			add_action( 'wp_ajax_automator_helpscout_fetch_properties', array( $this, 'fetch_properties' ) );

			add_action( 'rest_api_init', array( $this, 'init_webhook' ) );

			$this->webhook_endpoint = apply_filters( 'automator_helpscout_webhook_endpoint', '/helpscout', $this );

		}

		// Load the settings page.
		require_once __DIR__ . '/../settings/settings-helpscout.php';

		new Helpscout_Settings( $this );

	}

	public function fetch_tags() {

		try {

			$response = $this->api_request(
				array(
					'action' => 'get_tags',
				),
				null
			);

			$tags = ! empty( $response['data']['_embedded']['tags'] ) ? $response['data']['_embedded']['tags'] : array();

			$options = array();

			foreach ( $tags as $tag ) {

				$options[] = array(
					'text'  => $tag['name'],
					'value' => $tag['id'],
				);

			}

			wp_send_json( $options );

		} catch ( \Exception $e ) {

			wp_send_json(
				array(
					array(
						'text'  => $e->getMessage(),
						'value' => 'Error: ' . $e->getMessage(),
					),
				)
			);

		}

	}

	/**
	 * Fetches properties.
	 *
	 * @return void
	 */
	public function fetch_properties() {

		$rows = array();

		try {

			$response = $this->api_request(
				array(
					'action' => 'get_properties',
				),
				null
			);

			if ( ! empty( $response['data']['_embedded']['customer-properties'] ) ) {
				foreach ( $response['data']['_embedded']['customer-properties'] as $prop ) {
					$rows[] = array(
						'PROPERTY_SLUG'  => $prop['slug'],
						'PROPERTY_NAME'  => $prop['name'],
						'PROPERTY_VALUE' => '',
					);
				}
			}
		} catch ( \Exception $e ) {

			$error_message = json_decode( $e->getMessage(), true );

			if ( isset( $error_message['data']['_embedded']['errors'] ) ) {
				$error_message = implode( '. ', array_column( $error_message['data']['_embedded']['errors'], 'message' ) );
			}

			$data = array(
				'success' => false,
				'message' => $error_message,
			);

			wp_send_json( $data );

		}

		$data = array(
			'success' => true,
			'rows'    => $rows,
		);

		wp_send_json( $data );

	}

	/**
	 * Fetches mailbox users.
	 *
	 * @return void
	 */
	public function fetch_mailbox_users() {

		$selected_mailbox = filter_input( INPUT_POST, 'value' );

		$from_created_by_field = false;

		$data = array(
			array(
				'text'  => esc_attr__( 'Customer', 'uncanny-automator' ),
				'value' => $selected_mailbox . '|_CUSTOMER_',
			),
		);

		// Get mailbox id coming from `Created by` field.
		if ( false !== strpos( $selected_mailbox, '|' ) ) {
			$from_created_by_field                 = true;
			list( $selected_mailbox, $created_by ) = explode( '|', $selected_mailbox );
		}

		if ( $from_created_by_field ) {
			$data[0] = array(
				'text'  => esc_attr__( 'Anyone', 'uncanny-automator' ),
				'value' => '_ANYONE_',
			);
		}

		try {

			$response = $this->api_request(
				array(
					'mailbox_id' => $selected_mailbox,
					'action'     => 'get_mailbox_users',
				),
				null
			);

			if ( ! empty( $response['data']['_embedded']['users'] ) ) {

				foreach ( $response['data']['_embedded']['users'] as $user ) {

					$data[] = array(
						'text'  => implode( ' ', array( $user['firstName'], $user['lastName'] ) ) . ' - ' . $user['email'] . ' (' . $user['role'] . ')',
						'value' => $selected_mailbox . '|' . $user['id'],
					);

				}
			}
		} catch ( \Exception $e ) {

			$error_message = json_decode( $e->getMessage(), true );

			if ( isset( $error_message['data']['_embedded']['errors'] ) ) {

				$error_message = implode( '. ', array_column( $error_message['data']['_embedded']['errors'], 'message' ) );

			}

			$data = array(
				array(
					'text'  => 'An unexpected error has been encountered. ' . $error_message,
					'value' => 'Error: ' . $e->getCode(),
				),
			);
		}

		wp_send_json( $data );

	}

	public function fetch_conversations() {

		$selected_mailbox = intval( filter_input( INPUT_POST, 'value' ) );

		$data = array(
			array(
				'text'  => esc_html__( 'Any conversation', 'uncanny-automator' ),
				'value' => -1,
			),
		);

		try {

			if ( -1 === $selected_mailbox ) {
				wp_send_json( $data );
			}

			$response = $this->api_request(
				array(
					'mailbox' => $selected_mailbox,
					'action'  => 'get_conversations',
				),
				null
			);

			if ( ! empty( $response['data']['_embedded']['conversations'] ) ) {
				foreach ( $response['data']['_embedded']['conversations'] as $conversation ) {
					$data[] = array(
						'text'  => $conversation['subject'],
						'value' => $conversation['id'],
					);
				}
			}
		} catch ( \Exception $e ) {
			$data = array(
				array(
					'text'  => $e->getMessage(),
					'value' => 'Error: ' . $e->getCode(),
				),
			);
		}

		wp_send_json( $data );

	}

	/**
	 * Method api_request
	 *
	 * @param $params
	 *
	 * @return void
	 */
	public function api_request( $body, $action_data = null ) {

		$access_token = $this->get_access_token();

		$body['access_token'] = $access_token;

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => $body,
			'action'   => $action_data,
			'timeout'  => 15,
		);

		$response = Api_Server::api_call( $params );

		$this->handle_errors( $response );

		return $response;

	}

	/**
	 * Handles errors from the API.
	 *
	 * @param array $response The response from API.
	 *
	 * @return boolean True if no errors has occured. Otherwise, throws an Exception.
	 */
	protected function handle_errors( $response ) {

		$status_code = $response['statusCode'];

		$is_status_ok = $status_code >= 200 && $status_code <= 299;

		if ( 401 === $status_code ) {
			// Manually refresh the token if 401 is hit.
			$this->refresh_token( $this->get_client() );
		}

		if ( ! $is_status_ok ) {

			if ( ! empty( $response['data']['error'] ) ) {

				$err_message = $response['data']['error'] . ' - ' . $response['data']['error_description'];

				if ( 401 === $status_code ) {
					$err_message .= '. Attempting to refresh the token manually. Please re-run the action or refresh the page if in the recipe builder.';
				}

				throw new \Exception(
					$err_message,
					$status_code
				);

			}

			throw new \Exception( wp_json_encode( $response ), $status_code );

		}

		return true;

	}

	/**
	 * Retrieves the OAuth URL.
	 *
	 * @return string The OAuth URL.
	 */
	public function get_oauth_url() {

		$nonce = wp_create_nonce( self::NONCE_KEY );

		return add_query_arg(
			array(
				'action'   => 'authorization_request',
				'user_url' => rawurlencode( admin_url( 'admin-ajax.php?action=automator_helpscout_capture_tokens&nonce=' . $nonce ) ),
				'nonce'    => $nonce,
			),
			AUTOMATOR_API_URL . self::API_ENDPOINT
		);

	}

	/**
	 * Captures the token and saves it locally.
	 */
	private function capture_tokens() {

		$nonce = automator_filter_input( 'nonce' );

		$this->verify_access( $nonce );

		$tokens = (array) Automator_Helpers_Recipe::automator_api_decode_message(
			automator_filter_input( 'automator_api_message' ),
			$nonce
		);

		if ( empty( $tokens ) ) {

			$this->redirect(
				$this->get_settings_url(),
				array(
					'status' => 'error',
					'code'   => 403,
				)
			);

		}

		// Manually set the access token and refresh token expiration date.
		$tokens['expires_on'] = strtotime( current_time( 'mysql' ) ) + $tokens['expires_in'];

		update_option( self::CLIENT, $tokens, true );

		try {

			$client = $this->get_client();

			$response = $this->get_connected_user( $tokens );

			$client['user'] = $response['data'];

			update_option( self::CLIENT, $client, true );

		} catch ( \Exception $e ) {

			// Disconnect if it fails.
			$this->disconnect( false );

			$this->redirect(
				$this->get_settings_url(),
				array(
					'status'  => 'error',
					'action'  => 'get_resource_owner',
					'message' => rawurlencode( $e->getMessage() ),
					'code'    => $e->getCode(),
				)
			);

		}

		$this->redirect(
			$this->get_settings_url(),
			array(
				'status' => 'success',
				'code'   => 200,
			)
		);

	}

	/**
	 * Disconnects Help Scout user.
	 */
	public function disconnect( $do_redirect = true ) {

		$nonce = automator_filter_input( 'nonce' );

		$this->verify_access( $nonce );

		delete_option( 'automator_helpscout_client' );
		delete_option( 'uap_helpscout_enable_webhook' );
		delete_option( 'uap_helpscout_webhook_key' );

		delete_transient( self::TRANSIENT_MAILBOXES );

		if ( false === $do_redirect ) {
			return true;
		}

		$this->redirect(
			$this->get_settings_url(),
			array(
				'status' => 'success',
				'action' => 'disconnected',
				'code'   => 200,
			)
		);

	}

	public function get_connected_user() {

		return $this->api_request(
			array(
				'action' => 'get_resource_owner',
			),
			null
		);

	}

	public function verify_access( $nonce = '' ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->redirect(
				$this->get_settings_url(),
				array(
					'status' => 'error_not_admin',
					'code'   => 403,
				)
			);
		}

		if ( ! wp_verify_nonce( $nonce, self::NONCE_KEY ) ) {
			$this->redirect(
				$this->get_settings_url(),
				array(
					'status' => 'error_nonce_invalid',
					'code'   => 403,
				)
			);
		}

	}

	public function redirect( $redirect_url = '', $args = array() ) {

		wp_safe_redirect( add_query_arg( $args, $redirect_url ) );

		exit;

	}

	public function get_settings_url() {

		return add_query_arg(
			array(
				'post_type'   => 'uo-recipe',
				'page'        => 'uncanny-automator-config',
				'tab'         => 'premium-integrations',
				'integration' => 'helpscout',
			),
			admin_url( 'edit.php' )
		);

	}

	/**
	 * Retrieve the disconnect url.
	 */
	public function get_disconnect_url() {

		return add_query_arg(
			array(
				'nonce'  => wp_create_nonce( self::NONCE_KEY ),
				'action' => 'automator_helpscout_disconnect',
			),
			admin_url( 'admin-ajax.php' )
		);

	}

	public function get_client() {

		return automator_get_option( self::CLIENT, false );

	}

	public function get_client_user() {

		return isset( $this->get_client()['user'] ) ? $this->get_client()['user'] : '';

	}

	private function get_access_token() {

		$client = automator_get_option( self::CLIENT, array() );

		// If date today exceeded the expires on, it means the token has expired already.
		// Allowance of 2 hours (7200s) to account for token expiration timing.
		if ( time() >= absint( $client['expires_on'] ) - 7200 ) {
			// Refresh the token.
			$this->refresh_token( $client );
		}

		return $this->get_client()['access_token'];

	}

	protected function refresh_token( $client ) {

		try {

			$params = array(
				'endpoint' => self::API_ENDPOINT,
				'body'     => array(
					'action'        => 'refresh_access_token',
					'refresh_token' => $client['refresh_token'],
				),
				'action'   => null,
				'timeout'  => 15,
			);

			$response = Api_Server::api_call( $params );

			if ( 200 === $response['statusCode'] ) {

				$response = ! empty( $response['data'] ) ? $response['data'] : null;

				if ( ! empty( $response ) ) {

					$response['expires_on'] = strtotime( current_time( 'mysql' ) ) + $response['expires_in'];
					$response['user']       = $client['user'];

					update_option( self::CLIENT, $response, true );

				}
			}
		} catch ( \Exception $e ) {

			automator_log( $e->getMessage(), 'Failed to retrieve refresh token for Help Scout. Please re-connect.', true, 'help-scout' );

		}

	}
	public function fetch_mailboxes() {

		$saved_mailboxes = get_transient( self::TRANSIENT_MAILBOXES );

		if ( false === $saved_mailboxes ) {
			return $this->request_mailboxes();
		}

		return $saved_mailboxes;

	}

	public function request_mailboxes() {

		$options = array();

		try {

			$options[-1] = esc_html__( 'Any mailbox', 'uncanny-automator' );

			$response = $this->api_request(
				array(
					'action' => 'get_mailboxes',
				),
				null
			);

			if ( empty( $response['data']['_embedded']['mailboxes'] ) ) {
				return array();
			}

			foreach ( $response['data']['_embedded']['mailboxes'] as $mailbox ) {
				$options[ $mailbox['id'] ] = $mailbox['name'];
			}

			set_transient( self::TRANSIENT_MAILBOXES, $options, self::TRANSIENT_EXPIRES_TIME );

			return ! empty( $options ) ? $options : array();

		} catch ( \Exception $e ) {

			return array(
				'Error ' . $e->getCode() => $e->getMessage(),
			);

		}

	}

	/**
	 * Checks if webhook is enabled or not. We need to support both 'on' and 1 values for backwards compatibility.
	 *
	 * @return void
	 */
	public function is_webhook_enabled() {

		$webhook_enabled_option = automator_get_option( 'uap_helpscout_enable_webhook', false );

		// The get_option can return string or boolean sometimes.
		if ( 'on' === $webhook_enabled_option || 1 == $webhook_enabled_option ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
			return true;
		}

		return false;
	}

	/**
	 * Generate webhook key.
	 *
	 * @return void
	 */
	public function regenerate_webhook_key() {

		$new_key = md5( uniqid( wp_rand(), true ) );

		update_option( 'uap_helpscout_webhook_key', $new_key );

		return $new_key;

	}

	public function get_webhook_url() {

		return get_rest_url( null, '/uap/v2/helpscout' );

	}

	/**
	 * Retrieve the webhook key.
	 *
	 * @return string The webhook key.
	 */
	public function get_webhook_key() {

		$webhook_key = automator_get_option( 'uap_helpscout_webhook_key', false );

		if ( false === $webhook_key ) {
			$webhook_key = $this->regenerate_webhook_key();
		}

		return $webhook_key;
	}

	/**
	 * Initialize the incoming webhook if it's enabled
	 *
	 * @return void
	 */
	public function init_webhook() {
		if ( $this->is_webhook_enabled() && ( false !== $this->get_client() ) ) {
			register_rest_route(
				AUTOMATOR_REST_API_END_POINT,
				$this->webhook_endpoint,
				array(
					'methods'             => array( 'GET', 'POST' ),
					'callback'            => array( $this, 'webhook_callback' ),
					'permission_callback' => array( $this, 'validate_webhook' ),
				)
			);
		}
	}

	/**
	 * This function will fire for valid incoming webhook calls
	 *
	 * @param  mixed $request
	 * @return void
	 */
	public function webhook_callback( $request ) {

		if ( 'GET' === $request->get_method() ) {
			wp_send_json_success(
				array(
					'time' => time(),
				)
			);
		}

		do_action( 'automator_helpscout_webhook_received', $request->get_params(), $request->get_headers() );

		wp_send_json_success(
			array(
				'acknowledged' => time(),
			)
		);

	}

	/**
	 * Validate the incoming webhook
	 *
	 * @param  mixed $request
	 * @return void
	 */
	public function validate_webhook( $request ) {

		if ( 'GET' === $request->get_method() ) {
			return true;
		}

		if ( ! $this->is_from_helpscout( $request, $request->get_header( 'x_helpscout_signature' ) ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Determines whether the incoming request is from HelpScout or not.
	 *
	 * @param mixed $request
	 * @param string $signature
	 *
	 * @return boolean
	 */
	protected function is_from_helpscout( $request, $signature ) {

		return $signature
			=== base64_encode( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.PHP.YodaConditions.NotYoda
				hash_hmac(
					'sha1',
					wp_json_encode( $request->get_params() ),
					$this->get_webhook_key(),
					true
				)
			);

	}

	public function helpscout_regenerate_secret_key() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions' );
		}

		if ( ! wp_verify_nonce( automator_filter_input( 'nonce' ), 'helpscout_regenerate_key' ) ) {
			wp_die( 'Invalid nonce' );
		}

		$this->regenerate_webhook_key();

		$uri = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-config&tab=premium-integrations&integration=helpscout';

		wp_safe_redirect( $uri );

		exit;

	}

	public function get_regenerate_url() {
		return add_query_arg(
			array(
				'action' => 'helpscout_regenerate_secret_key',
				'nonce'  => wp_create_nonce( 'helpscout_regenerate_key' ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * Determines whether the incoming request from Help Scout matches the given event.
	 *
	 * @param array $request_headers The incoming request headers from Help Scout.
	 * @param string $x_helpscout_event The type of event to determine.
	 *
	 * @return boolean True if specific event matches the given event. Otherwise false.
	 */
	public function is_webhook_request_matches_event( $request_headers, $x_helpscout_event ) {

		if ( empty( $request_headers['x_helpscout_event'][0] ) ) {
			return false;
		}

		return $x_helpscout_event === $request_headers['x_helpscout_event'][0];

	}

	/**
	 * Formats the given date timestamp to WordPress date and time format.
	 *
	 * @return string The formatted date and time.
	 */
	public function format_date_timestamp( $timestamp ) {

		return gmdate( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );

	}

}
