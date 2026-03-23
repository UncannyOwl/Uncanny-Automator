<?php

namespace Uncanny_Automator;

use Exception;

/**
 * Class Api.
 *
 * @package Uncanny_Automator
 */
class Api_Server {

	/**
	 * @var string The key used for marking the failed license.
	 */
	const TRANSIENT_LICENSE_CHECK_FAILED = 'automator_license_check_failed';

	/**
	 * @var int The frequency of license check.
	 */
	private static $transient_api_license_expires = 60; // 1 minute by default.

	/**
	 * @var mixed|null
	 */
	public static $url;

	/**
	 * @var null
	 */
	public static $mock_response = null;

	/**
	 * @var null
	 */
	private static $instance = null;

	/**
	 * @var null
	 */
	private static $license = null;

	/**
	 * @var mixed
	 */
	public static $last_response = array();

	/**
	 * __construct
	 *
	 * @return void
	 */
	private function __construct() {

		self::$url = apply_filters( 'automator_api_url', AUTOMATOR_API_URL );

		/**
		 * Set the cache expiry to 12 hours. The nightly check is 24 hours. This is a
		 * safe number so the license check is atleast performed twice a day. One when actively
		 * editing the recipe page, and two, when nightly checks are made.
		 */
		self::$transient_api_license_expires = HOUR_IN_SECONDS * 12;

		add_filter( 'http_request_args', array( $this, 'add_api_headers' ), 10, 2 );
		add_filter( 'http_request_timeout', array( $this, 'default_api_timeout' ), 10, 2 );
		add_filter( 'automator_trigger_should_complete', array( $this, 'maybe_log_trigger' ), 10, 3 );
	}

	/**
	 * @param $instance
	 *
	 * @return void
	 */
	public static function set_instance( $instance ) {
		self::$instance = $instance;
	}

	/**
	 * Reset the license cache.
	 *
	 * @return void
	 */
	public static function reset_license_cache() {
		self::$license = null;
	}

	/**
	 * get_instance
	 *
	 * @return Api_server
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::set_instance( new Api_server() );
		}

		return self::$instance;
	}

	/**
	 * Method add_api_headers
	 *
	 * @param array $args
	 * @param string $request_url
	 *
	 * @return array
	 */
	public function add_api_headers( $args, $request_url ) {

		// If the request URL starts with the Automator API url
		if ( ! $this->is_api_url( $request_url ) ) {
			return $args;
		}

		$license_key = self::get_license_key();

		if ( ! $license_key ) {
			return $args;
		}

		$args['headers']['license-key'] = $license_key;
		$args['headers']['site-name']   = self::get_site_name();
		$args['headers']['item-name']   = self::get_item_name();

		return $args;
	}

	/**
	 * is_api_url
	 *
	 * @param mixed $url
	 *
	 * @return bool
	 */
	public function is_api_url( $url ) {
		return substr( $url, 0, strlen( self::$url ) ) === self::$url;
	}

	/**
	 * default_api_timeout
	 *
	 * @param int $timeout
	 * @param string $request_url
	 *
	 * @return int
	 */
	public function default_api_timeout( $timeout, $request_url = '' ) {

		if ( empty( $request_url ) || ! $this->is_api_url( $request_url ) ) {
			return $timeout;
		}

		return apply_filters( 'automator_api_timeout', 30, $request_url );
	}

	/**
	 * Method get_license_type
	 *
	 * @return string
	 */
	public static function get_license_type() {
		if ( defined( 'AUTOMATOR_PRO_FILE' ) && 'valid' === automator_get_option( 'uap_automator_pro_license_status' ) ) {
			return 'pro';
		} elseif ( 'valid' === automator_get_option( 'uap_automator_free_license_status' ) ) {
			return 'free';
		}

		return false;
	}

	/**
	 * Method get_license_key
	 *
	 * @return string
	 */
	public static function get_license_key() {
		$license_type = self::get_license_type();

		return automator_get_option( 'uap_automator_' . $license_type . '_license_key' );
	}

