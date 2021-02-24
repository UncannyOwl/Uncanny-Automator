<?php

namespace Uncanny_Automator;


use WC_Order;
use WC_Order_Item_Product;

/**
 * Class Wc_Tokens
 * @package Uncanny_Automator
 */
class Wc_Tokens {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'WC';
	/**
	 * @var array
	 */
	public $possible_order_fields = [];

	/**
	 * Pmp_Tokens constructor.
	 */
	public function __construct() {
		$this->possible_order_fields = array(
			'billing_first_name'  => esc_attr__( 'Billing first name', 'uncanny-automator' ),
			'billing_last_name'   => esc_attr__( 'Billing last name', 'uncanny-automator' ),
			'billing_company'     => esc_attr__( 'Billing company', 'uncanny-automator' ),
			'billing_country'     => esc_attr__( 'Billing country', 'uncanny-automator' ),
			'billing_address_1'   => esc_attr__( 'Billing address line 1', 'uncanny-automator' ),
			'billing_address_2'   => esc_attr__( 'Billing address line 2', 'uncanny-automator' ),
			'billing_city'        => esc_attr__( 'Billing city', 'uncanny-automator' ),
			'billing_state'       => esc_attr__( 'Billing state', 'uncanny-automator' ),
			'billing_postcode'    => esc_attr__( 'Billing postcode', 'uncanny-automator' ),
			'billing_phone'       => esc_attr__( 'Billing phone', 'uncanny-automator' ),
			'billing_email'       => esc_attr__( 'Billing email', 'uncanny-automator' ),
			'shipping_first_name' => esc_attr__( 'Shipping first name', 'uncanny-automator' ),
			'shipping_last_name'  => esc_attr__( 'Shipping last name', 'uncanny-automator' ),
			'shipping_company'    => esc_attr__( 'Shipping company', 'uncanny-automator' ),
			'shipping_country'    => esc_attr__( 'Shipping country', 'uncanny-automator' ),
			'shipping_address_1'  => esc_attr__( 'Shipping address line 1', 'uncanny-automator' ),
			'shipping_address_2'  => esc_attr__( 'Shipping address line 2', 'uncanny-automator' ),
			'shipping_city'       => esc_attr__( 'Shipping city', 'uncanny-automator' ),
			'shipping_state'      => esc_attr__( 'Shipping state', 'uncanny-automator' ),
			'shipping_postcode'   => esc_attr__( 'Shipping postcode', 'uncanny-automator' ),
			'order_id'            => esc_attr__( 'Order ID', 'uncanny-automator' ),
			'order_comments'      => esc_attr__( 'Order comments', 'uncanny-automator' ),
			'order_total'         => esc_attr__( 'Order total', 'uncanny-automator' ),
			'order_total_raw'     => esc_attr__( 'Order total (unformatted)', 'uncanny-automator' ),
			'order_status'        => esc_attr__( 'Order status', 'uncanny-automator' ),
			'order_subtotal'      => esc_attr__( 'Order subtotal', 'uncanny-automator' ),
			'order_subtotal_raw'  => esc_attr__( 'Order subtotal (unformatted)', 'uncanny-automator' ),
			'order_tax'           => esc_attr__( 'Order tax', 'uncanny-automator' ),
			'order_tax_raw'       => esc_attr__( 'Order tax (unformatted)', 'uncanny-automator' ),
			'order_discounts'     => esc_attr__( 'Order discounts', 'uncanny-automator' ),
			'order_discounts_raw' => esc_attr__( 'Order discounts (unformatted)', 'uncanny-automator' ),
			'order_coupons'       => esc_attr__( 'Order coupons', 'uncanny-automator' ),
			'order_products'      => esc_attr__( 'Order products', 'uncanny-automator' ),
			'order_products_qty'  => esc_attr__( 'Order products and quantity', 'uncanny-automator' ),
			'payment_method'      => esc_attr__( 'Payment method', 'uncanny-automator' ),
			'order_products_links'=> esc_attr__( 'Order products links', 'uncanny-automator' ),
		);

		add_action( 'uap_wc_trigger_save_meta', [ $this, 'uap_wc_trigger_save_meta_func' ], 20, 4 );

		//Adding WC tokens
		add_filter( 'automator_maybe_trigger_wc_woordertotal_tokens', [
			$this,
			'wc_ordertotal_possible_tokens'
		], 20, 2 );

		add_filter( 'automator_maybe_trigger_wc_wcorderstatus_tokens', [
			$this,
			'wc_ordertotal_possible_tokens'
		], 20, 2 );

		add_filter( 'automator_maybe_trigger_wc_wooproduct_tokens', [
			$this,
			'wc_wooproduct_possible_tokens'
		], 20, 2 );

		//Parsing data
		add_filter( 'automator_maybe_parse_token', [ $this, 'wc_ordertotal_tokens' ], 20, 6 );
		
		//Adding WC tokens
		add_filter( 'automator_maybe_trigger_wc_wcshipstationshipped_tokens', [
			$this,
			'wc_order_possible_tokens'
		], 20, 2 );
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
				if ( true === $trigger_result['result'] ) {
					global $uncanny_automator;
					$recipe = $uncanny_automator->get_recipes_data( true, $recipe_id );
					if ( is_array( $recipe ) ) {
						$recipe = array_pop( $recipe );
					}
					$triggers = $recipe['triggers'];
					if ( $triggers ) {
						foreach ( $triggers as $trigger ) {
							$trigger_id = $trigger['ID'];
							if ( ! key_exists( 'WOOPRODUCT', $trigger['meta'] )
							     && ! key_exists( 'WOORDERTOTAL', $trigger['meta'] )
							     && ! key_exists( 'WCORDERSTATUS', $trigger['meta'] )
							) {
								continue;
							} else {
								$user_id        = (int) $trigger_result['args']['user_id'];
								$trigger_log_id = (int) $trigger_result['args']['get_trigger_id'];
								$run_number     = (int) $trigger_result['args']['run_number'];

								$args = [
									'user_id'        => $user_id,
									'trigger_id'     => $trigger_id,
									'meta_key'       => 'order_id',
									'meta_value'     => $order_id,
									'run_number'     => $run_number, //get run number
									'trigger_log_id' => $trigger_log_id,
								];

								$uncanny_automator->insert_trigger_meta( $args );
							}
						}
					}
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
	public function wc_ordertotal_possible_tokens( $tokens = [], $args = [] ) {

		return $this->wc_possible_tokens( $tokens, $args, 'order' );
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 * @param string $type
	 *
	 * @return array
	 */
	public function wc_possible_tokens( $tokens = [], $args = [], $type = 'order' ) {

		$fields          = [];
		$trigger_meta    = $args['meta'];
		$possible_tokens = apply_filters( 'automator_woocommerce_possible_tokens', $this->possible_order_fields );
		foreach ( $possible_tokens as $token_id => $input_title ) {
			if ( 'billing_email' === (string) $token_id || 'shipping_email' === (string) $token_id ) {
				$input_type = 'email';
			} else {
				$input_type = 'text';
			}
			$fields[] = [
				'tokenId'         => $token_id,
				'tokenName'       => $input_title,
				'tokenType'       => $input_type,
				'tokenIdentifier' => $trigger_meta,
			];
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
	public function wc_wooproduct_possible_tokens( $tokens = [], $args = [] ) {

		return $this->wc_possible_tokens( $tokens, $args, 'product' );
	}

	/**
	 * Only load this integration and its triggers and actions if the related plugin is active
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
		global $uncanny_automator;
		$trigger_meta  = $pieces[1];
		$parse         = $pieces[2];
		$recipe_log_id = isset( $replace_args['recipe_log_id'] ) ? (int) $replace_args['recipe_log_id'] : $uncanny_automator->maybe_create_recipe_log_entry( $recipe_id, $user_id )['recipe_log_id'];
		if ( $trigger_data && $recipe_log_id ) {
			foreach ( $trigger_data as $trigger ) {
				if ( key_exists( $trigger_meta, $trigger['meta'] ) || ( isset( $trigger['meta']['code'] ) && $trigger_meta === $trigger['meta']['code'] ) ) {
					$trigger_id     = $trigger['ID'];
					$trigger_log_id = $replace_args['trigger_log_id'];
					$order_id       = $uncanny_automator->helpers->recipe->get_form_data_from_trigger_meta( 'order_id', $trigger_id, $trigger_log_id, $user_id );
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
								case 'WOORDERTOTAL':
									$value = wc_price( $order->get_total() );
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
									if ( is_array( $comments ) && ! empty( $comments ) ) {
										$value = '<ul>';
										$value .= '<li>' . implode( '</li><li>', $comments ) . '</li>';
										$value .= '</ul>';
									} else {
										$value = ! empty( $comments ) ? $comments : '';
									}
									break;
								case 'order_status':
									$value = $order->get_status();
									break;
								case 'order_total':
									$value = wc_price( $order->get_total() );
									break;
								case 'order_total_raw':
									$value = $order->get_total();
									break;
								case 'order_subtotal':
									$value = wc_price( $order->get_subtotal() );
									break;
								case 'order_subtotal_raw':
									$value = $order->get_subtotal();
									break;
								case 'order_tax':
									$value = wc_price( $order->get_total_tax() );
									break;
								case 'order_tax_raw':
									$value = $order->get_total_tax();
									break;
								case 'order_discounts':
									$value = wc_price( $order->get_discount_total() * - 1 );
									break;
								case 'order_discounts_raw':
									$value = ( $order->get_discount_total() * - 1 );
									break;
								case 'order_coupons':
									$coupons = $order->get_coupon_codes();
									if ( is_array( $coupons ) ) {
										$value = '<ul>';
										$value .= '<li>' . implode( '</li><li>', $coupons ) . '</li>';
										$value .= '</ul>';
									} else {
										$value = $coupons;
									}

									break;
								case 'order_products':
									$items = $order->get_items();
									if ( $items ) {
										$value = '<ul>';
										/** @var WC_Order_Item_Product $item */
										foreach ( $items as $item ) {
											$product = $item->get_product();
											$value   .= '<li>' . $product->get_title() . '</li>';
										}
										$value .= '</ul>';
									}

									break;
								case 'order_products_qty':
									$items = $order->get_items();
									if ( $items ) {
										$value = '<ul>';
										/** @var WC_Order_Item_Product $item */
										foreach ( $items as $item ) {
											$product = $item->get_product();
											$value   .= '<li>' . $product->get_title() . ' x ' . $item->get_quantity() . '</li>';
										}
										$value .= '</ul>';
									}

									break;
								case 'order_products_links':
									$items = $order->get_items();
									if ( $items ) {
										$value = '<ul>';
										/** @var WC_Order_Item_Product $item */
										foreach ( $items as $item ) {
											$product = $item->get_product();
											$value   .= '<li><a href="' . $product->get_permalink() . '">' . $product->get_title() . '</a></li>';
										}
										$value .= '</ul>';
									}
									
									break;
								case 'payment_method':
									$value = $order->get_payment_method_title();
									break;
								case 'CARRIER':
									$value = $uncanny_automator->helpers->recipe->get_form_data_from_trigger_meta( 'WOOORDER_CARRIER', $trigger_id, $trigger_log_id, $user_id );
									break;
								case 'TRACKING_NUMBER':
									$value = $uncanny_automator->helpers->recipe->get_form_data_from_trigger_meta( 'WOOORDER_TRACKING_NUMBER', $trigger_id, $trigger_log_id, $user_id );
									break;
								case 'SHIP_DATE':
									$value = $uncanny_automator->helpers->recipe->get_form_data_from_trigger_meta( 'WOOORDER_SHIP_DATE', $trigger_id, $trigger_log_id, $user_id );
									$value = $value ? date( 'Y-m-d H:i:s', $value ) : '';
									break;
							}
						}
					}
				}
			}
		}

		return $value;
	}

	/**
	 * @param WC_Order $order
	 * @param $value_to_match
	 *
	 * @return string
	 */
	public function get_woo_product_names_from_items( WC_Order $order, $value_to_match ) {
		$items          = $order->get_items();
		$product_titles = [];
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
	 * @param $value_to_match
	 *
	 * @return string
	 */
	public function get_woo_product_ids_from_items( WC_Order $order, $value_to_match ) {
		$items       = $order->get_items();
		$product_ids = [];
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
	 * @param $value_to_match
	 *
	 * @return string
	 */
	public function get_woo_product_urls_from_items( WC_Order $order, $value_to_match ) {
		$items       = $order->get_items();
		$product_ids = [];
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
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function wc_order_possible_tokens( $tokens = [], $args = [] ) {
		$args['meta'] = 'WCSHIPSTATIONSHIPPED';
		$fields       = [];
		$fields[]     = [
			'tokenId'         => 'TRACKING_NUMBER',
			'tokenName'       => esc_attr__( 'Shipping tracking number', 'uncanny-automator' ),
			'tokenType'       => 'text',
			'tokenIdentifier' => 'WCSHIPSTATIONSHIPPED',
		];
		$fields[]     = [
			'tokenId'         => 'CARRIER',
			'tokenName'       => esc_attr__( 'Shipping carrier', 'uncanny-automator' ),
			'tokenType'       => 'text',
			'tokenIdentifier' => 'WCSHIPSTATIONSHIPPED',
		];
		$fields[]     = [
			'tokenId'         => 'SHIP_DATE',
			'tokenName'       => esc_attr__( 'Ship date', 'uncanny-automator' ),
			'tokenType'       => 'text',
			'tokenIdentifier' => 'WCSHIPSTATIONSHIPPED',
		];
		$tokens       = array_merge( $tokens, $fields );
		
		return $this->wc_possible_tokens( $tokens, $args, 'order' );
	}
}