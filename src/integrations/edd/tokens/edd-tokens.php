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
				'tokenType'       => 'text',
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

			if ( in_array( $pieces[1], array( 'EDDPRODPURCHDISCOUNT', 'EDD_PRODUCTPURCHASE', 'EDDORDERREFUND' ), true ) ) {

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
				if ( is_numeric( $value ) && in_array( $pieces[2], array( 'EDDORDER_SUBTOTAL', 'EDDORDER_TOTAL' ), true ) ) {

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
