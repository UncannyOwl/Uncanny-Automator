<?php

namespace Uncanny_Automator;

//http_request_args

/**
 * Class Api.
 * @package Uncanny_Automator
 */
class Api_Server {

	public static $url;


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
		$licence_key  = get_option( 'uap_automator_' . $license_type . '_license_key' );

		return $licence_key;
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
}
