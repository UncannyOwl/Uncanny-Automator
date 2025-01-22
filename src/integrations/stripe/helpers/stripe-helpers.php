<?php

namespace Uncanny_Automator\Integrations\Stripe;

use Uncanny_Automator\Api_Server;

class Stripe_Helpers {

	const API_ENDPOINT = 'v2/stripe';

	const TOKEN_OPTION = 'automator_stripe_token';

	const USER_OPTION = 'automator_stripe_user';

	public $api;
	public $webhook;
	public $tokens;

	public function __construct() {
		$this->api     = new Stripe_Api( $this );
		$this->webhook = new Stripe_Webhook( $this );
		$this->tokens  = new Stripe_Tokens( $this );
	}

	/**
	 * get_credentials
	 *
	 * @return array
	 */
	public function get_credentials() {

		$client = automator_get_option( self::TOKEN_OPTION, array() );

		if ( empty( $client['stripe_user_id'] ) || empty( $client['vault_signature'] ) ) {
			throw new \Exception( 'Stripe is not connected' );
		}

		return $client;
	}

	/**
	 * get_mode
	 *
	 * @return string
	 */
	public function get_mode() {

		try {
			$client = $this->get_credentials();
			return $client['livemode'] ? 'live' : 'test';
		} catch ( \Exception $e ) {
			return 'live';
		}
	}

	/**
	 * is_connected
	 *
	 * @return bool
	 */
	public function is_connected() {

		try {
			$this->get_credentials();
			return true;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Integration status.
	 *
	 * @return string
	 */
	public function integration_status() {
		return $this->is_connected() ? 'success' : '';
	}

	/**
	 * store_token
	 *
	 * @param mixed $new_token
	 *
	 * @return int
	 */
	public function store_token( $new_token ) {

		$existing_token = automator_get_option( self::TOKEN_OPTION, array() );

		$updated_token = array_merge( $existing_token, $new_token );

		automator_update_option( self::TOKEN_OPTION, $updated_token );

		return 1;
	}

	/**
	 * generate_price_name
	 *
	 * @param  array $price
	 * @return string
	 */
	public function generate_price_name( $price ) {

		$price_name = '';

		if ( ! empty( $price['nickname'] ) ) {
			$price_name .= $price['nickname'] . ' (';
		}

		if ( ! empty( $price['unit_amount'] ) ) {
			$price_name .= $price['unit_amount'] / 100 . ' ' . $price['currency'];
		}

		if ( ! empty( $price['recurring'] ) ) {
			$price_name .= ' per ' . $price['recurring']['interval'];
		}

		if ( ! empty( $price['nickname'] ) ) {
			$price_name .= ')';
		}

		return apply_filters( 'automator_stripe_price_name', $price_name, $price );
	}

	/**
	 * unset_empty_recursively
	 *
	 * @param  array $array
	 * @return array
	 */
	public function unset_empty_recursively( $array ) {

		foreach ( $array as $key => $value ) {

			if ( is_array( $value ) ) {

				$cleaned_array = $this->unset_empty_recursively( $value );

				// If there are no elements left in the array after cleaning, unset it
				if ( empty( $cleaned_array ) ) {
					unset( $array[ $key ] );
					continue;
				}

				$array[ $key ] = $cleaned_array;
			}

			if ( '' === $value ) {
				unset( $array[ $key ] );
			}
		}

		return $array;
	}

	/**
	 * Convert an array with dot notation keys to a multidimensional array
	 *
	 * @param array $array
	 *
	 * @return array
	 */
	public function dots_to_array( $array ) {

		$new_array = array();

		foreach ( $array as $key => $value ) {

			$keys = explode( '.', $key );

			$last_key = array_pop( $keys );

			$pointer = &$new_array;

			foreach ( $keys as $key ) {

				if ( ! isset( $pointer[ $key ] ) ) {
					$pointer[ $key ] = array();
				}

				$pointer = &$pointer[ $key ];
			}

			$pointer[ $last_key ] = $value;
		}

		return $new_array;
	}

	/**
	 * disconnect
	 *
	 * @return void
	 */
	public function disconnect() {
		automator_delete_option( self::TOKEN_OPTION );
		automator_delete_option( self::USER_OPTION );
		automator_delete_option( Stripe_Settings::CONNECTION_MODE_OPTION );
	}

	/**
	 * format_amount
	 *
	 * @param  int $cents
	 * @return string
	 */
	public function format_amount( $cents ) {
		return number_format( $cents / 100, 2 );
	}

	/**
	 * format_date
	 *
	 * @param  int $timestamp
	 * @return string
	 */
	public function format_date( $timestamp ) {

		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );

		return date( $date_format . ' ' . $time_format, $timestamp );
	}
}