	/**
	 * Method get_item_name
	 *
	 * @return string
	 */
	public static function get_item_name() {

		$license_type = strtoupper( self::get_license_type() );

		if ( ! $license_type ) {
			return '';
		}

		if ( 'PRO' === $license_type ) {
			if ( defined( 'AUTOMATOR_' . $license_type . '_ITEM_NAME' ) ) {
				return constant( 'AUTOMATOR_' . $license_type . '_ITEM_NAME' );
			} elseif ( defined( 'AUTOMATOR_AUTOMATOR_' . $license_type . '_ITEM_NAME' ) ) {
				return constant( 'AUTOMATOR_AUTOMATOR_' . $license_type . '_ITEM_NAME' );
			}
		}

		return constant( 'AUTOMATOR_' . $license_type . '_ITEM_NAME' );
	}

	/**
	 * Method get_license_plan
	 *
	 * @return string
	 */
	public static function get_license_plan() {

		try {
			$license = self::get_license();

			// Check if we have the license_plan property.
			if ( self::has_license_plan_property( $license ) ) {
				return $license['license_plan'];
			}

			// If license exists but missing license_plan, try to refresh.
			if ( $license ) {
				$license = self::is_automator_connected( true );
				if ( self::has_license_plan_property( $license ) ) {
					return $license['license_plan'];
				}
			}
		} catch ( Exception $e ) {
			// Fall through to fallback logic.
			unset( $e );
		}

		// Fallback to basic/lite based on license type.
		$license_type = self::get_license_type();
		if ( ! $license_type ) {
			return '';
		}

		return 'pro' === $license_type ? 'basic' : 'lite';
	}

	/**
	 * Check if license has the license plan property.
	 * - Temporary fallback to work around license caching.
	 *
	 * @param mixed $license
	 * @return bool
	 */
	private static function has_license_plan_property( $license ) {
		return is_array( $license ) && isset( $license['license_plan'] );
	}

	/**
	 * Method get_site_name
	 * For sites like https://your-site:8888/
	 *
	 * @return string
	 */
	public static function get_site_name() {
		return preg_replace( '#^https?://#', '', get_home_url() );
	}

	/**
	 * Get formatted license renewal/expiry date for MCP payload.
	 *
	 * @return string Formatted date like "January 1, 2026" or empty string if lifetime/unavailable.
	 */
	public static function get_renewal_date_formatted(): string {
		$license_type = self::get_license_type();

		if ( ! $license_type ) {
			return '';
		}

		$expiry = automator_get_option( 'uap_automator_' . $license_type . '_license_expiry', '' );

		if ( empty( $expiry ) || 'lifetime' === $expiry ) {
			return '';
		}

		try {
			$date = new \DateTime( $expiry, wp_timezone() );
			return $date->format( 'F j, Y' );
		} catch ( Exception $e ) {
			return '';
		}
	}

	/**
	 * Get URL for purchasing additional credits.
	 *
	 * @return string URL to credits/pricing page.
	 */
	public static function get_url_get_credits(): string {
		return AUTOMATOR_LLM_CREDITS_URL;
	}

	/**
	 * Method add_endpoint_parts
	 *
	 * @param array $params
	 *
	 * @return array $params
	 */
	public function add_endpoint_parts( $params ) {

		$endpoint_parts = explode( '/', $params['endpoint'] );

		if ( 2 === count( $endpoint_parts ) ) {
			$params['api_version'] = array_shift( $endpoint_parts );
			$params['integration'] = array_shift( $endpoint_parts );
		}

		return $params;
	}

	/**
	 * Method filter_params
	 *
	 * @param array $params
	 *
	 * @return array $params
	 */
	public function filter_params( $params ) {

		$params = apply_filters( 'automator_api_call', $params );

		if ( ! empty( $params['integration'] ) ) {
			$params = apply_filters( 'automator_' . $params['integration'] . '_api_call', $params );

			if ( ! empty( $params['body']['action'] ) ) {
				$params = apply_filters( 'automator_' . $params['integration'] . '_' . $params['body']['action'] . '_api_call', $params );
			}
		}

		return $params;
	}

