<?php

namespace Uncanny_Automator;

class Wss_Tokens {

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'automator_before_trigger_completed', array( $this, 'save_token_data' ), 20, 2 );
		add_filter( 'automator_maybe_trigger_wholesalesuite_tokens', array( $this, 'wss_order_tokens' ), 20, 2 );
		add_filter(
			'automator_maybe_trigger_wholesalesuite_tokens',
			array(
				$this,
				'wss_leads_possible_tokens',
			),
			20,
			2
		);
		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_wss_tokens' ), 20, 6 );
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

		$trigger_meta_order_tokens = apply_filters(
			'automator_wholesale_suite_save_order_tokens',
			array( 'WSS_ORDER_RECEIVED' ),
			$args
		);

		$trigger_meta_lead_tokens = apply_filters(
			'automator_wholesale_suite_save_lead_tokens',
			array( 'WSS_LEAD_CREATED' ),
			$args
		);

		if ( in_array( $args['entry_args']['code'], $trigger_meta_order_tokens ) ) {
			$order_id          = $args['trigger_args'][0];
			$trigger_log_entry = $args['trigger_entry'];
			if ( ! empty( $order_id ) ) {
				Automator()->db->token->save( 'order_id', $order_id, $trigger_log_entry );
			}
		}

		if ( in_array( $args['entry_args']['code'], $trigger_meta_lead_tokens ) ) {
			$new_lead          = $args['trigger_args'][0];
			$trigger_log_entry = $args['trigger_entry'];
			if ( is_object( $new_lead ) ) {
				Automator()->db->token->save( 'lead_id', $new_lead->ID, $trigger_log_entry );
			}
		}
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array|array[]|mixed
	 */
	public function wss_order_tokens( $tokens = array(), $args = array() ) {
		$trigger_code = $args['triggers_meta']['code'];

		$trigger_validations = apply_filters(
			'automator_wholesale_suite_validate_common_order_tokens',
			array( 'WSS_ORDER_RECEIVED' ),
			$args
		);

		if ( in_array( $trigger_code, $trigger_validations, true ) ) {
			$possible_order_tokens = array(
				'WOOPRODUCT_ID'         => esc_attr__( 'Product ID', 'uncanny-automator' ),
				'WOOPRODUCT_URL'        => esc_attr__( 'Product URL', 'uncanny-automator' ),
				'WOOPRODUCT_TITLE'      => esc_attr__( 'Product title', 'uncanny-automator' ),
				'WOOPRODUCT_THUMB_ID'   => esc_attr__( 'Product featured image ID', 'uncanny-automator' ),
				'WOOPRODUCT_THUMB_URL'  => esc_attr__( 'Product featured image URL', 'uncanny-automator' ),
				'WOOPRODUCT_CATEGORIES' => esc_attr__( 'Product categories', 'uncanny-automator' ),
				'WOOPRODUCT_TAGS'       => esc_attr__( 'Product tags', 'uncanny-automator' ),
				'WOOPRODUCT_PRICE'      => esc_attr__( 'Product price', 'uncanny-automator' ),
				'product_sku'           => esc_attr__( 'Product SKU', 'uncanny-automator' ),
				'billing_first_name'    => esc_attr__( 'Billing first name', 'uncanny-automator' ),
				'billing_last_name'     => esc_attr__( 'Billing last name', 'uncanny-automator' ),
				'billing_company'       => esc_attr__( 'Billing company', 'uncanny-automator' ),
				'billing_country'       => esc_attr__( 'Billing country', 'uncanny-automator' ),
				'billing_address_1'     => esc_attr__( 'Billing address line 1', 'uncanny-automator' ),
				'billing_address_2'     => esc_attr__( 'Billing address line 2', 'uncanny-automator' ),
				'billing_city'          => esc_attr__( 'Billing city', 'uncanny-automator' ),
				'billing_state'         => esc_attr__( 'Billing state', 'uncanny-automator' ),
				'billing_postcode'      => esc_attr__( 'Billing postcode', 'uncanny-automator' ),
				'billing_phone'         => esc_attr__( 'Billing phone', 'uncanny-automator' ),
				'billing_email'         => esc_attr__( 'Billing email', 'uncanny-automator' ),
				'shipping_first_name'   => esc_attr__( 'Shipping first name', 'uncanny-automator' ),
				'shipping_last_name'    => esc_attr__( 'Shipping last name', 'uncanny-automator' ),
				'shipping_company'      => esc_attr__( 'Shipping company', 'uncanny-automator' ),
				'shipping_country'      => esc_attr__( 'Shipping country', 'uncanny-automator' ),
				'shipping_address_1'    => esc_attr__( 'Shipping address line 1', 'uncanny-automator' ),
				'shipping_address_2'    => esc_attr__( 'Shipping address line 2', 'uncanny-automator' ),
				'shipping_city'         => esc_attr__( 'Shipping city', 'uncanny-automator' ),
				'shipping_state'        => esc_attr__( 'Shipping state', 'uncanny-automator' ),
				'shipping_postcode'     => esc_attr__( 'Shipping postcode', 'uncanny-automator' ),
				'order_date'            => esc_attr__( 'Order date', 'uncanny-automator' ),
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
				'order_fees'            => esc_attr__( 'Order fees', 'uncanny-automator' ),
				'order_shipping'        => esc_attr__( 'Order shipping', 'uncanny-automator' ),
				'payment_method'        => esc_attr__( 'Payment method', 'uncanny-automator' ),
				'shipping_method'       => esc_attr__( 'Shipping method', 'uncanny-automator' ),
				'price_type'            => esc_attr__( 'Price type', 'uncanny-automator' ),
			);
			$fields                = array();
			foreach ( $possible_order_tokens as $token_id => $input_title ) {
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
					'tokenIdentifier' => $trigger_code,
				);
			}

			$tokens = array_merge( $tokens, $fields );
		}

		return $tokens;
	}

	/**
	 * @param $tokens
	 * @param $args
	 *
	 * @return array|mixed
	 */
	public function wss_leads_possible_tokens( $tokens = array(), $args = array() ) {
		$trigger_code = $args['triggers_meta']['code'];

		$trigger_meta_validations = apply_filters(
			'automator_wholesale_suite_validate_common_lead_tokens',
			array( 'WSS_LEAD_CREATED' ),
			$args
		);

		if ( in_array( $trigger_code, $trigger_meta_validations, true ) ) {
			$helper     = new Wholesale_Suite_Helpers();
			$all_fields = $helper->get_all_lead_form_fields();
			$fields     = array();
			foreach ( $all_fields as $key => $field ) {
				$fields[] = array(
					'tokenId'         => $key,
					'tokenName'       => $field['label'],
					'tokenType'       => $field['type'],
					'tokenIdentifier' => $trigger_code,
				);
			}

			$tokens = array_merge( $tokens, $fields );
		}

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
	 * @return float|int|mixed|string|null
	 */
	public function parse_wss_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if ( ! is_array( $pieces ) || ! isset( $pieces[1] ) || ! isset( $pieces[2] ) ) {
			return $value;
		}

		$trigger_order_tokens = apply_filters(
			'automator_wholesale_suite_parse_order_tokens',
			array( 'WSS_ORDER_RECEIVED' ),
			array(
				'pieces'       => $pieces,
				'recipe_id'    => $recipe_id,
				'trigger_data' => $trigger_data,
				'user_id'      => $user_id,
				'replace_args' => $replace_args,
			)
		);

		$trigger_lead_tokens = apply_filters(
			'automator_wholesale_suite_parse_lead_tokens',
			array( 'WSS_LEAD_CREATED' ),
			array(
				'pieces'       => $pieces,
				'recipe_id'    => $recipe_id,
				'trigger_data' => $trigger_data,
				'user_id'      => $user_id,
				'replace_args' => $replace_args,
			)
		);

		if ( ! array_intersect( $trigger_order_tokens, $pieces ) && ! array_intersect( $trigger_lead_tokens, $pieces ) ) {
			return $value;
		}

		$multi_line_separator = apply_filters( 'automator_woo_multi_item_separator', ' | ', $pieces );
		if ( array_intersect( $trigger_order_tokens, $pieces ) ) {
			$order_id = Automator()->db->token->get( 'order_id', $replace_args );
			if ( ! empty( $order_id ) ) {
				$order = wc_get_order( $order_id );
				if ( ! $order instanceof \WC_Order ) {
					return $value;
				}
			}
		}

		if ( array_intersect( $trigger_lead_tokens, $pieces ) ) {
			$lead_id = Automator()->db->token->get( 'lead_id', $replace_args );
		}

		$parse = $pieces[2];
		switch ( $parse ) {
			case 'order_id':
				$value = $order_id;
				break;
			case 'WOOPRODUCT_TITLE':
				$value_to_match = isset( $trigger['meta'][ $parse ] ) ? $trigger['meta'][ $parse ] : - 1;
				$value          = $this->get_woo_product_names_from_items( $order, $value_to_match );
				break;
			case 'WOOPRODUCT_ID':
				$value_to_match = isset( $trigger['meta'][ $parse ] ) ? $trigger['meta'][ $parse ] : - 1;
				$value          = $this->get_woo_product_ids_from_items( $order, $value_to_match );
				break;
			case 'WOOPRODUCT_PRICE':
				$value_to_match = isset( $trigger['meta'][ $parse ] ) ? $trigger['meta'][ $parse ] : - 1;
				$value          = $this->get_woo_product_price_from_items( $order, $value_to_match );
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
			case 'order_date':
				$value = $order->get_date_created()->format( get_option( 'date_format', 'F j, Y' ) );
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
			case 'order_fees':
				$value = wc_price( $order->get_total_fees() );
				break;
			case 'order_shipping':
				$value = wc_price( $order->get_shipping_total() );
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
					/** @var \WC_Order_Item_Product $item */
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
					/** @var \WC_Order_Item_Product $item */
					foreach ( $items as $item ) {
						$product = $item->get_product();
						$prods[] = $product->get_title() . ' x ' . $item->get_quantity();
					}
				}
				$value = implode( $multi_line_separator, $prods );

				break;
			case 'order_qty':
				$qty = 0;
				/** @var \WC_Order_Item_Product $item */
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
					/** @var \WC_Order_Item_Product $item */
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
			case 'shipping_method':
				$value = $order->get_shipping_method();
				break;
			case 'product_sku':
				$value = $this->get_products_skus( $order );
				break;
			case 'WOOPRODUCT_CATEGORIES':
				$value_to_match = isset( $trigger['meta'][ $parse ] ) ? $trigger['meta'][ $parse ] : - 1;
				$value          = $this->get_woo_product_categories_from_items( $order, $value_to_match );
				break;
			case 'WOOPRODUCT_TAGS':
				$value_to_match = isset( $trigger['meta'][ $parse ] ) ? $trigger['meta'][ $parse ] : - 1;
				$value          = $this->get_woo_product_tags_from_items( $order, $value_to_match );
				break;
			case 'order_summary':
				$value = $this->build_summary_style_html( $order );
				break;
			case 'WSS_CUSTOMER_ROLE':
				$value = get_post_meta( $order_id, 'wwp_wholesale_role', true );
				break;
			case 'price_type':
				$items = $order->get_items();
				$prods = array();
				if ( $items ) {
					/** @var \WC_Order_Item_Product $item */
					foreach ( $items as $item ) {
						$prods[] = ( wc_get_order_item_meta( $item->get_id(), '_wwp_wholesale_priced', true ) === 'yes' ) ? 'wholesale price' : 'retail price';
					}
				}
				$value = implode( $multi_line_separator, $prods );
				break;
			case 'wwlc_username':
				$user  = get_userdata( $lead_id );
				$value = $user->user_login;
				break;
			case 'user_email':
				$user  = get_userdata( $lead_id );
				$value = $user->user_email;
				break;
			case 'wwlc_role':
				$value = wwlc_get_user_role( $lead_id );
				break;
			default:
				$value = get_user_meta( $lead_id, $parse, true );
				break;
		}

		return $value;
	}

	/**
	 * @param \WC_Order $order
	 * @param          $value_to_match
	 *
	 * @return string
	 */
	public function get_woo_product_names_from_items( \WC_Order $order, $value_to_match ) {
		$items          = $order->get_items();
		$product_titles = array();
		if ( $items ) {
			/** @var \WC_Order_Item_Product $item */
			foreach ( $items as $item ) {
				if ( absint( $value_to_match ) === absint( $item->get_product_id() ) || absint( '-1' ) === absint( $value_to_match ) ) {
					$product_titles[] = $item->get_product()->get_name();
				}
			}
		}

		return join( ', ', $product_titles );
	}

	/**
	 * @param \WC_Order $order
	 * @param          $value_to_match
	 *
	 * @return string
	 */
	public function get_woo_product_ids_from_items( \WC_Order $order, $value_to_match ) {
		$items       = $order->get_items();
		$product_ids = array();
		if ( $items ) {
			/** @var \WC_Order_Item_Product $item */
			foreach ( $items as $item ) {
				if ( absint( $value_to_match ) === absint( $item->get_product_id() ) || absint( '-1' ) === absint( $value_to_match ) ) {
					$product_ids[] = $item->get_product_id();
				}
			}
		}

		return join( ', ', $product_ids );
	}

	/**
	 * @param \WC_Order $order
	 * @param $value_to_match
	 *
	 * @return string
	 */
	public function get_woo_product_price_from_items( \WC_Order $order, $value_to_match ) {
		$items          = $order->get_items();
		$product_prices = array();
		if ( $items ) {
			/** @var \WC_Order_Item_Product $item */
			foreach ( $items as $item ) {
				if ( absint( $value_to_match ) === absint( $item->get_product_id() ) || absint( '-1' ) === absint( $value_to_match ) ) {
					$product = $item->get_product();
					$price   = $product->get_price();
					$type    = wc_get_order_item_meta( $item->get_id(), '_wwp_wholesale_priced', true );
					if ( 'yes' === $type ) {
						$role  = get_post_meta( $order->get_id(), 'wwp_wholesale_role', true );
						$price = get_post_meta( $product->get_id(), $role . '_wholesale_price', true );
					}
					$product_prices[] = wc_price( $price );
				}
			}
		}

		return join( ', ', $product_prices );
	}

	/**
	 * @param \WC_Order $order
	 * @param          $value_to_match
	 *
	 * @return string
	 */
	public function get_woo_product_urls_from_items( \WC_Order $order, $value_to_match ) {
		$items       = $order->get_items();
		$product_ids = array();
		if ( $items ) {
			/** @var \WC_Order_Item_Product $item */
			foreach ( $items as $item ) {
				if ( absint( $value_to_match ) === absint( $item->get_product_id() ) || absint( '-1' ) === absint( $value_to_match ) ) {
					$product_ids[] = get_permalink( $item->get_product_id() );
				}
			}
		}

		return join( ', ', $product_ids );
	}

	/**
	 * @param \WC_Order $order
	 * @param          $value_to_match
	 *
	 * @return string
	 */
	public function get_woo_product_image_ids_from_items( $order, $value_to_match ) {
		$items             = $order->get_items();
		$product_image_ids = array();
		if ( $items ) {
			/** @var \WC_Order_Item_Product $item */
			foreach ( $items as $item ) {
				if ( absint( $value_to_match ) === absint( $item->get_product_id() ) || absint( '-1' ) === absint( $value_to_match ) ) {
					$product_image_ids[] = get_post_thumbnail_id( $item->get_product_id() );
				}
			}
		}

		return join( ', ', $product_image_ids );
	}

	/**
	 * @param \WC_Order $order
	 * @param          $value_to_match
	 *
	 * @return string
	 */
	public function get_woo_product_image_urls_from_items( $order, $value_to_match ) {
		$items              = $order->get_items();
		$product_image_urls = array();
		if ( $items ) {
			/** @var \WC_Order_Item_Product $item */
			foreach ( $items as $item ) {
				if ( absint( $value_to_match ) === absint( $item->get_product_id() ) || absint( '-1' ) === absint( $value_to_match ) ) {
					$product_image_urls[] = get_the_post_thumbnail_url( $item->get_product_id(), 'full' );
				}
			}
		}

		return join( ', ', $product_image_urls );
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
			/** @var \WC_Order_Item_Product $item */
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
	 * @param \WC_Order $order
	 * @param $value_to_match
	 *
	 * @return string
	 */
	public function get_woo_product_categories_from_items( \WC_Order $order, $value_to_match ) {
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
	 * @param \WC_Order $order
	 * @param $value_to_match
	 *
	 * @return string
	 */
	public function get_woo_product_tags_from_items( \WC_Order $order, $value_to_match ) {
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
}
