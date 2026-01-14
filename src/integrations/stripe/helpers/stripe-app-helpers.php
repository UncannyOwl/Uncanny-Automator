<?php

namespace Uncanny_Automator\Integrations\Stripe;

use Uncanny_Automator\App_Integrations\App_Helpers;


class Stripe_App_Helpers extends App_Helpers {

	/**
	 * Token option name ( credentials)
	 *
	 * @var string
	 */
	const TOKEN_OPTION = 'automator_stripe_token';

	/**
	 * Tokens class
	 *
	 * @var Stripe_Tokens
	 */
	public $tokens;

	////////////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////////////

	/**
	 * Set additional properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Override the default credentials option name.
		$this->set_credentials_option_name( self::TOKEN_OPTION );
		// Override the default account option name.
		$this->set_account_option_name( 'automator_stripe_user' );

		// Initialize the tokens class.
		$this->tokens = new Stripe_Tokens( $this );
	}

	/**
	 * validate_credentials
	 *
	 * @return array
	 */
	public function validate_credentials( $credentials, $args = array() ) {

		if ( empty( $credentials['stripe_user_id'] ) || empty( $credentials['vault_signature'] ) ) {
			throw new \Exception( esc_html_x( 'Stripe is not connected', 'Stripe', 'uncanny-automator' ) );
		}

		return $credentials;
	}

	/**
	 * prepare_credentials_for_storage
	 *
	 * @param  array $credentials
	 * @return array
	 */
	public function prepare_credentials_for_storage( $credentials ) {

		// Check existing credentials.
		$existing_credentials = automator_get_option( self::TOKEN_OPTION, array() );
		$existing_credentials = is_array( $existing_credentials )
			? $existing_credentials
			: array();

		// Merge the new credentials with the existing ones.
		$prepared_credentials = array_merge( $existing_credentials, $credentials );

		return $prepared_credentials;
	}

	////////////////////////////////////////////////////////////
	// Integration specific methods
	////////////////////////////////////////////////////////////

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
	 * @param  array $array_to_process
	 * @return array
	 */
	public function unset_empty_recursively( $array_to_process ) {

		foreach ( $array_to_process as $key => $value ) {

			if ( is_array( $value ) ) {

				$cleaned_array = $this->unset_empty_recursively( $value );

				// If there are no elements left in the array after cleaning, unset it
				if ( empty( $cleaned_array ) ) {
					unset( $array_to_process[ $key ] );
					continue;
				}

				$array_to_process[ $key ] = $cleaned_array;
			}

			if ( '' === $value ) {
				unset( $array_to_process[ $key ] );
			}
		}

		return $array_to_process;
	}