	/**
	 * api_call
	 *
	 * @param string $endpoint
	 * @param array $body
	 *
	 * @return array
	 */
	public static function api_call( $params ) {

		if ( true === AUTOMATOR_DISABLE_APP_INTEGRATION_REQUESTS ) {
			throw new Exception( 'App integrations have been disabled in wp-config.php.', 500 );
		}

		$api = self::get_instance();

		if ( null !== self::$mock_response ) {
			return self::$mock_response;
		}

		if ( empty( $params['endpoint'] ) ) {
			throw new Exception( 'Endpoint is required', 500 );
		}

		if ( empty( $params['body'] ) ) {
			throw new Exception( 'Request body is required', 500 );
		}

		$params = $api->add_endpoint_parts( $params );

		$params['method']             = 'POST';
		$params['url']                = self::$url . $params['endpoint'];
		$params['body']['plugin_ver'] = AUTOMATOR_PLUGIN_VERSION;

		$params = $api->filter_params( $params );

		$response = self::call( $params );

		$code          = wp_remote_retrieve_response_code( $response );
		$response_body = $api->get_response_body( $response, $code );

		$api->maybe_throw_exception( $response_body, $code );

		if ( ! isset( $response_body['statusCode'] ) ) {
			throw new Exception( 'Unrecognized API response', 500 );
		}

		return $response_body;
	}

