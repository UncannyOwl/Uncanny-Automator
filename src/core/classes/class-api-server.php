<?php

namespace Uncanny_Automator;

//http_request_args

/**
 * Class Api.
 *
 * @package Uncanny_Automator
 */
class Api_Server {

	public static $url;

	public static $mock_response = null;

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {

		add_filter( 'http_request_args', array( $this, 'add_api_headers' ), 10, 2 );

		self::$url = apply_filters( 'automator_api_url', AUTOMATOR_API_URL );

	}

	/**
	 * add_api_headers
	 *
	 * @param array $args
	 * @param string $request_url
	 *
	 * @return array
	 */
	public function add_api_headers( $args, $request_url ) {

		$license_key = self::get_license_key();

		if ( ! $license_key ) {
			return $args;
		}

		// If the request URL starts with the Automator API url
		if ( substr( $request_url, 0, strlen( self::$url ) ) === self::$url ) {
			$args['headers']['license-key'] = $license_key;
			$args['headers']['site-name']   = self::get_site_name();
			$args['headers']['item-name']   = self::get_item_name();
		}

		return $args;
	}

	/**
	 * get_license_type
	 *
	 * @return string
	 */
	public static function get_license_type() {
		if ( defined( 'AUTOMATOR_PRO_FILE' ) && 'valid' === get_option( 'uap_automator_pro_license_status' ) ) {
			return 'pro';
		} elseif ( 'valid' === get_option( 'uap_automator_free_license_status' ) ) {
			return 'free';
		}

		return false;
	}

	/**
	 * get_license_key
	 *
	 * @return string
	 */
	public static function get_license_key() {
		$license_type = self::get_license_type();

		return get_option( 'uap_automator_' . $license_type . '_license_key' );
	}

	/**
	 * get_item_name
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
	 * get_site_name
	 *
	 * @return string
	 */
	public static function get_site_name() {
		return preg_replace( '(^https?://)', '', get_home_url() );
	}
	
	/**
	 * api_call
	 *
	 * @param  string $endpoint
	 * @param  array $body
	 * @return void
	 */
	public static function api_call( $endpoint, $body ) {

		if ( null !== self::$mock_response ) {
			return self::$mock_response;
		}

		$response = wp_remote_post(
			self::$url . $endpoint,
			array(
				'body'      => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $response_body ) ) {
			automator_log( var_export( $response_body, true ), 'Invalid API response: ' );
			throw new \Exception( 'Invalid API response' );
		}

		if ( isset( $response_body['error'] ) && isset( $response_body['error']['description'] ) ) {
			$error = $response_body['error']['description'];
			automator_log( $error, 'api_call returned an error: ' );
			throw new \Exception( $error, $response_body['statusCode'] );
		}

		if ( 200 === $response_body['statusCode'] && isset( $response_body['data'] ) ) {
			return $response_body['data'];
		} 

		automator_log( var_export( $response_body, true ), 'api_call returned an error: ' );
		throw new \Exception( var_export( $response_body, true ) );
	}
	
	/**
	 * get_license
	 *
	 * @return mixed false||array
	 */
	public static function get_license() {

		$cached_license = get_transient( 'automator_api_license' );

		if ( false !== $cached_license ) {
			return $cached_license;
		}

		$request_body = array(
			'action'  => 'get_credits'
		);

		$license = self::api_call( 'v2/credits', $request_body );

		if ( false !== $license) {
			set_transient( 'automator_api_license', $license, HOUR_IN_SECONDS );
		}

		return $license;
	}
	
	/**
	 * has_valid_license
	 *
	 * @return mixed false||array
	 */
	public static function has_valid_license() {

		$license = self::get_license();
		
		if ( ! $license ) {
			return false;
		}
		
		if ( ! isset( $license['license'] ) || 'valid' !== $license['license'] ) {
			return false;
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
		
		if ( ! $license ) {
			return false;
		}

		if ( 'Uncanny Automator Pro' === $license['item_name'] ) {
			return true;
		}

		if ( intval( $license['paid_usage_count'] ) >= intval( $license['usage_limit'] ) ) {
			return false;
		}

		return true;

	}
	
	/**
	 * charge_credit
	 *
	 * @return mixed false||array 
	 */
	public static function charge_credit() {

		if ( ! self::has_credits() ) {
			return false;
		}

		$body = array(
			'action'  => 'reduce_credits',
		);

		$license = self::api_call( 'v2/credits', $body );

		return $license;

	}
}