	/**
	 * Convert an array with dot notation keys to a multidimensional array
	 *
	 * @param array $array_to_process
	 *
	 * @return array
	 */
	public function dots_to_array( $array_to_process ) {

		$new_array = array();

		foreach ( $array_to_process as $key => $value ) {

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

		return wp_date(
			sprintf( '%s %s', $date_format, $time_format ),
			$timestamp
		);
	}

	/**
	 * explode_fields
	 *
	 * Explode comma separated values strings into arrays in specific fields
	 *
	 * @param  array $array_to_process_to_process
	 * @param  array $fields_to_explode
	 * @return array
	 */
	public function explode_fields( $array_to_process, $fields_to_explode ) {

		foreach ( $array_to_process as $field => $value ) {

			if ( ! in_array( $field, $fields_to_explode, true ) ) {
				continue;
			}

			$value = str_replace( ' ', '', $value );

			$array_to_process[ $field ] = explode( ',', $value );
		}

		return $array_to_process;
	}

	/**
	 * parse_metadata_fields
	 *
	 * Parse metadata fields from key:value format to associative arrays
	 * Handles fields ending with .metadata in dot notation
	 *
	 * @param  array $array_to_process
	 * @return array
	 */
	public function parse_metadata_fields( $array_to_process ) {

		foreach ( $array_to_process as $field => $value ) {

			// Check if the field ends with .metadata
			if ( '.metadata' !== substr( $field, -9 ) ) {
				continue;
			}

			// Skip if value is empty or not a string
			if ( empty( $value ) || ! is_string( $value ) ) {
				continue;
			}

			$metadata = array();

			// Split by comma for multiple metadata entries (e.g., "key1:value1,key2:value2")
			$pairs = array_map( 'trim', explode( ',', $value ) );

			foreach ( $pairs as $pair ) {
				// Split by colon to get key and value
				$parts = array_map( 'trim', explode( ':', $pair, 2 ) );

				// Only process if we have both key and value
				if ( 2 === count( $parts ) && '' !== $parts[0] ) {
					$metadata[ $parts[0] ] = $parts[1];
				}
			}

			// Replace the string value with the parsed metadata array
			if ( ! empty( $metadata ) ) {
				$array_to_process[ $field ] = $metadata;
			}
		}

		return $array_to_process;
	}

	/**
	 * Check if a subscription contains a specific price ID.
	 *
	 * This method is future-proof, checking both modern 'price' and legacy 'plan' objects.
	 * Supports multi-item subscriptions by checking all line items.
	 *
	 * @param array  $subscription The subscription object from Stripe webhook.
	 * @param string $price_id     The price ID to match, or '-1' for any price.
	 *
	 * @return bool True if the subscription contains the price, false otherwise.
	 */
	public function subscription_contains_price( $subscription, $price_id ) {

		// If "Any" is selected, match any subscription
		if ( '-1' === $price_id ) {
			return true;
		}

		// Method 1: Check modern items.data array (preferred, supports multiple items)
		if ( ! empty( $subscription['items']['data'] ) && is_array( $subscription['items']['data'] ) ) {
			foreach ( $subscription['items']['data'] as $item ) {
				// Try modern 'price' object first
				if ( ! empty( $item['price']['id'] ) && $price_id === $item['price']['id'] ) {
					return true;
				}
				// Fallback to legacy 'plan' object
				if ( ! empty( $item['plan']['id'] ) && $price_id === $item['plan']['id'] ) {
					return true;
				}
			}
		}

		// Method 2: Check legacy subscription-level 'plan' (for backward compatibility)
		if ( ! empty( $subscription['plan']['id'] ) && $price_id === $subscription['plan']['id'] ) {
			return true;
		}

		return false;
	}

	/**
	 * Extract price ID from invoice line item.
	 *
	 * Handles multiple Stripe data structures:
	 * - Modern pricing.price_details (invoice line items)
	 * - Expanded price object (checkout sessions)
	 * - Legacy plan object
	 *
	 * @param array $line_item Invoice or checkout line item from Stripe webhook.
	 *
	 * @return string|null Price ID or null if not found.
	 */
	public function get_line_item_price_id( $line_item ) {

		// Method 1: Modern pricing.price_details (invoice line items)
		if ( ! empty( $line_item['pricing']['price_details']['price'] ) ) {
			return $line_item['pricing']['price_details']['price'];
		}

		// Method 2: Expanded price object (checkout sessions)
		if ( ! empty( $line_item['price']['id'] ) ) {
			return $line_item['price']['id'];
		}

		// Method 3: Legacy plan object
		if ( ! empty( $line_item['plan']['id'] ) ) {
			return $line_item['plan']['id'];
		}

		return null;
	}

	/**
	 * Extract product ID from invoice line item.
	 *
	 * Handles multiple Stripe data structures:
	 * - Modern pricing.price_details (invoice line items)
	 * - Expanded price.product object (checkout sessions)
	 * - Legacy plan.product object
	 *
	 * @param array $line_item Invoice or checkout line item from Stripe webhook.
	 *
	 * @return string|null Product ID or null if not found.
	 */
	public function get_line_item_product_id( $line_item ) {

		// Method 1: Modern pricing.price_details (invoice line items)
		if ( ! empty( $line_item['pricing']['price_details']['product'] ) ) {
			return $line_item['pricing']['price_details']['product'];
		}

		// Method 2: Expanded price object (checkout sessions)
		if ( ! empty( $line_item['price']['product'] ) ) {
			// Product can be expanded object or ID string
			if ( is_array( $line_item['price']['product'] ) ) {
				return $line_item['price']['product']['id'] ?? null;
			}
			return $line_item['price']['product'];
		}

		// Method 3: Legacy plan object
		if ( ! empty( $line_item['plan']['product'] ) ) {
			// Product can be expanded object or ID string
			if ( is_array( $line_item['plan']['product'] ) ) {
				return $line_item['plan']['product']['id'] ?? null;
			}
			return $line_item['plan']['product'];
		}

		return null;
	}
}
