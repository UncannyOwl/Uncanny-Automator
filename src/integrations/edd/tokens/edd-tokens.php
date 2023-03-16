<?php

namespace Uncanny_Automator;

/**
 * Class Edd_Tokens
 *
 * @package Uncanny_Automator
 */
class Edd_Tokens {

	/** Integration code
	 *
	 * @var string
	 */
	public static $integration = 'EDD';

	public function __construct() {

		// Parse all EDD triggers.
		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_edd_trigger_tokens' ), 999, 6 );

		// Add tokens to EDDORDERREFUND (A user's Stripe payment is refunded) trigger.
		add_filter( 'automator_maybe_trigger_edd_eddorderrefund_tokens', array( $this, 'edd_possible_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_trigger_edd_tokens', array( $this, 'edd_payment_possible_tokens' ), 20, 2 );

		add_action( 'automator_before_trigger_completed', array( $this, 'save_token_data' ), 20, 2 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_edd_tokens' ), 20, 6 );

	}

	/**
	 * save_token_data
	 *
	 * @param mixed $args
	 * @param mixed $trigger
	 *
	 * @return void
	 */
	public function save_token_data( $args, $trigger ) {
		if ( ! isset( $args['trigger_args'] ) || ! isset( $args['entry_args']['code'] ) ) {
			return;
		}

		$trigger_meta_validations = apply_filters(
			'automator_edd_validate_trigger_code_pieces',
			array( 'EDD_ANON_PURCHASE' ),
			$args
		);

		if ( in_array( $args['entry_args']['code'], $trigger_meta_validations ) ) {
			$payment_id        = $args['trigger_args'][0];
			$trigger_log_entry = $args['trigger_entry'];
			if ( ! empty( $payment_id ) ) {
				Automator()->db->token->save( 'payment_id', $payment_id, $trigger_log_entry );
			}
		}
	}

	/**
	 * parse_tokens
	 *
	 * @param mixed $value
	 * @param mixed $pieces
	 * @param mixed $recipe_id
	 * @param mixed $trigger_data
	 * @param mixed $user_id
	 * @param mixed $replace_args
	 *
	 * @return void
	 */
	public function parse_edd_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if ( ! is_array( $pieces ) || ! isset( $pieces[1] ) || ! isset( $pieces[2] ) ) {
			return $value;
		}

		$trigger_meta_validations = apply_filters(
			'automator_edd_validate_trigger_code_parse',
			array( 'EDD_ANON_PURCHASE' ),
			array(
				'pieces'       => $pieces,
				'recipe_id'    => $recipe_id,
				'trigger_data' => $trigger_data,
				'user_id'      => $user_id,
				'replace_args' => $replace_args,
			)
		);

		if ( ! array_intersect( $trigger_meta_validations, $pieces ) ) {
			return $value;
		}

		$to_replace = $pieces[2];
		$payment_id = Automator()->db->token->get( 'payment_id', $replace_args );

		if ( ! class_exists( '\EDD_Payment' ) ) {
			return;
		}

		$payment = new \EDD_Payment( $payment_id );

		switch ( $to_replace ) {
			case 'EDD_PAYMENT_KEY';
				$value = $payment->key;
				break;
			case 'EDD_PAYMENT_ID';
				$value = $payment->ID;
				break;
			case 'EDD_PAYMENT_SUBTOTAL';
				$value = edd_currency_filter( edd_format_amount( $payment->subtotal ) );
				break;
			case 'EDD_PAYMENT_TOTAL';
				$value = edd_currency_filter( edd_format_amount( $payment->total ) );
				break;
			case 'EDD_PAYMENT_TAX';
				$value = edd_currency_filter( edd_format_amount( $payment->tax ) );
				break;
			case 'EDD_PAYMENT_DISCOUNT';
				$value = edd_currency_filter( edd_format_amount( $payment->discounted_amount ) );
				break;
			case 'EDD_PAYMENT_STATUS';
				$value = $payment->status_nicename;
				break;
			case 'EDD_PAYMENT_GATEWAY';
				$value = $payment->gateway;
				break;
			case 'EDD_PAYMENT_CURRENCY';
				$value = $payment->currency;
				break;
			case 'EDD_CUSTOMER_ID';
				$value = $payment->customer_id;
				break;
			case 'EDD_CUSTOMER_FIRSTNAME';
				$value = $payment->first_name;
				break;
			case 'EDD_CUSTOMER_LASTNAME';
				$value = $payment->last_name;
				break;
			case 'EDD_CUSTOMER_EMAIL';
				$value = $payment->email;
				break;
			case 'EDD_CUSTOMER_ADDRESS_LINE1';
				$value = $payment->address['line1'];
				break;
			case 'EDD_CUSTOMER_ADDRESS_LINE2';
				$value = $payment->address['line2'];
				break;
			case 'EDD_CUSTOMER_ADDRESS_CITY';
				$value = $payment->address['city'];
				break;
			case 'EDD_CUSTOMER_ADDRESS_STATE';
				$value = $payment->address['state'];
				break;
			case 'EDD_CUSTOMER_ADDRESS_COUNTRY';
				$value = $payment->address['country'];
				break;
			case 'EDD_CUSTOMER_ADDRESS_ZIP';
				$value = $payment->address['zip'];
				break;
			case 'EDD_DOWNLOAD_NAME';
				$value = $this->get_product_data( $trigger_data, $payment_id, 'name' );
				break;
			case 'EDD_DOWNLOAD_ID';
				$value = $this->get_product_data( $trigger_data, $payment_id, 'id' );
				break;
			case 'EDD_DOWNLOAD_PRICE';
				$value = $this->get_product_data( $trigger_data, $payment_id, 'price' );
				break;
			case 'EDD_DOWNLOAD_QUANTITY';
				$value = $this->get_product_data( $trigger_data, $payment_id, 'quantity' );
				break;
			case 'EDD_DOWNLOAD_SUBTOTAL';
				$value = $this->get_product_data( $trigger_data, $payment_id, 'subtotal' );
				break;
			case 'EDD_DOWNLOAD_TAX';
				$value = $this->get_product_data( $trigger_data, $payment_id, 'tax' );
				break;
			case 'EDD_DOWNLOAD_URL';
				$value = $this->get_product_data( $trigger_data, $payment_id, 'link' );
				break;
			case 'EDD_DOWNLOAD_THUMB_ID';
				$value = $this->get_product_data( $trigger_data, $payment_id, 'thumb_id' );
				break;
			case 'EDD_DOWNLOAD_THUMB_URL';
				$value = $this->get_product_data( $trigger_data, $payment_id, 'thumb_url' );
				break;
		}

		return $value;
	}

	/**
	 * @param array  $trigger_data
	 * @param int    $payment_id
	 * @param string $type
	 *
	 * @return string
	 */
	private function get_product_data( $trigger_data, $payment_id, $type ) {
		$product_id = $trigger_data[0]['meta']['EDD_PRODUCTS'];
		$cart_items = edd_get_payment_meta_cart_details( $payment_id );
		$products   = array();
		foreach ( $cart_items as $item ) {
			if ( absint( $item['id'] ) === absint( $product_id ) || intval( '-1' ) === intval( $product_id ) ) {
				switch ( $type ) {
					case 'id':
						$products[ $type ][] = $item['id'];
						break;
					case 'name':
						$products[ $type ][] = $item['name'];
						break;
					case 'price':
						$products[ $type ][] = edd_currency_filter( edd_format_amount( $item['price'] ) );
						break;
					case 'quantity':
						$products[ $type ][] = $item['quantity'];
						break;
					case 'subtotal':
						$products[ $type ][] = edd_currency_filter( edd_format_amount( $item['subtotal'] ) );
						break;
					case 'tax':
						$products[ $type ][] = edd_currency_filter( edd_format_amount( $item['tax'] ) );
						break;
					case 'link':
						$products[ $type ][] = get_permalink( ( $item['id'] ) );
						break;
					case 'thumb_id':
						$products[ $type ][] = get_post_thumbnail_id( $item['id'] );
						break;
					case 'thumb_url':
						$products[ $type ][] = get_the_post_thumbnail_url( $item['id'] );
						break;
				}
			}
		}

		return join( ', ', $products[ $type ] );
	}

	/**
	 * Stripe refunded action possible tokens.
	 *
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function edd_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}

		$trigger_integration = $args['integration'];
		$trigger_meta        = $args['meta'];

		$fields = array(
			array(
				'tokenId'         => 'EDDCUSTOMER_EMAIL',
				'tokenName'       => __( 'Customer email', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'EDDPRODUCT_DISCOUNT_CODES',
				'tokenName'       => __( 'Discount codes used', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'EDDORDER_ID',
				'tokenName'       => __( 'Order ID', 'uncanny-automator' ),
				'tokenType'       => 'int',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'EDDPRODUCT_ORDER_DISCOUNTS',
				'tokenName'       => __( 'Order discounts', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'EDDORDER_SUBTOTAL',
				'tokenName'       => __( 'Order subtotal', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'EDDPRODUCT_ORDER_TAX',
				'tokenName'       => __( 'Order tax', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'EDDORDER_TOTAL',
				'tokenName'       => __( 'Order total', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'EDDORDER_ITEMS',
				'tokenName'       => __( 'Ordered items', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'EDDPRODUCT_PAYMENT_METHOD',
				'tokenName'       => __( 'Payment method', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
		);

		if ( class_exists( '\EDD_Software_Licensing' ) ) {
			array_push(
				$fields,
				array(
					'tokenId'         => 'EDDPRODUCT_LICENSE_KEY',
					'tokenName'       => __( 'License key', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_meta,
				)
			);
		}

		$tokens = array_merge( $tokens, $fields );

		$arr_column_tokens_collection = array_column( $tokens, 'tokenName' );

		array_multisort( $arr_column_tokens_collection, SORT_ASC, $tokens );

		return $tokens;

	}

	/**
	 * @param $tokens
	 * @param $args
	 *
	 * @return array|array[]
	 */
	public function edd_payment_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}

