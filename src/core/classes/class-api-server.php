<?php

namespace Uncanny_Automator;

use Exception;
use Uncanny_Automator\App\Infrastructure\Api_Log\Api_Log_Compressor;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Dispatch\License_Key_Requirement;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Request;
use Uncanny_Automator\App\Infrastructure\License\Payment_Required_Message;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

		// The license headers of a platform request are Platform_Headers_Injector's
		// and its default timeout Platform_Default_Timeout's, both registered by
		// Application_Bootstrap. Only the trigger charge is still registered here.
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
		self::get_license_manager()->reset_cache();
	}

	/**
	 * get_instance
	 *
	 * @return Api_Server
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::set_instance( new Api_Server() );
		}

		return self::$instance;
	}

	/**
	 * The license this site speaks with: 'pro', 'free', or false when there is none.
	 *
	 * Delegates to License_Manager, which owns the one rule for which license
	 * is in use. A second copy of it here once decided the license headers of
	 * every request while the registration signed with the manager's answer.
	 * `false` (not '') is what callers compare against.
	 *
	 * @deprecated Use License_Manager::get_type().
	 * @return string|false
	 */
	public static function get_license_type() {
		$type = self::get_license_manager()->get_type();

		return '' === $type ? false : $type;
	}

	/**
	 * The key of the license in use, or false when there is none.
	 *
	 * @deprecated Use License_Manager::get_key().
	 * @return string|false
	 */
	public static function get_license_key() {
		$key = self::get_license_manager()->get_key();

		return '' === $key ? false : $key;
	}

	/**
	 * The item name of the license in use, or '' when there is none.
	 *
	 * @deprecated Use License_Manager::get_item_name().
	 * @return string
	 */
	public static function get_item_name() {
		return self::get_license_manager()->get_item_name();
	}

	/**
	 * The plan of the license in use ('' without a license).
	 *
	 * @deprecated Use License_Manager::get_plan().
	 * @return string
	 */
	public static function get_license_plan() {
		return self::get_license_manager()->get_plan();
	}

	/**
	 * The host the platform knows this site by: the home URL without its scheme.
	 * For sites like https://your-site:8888/
	 *
	 * @deprecated Use License_Manager::get_site_name().
	 * @return string
	 */
	public static function get_site_name() {
		return self::get_license_manager()->get_site_name();
	}

	/**
	 * Get formatted license renewal/expiry date for MCP payload.
	 *
	 * @deprecated Use License_Manager::get_renewal_date().
	 * @return string Formatted date like "January 1, 2026" or empty string if lifetime/unavailable.
	 */
	public static function get_renewal_date_formatted(): string {
		return self::get_license_manager()->get_renewal_date();
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
	 * @deprecated Use Api_Client::send() directly.
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

		// Forwarded: a site with no license key sends nothing. The params are filtered already.
		( new License_Key_Requirement() )->enforce( Request::from_filtered_legacy_params( $params ), (string) $params['url'] );

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

		// Handle zero credits from client with upgrade to link. The rule is
		// shared with /v3, which shows the same thing for the same answer.
		if ( 402 === $code && Payment_Required_Message::is_credit_limit( (string) ( $response_body['error']['description'] ?? '' ) ) ) {
			throw new Exception( esc_html( Payment_Required_Message::CREDIT_LIMIT ), 402 );
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

		if ( 500 <= (int) $code ) {
			throw new Exception( sprintf( 'The app integration request failed (HTTP %d). Check the affected item in the connected app before running this action again. If the failure continues, contact Automator support with this HTTP code.', (int) $code ), (int) $code );
		}
	}

	/**
	 * call
	 *
	 * @deprecated Use Api_Client::send() directly.
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

		// Retry only connection-stage failures; an HTTP status cannot establish
		// that an action was not executed.
		self::$last_response = \Uncanny_Automator\App\Infrastructure\Http\Transient_Retry::request(
			(string) $params['url'],
			$request
		);

		self::$last_response = apply_filters( 'automator_api_last_response', self::$last_response, $request, $params );

		do_action( 'automator_api_response', self::$last_response, $request, $params );

		$time_spent = round( ( microtime( true ) - $time_before ) * 1000 );

		$params['time_spent'] = $time_spent;

		$api_log_id = $api->maybe_log_action( $params, $request, self::$last_response );

		if ( is_wp_error( self::$last_response ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text API error; escape only at HTML rendering boundaries.
			throw new Exception( \Uncanny_Automator\App\Infrastructure\Http\Transport_Failure::message( self::$last_response ), 500 );
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
	 * Delegates to License_Manager, the one place that reads the license
	 * cache, fetches v2/credits and decides what a failed fetch costs (a
	 * backoff for a temporary failure, the one-hour latch for a verdict).
	 * This facade keeps no failure state of its own: a second writer of the
	 * latch is how a paid license was once stopped for an hour by a blip.
	 *
	 * @deprecated Use License_Manager::get_license_data().
	 * @return array|false The license data, or false when it is unavailable.
	 */
	public static function get_license() {

		$license = self::get_license_manager()->get_license_data();

		return is_array( $license ) ? $license : false;
	}

	/**
	 * has_valid_license
	 *
	 * @deprecated Use License_Manager::validate().
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
	 * The credit rule (a paid plan has no cap, the free plan stops at its
	 * limit) is Credit_Manager's. The license is checked first, here, so an
	 * unavailable one keeps the message this method has always thrown.
	 *
	 * @deprecated Use Credit_Manager::has_credits().
	 * @return bool
	 */
	public static function has_credits() {

		self::has_valid_license();

		return self::get_credit_manager()->has_credits();
	}

	/**
	 * charge_credit
	 *
	 * Credit_Manager checks the credits and charges them. The only thing kept
	 * here is how this method has always reported the free account's
	 * credit-limit refusal: as the upgrade message, not the platform's text.
	 *
	 * @deprecated Use Credit_Manager::charge().
	 * @return array The platform's answer: statusCode, data, credits.
	 */
	public function charge_usage( $trigger_data = null ) {

		self::has_credits();

		try {
			return self::get_credit_manager()->charge( is_array( $trigger_data ) ? $trigger_data : null );
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The platform's own message, as every caller has always received it; the action log escapes on output.
			throw self::credit_limit_as_upgrade_message( $e );
		}
	}

	/**
	 * The exception a /v2 call has always thrown for a refusal: the free
	 * account's credit-limit 402 becomes the upgrade message, every other
	 * failure is untouched. The words that mark the refusal are
	 * Payment_Required_Message's, shared with /v3.
	 *
	 * @param Exception $e The failure.
	 *
	 * @return Exception
	 */
	private static function credit_limit_as_upgrade_message( Exception $e ): Exception {

		if ( 402 === (int) $e->getCode() && Payment_Required_Message::is_credit_limit( $e->getMessage() ) ) {
			return new Exception( esc_html( Payment_Required_Message::CREDIT_LIMIT ), 402 );
		}

		return $e;
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

		// Bare-minimum replay payload — endpoint / method / body /
		// action are everything Api_Server::api_call needs to refire
		// the call sync from `Recipe_Post_Rest_Api::resend_api_request()`.
		// Headers (license-key, site-name, etc.) are rebuilt fresh
		// from current site state at resend time, so storing them is
		// pure bloat. `url` is a function of base + endpoint and gets
		// recomputed too. `time_spent` lives in its own column. Net
		// effect on uap_api_log row size: ~10x smaller for typical
		// rows after gzip compression.
		$compact_params = array(
			'endpoint' => isset( $params['endpoint'] ) ? $params['endpoint'] : '',
			'method'   => isset( $params['method'] ) ? $params['method'] : 'POST',
			'body'     => isset( $params['body'] ) && is_array( $params['body'] ) ? $params['body'] : array(),
			'action'   => isset( $params['action'] ) ? $params['action'] : null,
		);

		$log = array(
			'type'          => 'action',
			'recipe_log_id' => $params['action']['recipe_log_id'],
			'item_log_id'   => isset( $params['action']['action_log_id'] ) ? $params['action']['action_log_id'] : '',
			'endpoint'      => $params['endpoint'],
			'params'        => Api_Log_Compressor::encode( $compact_params ),
			'request'       => '', // headers/timeout no longer persisted; rebuilt at resend.
			'response'      => Api_Log_Compressor::encode( apply_filters( 'automator_log_api_responses', false, $response ) ),
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
			// No params: nothing reads them for a trigger, and webhook payloads may hold personal data and webhook keys.
			'params'        => '',
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
	 * A forced check reaches the platform once per request; the license it
	 * fetched answers the rest of them. Everything else is License_Manager's.
	 *
	 * @deprecated Use License_Manager::is_connected().
	 * @return array|false The license data, or false when it is unavailable.
	 */
	public static function is_automator_connected( $force_refresh = false ) {

		// Limit to only one call per session.
		if ( null !== self::$license ) {
			return self::$license;
		}

		$manager = self::get_license_manager();

		if ( '' === $manager->get_key() ) {
			self::set_connection_error_message( 'Unable to fetch the license key.' );

			return false;
		}

		$license = $manager->get_license_data( (bool) $force_refresh );

		if ( is_array( $license ) ) {
			if ( $force_refresh ) {
				self::$license = $license;
			}

			return $license;
		}

		$reason = get_transient( self::TRANSIENT_LICENSE_CHECK_FAILED );

		self::set_connection_error_message(
			is_string( $reason ) && '' !== $reason
				? 'API error exception: ' . $reason
				: 'An error has occured while connecting. Please try again later.'
		);

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

	/**
	 * Get the API client service.
	 *
	 * @return \Uncanny_Automator\App\Infrastructure\Automator_Platform\Client
	 */
	public static function get_api_client() {
		return \Uncanny_Automator\App\Infrastructure\automator_api_client();
	}

	/**
	 * Get the license manager service.
	 *
	 * @return \Uncanny_Automator\App\Infrastructure\License\License_Manager
	 */
	public static function get_license_manager() {
		return \Uncanny_Automator\App\Infrastructure\automator_license_manager();
	}

	/**
	 * Get the credit manager service.
	 *
	 * @return \Uncanny_Automator\App\Infrastructure\License\Credit_Manager
	 */
	public static function get_credit_manager() {
		return \Uncanny_Automator\App\Infrastructure\automator_credit_manager();
	}
}

Api_Server::get_instance();
