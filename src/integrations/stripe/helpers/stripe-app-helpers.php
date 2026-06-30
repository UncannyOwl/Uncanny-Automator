<?php

namespace Uncanny_Automator\Integrations\Stripe;

use Uncanny_Automator\App_Integrations\App_Helpers;

/**
 * Class Stripe_App_Helpers
 *
 * @package Uncanny_Automator
 *
 * @property Stripe_Api_Caller $api
 */
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
	 * Validate that stored Stripe credentials contain the required fields.
	 *
	 * @param array $credentials The credentials to validate.
	 * @param array $args        Optional contextual arguments.
	 *
	 * @return array The validated credentials.
	 * @throws \Exception If the Stripe account is not connected (missing user id or signature).
	 */
	public function validate_credentials( $credentials, $args = array() ) {

		if ( empty( $credentials['stripe_user_id'] ) || empty( $credentials['vault_signature'] ) ) {
			throw new \Exception( esc_html_x( 'Stripe is not connected', 'Stripe', 'uncanny-automator' ) );
		}

		return $credentials;
	}

	/**
	 * Merge new credentials over any existing stored credentials before saving.
	 *
	 * @param array $credentials The new credentials to store.
	 *
	 * @return array The merged credentials.
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
	 * Get the connected account's mode.
	 *
	 * @return string 'live' or 'test' (defaults to 'live' when credentials are unavailable).
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
	 * Build the cache-key account scope: "{stripe_user_id}_{mode}".
	 *
	 * Ensures cached option lists never bleed across accounts or test/live mode.
	 *
	 * @return string
	 */
	protected function get_account_cache_suffix() {
		$account_id = '';
		$mode       = 'live';
		try {
			$credentials = $this->get_credentials();
			$account_id  = $credentials['stripe_user_id'] ?? '';
			$mode        = ! empty( $credentials['livemode'] ) ? 'live' : 'test';
		} catch ( \Exception $e ) {
			$account_id = '';
			$mode       = 'live';
		}
		return $account_id . '_' . $mode;
	}

	/**
	 * Fetch + cache formatted price options for one Stripe price type.
	 *
	 * @param string $type    'recurring' or 'one_time'.
	 * @param bool   $refresh When true, bypass the cache and re-fetch.
	 * @return array          List of { text, value } option arrays (no 'Any').
	 */
	private function get_price_options_cached( $type, $refresh ) {

		$prefix = ( 'recurring' === $type ) ? 'recurring_prices_' : 'onetime_prices_';
		$key    = $this->get_option_key( $prefix . $this->get_account_cache_suffix() );

		if ( ! $refresh ) {
			$cached = $this->get_app_option( $key );
			if ( ! $cached['refresh'] && ! empty( $cached['data'] ) ) {
				return $cached['data'];
			}
		}

		$response = $this->api->get_price_options( $type );
		$options  = $response['data']['options'] ?? array();

		if ( ! empty( $options ) ) {
			$this->save_app_option( $key, $options );
		}

		return $options;
	}

	/**
	 * Delete the cached price options for the connected account/mode.
	 *
	 * Called on disconnect so stale price lists never persist into a re-connect.
	 * Uses the same key suffix as the cache writer so the keys match exactly.
	 *
	 * @return void
	 */
	public function delete_cached_price_options() {
		$suffix = $this->get_account_cache_suffix();
		$this->delete_prefixed_app_option( 'recurring_prices_' . $suffix );
		$this->delete_prefixed_app_option( 'onetime_prices_' . $suffix );
	}

	/**
	 * Prepend the "Any" (-1) sentinel used by the price triggers.
	 *
	 * @param array $options List of { text, value } options.
	 * @return array
	 */
	private function prepend_any( $options ) {
		array_unshift(
			$options,
			array(
				'text'  => esc_html_x( 'Any', 'Stripe', 'uncanny-automator' ),
				'value' => '-1',
			)
		);
		return $options;
	}

	/**
	 * Remote_Data: recurring price options (subscription triggers).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 * @return array
	 */
	protected function remote_data_get_recurring_prices( $request ): array {
		try {
			$options = $this->get_price_options_cached( 'recurring', $request->is_refresh() );
			return $this->remote_data_success( $this->prepend_any( $options ) );
		} catch ( \Exception $e ) {
			return $this->remote_data_error( $e->getMessage() );
		}
	}

	/**
	 * Remote_Data: one-time price options (product triggers).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 * @return array
	 */
	protected function remote_data_get_onetime_prices( $request ): array {
		try {
			$options = $this->get_price_options_cached( 'one_time', $request->is_refresh() );
			return $this->remote_data_success( $this->prepend_any( $options ) );
		} catch ( \Exception $e ) {
			return $this->remote_data_error( $e->getMessage() );
		}
	}

	/**
	 * Build the create-payment-link repeater row fields: a PRICE select (combined
	 * recurring + one-time cached options) and a QUANTITY input.
	 *
	 * @param bool $refresh When true, bypass the price cache and re-fetch.
	 * @return array Row field definitions (PRICE, QUANTITY).
	 */
	public function get_payment_link_row_fields( $refresh = false ) {

		$price_options = array_merge(
			$this->get_price_options_cached( 'recurring', $refresh ),
			$this->get_price_options_cached( 'one_time', $refresh )
		);

		return array(
			array(
				'option_code'           => 'PRICE',
				'label'                 => esc_html_x( 'Product and price', 'Stripe', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'read_only'             => false,
				'supports_custom_value' => true,
				'placeholder'           => esc_html_x( 'Select a product and price', 'Stripe', 'uncanny-automator' ),
				'description'           => esc_html_x( 'Select a product and price or enter a Stripe Price ID (starts with price_ or plan_)', 'Stripe', 'uncanny-automator' ),
				'options'               => $price_options,
			),
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => 'QUANTITY',
					'label'       => esc_html_x( 'Quantity', 'Stripe', 'uncanny-automator' ),
					'input_type'  => 'text',
					'tokens'      => true,
					'default'     => 1,
				)
			),
		);
	}

	/**
	 * Remote_Data: field configuration for create-payment-link's items repeater.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 * @return array
	 */
	protected function remote_data_get_payment_link_fields( $request ): array {
		try {
			return $this->remote_data_success(
				array( 'fields' => $this->get_payment_link_row_fields( $request->is_refresh() ) ),
				'field_properties'
			);
		} catch ( \Exception $e ) {
			return $this->remote_data_error( $e->getMessage() );
		}
	}

	/**
	 * Recursively remove empty-string values and emptied sub-arrays from an array.
	 *
	 * @param array $array_to_process The array to clean.
	 *
	 * @return array The array with empty values removed.
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
	 * Whether a currency is zero-decimal (charged in its base unit, not divided by 100).
	 *
	 * @param string $currency ISO 4217 currency code.
	 *
	 * @return bool
	 */
	public function is_zero_decimal_currency( $currency ) {
		// Zero-decimal currencies per https://docs.stripe.com/currencies#zero-decimal
		$zero_decimal = array( 'bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf' );

		return ! empty( $currency ) && in_array( strtolower( $currency ), $zero_decimal, true );
	}

	/**
	 * Format a Stripe integer amount into a human-readable string for its currency.
	 *
	 * @param int    $cents    Amount in the smallest currency unit.
	 * @param string $currency ISO 4217 currency code (e.g. 'usd', 'jpy').
	 *
	 * @return string The formatted amount (zero-decimal currencies are not divided by 100).
	 */
	public function format_amount( $cents, $currency = '' ) {

		if ( $this->is_zero_decimal_currency( $currency ) ) {
			return number_format( $cents, 0 );
		}

		return number_format( $cents / 100, 2 );
	}

	/**
	 * Format a Unix timestamp using the site's configured date and time formats.
	 *
	 * @param int $timestamp Unix timestamp.
	 *
	 * @return string The formatted date-time string.
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
	 * Explode comma-separated string values into arrays for the given field keys.
	 *
	 * @param array $array_to_process  The fields to process.
	 * @param array $fields_to_explode Field keys whose values should be exploded.
	 *
	 * @return array The processed fields.
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
	 * Parse "key:value" metadata strings into associative arrays.
	 *
	 * Handles fields whose keys end in '.metadata' (dot notation).
	 *
	 * @param array $array_to_process The fields to process.
	 *
	 * @return array The processed fields with metadata values expanded.
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