	/**
	 * Method maybe_throw_exception
	 *
	 * @param array $response_body The response body.
	 * @param integer $code The HTTP Status code.
	 *
	 * @return void
	 * @throws Exception If there is an error with the response.
	 */
	private function maybe_throw_exception( $response_body = array(), $code = 200 ) {

		if ( ! is_array( $response_body ) ) {
			automator_log( var_export( $response_body, true ), 'Invalid API response: ' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
			throw new Exception( 'Invalid API response', 500 );
		}

		// Handle zero credits from client with upgrade to link.
		if ( 402 === $code && false !== strpos( $response_body['error']['description'], 'Upgrade to Uncanny Automator Pro' ) ) {
			throw new Exception( 'Credit required for action/trigger. Current credits: 0. {{automator_upgrade_link}}.', 402 );
		}

		if ( isset( $response_body['error'] ) && isset( $response_body['error']['description'] ) ) {
			$error = $response_body['error']['description'];
			automator_log( $error, 'api_call returned an error: ' );
			throw new Exception( esc_html( $error ), absint( $response_body['statusCode'] ) );
		}

		// Handle response body that has [data][error][message] (e.g. Instagram user media publish limit exceeded).
		if ( isset( $response_body['data']['error'] ) && isset( $response_body['data']['error']['message'] ) ) {
			throw new Exception( 'API has responded with an error message: ' . esc_html( $response_body['data']['error']['message'] ), absint( $response_body['statusCode'] ) );
		}
	}

	/**
	 * call
	 *
	 * @param string $method
	 * @param string $url
	 * @param array $body
	 * @param array $action
	 *
	 * @return mixed $response
	 */
	public static function call( $params ) {

		$api = self::get_instance();

		if ( empty( $params['method'] ) ) {
			throw new Exception( 'Request method is required', 500 );
		}

		if ( empty( $params['url'] ) ) {
			throw new Exception( 'URL is required', 500 );
		}

		$request = array();

		$request = $api->maybe_add_optional_params( $request, $params );

		$request = apply_filters( 'automator_call', $request, $params );

		$time_before = microtime( true );

		self::$last_response = wp_remote_request(
			$params['url'],
			$request
		);

		self::$last_response = apply_filters( 'automator_api_last_response', self::$last_response, $request, $params );

		do_action( 'automator_api_response', self::$last_response, $request, $params );

		$time_spent = round( ( microtime( true ) - $time_before ) * 1000 );

		$params['time_spent'] = $time_spent;

		$api_log_id = $api->maybe_log_action( $params, $request, self::$last_response );

		if ( is_wp_error( self::$last_response ) ) {
			throw new Exception( esc_html( 'WordPress was not able to make a request: ' . self::$last_response->get_error_message() ), 500 );
		}

		self::$last_response['api_log_id'] = $api_log_id;

		return self::$last_response;
	}


	/**
	 * @param $request
	 * @param $params
	 *
	 * @return mixed
	 */
	public function maybe_add_optional_params( $request, $params ) {

		$optional_params = array(
			'method',
			'body',
			'timeout',
			'redirection',
			'httpversion',
			'user-agent',
			'reject_unsafe_urls',
			'blocking',
			'headers',
			'cookies',
			'compress',
			'decompress',
			'sslverify',
			'sslcertificates',
			'stream',
			'filename',
			'limit_response_size',
		);

		foreach ( $optional_params as $optional_param ) {
			if ( isset( $params[ $optional_param ] ) ) {
				$request[ $optional_param ] = $params[ $optional_param ];
			}
		}

		return $request;
	}

	/**
	 * get_license
	 *
	 * @return mixed false||array
	 */
	public static function get_license() {

		$cached_license = get_transient( 'automator_api_license' );

		$has_failed = false !== get_transient( self::TRANSIENT_LICENSE_CHECK_FAILED );

		// Early bail if failing.
		if ( true === $has_failed ) {
			return false;
		}

		// Serve the cached license if its there.
		if ( false !== $cached_license ) {
			return $cached_license;
		}

		$params = array(
			'endpoint' => 'v2/credits',
			'body'     => array(
				'action' => 'get_credits',
			),
		);

		try {

			// Fail early with a clear message if no license key is configured.
			// Without this check, the API call proceeds without auth headers
			// and returns a cryptic "missing required headers" error.
			if ( ! self::get_license_key() ) {
				throw new Exception( 'Invalid license key.' );
			}

			$response = self::api_call( $params );

			$license = $response['data'];

			self::$license = $license;

			// Save the license.
			set_transient( 'automator_api_license', $license, self::$transient_api_license_expires );

			// Removes any failed license checks.
			delete_transient( self::TRANSIENT_LICENSE_CHECK_FAILED );

			// do action?

			return $license;

		} catch ( Exception $e ) {

			$error_message = 'Unable to fetch the license: ' . $e->getMessage();

			set_transient( self::TRANSIENT_LICENSE_CHECK_FAILED, $error_message );

			throw new Exception( esc_html( $error_message ) );

		}
	}

	/**
	 * has_valid_license
	 *
	 * @return mixed false||array
	 */
	public static function has_valid_license() {

		$license = self::get_license();

		if ( ! isset( $license['license'] ) || 'valid' !== $license['license'] ) {
			throw new Exception( esc_html__( 'License is not valid', 'uncanny-automator' ) );
		}

		return $license;
	}

	/**
	 * has_credits
	 *
	 * @return bool
	 */
	public static function has_credits() {

		$license = self::has_valid_license();

		if ( 'Uncanny Automator Pro' === $license['item_name'] ) {
			return true;
		}

		if ( intval( $license['paid_usage_count'] ) >= intval( $license['usage_limit'] ) ) {
			throw new Exception( esc_html__( 'Not enough credits', 'uncanny-automator' ) );
		}

		return true;
	}

	/**
	 * charge_credit
	 *
	 * @return mixed false||array
	 */
	public function charge_usage( $trigger_data = null ) {

		$license = array();

		self::has_credits();

		$params = array(
			'endpoint' => 'v2/credits',
			'body'     => array(
				'action' => 'reduce_credits',
			),
		);

		$license = self::api_call( $params );

		set_transient( 'automator_api_license', $license['data'], self::$transient_api_license_expires );

		return $license;
	}

	/**
	 * create_payload
	 *
	 * @param mixed $body
	 * @param mixed $code
	 *
	 * @return void
	 */
	public function create_payload( $body = null, $code = null, $error = null ) {

		$payload = array(
			'data'       => $body,
			'statusCode' => $code,
		);

		if ( null !== $error ) {
			$payload['error'] = array( 'description' => $error );
		}

		return $payload;
	}

	/**
	 * Will log an action in the action meta.
	 */
	public function maybe_log_action( $params, $request, $response ) {

		if ( ! isset( $params['action'] ) ) {
			return;
		}

		$credits = $this->get_response_credits( $response );

		$log = array(
			'type'          => 'action',
			'recipe_log_id' => $params['action']['recipe_log_id'],
			'item_log_id'   => isset( $params['action']['action_log_id'] ) ? $params['action']['action_log_id'] : '',
			'endpoint'      => $params['endpoint'],
			'params'        => maybe_serialize( $params ),
			'request'       => maybe_serialize( $request ),
			'response'      => maybe_serialize( apply_filters( 'automator_log_api_responses', false, $response ) ),
			'balance'       => isset( $credits['balance'] ) ? $credits['balance'] : null,
			'price'         => isset( $credits['price'] ) ? $credits['price'] : null,
			'status'        => $this->get_response_code( $response ),
			'time_spent'    => isset( $params['time_spent'] ) ? $params['time_spent'] : 0,
		);

		return $this->add_log( $log );
	}

	/**
	 * @param $log
	 *
	 * @return bool|int
	 */
	public function add_log( $log ) {
		return Automator()->db->api->add( $log );
	}

	/**
	 * @param $response
	 *
	 * @return mixed|null
	 */
	public function get_response_credits( $response ) {

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $response_body['credits'] ) ) {
			return null;
		}

