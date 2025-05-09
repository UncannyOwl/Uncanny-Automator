<?php

namespace Uncanny_Automator;

use WC_Order;
use WC_Order_Item_Product;

/**
 * Class Wc_Tokens
 *
 * @package Uncanny_Automator
 */
class Wc_Tokens {

	/**
	 * @var array
	 */
	public $possible_order_fields = array();

	/**
	 * Pmp_Tokens constructor.
	 */
	public function __construct() {
		$this->possible_order_fields = array(
			'product_sku'           => esc_attr__( 'Product SKU', 'uncanny-automator' ),
			'WOOPRODUCT_CATEGORIES' => esc_attr__( 'Product categories', 'uncanny-automator' ),
			'WOOPRODUCT_TAGS'       => esc_attr__( 'Product tags', 'uncanny-automator' ),
			'billing_first_name'    => esc_attr__( 'Billing first name', 'uncanny-automator' ),
			'billing_last_name'     => esc_attr__( 'Billing last name', 'uncanny-automator' ),
			'billing_company'       => esc_attr__( 'Billing company', 'uncanny-automator' ),
			'billing_country'       => esc_attr__( 'Billing country', 'uncanny-automator' ),
			'billing_country_name'  => esc_attr__( 'Billing country (full name)', 'uncanny-automator' ),
			'billing_address_1'     => esc_attr__( 'Billing address line 1', 'uncanny-automator' ),
			'billing_address_2'     => esc_attr__( 'Billing address line 2', 'uncanny-automator' ),
			'billing_city'          => esc_attr__( 'Billing city', 'uncanny-automator' ),
			'billing_state'         => esc_attr__( 'Billing state', 'uncanny-automator' ),
			'billing_state_name'    => esc_attr__( 'Billing state (full name)', 'uncanny-automator' ),
			'billing_postcode'      => esc_attr__( 'Billing postcode', 'uncanny-automator' ),
			'billing_phone'         => esc_attr__( 'Billing phone', 'uncanny-automator' ),
			'billing_email'         => esc_attr__( 'Billing email', 'uncanny-automator' ),
			'shipping_first_name'   => esc_attr__( 'Shipping first name', 'uncanny-automator' ),
			'shipping_last_name'    => esc_attr__( 'Shipping last name', 'uncanny-automator' ),
			'shipping_company'      => esc_attr__( 'Shipping company', 'uncanny-automator' ),
			'shipping_country'      => esc_attr__( 'Shipping country', 'uncanny-automator' ),
			'shipping_country_name' => esc_attr__( 'Shipping country (full name)', 'uncanny-automator' ),
			'shipping_address_1'    => esc_attr__( 'Shipping address line 1', 'uncanny-automator' ),
			'shipping_address_2'    => esc_attr__( 'Shipping address line 2', 'uncanny-automator' ),
			'shipping_city'         => esc_attr__( 'Shipping city', 'uncanny-automator' ),
			'shipping_state'        => esc_attr__( 'Shipping state', 'uncanny-automator' ),
			'shipping_state_name'   => esc_attr__( 'Shipping state (full name)', 'uncanny-automator' ),
			'shipping_postcode'     => esc_attr__( 'Shipping postcode', 'uncanny-automator' ),
			'order_date'            => esc_attr__( 'Order date', 'uncanny-automator' ),
			'order_time'            => esc_attr__( 'Order time', 'uncanny-automator' ),
			'order_date_time'       => esc_attr__( 'Order date and time', 'uncanny-automator' ),
			'order_id'              => esc_attr__( 'Order ID', 'uncanny-automator' ),
			'order_comments'        => esc_attr__( 'Order comments', 'uncanny-automator' ),
			'order_total'           => esc_attr__( 'Order total', 'uncanny-automator' ),
			'order_total_raw'       => esc_attr__( 'Order total (unformatted)', 'uncanny-automator' ),
			'order_status'          => esc_attr__( 'Order status', 'uncanny-automator' ),
			'order_subtotal'        => esc_attr__( 'Order subtotal', 'uncanny-automator' ),
			'order_subtotal_raw'    => esc_attr__( 'Order subtotal (unformatted)', 'uncanny-automator' ),
			'order_tax'             => esc_attr__( 'Order tax', 'uncanny-automator' ),
			'order_tax_raw'         => esc_attr__( 'Order tax (unformatted)', 'uncanny-automator' ),
			'order_discounts'       => esc_attr__( 'Order discounts', 'uncanny-automator' ),
			'order_discounts_raw'   => esc_attr__( 'Order discounts (unformatted)', 'uncanny-automator' ),
			'order_coupons'         => esc_attr__( 'Order coupons', 'uncanny-automator' ),
			'order_products'        => esc_attr__( 'Order products', 'uncanny-automator' ),
			'order_products_qty'    => esc_attr__( 'Order products and quantity', 'uncanny-automator' ),
			'order_qty'             => esc_attr__( 'Order quantity', 'uncanny-automator' ),
			'order_products_links'  => esc_attr__( 'Order products links', 'uncanny-automator' ),
			'order_summary'         => esc_attr__( 'Order summary', 'uncanny-automator' ),
			'order_fees'            => esc_attr__( 'Order fee', 'uncanny-automator' ),
			'order_fees_raw'        => esc_attr__( 'Order fee (unformatted)', 'uncanny-automator' ),
			'order_shipping'        => esc_attr__( 'Shipping fee', 'uncanny-automator' ),
			'order_shipping_raw'    => esc_attr__( 'Shipping fee (unformatted)', 'uncanny-automator' ),
			'payment_method'        => esc_attr__( 'Payment method', 'uncanny-automator' ),
			'shipping_method'       => esc_attr__( 'Shipping method', 'uncanny-automator' ),
			'payment_url'           => esc_attr__( 'Payment URL', 'uncanny-automator' ),
			'payment_url_checkout'  => esc_attr__( 'Direct checkout URL', 'uncanny-automator' ),
			'user_total_spend'      => esc_attr__( "User's total spend", 'uncanny-automator' ),
			'user_total_spend_raw'  => esc_attr__( "User's total spend (unformatted)", 'uncanny-automator' ),
		);

		if ( function_exists( 'stripe_wc' ) || class_exists( '\WC_Stripe_Helper' ) || function_exists( 'woocommerce_gateway_stripe' ) ) {
			$this->possible_order_fields['stripe_fee']        = esc_attr__( 'Stripe fee', 'uncanny-automator' );
			$this->possible_order_fields['stripe_fee_raw']    = esc_attr__( 'Stripe fee (unformatted)', 'uncanny-automator' );
			$this->possible_order_fields['stripe_payout']     = esc_attr__( 'Stripe payout', 'uncanny-automator' );
			$this->possible_order_fields['stripe_payout_raw'] = esc_attr__( 'Stripe payout (unformatted)', 'uncanny-automator' );
		}

		add_action(
			'uap_wc_trigger_save_meta',
			array(
				$this,
				'uap_wc_trigger_save_meta_func',
			),
			20,
			4
		);

		//Adding WC tokens
		add_filter(
			'automator_maybe_trigger_wc_woordertotal_tokens',
			array(
				$this,
				'wc_ordertotal_possible_tokens',
			),
			20,
			2
		);

		add_filter(
			'automator_maybe_trigger_wc_wcorderstatus_tokens',
			array(
				$this,
				'wc_ordertotal_possible_tokens',
			),
			20,
			2
		);

		add_filter(
			'automator_maybe_trigger_wc_wooproduct_tokens',
			array(
				$this,
				'wc_wooproduct_possible_tokens',
			),
			20,
			2
		);

		//Parsing data
		add_filter(
			'automator_maybe_parse_token',
			array(
				$this,
				'wc_ordertotal_tokens',
			),
			2000,
			6
		);

		add_filter(
			'automator_maybe_parse_token',
			array(
				$this,
				'wc_non_ordertotal_tokens',
			),
			200,
			6
		);

		//Adding WC tokens
		add_filter(
			'automator_maybe_trigger_wc_wcshipstationshipped_tokens',
			array(
				$this,
				'wc_order_possible_tokens',
			),
			20,
			2
		);
	}

