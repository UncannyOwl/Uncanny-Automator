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
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WC';
	/**
	 * @var array
	 */
	public $possible_order_fields = array();

	/**
	 * Pmp_Tokens constructor.
	 */
	public function __construct() {
		$this->possible_order_fields = array(
			'billing_first_name'   => esc_attr__( 'Billing first name', 'uncanny-automator' ),
			'billing_last_name'    => esc_attr__( 'Billing last name', 'uncanny-automator' ),
			'billing_company'      => esc_attr__( 'Billing company', 'uncanny-automator' ),
			'billing_country'      => esc_attr__( 'Billing country', 'uncanny-automator' ),
			'billing_address_1'    => esc_attr__( 'Billing address line 1', 'uncanny-automator' ),
			'billing_address_2'    => esc_attr__( 'Billing address line 2', 'uncanny-automator' ),
			'billing_city'         => esc_attr__( 'Billing city', 'uncanny-automator' ),
			'billing_state'        => esc_attr__( 'Billing state', 'uncanny-automator' ),
			'billing_postcode'     => esc_attr__( 'Billing postcode', 'uncanny-automator' ),
			'billing_phone'        => esc_attr__( 'Billing phone', 'uncanny-automator' ),
			'billing_email'        => esc_attr__( 'Billing email', 'uncanny-automator' ),
			'shipping_first_name'  => esc_attr__( 'Shipping first name', 'uncanny-automator' ),
			'shipping_last_name'   => esc_attr__( 'Shipping last name', 'uncanny-automator' ),
			'shipping_company'     => esc_attr__( 'Shipping company', 'uncanny-automator' ),
			'shipping_country'     => esc_attr__( 'Shipping country', 'uncanny-automator' ),
			'shipping_address_1'   => esc_attr__( 'Shipping address line 1', 'uncanny-automator' ),
			'shipping_address_2'   => esc_attr__( 'Shipping address line 2', 'uncanny-automator' ),
			'shipping_city'        => esc_attr__( 'Shipping city', 'uncanny-automator' ),
			'shipping_state'       => esc_attr__( 'Shipping state', 'uncanny-automator' ),
			'shipping_postcode'    => esc_attr__( 'Shipping postcode', 'uncanny-automator' ),
			'order_id'             => esc_attr__( 'Order ID', 'uncanny-automator' ),
			'order_comments'       => esc_attr__( 'Order comments', 'uncanny-automator' ),
			'order_total'          => esc_attr__( 'Order total', 'uncanny-automator' ),
			'order_total_raw'      => esc_attr__( 'Order total (unformatted)', 'uncanny-automator' ),
			'order_status'         => esc_attr__( 'Order status', 'uncanny-automator' ),
			'order_subtotal'       => esc_attr__( 'Order subtotal', 'uncanny-automator' ),
			'order_subtotal_raw'   => esc_attr__( 'Order subtotal (unformatted)', 'uncanny-automator' ),
			'order_tax'            => esc_attr__( 'Order tax', 'uncanny-automator' ),
			'order_tax_raw'        => esc_attr__( 'Order tax (unformatted)', 'uncanny-automator' ),
			'order_discounts'      => esc_attr__( 'Order discounts', 'uncanny-automator' ),
			'order_discounts_raw'  => esc_attr__( 'Order discounts (unformatted)', 'uncanny-automator' ),
			'order_coupons'        => esc_attr__( 'Order coupons', 'uncanny-automator' ),
			'order_products'       => esc_attr__( 'Order products', 'uncanny-automator' ),
			'order_products_qty'   => esc_attr__( 'Order products and quantity', 'uncanny-automator' ),
			'order_qty'            => esc_attr__( 'Order quantity', 'uncanny-automator' ),
			'payment_method'       => esc_attr__( 'Payment method', 'uncanny-automator' ),
			'order_products_links' => esc_attr__( 'Order products links', 'uncanny-automator' ),
			'order_summary'        => esc_attr__( 'Order summary', 'uncanny-automator' ),
		);

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
			20,
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

					$run_number = Automator()->get->trigger_run_number(
						$trigger_result['args']['trigger_id'],
						$trigger_result['args']['get_trigger_id'],
						$trigger_result['args']['user_id']
					);

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
	 * @param array $tokens
	 * @param array $args
	 * @param string $type
	 *
	 * @return array
	 */
	public function wc_possible_tokens( $tokens = array(), $args = array(), $type = 'order' ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}

		$fields          = array();
		$trigger_meta    = $args['meta'];
		$possible_tokens = apply_filters( 'automator_woocommerce_possible_tokens', $this->possible_order_fields );
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

		return $tokens;
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
	 * Only load this integration and its triggers and actions if the related
	 * plugin is active
	 *
	 * @param $status
	 * @param $plugin
	 *
	 * @return bool
	 */
	public function plugin_active( $status, $plugin ) {

		if ( self::$integration === $plugin ) {
			if ( class_exists( 'WooCommerce' ) ) {
				$status = true;
			} else {
				$status = false;
			}
		}

		return $status;
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
		);

		if ( $pieces ) {
			if ( array_intersect( $to_match, $pieces ) ) {
				$value = $this->replace_values( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args );
			}
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

		$trigger_meta         = $pieces[1];
		$parse                = $pieces[2];
		$multi_line_separator = apply_filters( 'automator_woo_multi_item_separator', ' | ', $pieces );
		$recipe_log_id        = isset( $replace_args['recipe_log_id'] ) ? (int) $replace_args['recipe_log_id'] : Automator()->maybe_create_recipe_log_entry( $recipe_id, $user_id )['recipe_log_id'];
		if ( $trigger_data && $recipe_log_id ) {
			foreach ( $trigger_data as $trigger ) {
				if ( ! is_array( $trigger ) || empty( $trigger ) ) {
					continue;
				}
				if ( key_exists( $trigger_meta, $trigger['meta'] ) || ( isset( $trigger['meta']['code'] ) && $trigger_meta === $trigger['meta']['code'] ) ) {
					$trigger_id     = $trigger['ID'];
					$trigger_log_id = $replace_args['trigger_log_id'];
					$order_id       = Automator()->helpers->recipe->get_form_data_from_trigger_meta( 'order_id', $trigger_id, $trigger_log_id, $user_id );
					if ( ! empty( $order_id ) ) {
						$order = wc_get_order( $order_id );
						if ( $order && $order instanceof WC_Order ) {
							switch ( $parse ) {
								case 'order_id':
									$value = $order_id;
									break;
								case 'WCORDERSTATUS':
									$value = $order->get_status();
									break;
								case 'WOOPRODUCT':
									$value_to_match = isset( $trigger['meta'][ $parse ] ) ? $trigger['meta'][ $parse ] : - 1;
									$value          = $this->get_woo_product_names_from_items( $order, $value_to_match );
									break;
								case 'WOOPRODUCT_ID':
									$value_to_match = isset( $trigger['meta'][ $parse ] ) ? $trigger['meta'][ $parse ] : - 1;
									$value          = $this->get_woo_product_ids_from_items( $order, $value_to_match );
									break;
								case 'WOOPRODUCT_URL':
									$value_to_match = isset( $trigger['meta'][ $parse ] ) ? $trigger['meta'][ $parse ] : - 1;
									$value          = $this->get_woo_product_urls_from_items( $order, $value_to_match );
									break;
								case 'WOOPRODUCT_THUMB_ID':
									$value_to_match = isset( $trigger['meta'][ $parse ] ) ? $trigger['meta'][ $parse ] : - 1;
									$value          = $this->get_woo_product_image_ids_from_items( $order, $value_to_match );
									break;
								case 'WOOPRODUCT_THUMB_URL':
									$value_to_match = isset( $trigger['meta'][ $parse ] ) ? $trigger['meta'][ $parse ] : - 1;
									$value          = $this->get_woo_product_image_urls_from_items( $order, $value_to_match );
									break;
								case 'WOOPRODUCT_ORDER_QTY':
									$product_id   = isset( $trigger['meta']['WOOPRODUCT'] ) ? intval( $trigger['meta']['WOOPRODUCT'] ) : - 1;
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
								case 'billing_postcode':
									$value = $order->get_billing_postcode();
									break;
								case 'billing_phone':
									$value = $order->get_billing_phone();
									break;
								case 'billing_email':
									$value = $order->get_billing_email();
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
									$value = strip_tags( wc_price( $order->get_total() ) );
									break;
								case 'order_total_raw':
									$value = $order->get_total();
									break;
								case 'order_subtotal':
									$value = strip_tags( wc_price( $order->get_subtotal() ) );
									break;
								case 'order_subtotal_raw':
									$value = $order->get_subtotal();
									break;
								case 'order_tax':
									$value = strip_tags( wc_price( $order->get_total_tax() ) );
									break;
								case 'order_tax_raw':
									$value = $order->get_total_tax();
									break;
								case 'order_discounts':
									$value = strip_tags( wc_price( $order->get_discount_total() * - 1 ) );
									break;
								case 'order_discounts_raw':
									$value = ( $order->get_discount_total() * - 1 );
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
								case 'CARRIER':
									$value = Automator()->helpers->recipe->get_form_data_from_trigger_meta( 'WOOORDER_CARRIER', $trigger_id, $trigger_log_id, $user_id );
									break;
								case 'TRACKING_NUMBER':
									$value = Automator()->helpers->recipe->get_form_data_from_trigger_meta( 'WOOORDER_TRACKING_NUMBER', $trigger_id, $trigger_log_id, $user_id );
									break;
								case 'SHIP_DATE':
									$value = Automator()->helpers->recipe->get_form_data_from_trigger_meta( 'WOOORDER_SHIP_DATE', $trigger_id, $trigger_log_id, $user_id );
									$value = $value ? date( 'Y-m-d H:i:s', $value ) : ''; //phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
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
				}
			}
		}

		return $value;
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
				if ( absint( $value_to_match ) === absint( $item->get_product_id() ) || absint( '-1' ) === absint( $value_to_match ) ) {
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
				if ( absint( $value_to_match ) === absint( $item->get_product_id() ) || absint( '-1' ) === absint( $value_to_match ) ) {
					$product_ids[] = $item->get_product_id();
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
	public function get_woo_product_urls_from_items( WC_Order $order, $value_to_match ) {
		$items       = $order->get_items();
		$product_ids = array();
		if ( $items ) {
			/** @var WC_Order_Item_Product $item */
			foreach ( $items as $item ) {
				if ( absint( $value_to_match ) === absint( $item->get_product_id() ) || absint( '-1' ) === absint( $value_to_match ) ) {
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
				if ( absint( $value_to_match ) === absint( $item->get_product_id() ) || absint( '-1' ) === absint( $value_to_match ) ) {
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
				if ( absint( $value_to_match ) === absint( $item->get_product_id() ) || absint( '-1' ) === absint( $value_to_match ) ) {
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
	private function build_summary_style_html( $order ) {
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

}