		return $response_body['credits'];
	}

	/**
	 * @param $response
	 *
	 * @return int|mixed|string
	 */
	public function get_response_code( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response->get_error_code();
		}

		return wp_remote_retrieve_response_code( $response );
	}

	/**
	 * Method get_response_body
	 *
	 * @param array $response
	 * @param int $code
	 *
	 * @return array
	 */
	public function get_response_body( $response, $code ) {

		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		//Some endpoints like Mailchimp send a NULL as the reponse with a code in case of success.
		if ( empty( $response_body ) && ! empty( $code ) ) {
			return $this->create_payload( null, $code );
		}

		return $response_body;
	}

	/**
	 * @param $process_further
	 * @param $args
	 * @param $trigger
	 *
	 * @return false|mixed
	 */
	public function maybe_log_trigger( $process_further, $args, $trigger ) {

		if ( ! $trigger->get_uses_api() ) {
			return $process_further;
		}

		$recipe_log_id  = $args['entry_args']['recipe_log_id'] ?? null;
		$trigger_log_id = $args['entry_args']['trigger_log_id'] ?? null;

		$log = array(
			'type'          => 'trigger',
			'recipe_log_id' => $recipe_log_id,
			'item_log_id'   => $trigger_log_id,
			'params'        => $args['trigger_args'],
		);

		try {
			$api_response   = $this->charge_usage();
			$credits        = $api_response['credits'];
			$log['balance'] = isset( $credits['balance'] ) ? $credits['balance'] : null;
			$log['price']   = isset( $credits['price'] ) ? $credits['price'] : null;
		} catch ( Exception $e ) {
			$log['response'] = $e->getMessage();
			$process_further = false;
		}

		$this->add_log( $log );

		return $process_further;
	}

	/**
	 * add_trigger_meta
	 *
	 * @param array $params
	 * @param array $log
	 *
	 * @return void
	 */
	public function add_trigger_meta( $args, $log ) {

		$log_entry = $args['trigger_entry'];

		$trigger_id     = $log_entry['trigger_id'];
		$trigger_log_id = $log_entry['trigger_log_id'];
		$run_number     = $log_entry['run_number'];

		$args = array(
			'user_id'    => $log_entry['user_id'],
			'meta_key'   => 'api_log',
			'meta_value' => maybe_serialize( $log ),
			'run_time'   => $run_number,
		);

		Automator()->db->trigger->add_meta( $trigger_id, $trigger_log_id, $run_number, $args );
	}

	/**
	 * is_automator_connected
	 *
	 * @return void
	 */
	public static function is_automator_connected( $force_refresh = false ) {

		// Limit to only one call per session
		if ( null !== self::$license ) {
			return self::$license;
		}

		if ( $force_refresh ) {
			delete_transient( 'automator_api_license' );
			delete_transient( self::TRANSIENT_LICENSE_CHECK_FAILED );
		}

		$license_key = self::get_license_key();

		if ( false === $license_key ) {
			self::set_connection_error_message( 'Unable to fetch the license key.' );

			return false;
		}

		try {
			return self::get_license();
		} catch ( Exception $e ) {
			automator_log( $e->getMessage() );
			self::set_connection_error_message( 'API error exception: ' . $e->getCode() . ' ' . $e->getMessage() );

			return false;
		}

		self::set_connection_error_message( 'An error has occured while connecting. Please try again later.' );

		return false;
	}

	/**
	 * Sets an error message for one minute that can be shown in the front-end.
	 *
	 * @param string $error_message
	 *
	 * @return void
	 */
	public static function set_connection_error_message( $error_message ) {

		set_transient( 'automator_setup_wizard_error', $error_message, MINUTE_IN_SECONDS );
	}
}

Api_Server::get_instance();