	/**
	 * @param $order_id
	 * @param $recipe_id
	 * @param $args
	 * @param $type
	 */
	public function uap_wc_trigger_save_meta_func( $order_id, $recipe_id, $args, $type ) {

		if ( ! empty( $order_id ) && is_array( $args ) && $recipe_id ) {

			foreach ( $args as $trigger_result ) {

				if ( true === $trigger_result['result'] && $trigger_result['args']['trigger_id'] && $trigger_result['args']['get_trigger_id'] ) {

					$trigger_id     = (int) $trigger_result['args']['trigger_id'];
					$user_id        = (int) $trigger_result['args']['user_id'];
					$trigger_log_id = (int) $trigger_result['args']['get_trigger_id'];
					$run_number     = (int) $trigger_result['args']['run_number'];

					$args = array(
						'user_id'        => $user_id,
						'trigger_id'     => $trigger_id,
						'meta_key'       => 'order_id',
						'meta_value'     => $order_id,
						'run_number'     => $run_number, //get run number
						'trigger_log_id' => $trigger_log_id,
					);

					Automator()->insert_trigger_meta( $args );

				}
			}
		}
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function wc_ordertotal_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}

		return $this->wc_possible_tokens( $tokens, $args, 'order' );
	}

	/**
	 * @param array  $tokens
	 * @param array  $args
	 * @param string $type
	 *
	 * @return array
	 */
	public function wc_possible_tokens( $tokens = array(), $args = array(), $type = 'order' ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}

		$fields           = array();
		$trigger_meta     = $args['meta'];
		$add_order_tokens = true;
		if ( isset( $args['triggers_meta']['code'] ) && 'VIEWWOOPRODUCT' === $args['triggers_meta']['code'] ) {
			$add_order_tokens = false;
		}
		$possible_tokens = array();
		if ( $add_order_tokens ) {
			$possible_tokens = apply_filters( 'automator_woocommerce_possible_tokens', $this->possible_order_fields );
		}
		foreach ( $possible_tokens as $token_id => $input_title ) {
			if ( 'billing_email' === (string) $token_id || 'shipping_email' === (string) $token_id ) {
				$input_type = 'email';
			} elseif ( 'order_qty' === (string) $token_id ) {
				$input_type = 'int';
			} else {
				$input_type = 'text';
			}
			$fields[] = array(
				'tokenId'         => $token_id,
				'tokenName'       => $input_title,
				'tokenType'       => $input_type,
				'tokenIdentifier' => $trigger_meta,
			);
		}
		$tokens = array_merge( $tokens, $fields );

		return Automator()->utilities->remove_duplicate_token_ids( $tokens );
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function wc_wooproduct_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}

		return $this->wc_possible_tokens( $tokens, $args, 'product' );
	}

	/**
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return string|null
	 */
	public function wc_ordertotal_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		$to_match = array(
			'WOORDERTOTAL',
			'WOOPRODUCT',
			'WOOPRODUCT_ID',
			'WOOPRODUCT_URL',
			'WOOPRODUCT_THUMB_ID',
			'WOOPRODUCT_THUMB_URL',
			'WOOPRODUCT_ORDER_QTY',
			'WCORDERSTATUS',
			'WCORDERCOMPLETE',
			'WCSHIPSTATIONSHIPPED',
			'WOOPRODUCT_PRODUCT_PRICE',
			'WOOPRODUCT_PRODUCT_PRICE_UNFORMATTED',
			'WOOPRODUCT_PRODUCT_SALE_PRICE',
			'WOOPRODUCT_PRODUCT_SALE_PRICE_UNFORMATTED',
		);
		if ( empty( $pieces ) ) {
			return $value;
		}
		if ( array_intersect( $to_match, $pieces ) ) {
			$value = $this->replace_values( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args );
		}

		return $value;
	}

	/**
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return array|mixed|string|null
	 */
	public function wc_non_ordertotal_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		$to_match = array(
			'VIEWWOOPRODUCT',
		);
		if ( empty( $pieces ) ) {
			return $value;
		}

		if ( array_intersect( $to_match, $pieces ) ) {
			$value = $this->replace_product_only_values( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args );
		}

		return $value;
	}

	/**
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return array|string|null
	 */
	public function replace_values( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if ( empty( $trigger_data ) || empty( $replace_args ) ) {
			return $value;
		}

		$parse = $pieces[2];

		$multi_line_separator = apply_filters( 'automator_woo_multi_item_separator', ' | ', $pieces );

		foreach ( $trigger_data as $trigger ) {
			if ( ! is_array( $trigger ) || empty( $trigger ) ) {
				continue;
			}
			$trigger_id     = $trigger['ID'];
			$trigger_log_id = $replace_args['trigger_log_id'];
			$order          = null;

			// Use the Trigger's user id if its available.
			if ( isset( $replace_args['recipe_triggers'][ $trigger['ID'] ]['user_id'] ) ) {
				$replace_args['user_id'] = $replace_args['recipe_triggers'][ $trigger['ID'] ]['user_id'];
			}

			$order_id = Automator()->db->token->get( 'order_id', $replace_args );

			if ( ! empty( $order_id ) ) {
				$order = wc_get_order( $order_id );
			}

			if ( $order instanceof WC_Order ) {
				switch ( $parse ) {
					case 'order_id':
						$value = $order_id;
						break;
					case 'WCORDERSTATUS':
						$value = $order->get_status();
						break;
					case 'WOOPRODUCT':
						//$value_to_match = isset( $trigger['meta'][ $parse ] ) ? $trigger['meta'][ $parse ] : - 1;
						$value = $this->get_woo_product_names_from_items( $order, '-1' );
						break;
					case 'WOOPRODUCT_ID':
						$value_to_match = isset( $trigger['meta'][ $parse ] ) ? $trigger['meta'][ $parse ] : '-1';
						$value          = $this->get_woo_product_ids_from_items( $order, $value_to_match );
						break;
					case 'WOOPRODUCT_PRODUCT_PRICE':
						$value_to_match = isset( $trigger['meta'][ $parse ] ) ? $trigger['meta'][ $parse ] : '-1';
						$value          = $this->get_woo_product_price_from_items( $order, $value_to_match );
						break;
					case 'WOOPRODUCT_PRODUCT_PRICE_UNFORMATTED':
						$value_to_match = isset( $trigger['meta'][ $parse ] ) ? $trigger['meta'][ $parse ] : '-1';
						$value          = $this->get_woo_product_price_from_items( $order, $value_to_match, true );
						break;
					case 'WOOPRODUCT_PRODUCT_SALE_PRICE':
						$value_to_match = isset( $trigger['meta'][ $parse ] ) ? $trigger['meta'][ $parse ] : '-1';
						$value          = $this->get_woo_product_price_from_items( $order, $value_to_match, false, true );
						break;
					case 'WOOPRODUCT_PRODUCT_SALE_PRICE_UNFORMATTED':
						$value_to_match = isset( $trigger['meta'][ $parse ] ) ? $trigger['meta'][ $parse ] : '-1';
						$value          = $this->get_woo_product_price_from_items( $order, $value_to_match, true, true );
						break;
					case 'WOOPRODUCT_URL':
						$value_to_match = isset( $trigger['meta'][ $parse ] ) ? $trigger['meta'][ $parse ] : '-1';
						$value          = $this->get_woo_product_urls_from_items( $order, $value_to_match );
						break;
					case 'WOOPRODUCT_THUMB_ID':
						$value_to_match = isset( $trigger['meta'][ $parse ] ) ? $trigger['meta'][ $parse ] : '-1';
						$value          = $this->get_woo_product_image_ids_from_items( $order, $value_to_match );
						break;
					case 'WOOPRODUCT_THUMB_URL':
						$value_to_match = isset( $trigger['meta'][ $parse ] ) ? $trigger['meta'][ $parse ] : '-1';
						$value          = $this->get_woo_product_image_urls_from_items( $order, $value_to_match );
						break;
					case 'WOOPRODUCT_ORDER_QTY':
						$product_id   = isset( $trigger['meta']['WOOPRODUCT'] ) ? intval( $trigger['meta']['WOOPRODUCT'] ) : '-1';
						$items        = $order->get_items();
						$product_qtys = array();
						if ( $items ) {
							/** @var WC_Order_Item_Product $item */
							foreach ( $items as $item ) {
								$product = $item->get_product();
								if ( $product_id === $product->get_id() || ( intval( '-1' ) === intval( $product_id ) && 1 === count( $items ) ) ) {
									$value = $item->get_quantity();
									break;
								} elseif ( intval( '-1' ) === intval( $product_id ) ) {
									$product_qtys[] = $item->get_name() . ' x ' . $item->get_quantity();
								}
							}
						}
						if ( ! empty( $product_qtys ) ) {
							$value = join( $multi_line_separator, $product_qtys );
						}
						break;
					case 'WOORDERTOTAL':
						$value = wp_strip_all_tags( wc_price( $order->get_total() ) );
						break;
					case 'NUMBERCOND':
						$val = isset( $trigger['meta'][ $parse ] ) ? $trigger['meta'][ $parse ] : '';
						switch ( $val ) {
							case '<':
								$value = esc_attr__( 'less than', 'uncanny-automator' );
								break;
							case '>':
								$value = esc_attr__( 'greater than', 'uncanny-automator' );
								break;
							case '=':
								$value = esc_attr__( 'equal to', 'uncanny-automator' );
								break;
							case '!=':
								$value = esc_attr__( 'not equal to', 'uncanny-automator' );
								break;
							case '>=':
								$value = esc_attr__( 'greater or equal to', 'uncanny-automator' );
								break;
							case '<=':
								$value = esc_attr__( 'less or equal to', 'uncanny-automator' );
								break;
							default:
								$value = '';
								break;
						}
						break;
					case 'NUMTIMES':
						$value = absint( $replace_args['run_number'] );
						break;
					case 'billing_first_name':
						$value = $order->get_billing_first_name();
						break;
					case 'billing_last_name':
						$value = $order->get_billing_last_name();
						break;
					case 'billing_company':
						$value = $order->get_billing_company();
						break;
					case 'billing_country':
						$value = $order->get_billing_country();
						break;
					case 'billing_country_name':
						$value = $this->get_country_name_from_code( $order->get_billing_country() );
						break;
					case 'billing_address_1':
						$value = $order->get_billing_address_1();
						break;
					case 'billing_address_2':
						$value = $order->get_billing_address_2();
						break;
					case 'billing_city':
						$value = $order->get_billing_city();
						break;
					case 'billing_state':
						$value = $order->get_billing_state();
						break;
					case 'billing_state_name':
						$value = $this->get_state_name_from_codes( $order->get_billing_state(), $order->get_billing_country() );
						break;
					case 'billing_postcode':
						$value = $order->get_billing_postcode();
						break;
					case 'billing_phone':
						$value = $order->get_billing_phone();
						break;
					case 'billing_email':
						$value = $order->get_billing_email();
						break;
					case 'order_date':
						$value = $order->get_date_created()->format( get_option( 'date_format', 'F j, Y' ) );
						break;
					case 'order_time':
						$value = $order->get_date_created()->format( get_option( 'time_format', 'H:i:s' ) );
						break;
					case 'order_date_time':
						$value = $order->get_date_created()->format( sprintf( '%s %s', get_option( 'date_format', 'F j, Y' ), get_option( 'time_format', 'H:i:s' ) ) );
						break;
					case 'shipping_first_name':
						$value = $order->get_shipping_first_name();
						break;
					case 'shipping_last_name':
						$value = $order->get_shipping_last_name();
						break;
					case 'shipping_company':
						$value = $order->get_shipping_company();
						break;
					case 'shipping_country':
						$value = $order->get_shipping_country();
						break;
					case 'shipping_country_name':
						$value = $this->get_country_name_from_code( $order->get_shipping_country() );
						break;
					case 'shipping_address_1':
						$value = $order->get_shipping_address_1();
						break;
					case 'shipping_address_2':
						$value = $order->get_shipping_address_2();
						break;
					case 'shipping_city':
						$value = $order->get_shipping_city();
						break;
					case 'shipping_state':
						$value = $order->get_shipping_state();
						break;
					case 'shipping_state_name':
						$value = $this->get_state_name_from_codes( $order->get_shipping_state(), $order->get_shipping_country() );
						break;
					case 'shipping_postcode':
						$value = $order->get_shipping_postcode();
						break;
					case 'shipping_phone':
						$value = get_post_meta( $order_id, 'shipping_phone', true );
						break;
					case 'order_comments':
						$comments = $order->get_customer_note();
						if ( is_array( $comments ) ) {
							$comments = join( $multi_line_separator, $comments );
						}
						$value = ! empty( $comments ) ? $comments : '';
						break;
					case 'order_status':
						$value = $order->get_status();
						break;
					case 'order_total':
						$value = wp_strip_all_tags( wc_price( $order->get_total() ) );
						break;
					case 'order_total_raw':
						$value = $order->get_total();
						break;
					case 'order_subtotal':
						$value = wp_strip_all_tags( wc_price( $order->get_subtotal() ) );
						break;
					case 'order_subtotal_raw':
						$value = $order->get_subtotal();
						break;
					case 'order_tax':
						$value = wp_strip_all_tags( wc_price( $order->get_total_tax() ) );
						break;
					case 'order_fees':
						$value = wc_price( $order->get_total_fees() );
						break;
					case 'order_fees_raw':
						$value = $order->get_total_fees();
						break;
					case 'order_shipping':
						$value = wc_price( $order->get_shipping_total() );
						break;
					case 'order_shipping_raw':
						$value = $order->get_shipping_total();
						break;
					case 'order_tax_raw':
						$value = $order->get_total_tax();
						break;
					case 'order_discounts':
						$value = wp_strip_all_tags( wc_price( $order->get_discount_total() * - 1 ) );
						break;
					case 'order_discounts_raw':
						$value = ( $order->get_discount_total() * - 1 );
						break;
					case 'user_total_spend_raw':
						$customer_id = $order->get_user_id();
						$value       = wc_get_customer_total_spent( $customer_id );
						break;
					case 'user_total_spend':
						$customer_id = $order->get_user_id();
						$value       = wc_price( wc_get_customer_total_spent( $customer_id ) );
						break;
					case 'order_coupons':
						$coupons = $order->get_coupon_codes();
						$value   = join( ', ', $coupons );
						break;
					case 'order_products':
						$items = $order->get_items();
						$prods = array();
						if ( $items ) {
							/** @var WC_Order_Item_Product $item */
							foreach ( $items as $item ) {
								$product = $item->get_product();
								$prods[] = $product->get_title();
							}
						}
						$value = join( $multi_line_separator, $prods );

						break;
					case 'order_products_qty':
						$items = $order->get_items();
						$prods = array();
						if ( $items ) {
							/** @var WC_Order_Item_Product $item */
							foreach ( $items as $item ) {
								$product = $item->get_product();
								$prods[] = $product->get_title() . ' x ' . $item->get_quantity();
							}
						}
						$value = implode( $multi_line_separator, $prods );

						break;
					case 'order_qty':
						$qty = 0;
						/** @var WC_Order_Item_Product $item */
						$items = $order->get_items();
						foreach ( $items as $item ) {
							$qty = $qty + $item->get_quantity();
						}
						$value = $qty;
						break;
					case 'order_products_links':
						$items = $order->get_items();
						$prods = array();
						if ( $items ) {
							/** @var WC_Order_Item_Product $item */
							foreach ( $items as $item ) {
								$product = $item->get_product();
								$prods[] = '<a href="' . $product->get_permalink() . '">' . $product->get_title() . '</a>';
							}
						}

						$value = join( $multi_line_separator, $prods );
						break;
					case 'payment_method':
						$value = $order->get_payment_method_title();
						break;

					case 'payment_url':
						$value = $order->get_checkout_payment_url();
						break;

					case 'payment_url_checkout':
						$value = $order->get_checkout_payment_url( true );
						break;

					case 'stripe_fee':
						$value = 0;
						if ( function_exists( 'stripe_wc' ) ) {
							$value = \WC_Stripe_Utils::display_fee( $order );
						}
						if ( ( function_exists( 'woocommerce_gateway_stripe' ) || class_exists( '\WC_Stripe_Helper' ) ) && 0 === $value ) {
							$value = \WC_Stripe_Helper::get_stripe_fee( $order );
						}

						break;

					case 'stripe_fee_raw':
						$value = 0;
						if ( function_exists( 'stripe_wc' ) ) {
							$value = \WC_Stripe_Utils::display_fee( $order );
						}
						if ( ( function_exists( 'woocommerce_gateway_stripe' ) || class_exists( '\WC_Stripe_Helper' ) ) && 0 === $value ) {
							$value = \WC_Stripe_Helper::get_stripe_fee( $order );
						}

						if ( ! empty( $value ) ) {
							$value = $this->clean_wc_price( $value );
						}
						break;

					case 'stripe_payout':
						$value = 0;
						if ( function_exists( 'stripe_wc' ) ) {
							$value = \WC_Stripe_Utils::display_net( $order );
						}
						if ( class_exists( '\WC_Stripe_Helper' ) && 0 === $value ) {
							$value = \WC_Stripe_Helper::get_stripe_net( $order );
						}
						break;

					case 'stripe_payout_raw':
						$value = 0;
						if ( function_exists( 'stripe_wc' ) ) {
							$value = \WC_Stripe_Utils::display_net( $order );
						}
						if ( class_exists( '\WC_Stripe_Helper' ) && 0 === $value ) {
							$value = \WC_Stripe_Helper::get_stripe_net( $order );
						}
						if ( ! empty( $value ) ) {
							$value = $this->clean_wc_price( $value );
						}
						break;

					case 'shipping_method':
						$value = $order->get_shipping_method();
						break;
					case 'product_sku':
						$value = $this->get_products_skus( $order );
						break;
					case 'WOOPRODUCT_CATEGORIES':
						$value_to_match = isset( $trigger['meta'][ $parse ] ) ? $trigger['meta'][ $parse ] : '-1';
						$value          = $this->get_woo_product_categories_from_items( $order, $value_to_match );
						break;
					case 'WOOPRODUCT_TAGS':
						$value_to_match = isset( $trigger['meta'][ $parse ] ) ? $trigger['meta'][ $parse ] : '-1';
						$value          = $this->get_woo_product_tags_from_items( $order, $value_to_match );
						break;
					case 'CARRIER':
						$value = Automator()->helpers->recipe->get_form_data_from_trigger_meta( 'WOOORDER_CARRIER', $trigger_id, $trigger_log_id, $user_id );
						break;
					case 'TRACKING_NUMBER':
						$value = Automator()->helpers->recipe->get_form_data_from_trigger_meta( 'WOOORDER_TRACKING_NUMBER', $trigger_id, $trigger_log_id, $user_id );
						break;
					case 'SHIP_DATE':
						$value = Automator()->helpers->recipe->get_form_data_from_trigger_meta( 'WOOORDER_SHIP_DATE', $trigger_id, $trigger_log_id, $user_id );
						$value = $value ? wp_date( 'Y-m-d H:i:s', $value ) : '';
						break;
					case 'order_summary':
						$value = $this->build_summary_style_html( $order );
						break;
					default:
						if ( preg_match( '/custom_order_meta/', $parse ) ) {
							$custom_meta = explode( '|', $parse );
							if ( ! empty( $custom_meta ) && count( $custom_meta ) > 1 && 'custom_order_meta' === $custom_meta[0] ) {
								$meta_key = $custom_meta[1];
								if ( $order->meta_exists( $meta_key ) ) {
									$value = $order->get_meta( $meta_key );
									if ( is_array( $value ) ) {
										$value = join( $multi_line_separator, $value );
									}
								}
								$value = apply_filters( 'automator_woocommerce_custom_order_meta_token_parser', $value, $meta_key, $pieces, $order );
							}
						}
						if ( preg_match( '/custom_item_meta/', $parse ) ) {
							$custom_meta = explode( '|', $parse );
							if ( ! empty( $custom_meta ) && count( $custom_meta ) > 1 && 'custom_item_meta' === $custom_meta[0] ) {
								$meta_key = $custom_meta[1];
								$items    = $order->get_items();
								if ( $items ) {
									/** @var WC_Order_Item_Product $item */
									foreach ( $items as $item ) {
										if ( $item->meta_exists( $meta_key ) ) {
											$value = $item->get_meta( $meta_key );
										}
										$value = apply_filters( 'automator_woocommerce_custom_item_meta_token_parser', $value, $meta_key, $pieces, $order, $item );
									}
								}
							}
						}
						break;
				}
				$token        = $parse;
				$token_pieces = $pieces;
				/**
				 * @since 3.2
				 */
				$value = apply_filters( 'automator_woocommerce_token_parser', $value, $token, $token_pieces, $order );
			}
		}

		return $value;
	}

	/**
	 * @param $price
	 *
	 * @return float|mixed
	 */
	public function clean_wc_price( $price ) {
		// Regular expression to match the numeric/float value after the currency symbol
		$pattern = '/<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">.*?<\/span>([0-9,]+(?:\.[0-9]+)?)<\/bdi><\/span>/';

		// Extract the value
		if ( preg_match( $pattern, $price, $matches ) ) {
			// Convert the captured value to a float
			return floatval( str_replace( ',', '', $matches[1] ) );
		}

		return $price;
	}

	/**
	 * @param WC_Order $order
	 * @param          $value_to_match
	 *
	 * @return string
	 */
	public function get_woo_product_names_from_items( WC_Order $order, $value_to_match ) {
		$items          = $order->get_items();
		$product_titles = array();
		if ( $items ) {
			/** @var WC_Order_Item_Product $item */
			foreach ( $items as $item ) {
				if ( absint( $value_to_match ) === absint( $item->get_product_id() ) || intval( '-1' ) === intval( $value_to_match ) ) {
					$product_titles[] = $item->get_product()->get_name();
				}
			}
		}

		return join( ', ', $product_titles );
	}

	/**
	 * @param WC_Order $order
	 * @param          $value_to_match
	 *
	 * @return string
	 */
	public function get_woo_product_ids_from_items( WC_Order $order, $value_to_match ) {
		$items       = $order->get_items();
		$product_ids = array();
		if ( $items ) {
			/** @var WC_Order_Item_Product $item */
			foreach ( $items as $item ) {
				if ( absint( $value_to_match ) === absint( $item->get_product_id() ) || intval( '-1' ) === intval( $value_to_match ) ) {
					$product_ids[] = $item->get_product_id();
				}
			}
		}

		return join( ', ', $product_ids );
	}

	/**
	 * @param \WC_Order $order
	 * @param           $value_to_match
	 * @param bool      $unformatted
	 * @param bool      $sale
	 *
	 * @return string
	 */
	public function get_woo_product_price_from_items( WC_Order $order, $value_to_match, $unformatted = false, $sale = false ) {
		$items          = $order->get_items();
		$product_prices = array();
		if ( $items ) {
			/** @var WC_Order_Item_Product $item */
			foreach ( $items as $item ) {
				if ( absint( $value_to_match ) === absint( $item->get_product_id() ) || intval( '-1' ) === intval( $value_to_match ) ) {
					$product = $item->get_product();
					if ( $unformatted ) {
						$product_prices[] = ! $sale ? $product->get_price() : $product->get_sale_price();
					} else {
						$product_prices[] = ! $sale ? wc_price( $product->get_price() ) : wc_price( $product->get_sale_price() );
					}
				}
			}
		}

		return join( ', ', $product_prices );
	}

	/**
	 * @param WC_Order $order
	 * @param          $value_to_match
	 *
	 * @return string
	 */
	public function get_woo_product_urls_from_items( WC_Order $order, $value_to_match ) {
		$items       = $order->get_items();
		$product_ids = array();
		if ( $items ) {
			/** @var WC_Order_Item_Product $item */
			foreach ( $items as $item ) {
				if ( absint( $value_to_match ) === absint( $item->get_product_id() ) || intval( '-1' ) === intval( $value_to_match ) ) {
					$product_ids[] = get_permalink( $item->get_product_id() );
				}
			}
		}

		return join( ', ', $product_ids );
	}

	/**
	 * @param WC_Order $order
	 * @param          $value_to_match
	 *
	 * @return string
	 */
	public function get_woo_product_image_ids_from_items( $order, $value_to_match ) {
		$items             = $order->get_items();
		$product_image_ids = array();
		if ( $items ) {
			/** @var WC_Order_Item_Product $item */
			foreach ( $items as $item ) {
				if ( absint( $value_to_match ) === absint( $item->get_product_id() ) || intval( '-1' ) === intval( $value_to_match ) ) {
					$product_image_ids[] = get_post_thumbnail_id( $item->get_product_id() );
				}
			}
		}

		return join( ', ', $product_image_ids );
	}

	/**
	 * @param WC_Order $order
	 * @param          $value_to_match
	 *
	 * @return string
	 */
	public function get_woo_product_image_urls_from_items( $order, $value_to_match ) {
		$items              = $order->get_items();
		$product_image_urls = array();
		if ( $items ) {
			/** @var WC_Order_Item_Product $item */
			foreach ( $items as $item ) {
				if ( absint( $value_to_match ) === absint( $item->get_product_id() ) || intval( '-1' ) === intval( $value_to_match ) ) {
					$product_image_urls[] = get_the_post_thumbnail_url( $item->get_product_id(), 'full' );
				}
			}
		}

		return join( ', ', $product_image_urls );
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function wc_order_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}
		$args['meta'] = 'WCSHIPSTATIONSHIPPED';
		$fields       = array();
		$fields[]     = array(
			'tokenId'         => 'TRACKING_NUMBER',
			'tokenName'       => esc_attr__( 'Shipping tracking number', 'uncanny-automator' ),
			'tokenType'       => 'text',
			'tokenIdentifier' => 'WCSHIPSTATIONSHIPPED',
		);
		$fields[]     = array(
			'tokenId'         => 'CARRIER',
			'tokenName'       => esc_attr__( 'Shipping carrier', 'uncanny-automator' ),
			'tokenType'       => 'text',
			'tokenIdentifier' => 'WCSHIPSTATIONSHIPPED',
		);
		$fields[]     = array(
			'tokenId'         => 'SHIP_DATE',
			'tokenName'       => esc_attr__( 'Ship date', 'uncanny-automator' ),
			'tokenType'       => 'text',
			'tokenIdentifier' => 'WCSHIPSTATIONSHIPPED',
		);
		$tokens       = array_merge( $tokens, $fields );

		return $this->wc_possible_tokens( $tokens, $args, 'order' );
	}

	/**
	 * @param $order
	 *
	 * @return string
	 */
	public function build_summary_style_html( $order ) {
		$font_colour      = apply_filters( 'automator_woocommerce_order_summary_text_color', '#000', $order );
		$font_family      = apply_filters( 'automator_woocommerce_order_summary_font_family', "'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif", $order );
		$table_styles     = apply_filters( 'automator_woocommerce_order_summary_table_style', '', $order );
		$border_colour    = apply_filters( 'automator_woocommerce_order_summary_border_color', '#eee', $order );
		$tr_border_colour = apply_filters( 'automator_woocommerce_order_summary_tr_border_color', '#e5e5e5', $order );
		$tr_text_colour   = apply_filters( 'automator_woocommerce_order_summary_tr_text_color', '#636363', $order );
		$td_border_colour = apply_filters( 'automator_woocommerce_order_summary_td_border_color', '#e5e5e5', $order );
		$td_text_colour   = apply_filters( 'automator_woocommerce_order_summary_td_text_color', '#636363', $order );

		$html   = array();
		$html[] = sprintf(
			'<table class="td" cellspacing="0" cellpadding="6" border="1" style="color:%s; border: 1px solid %s; vertical-align: middle; width: 100%%; font-family: %s;%s">',
			$font_colour,
			$border_colour,
			$font_family,
			$table_styles
		);
		$items  = $order->get_items();
		$html[] = '<thead>';
		$html[] = '<tr class="row">';
		$th     = sprintf(
			'<th class="td" scope="col" style="color: %s; border: 1px solid %s; vertical-align: middle; padding: 12px; text-align: left;">',
			$tr_text_colour,
			$tr_border_colour
		);
		$html[] = $th . '<strong>' . apply_filters( 'automator_woocommerce_order_summary_product_title', esc_attr__( 'Product', 'uncanny-automator' ) ) . '</strong></th>';
		$html[] = $th . '<strong>' . apply_filters( 'automator_woocommerce_order_summary_quantity_title', esc_attr__( 'Quantity', 'uncanny-automator' ) ) . '</strong></th>';
		$html[] = $th . '<strong>' . apply_filters( 'automator_woocommerce_order_summary_price_title', esc_attr__( 'Price', 'uncanny-automator' ) ) . '</strong></th>';
		$html[] = '</thead>';
		if ( $items ) {
			/** @var WC_Order_Item_Product $item */
			$td = sprintf(
				'<td class="td" style="color: %s; border: 1px solid %s; padding: 12px; text-align: left; vertical-align: middle; font-family: %s">',
				$td_text_colour,
				$td_border_colour,
				$font_family
			);
			foreach ( $items as $item ) {
				$product = $item->get_product();
				if ( true === apply_filters( 'automator_woocommerce_order_summary_show_product_in_invoice', true, $product, $item, $order ) ) {
					$html[] = '<tr class="order_item">';
					$title  = $product->get_title();
					if ( $item->get_variation_id() ) {
						$variation      = new \WC_Product_Variation( $item->get_variation_id() );
						$variation_name = implode( ' / ', $variation->get_variation_attributes() );
						$title          = apply_filters( 'automator_woocommerce_order_summary_line_item_title', "$title - $variation_name", $product, $item, $order );
					}
					if ( true === apply_filters( 'automator_woocommerce_order_summary_link_to_line_item', true, $product, $item, $order ) ) {
						$title = sprintf( '<a style="color: %s; vertical-align: middle; padding: 12px 0; text-align: left;" href="%s">%s</a>', $td_text_colour, $product->get_permalink(), $title );
					}
					$html[] = sprintf( '%s %s</td>', $td, $title );
					$html[] = $td . $item->get_quantity() . '</td>';
					$html[] = $td . wc_price( $item->get_total() ) . '</td>';
					$html[] = '</tr>';
				}
			}
		}

		$td       = sprintf(
			'<td colspan="2" class="td" style="color: %s; border: 1px solid %s; vertical-align: middle; padding: 12px; text-align: left; border-top-width: 4px;">',
			$td_text_colour,
			$td_border_colour
		);
		$td_right = sprintf(
			'<td class="td" style="color: %s; border: 1px solid %s; vertical-align: middle; padding: 12px; text-align: left; border-top-width: 4px;">',
			$td_text_colour,
			$td_border_colour
		);
		// Subtotal
		if ( true === apply_filters( 'automator_woocommerce_order_summary_show_subtotal', true, $order ) ) {
			$html[] = '<tr>';
			$html[] = $td;
			$html[] = apply_filters( 'automator_woocommerce_order_summary_subtotal_title', esc_attr__( 'Subtotal:', 'uncanny-automator' ) );
			$html[] = '</td>';
			$html[] = $td_right;
			$html[] = $order->get_subtotal_to_display();
			$html[] = '</td>';
			$html[] = '</tr>';
		}
		// Tax
		if ( true === apply_filters( 'automator_woocommerce_order_summary_show_taxes', true, $order ) ) {
			if ( ! empty( $order->get_taxes() ) ) {
				$html[] = '<tr>';
				$html[] = $td;
				$html[] = apply_filters( 'automator_woocommerce_order_summary_tax_title', esc_attr__( 'Tax:', 'uncanny-automator' ) );
				$html[] = '</td>';
				$html[] = $td_right;
				$html[] = wc_price( $order->get_total_tax() );
				$html[] = '</td>';
				$html[] = '</tr>';
			}
		}
		// Payment method
		if ( true === apply_filters( 'automator_woocommerce_order_summary_show_payment_method', true, $order ) ) {
			$html[] = '<tr>';
			$html[] = $td;
			$html[] = apply_filters( 'automator_woocommerce_order_summary_payment_method_title', esc_attr__( 'Payment method:', 'uncanny-automator' ) );
			$html[] = '</td>';
			$html[] = $td_right;
			$html[] = $order->get_payment_method_title();
			$html[] = '</td>';
			$html[] = '</tr>';
		}
		// Total
		if ( true === apply_filters( 'automator_woocommerce_order_summary_show_total', true, $order ) ) {
			$html[] = '<tr>';
			$html[] = $td;
			$html[] = apply_filters( 'automator_woocommerce_order_summary_total_title', esc_attr__( 'Total:', 'uncanny-automator' ) );
			$html[] = '</td>';
			$html[] = $td_right;
			$html[] = $order->get_formatted_order_total();
			$html[] = '</td>';
			$html[] = '</tr>';
		}
		$html[] = '</table>';
		$html   = apply_filters( 'automator_order_summary_html_raw', $html, $order );

		return implode( PHP_EOL, $html );
	}

	/**
	 * Method get_products_skus.
	 *
	 * @param \WC_Order $order Instance of WC_Order.
	 *
	 * @return string The product SKUs (comma separated) .
	 */
	public function get_products_skus( $order ) {

		$skus = array_map(
			function ( $item ) {

				$product = wc_get_product( $item->get_product_id() );

				return $product->get_sku();

			},
			$order->get_items()
		);

		return implode( ', ', $skus );

	}

	/**
	 * @param WC_Order $order
	 * @param          $value_to_match
	 *
	 * @return string
	 */
	public function get_woo_product_categories_from_items( WC_Order $order, $value_to_match ) {
		if ( intval( '-1' ) === intval( $value_to_match ) ) {
			$return = array();
			if ( $order->get_items() ) {
				/** @var \WC_Order_Item_Product $item */
				foreach ( $order->get_items() as $item ) {
					$terms = wp_get_post_terms( $item->get_product_id(), 'product_cat' );
					if ( $terms ) {
						foreach ( $terms as $term ) {
							$return[] = $term->name;
						}
					}
				}
			}

			$return = array_unique( $return );

			return join( ', ', $return );
		}
		$term = get_term_by( 'ID', $value_to_match, 'product_cat' );
		if ( ! $term ) {
			return '';
		}

		return $term->name;
	}

	/**
	 * @param WC_Order $order
	 * @param          $value_to_match
	 *
	 * @return string
	 */
	public function get_woo_product_tags_from_items( WC_Order $order, $value_to_match ) {
		if ( intval( '-1' ) === intval( $value_to_match ) ) {
			$return = array();
			if ( $order->get_items() ) {
				foreach ( $order->get_items() as $item ) {
					$terms = wp_get_post_terms( $item->get_product_id(), 'product_tag' );
					if ( $terms ) {
						foreach ( $terms as $term ) {
							$return[] = $term->name;
						}
					}
				}
			}

			$return = array_unique( $return );

			return join( ', ', $return );
		}
		$term = get_term_by( 'ID', $value_to_match, 'product_tag' );
		if ( ! $term ) {
			return '';
		}

		return $term->name;

	}


	/**
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return false|int|mixed|string|null
	 */
	public function replace_product_only_values( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		if ( empty( $trigger_data ) || empty( $replace_args ) ) {
			return $value;
		}
		$meta_key   = $pieces[2];
		$product_id = Automator()->db->token->get( 'product_id', $replace_args );
		if ( empty( $product_id ) ) {
			return $value;
		}
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof \WC_Product ) {
			return $value;
		}
		switch ( $meta_key ) {
			case 'WOOPRODUCT':
				$value = get_the_title( $product_id );
				break;
			case 'WOOPRODUCT_ID':
				$value = $product_id;
				break;
			case 'WOOPRODUCT_PRODUCT_PRICE':
				$value = wc_price( $product->get_price() );
				break;
			case 'WOOPRODUCT_PRODUCT_PRICE_UNFORMATTED':
				$value = $product->get_price();
				break;
			case 'WOOPRODUCT_PRODUCT_SALE_PRICE':
				$value = wc_price( $product->get_sale_price() );
				break;
			case 'WOOPRODUCT_PRODUCT_SALE_PRICE_UNFORMATTED':
				$value = $product->get_sale_price();
				break;
			case 'WOOPRODUCT_SKU':
				$value = $product->get_sku();
				break;
			case 'WOOPRODUCT_URL':
				$value = get_permalink( $product_id );
				break;
			case 'WOOPRODUCT_THUMB_ID':
				$value = get_post_thumbnail_id( $product_id );
				break;
			case 'WOOPRODUCT_THUMB_URL':
				$value = get_the_post_thumbnail_url( $product_id, 'full' );
				break;
			case 'NUMTIMES':
				$value = absint( $replace_args['run_number'] );
				break;
			case 'WOOPRODUCT_CATEGORIES':
				$return = array();
				$terms  = wp_get_post_terms( $product_id, 'product_cat' );
				if ( $terms ) {
					foreach ( $terms as $term ) {
						$return[] = $term->name;
					}
				}
				$value = join( ', ', $return );
				break;
			case 'WOOPRODUCT_TAGS':
				$return = array();
				$terms  = wp_get_post_terms( $product_id, 'product_tag' );
				if ( $terms ) {
					foreach ( $terms as $term ) {
						$return[] = $term->name;
					}
				}
				$value = join( ', ', $return );
				break;
			default:
				$value = apply_filters( 'automator_woocommerce_product_meta_token_parser', $value, $meta_key, $pieces, $product );
				break;
		}

		return $value;
	}

	/**
	 * Helper function to return country name from provided code.
	 *
	 * @param string $country_code
	 *
	 * @return string $country_name if found, otherwise $country_code
	 */
	public function get_country_name_from_code( $country_code ) {
		$countries = WC()->countries->get_countries();
		if ( ! empty( $countries ) ) {
			foreach ( $countries as $country_key => $country_name ) {
				if ( $country_key === $country_code ) {
					return $country_name;
				}
			}
		}

		return $country_code;
	}

	/**
	 * Helper function to return state name from provided codes.
	 *
	 * @param string $state_code
	 * @param string $country_code
	 *
	 * @return string $state_name if found, otherwise $state_code
	 */
	public function get_state_name_from_codes( $state_code, $country_code ) {
		$states = WC()->countries->get_states( $country_code );
		if ( ! empty( $states ) ) {
			foreach ( $states as $state_key => $state_name ) {
				if ( $state_key === $state_code ) {
					return $state_name;
				}
			}
		}

		return $state_code;
	}
}
