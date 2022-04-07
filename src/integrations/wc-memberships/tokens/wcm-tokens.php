<?php

namespace Uncanny_Automator;

/**
 * Class Wcm_Tokens
 *
 * @package Uncanny_Automator
 */
class Wcm_Tokens {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WCMEMBERSHIPS';

	/**
	 * Wcm_Tokens constructor.
	 */
	public function __construct() {
		add_filter(
			'automator_maybe_trigger_wcmemberships_wcmmembershipplan_tokens',
			array(
				$this,
				'wcm_possible_order_tokens',
			),
			20,
			2
		);
		add_filter( 'automator_maybe_parse_token', array( $this, 'wcm_parse_tokens' ), 20, 6 );
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
			if ( class_exists( 'WC_Memberships_Loader' ) ) {
				$status = true;
			} else {
				$status = false;
			}
		}

		return $status;
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 * @param string $type
	 *
	 * @return array
	 */
	public function wcm_possible_order_tokens( $tokens = array(), $args = array(), $type = 'order' ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}
		$fields          = array();
		$trigger_meta    = $args['meta'];
		$possible_tokens = array(
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
			'order_status'         => esc_attr__( 'Order status', 'uncanny-automator' ),
			'order_subtotal'       => esc_attr__( 'Order subtotal', 'uncanny-automator' ),
			'order_tax'            => esc_attr__( 'Order tax', 'uncanny-automator' ),
			'order_discounts'      => esc_attr__( 'Order discounts', 'uncanny-automator' ),
			'order_coupons'        => esc_attr__( 'Order coupons', 'uncanny-automator' ),
			'order_products'       => esc_attr__( 'Order products', 'uncanny-automator' ),
			'order_products_qty'   => esc_attr__( 'Order products and quantity', 'uncanny-automator' ),
			'payment_method'       => esc_attr__( 'Payment method', 'uncanny-automator' ),
			'order_products_links' => esc_attr__( 'Order products links', 'uncanny-automator' ),
		);
		foreach ( $possible_tokens as $token_id => $input_title ) {
			if ( 'billing_email' === (string) $token_id || 'shipping_email' === (string) $token_id ) {
				$input_type = 'email';
			} else {
				$input_type = 'text';
			}
			$fields[] = array(
				'tokenId'         => $token_id,
				'tokenName'       => $input_title,
				'tokenType'       => $input_type,
				'tokenIdentifier' => 'WCMPLANORDERID',
			);
		}
		$tokens = array_merge( $tokens, $fields );

		return $tokens;
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
	public function wcm_parse_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		$to_match = array(
			'WCMMEMBERSHIPPLAN',
			'WCMPLANORDERID',
			'WCMUSERADDED',
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

		global $wpdb;
		$trigger_meta  = $pieces[1];
		$parse         = $pieces[2];
		$recipe_log_id = isset( $replace_args['recipe_log_id'] ) ? (int) $replace_args['recipe_log_id'] : Automator()->maybe_create_recipe_log_entry( $recipe_id, $user_id )['recipe_log_id'];
		if ( $trigger_data && $recipe_log_id ) {
			foreach ( $trigger_data as $trigger ) {
				if ( key_exists( $trigger_meta, $trigger['meta'] ) || ( isset( $trigger['meta']['code'] ) && $trigger_meta === $trigger['meta']['code'] ) ) {
					$trigger_id     = $trigger['ID'];
					$trigger_log_id = $replace_args['trigger_log_id'];
					if ( 'WCMMEMBERSHIPPLAN' === $parse ) {
						$trigger_log_id = isset( $replace_args['trigger_log_id'] ) ? absint( $replace_args['trigger_log_id'] ) : 0;
						$entry          = $wpdb->get_var(
							$wpdb->prepare(
								"SELECT meta_value
FROM {$wpdb->prefix}uap_trigger_log_meta
WHERE meta_key = %s
  AND automator_trigger_log_id = %d
  AND automator_trigger_id = %d
LIMIT 0,1",
								'WCMMEMBERSHIPPLAN',
								$trigger_log_id,
								$trigger_id
							)
						);

						if ( ! empty( $entry ) ) {
							$value = get_the_title( $entry );
						}
					} else {
						$order_id = Automator()->helpers->recipe->get_form_data_from_trigger_meta( 'WCMPLANORDERID', $trigger_id, $trigger_log_id, $user_id );
						if ( ! empty( $order_id ) ) {
							$order = wc_get_order( $order_id );
							if ( $order && $order instanceof \WC_Order ) {
								switch ( $parse ) {
									case 'order_id':
										$value = $order_id;
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
									case 'order_subtotal':
										$value = wc_price( $order->get_subtotal() );
										break;
									case 'order_tax':
										$value = wc_price( $order->get_total_tax() );
										break;
									case 'order_discounts':
										$value = wc_price( $order->get_discount_total() * - 1 );
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
											/** @var \WC_Order_Item_Product $item */
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
											/** @var \WC_Order_Item_Product $item */
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
											/** @var \WC_Order_Item_Product $item */
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
								}
							}
						}
					}
				}
			}
		}

		return $value;
	}
}