		$trigger_code = $args['triggers_meta']['code'];

		$trigger_meta_validations = apply_filters(
			'automator_edd_validate_common_trigger_tokens',
			array( 'EDD_ANON_PURCHASE' ),
			$args
		);

		if ( in_array( $trigger_code, $trigger_meta_validations, true ) ) {

			$fields = array(
				array(
					'tokenId'         => 'EDD_DOWNLOAD_ID',
					'tokenName'       => __( 'Download ID', 'uncanny-automator' ),
					'tokenType'       => 'int',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'EDD_DOWNLOAD_NAME',
					'tokenName'       => __( 'Download name', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'EDD_DOWNLOAD_URL',
					'tokenName'       => __( 'Download URL', 'uncanny-automator' ),
					'tokenType'       => 'url',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'EDD_DOWNLOAD_THUMB_ID',
					'tokenName'       => __( 'Download featured image ID', 'uncanny-automator' ),
					'tokenType'       => 'int',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'EDD_DOWNLOAD_THUMB_URL',
					'tokenName'       => __( 'Download featured image URL', 'uncanny-automator' ),
					'tokenType'       => 'url',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'EDD_DOWNLOAD_PRICE',
					'tokenName'       => __( 'Download price', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'EDD_DOWNLOAD_QUANTITY',
					'tokenName'       => __( 'Download quantity', 'uncanny-automator' ),
					'tokenType'       => 'int',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'EDD_DOWNLOAD_SUBTOTAL',
					'tokenName'       => __( 'Download subtotal', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'EDD_DOWNLOAD_TAX',
					'tokenName'       => __( 'Download tax', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'EDD_PAYMENT_KEY',
					'tokenName'       => __( 'Payment key', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'EDD_PAYMENT_ID',
					'tokenName'       => __( 'Payment ID', 'uncanny-automator' ),
					'tokenType'       => 'int',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'EDD_PAYMENT_SUBTOTAL',
					'tokenName'       => __( 'Payment subtotal', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'EDD_PAYMENT_TOTAL',
					'tokenName'       => __( 'Payment total', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'EDD_PAYMENT_TAX',
					'tokenName'       => __( 'Payment tax', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'EDD_PAYMENT_DISCOUNT',
					'tokenName'       => __( 'Payment discount', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'EDD_PAYMENT_STATUS',
					'tokenName'       => __( 'Payment status', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'EDD_PAYMENT_GATEWAY',
					'tokenName'       => __( 'Payment gateway', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'EDD_PAYMENT_CURRENCY',
					'tokenName'       => __( 'Payment currency', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'EDD_CUSTOMER_ID',
					'tokenName'       => __( 'Customer ID', 'uncanny-automator' ),
					'tokenType'       => 'int',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'EDD_CUSTOMER_FIRSTNAME',
					'tokenName'       => __( 'Customer first name', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'EDD_CUSTOMER_LASTNAME',
					'tokenName'       => __( 'Customer last name', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'EDD_CUSTOMER_EMAIL',
					'tokenName'       => __( 'Customer email', 'uncanny-automator' ),
					'tokenType'       => 'email',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'EDD_CUSTOMER_ADDRESS_LINE1',
					'tokenName'       => __( 'Customer address - Line 1', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'EDD_CUSTOMER_ADDRESS_LINE2',
					'tokenName'       => __( 'Customer address - Line 2', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'EDD_CUSTOMER_ADDRESS_CITY',
					'tokenName'       => __( 'Customer address - City', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'EDD_CUSTOMER_ADDRESS_STATE',
					'tokenName'       => __( 'Customer address - State', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'EDD_CUSTOMER_ADDRESS_COUNTRY',
					'tokenName'       => __( 'Customer address - Country', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'EDD_CUSTOMER_ADDRESS_ZIP',
					'tokenName'       => __( 'Customer address - Zip', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
			);

			$tokens = array_merge( $tokens, $fields );
		}

		return $tokens;

	}

	/**
	 * Parse EDD tokens.
	 *
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return mixed
	 */
	public function parse_edd_trigger_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if ( $pieces ) {

			if ( in_array(
				$pieces[1],
				array(
					'EDDPRODPURCHDISCOUNT',
					'EDD_PRODUCTPURCHASE',
					'EDDORDERREFUND',
				),
				true
			) ) {

				// Parse new tokens.
				if ( isset( $pieces[2] ) && in_array( $pieces[2], $this->get_edd_order_tokens(), true ) ) {

					$order_info = json_decode( Automator()->db->token->get( 'EDD_DOWNLOAD_ORDER_PAYMENT_INFO', $replace_args ) );

					return $this->meta_to_value( $order_info, $pieces[2] );
				}

				// Parse EDD Thumbnail URL and DOWNLOAD
				if ( isset( $pieces[2] ) && 'EDDPRODUCTS_THUMB_URL' === $pieces[2] ) {
					$post_id = absint( Automator()->db->token->get( 'EDDPRODUCTS_ID', $replace_args ) );
					if ( ! empty( $post_id ) ) {
						return get_the_post_thumbnail_url( $post_id );
					}
				}

				// Parse EDD Thumbnail URL and DOWNLOAD
				if ( isset( $pieces[2] ) && 'EDDPRODUCTS_THUMB_ID' === $pieces[2] ) {
					$post_id = absint( Automator()->db->token->get( 'EDDPRODUCTS_ID', $replace_args ) );
					if ( ! empty( $post_id ) ) {
						return get_post_thumbnail_id( $post_id );
					}
				}
			}

			// Parse old tokens.
			if ( in_array( 'EDDORDERREFUNDED', $pieces, true ) || in_array( 'EDDORDERREFUND', $pieces, true ) ) {

				global $wpdb;

				$trigger_id   = $pieces[0];
				$trigger_meta = $pieces[2];

				$trigger_log_id = isset( $replace_args['trigger_log_id'] ) ? absint( $replace_args['trigger_log_id'] ) : 0;

				$entry = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT meta_value
						FROM {$wpdb->prefix}uap_trigger_log_meta
						WHERE meta_key = %s
						AND automator_trigger_log_id = %d
						AND automator_trigger_id = %d",
						$trigger_meta,
						$trigger_log_id,
						$trigger_id
					)
				);

				$value = maybe_unserialize( $entry );

				// Format if its a numeric value.
				if ( is_numeric( $value ) && in_array(
					$pieces[2],
					array(
						'EDDORDER_SUBTOTAL',
						'EDDORDER_TOTAL',
					),
					true
				) ) {

					$value = number_format( $value, 2 );

				}
			}
		}//end if

		return $value;
	}

	/**
	 * Get the order tokens.
	 *
	 * @return array The order tokens.
	 */
	public function get_edd_order_tokens() {

		return array(
			'EDDPRODUCT_DISCOUNT_CODES',
			'EDDPRODUCT_ORDER_DISCOUNTS',
			'EDDPRODUCT_ORDER_SUBTOTAL',
			'EDDPRODUCT_ORDER_TAX',
			'EDDPRODUCT_ORDER_TOTAL',
			'EDDPRODUCT_PAYMENT_METHOD',
			'EDDPRODUCT_LICENSE_KEY',
		);
	}

	/**
	 * Map the provided object with key.
	 *
	 * @return mixed The value.
	 */
	public function meta_to_value( $object, $key = '' ) {

		if ( empty( $key ) ) {
			return '';
		}

		$meta = array(
			'EDDPRODUCT_DISCOUNT_CODES'  => $object->discount_codes,
			'EDDPRODUCT_ORDER_DISCOUNTS' => number_format( $object->order_discounts, 2 ),
			'EDDPRODUCT_ORDER_SUBTOTAL'  => number_format( $object->order_subtotal, 2 ),
			'EDDPRODUCT_ORDER_TAX'       => number_format( $object->order_tax, 2 ),
			'EDDPRODUCT_ORDER_TOTAL'     => number_format( $object->order_total, 2 ),
			'EDDPRODUCT_PAYMENT_METHOD'  => $object->payment_method,
			'EDDPRODUCT_LICENSE_KEY'     => $object->license_key,
		);

		if ( ! array_key_exists( $key, $meta ) ) {
			return '';
		}

		return $meta[ $key ];

	}
}
