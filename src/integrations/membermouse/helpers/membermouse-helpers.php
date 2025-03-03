<?php

namespace Uncanny_Automator\Integrations\MemberMouse;

/**
 * Class Membermouse_Helper
 *
 * @package Uncanny_Automator
 */
class Membermouse_Helpers {


	/**
	 * @param bool $include_cf
	 *
	 * @return array[]
	 */
	public function get_all_member_tokens( $include_cf = false ) {
		$tokens = array(
			array(
				'tokenId'   => 'MM_MEMBER_ID',
				'tokenName' => esc_html__( 'Member ID', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'MM_MEMBER_USERNAME',
				'tokenName' => esc_html__( 'Member username', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_MEMBER_EMAIL',
				'tokenName' => esc_html__( 'Member email', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'MM_MEMBER_FIRST_NAME',
				'tokenName' => esc_html__( 'Member first name', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_MEMBER_LAST_NAME',
				'tokenName' => esc_html__( 'Member last name', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_MEMBER_PHONE',
				'tokenName' => esc_html__( 'Member phone', 'uncanny-automator' ),
				'tokenType' => 'tel',
			),
			array(
				'tokenId'   => 'MM_MEMBER_STATUS',
				'tokenName' => esc_html__( 'Member status', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_MEMBER_MEMBERSHIP_LEVEL',
				'tokenName' => esc_html__( 'Membership level', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_MEMBER_SINCE',
				'tokenName' => esc_html__( 'Member since', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'MM_MEMBER_BILLING_ADDRESS',
				'tokenName' => esc_html__( 'Billing address', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_MEMBER_BILLING_ADDRESS2',
				'tokenName' => esc_html__( 'Billing address2', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_MEMBER_BILLING_CITY',
				'tokenName' => esc_html__( 'Billing city', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_MEMBER_BILLING_STATE',
				'tokenName' => esc_html__( 'Billing state', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_MEMBER_BILLING_ZIPCODE',
				'tokenName' => esc_html__( 'Billing zipcode', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_MEMBER_BILLING_COUNTRY',
				'tokenName' => esc_html__( 'Billing country', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_MEMBER_SHIPPING_ADDRESS',
				'tokenName' => esc_html__( 'Shipping address', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_MEMBER_SHIPPING_ADDRESS2',
				'tokenName' => esc_html__( 'Shipping address2', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_MEMBER_SHIPPING_CITY',
				'tokenName' => esc_html__( 'Shipping city', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_MEMBER_SHIPPING_STATE',
				'tokenName' => esc_html__( 'Shipping state', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_MEMBER_SHIPPING_ZIPCODE',
				'tokenName' => esc_html__( 'Shipping zipcode', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_MEMBER_SHIPPING_COUNTRY',
				'tokenName' => esc_html__( 'Shipping country', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);

		if ( true === $include_cf ) {
			$custom_fields = $this->get_custom_fields();
			foreach ( $custom_fields as $id => $custom_field ) {
				$tokens[] = array(
					'tokenId'   => $id,
					'tokenName' => $custom_field,
					'tokenType' => 'text',
				);
			}
		}

		return $tokens;
	}

	/**
	 * @return array[]
	 */
	public function get_all_bundle_tokens() {
		return array(
			array(
				'tokenId'   => 'MM_BUNDLE_ID',
				'tokenName' => esc_html__( 'Bundle ID', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'MM_BUNDLE_NAME',
				'tokenName' => esc_html__( 'Bundle name', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_BUNDLE_STATUS',
				'tokenName' => esc_html__( 'Bundle status', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_BUNDLE_TYPE',
				'tokenName' => esc_html__( 'Bundle type', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_BUNDLE_ACTIVE_DAYS',
				'tokenName' => esc_html__( 'Day(s) with bundle', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'MM_BUNDLE_APPLIED_DATE',
				'tokenName' => esc_html__( 'Bundle applied date', 'uncanny-automator' ),
				'tokenType' => 'datetime',
			),
			array(
				'tokenId'   => 'MM_BUNDLE_EXPIRY_DATE',
				'tokenName' => esc_html__( 'Bundle expires on', 'uncanny-automator' ),
				'tokenType' => 'datetime',
			),
			array(
				'tokenId'   => 'MM_BUNDLE_CANCEL_DATE',
				'tokenName' => esc_html__( 'Bundle cancels on', 'uncanny-automator' ),
				'tokenType' => 'datetime',
			),
		);
	}

	/**
	 * @return array[]
	 */
	public function get_all_product_tokens() {
		return array(
			array(
				'tokenId'   => 'MM_PRODUCT_ID',
				'tokenName' => esc_html__( 'Product ID', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'MM_PRODUCT_REFERENCE_KEY',
				'tokenName' => esc_html__( 'Product reference key', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'MM_PRODUCT_NAME',
				'tokenName' => esc_html__( 'Product name', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_PRODUCT_SKU',
				'tokenName' => esc_html__( 'Product SKU', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_PRODUCT_DESCRIPTION',
				'tokenName' => esc_html__( 'Product description', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_PRODUCT_PRICE',
				'tokenName' => esc_html__( 'Product price', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'MM_PRODUCT_CURRENCY',
				'tokenName' => esc_html__( 'Product currency', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_PRODUCT_IS_SHIPPABLE',
				'tokenName' => esc_html__( 'Is shippable product?', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_PRODUCT_REBILL_PERIOD',
				'tokenName' => esc_html__( 'Subscription rebill period', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_PRODUCT_REBILL_FREQUENCY',
				'tokenName' => esc_html__( 'Subscription rebill frequency', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_PRODUCT_LIMIT_PAYMENTS',
				'tokenName' => esc_html__( 'Has payment limit?', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_PRODUCT_NUMBER_OF_PAYMENTS',
				'tokenName' => esc_html__( 'Payment plan limit', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'MM_PRODUCT_HAS_TRAIL',
				'tokenName' => esc_html__( 'Has trial?', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_PRODUCT_TRAIL_LIMIT',
				'tokenName' => esc_html__( 'Has trial limit?', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_PRODUCT_TRAIL_LIMIT_ALT_PRODUCT_ID',
				'tokenName' => esc_html__( 'Alternative product ID', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_PRODUCT_TRAIL_AMOUNT',
				'tokenName' => esc_html__( 'Trial amount', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_PRODUCT_TRAIL_DURATION',
				'tokenName' => esc_html__( 'Trial duration', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_PRODUCT_TRAIL_FREQUENCY',
				'tokenName' => esc_html__( 'Trial frequency', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * @return array[]
	 */
	public function get_all_order_tokens() {
		return array(
			array(
				'tokenId'   => 'MM_ORDER_NUMBER',
				'tokenName' => esc_html__( 'Order number', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'MM_ORDER_TRANSACTION_ID',
				'tokenName' => esc_html__( 'Transaction ID', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'MM_ORDER_IP_ADDRESS',
				'tokenName' => esc_html__( 'IP address', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_ORDER_SHIPPING_METHOD',
				'tokenName' => esc_html__( 'Shipping method', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_ORDER_SHIPPING',
				'tokenName' => esc_html__( 'Shipping charges', 'uncanny-automator' ),
				'tokenType' => 'float',
			),
			array(
				'tokenId'   => 'MM_ORDER_BILLING_ADDRESS',
				'tokenName' => esc_html__( 'Billing address', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_ORDER_BILLING_ADDRESS2',
				'tokenName' => esc_html__( 'Billing address - Line 2', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_ORDER_BILLING_CITY',
				'tokenName' => esc_html__( 'Billing city', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_ORDER_BILLING_STATE',
				'tokenName' => esc_html__( 'Billing state', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_ORDER_BILLING_ZIPCODE',
				'tokenName' => esc_html__( 'Billing zipcode', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_ORDER_BILLING_COUNTRY',
				'tokenName' => esc_html__( 'Billing country', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_ORDER_SHIPPING_ADDRESS',
				'tokenName' => esc_html__( 'Shipping address', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_ORDER_SHIPPING_ADDRESS2',
				'tokenName' => esc_html__( 'Shipping address - Line 2', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_ORDER_SHIPPING_CITY',
				'tokenName' => esc_html__( 'Shipping city', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_ORDER_SHIPPING_STATE',
				'tokenName' => esc_html__( 'Shipping state', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_ORDER_SHIPPING_ZIPCODE',
				'tokenName' => esc_html__( 'Shipping zipcode', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_ORDER_SHIPPING_COUNTRY',
				'tokenName' => esc_html__( 'Shipping country', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_ORDER_AFFILIATE_ID',
				'tokenName' => esc_html__( 'Affiliate ID', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'MM_ORDER_SUBAFFILIATE_ID',
				'tokenName' => esc_html__( 'Sub affiliate ID', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'MM_ORDER_PRODUCTS',
				'tokenName' => esc_html__( 'Order product(s)', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			//          array(
			//              'tokenId'   => 'MM_ORDER_PRORATIONS',
			//              'tokenName' => esc_html__( 'Order propations', 'uncanny-automator' ),
			//              'tokenType' => 'text',
			//          ),
				array(
					'tokenId'   => 'MM_ORDER_COUPONS',
					'tokenName' => esc_html__( 'Order coupon(s)', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
			array(
				'tokenId'   => 'MM_ORDER_DATE',
				'tokenName' => esc_html__( 'Order date', 'uncanny-automator' ),
				'tokenType' => 'datetime',
			),
			array(
				'tokenId'   => 'MM_ORDER_TOTAL',
				'tokenName' => esc_html__( 'Order total', 'uncanny-automator' ),
				'tokenType' => 'float',
			),
			array(
				'tokenId'   => 'MM_ORDER_SUBTOTAL',
				'tokenName' => esc_html__( 'Order subtotal', 'uncanny-automator' ),
				'tokenType' => 'float',
			),
			array(
				'tokenId'   => 'MM_ORDER_DISCOUNT',
				'tokenName' => esc_html__( 'Order discount', 'uncanny-automator' ),
				'tokenType' => 'float',
			),
		);
	}

	/**
	 * @param $mm_data
	 *
	 * @return array
	 */
	public function parse_mm_token_values( $mm_data ) {

		return array(
			'MM_MEMBER_ID'                => $mm_data['member_id'],
			'MM_MEMBER_SINCE'             => $mm_data['days_as_member'] . ' day(s)',
			'MM_MEMBER_STATUS'            => $mm_data['status_name'],
			'MM_MEMBER_MEMBERSHIP_LEVEL'  => $mm_data['membership_level_name'],
			'MM_MEMBER_FIRST_NAME'        => $mm_data['first_name'],
			'MM_MEMBER_LAST_NAME'         => $mm_data['last_name'],
			'MM_MEMBER_USERNAME'          => $mm_data['username'],
			'MM_MEMBER_EMAIL'             => $mm_data['email'],
			'MM_MEMBER_PHONE'             => $mm_data['phone'],
			'MM_MEMBER_BILLING_ADDRESS'   => $mm_data['billing_address'],
			'MM_MEMBER_BILLING_ADDRESS2'  => $mm_data['billing_address2'],
			'MM_MEMBER_BILLING_CITY'      => $mm_data['billing_city'],
			'MM_MEMBER_BILLING_STATE'     => $mm_data['billing_state'],
			'MM_MEMBER_BILLING_ZIPCODE'   => $mm_data['billing_zip_code'],
			'MM_MEMBER_BILLING_COUNTRY'   => $mm_data['billing_country'],
			'MM_MEMBER_SHIPPING_ADDRESS'  => $mm_data['shipping_address'],
			'MM_MEMBER_SHIPPING_ADDRESS2' => $mm_data['shipping_address2'],
			'MM_MEMBER_SHIPPING_CITY'     => $mm_data['shipping_city'],
			'MM_MEMBER_SHIPPING_STATE'    => $mm_data['shipping_state'],
			'MM_MEMBER_SHIPPING_ZIPCODE'  => $mm_data['shipping_zip_code'],
			'MM_MEMBER_SHIPPING_COUNTRY'  => $mm_data['shipping_country'],
		);
	}

	/**
	 * @param $mm_data
	 *
	 * @return array
	 */
	public function parse_mm_bundle_token_values( $mm_data ) {
		$bundle_details = \MM_AppliedBundle::getAppliedBundle( $mm_data['member_id'], $mm_data['bundle_id'] );
		$bundle         = new \MM_Bundle( $mm_data['bundle_id'] );
		$bundle_type    = $bundle->isFree();

		return array(
			'MM_BUNDLE_ID'           => $mm_data['bundle_id'],
			'MM_BUNDLE_NAME'         => $mm_data['bundle_name'],
			'MM_BUNDLE_STATUS'       => $mm_data['bundle_status_name'],
			'MM_BUNDLE_TYPE'         => ( true === $bundle_type ) ? 'Free' : 'Paid',
			'MM_BUNDLE_ACTIVE_DAYS'  => $mm_data['days_with_bundle'] . ' day(s)',
			'MM_BUNDLE_APPLIED_DATE' => $bundle_details->getApplyDate( true ),
			'MM_BUNDLE_EXPIRY_DATE'  => $bundle_details->getExpirationDate( true ),
			'MM_BUNDLE_CANCEL_DATE'  => $bundle_details->getCancellationDate( true ),
		);
	}

	/**
	 * @param $mm_data
	 *
	 * @return array
	 */
	public function parse_mm_order_token_values( $mm_data ) {
		$products   = json_decode( stripslashes( $mm_data['order_products'] ), true );
		$prorations = json_decode( stripslashes( $mm_data['order_prorations'] ), true );
		$coupons    = json_decode( stripslashes( $mm_data['order_coupons'] ), true );

		$ordered_products = array();
		$order_prorations = array();
		$order_coupons    = array();
		if ( ! empty( $products ) ) {
			foreach ( $products as $product ) {
				$ordered_products[] = $product['name'] . ' x ' . $product['quantity'];
			}
			$products = join( ' | ', $ordered_products );
		}

		if ( ! empty( $coupons ) ) {
			foreach ( $coupons as $coupon ) {
				$order_coupons[] = $coupon['code'];
			}
			$coupons = join( ' | ', $order_coupons );
		}

		if ( ! empty( $prorations ) ) {
			foreach ( $prorations as $proration ) {
				$order_prorations[] = $proration['description'] . ' x ' . $proration['amount'];
			}
			$prorations = join( ' | ', $order_prorations );
		}

		return array(
			'MM_ORDER_NUMBER'            => $mm_data['order_number'],
			'MM_ORDER_TRANSACTION_ID'    => $mm_data['order_transaction_id'],
			'MM_ORDER_IP_ADDRESS'        => $mm_data['order_ip_address'],
			'MM_ORDER_SHIPPING_METHOD'   => $mm_data['order_shipping_method'],
			'MM_ORDER_SHIPPING'          => $mm_data['order_shipping'],
			'MM_ORDER_BILLING_ADDRESS'   => $mm_data['order_billing_address'],
			'MM_ORDER_BILLING_ADDRESS2'  => $mm_data['order_billing_address2'],
			'MM_ORDER_BILLING_CITY'      => $mm_data['order_billing_city'],
			'MM_ORDER_BILLING_STATE'     => $mm_data['order_billing_state'],
			'MM_ORDER_BILLING_ZIPCODE'   => $mm_data['order_billing_zipcode'],
			'MM_ORDER_BILLING_COUNTRY'   => $mm_data['order_billing_country'],
			'MM_ORDER_SHIPPING_ADDRESS'  => $mm_data['order_shipping_address'],
			'MM_ORDER_SHIPPING_ADDRESS2' => $mm_data['order_shipping_address2'],
			'MM_ORDER_SHIPPING_CITY'     => $mm_data['order_shipping_city'],
			'MM_ORDER_SHIPPING_STATE'    => $mm_data['order_shipping_state'],
			'MM_ORDER_SHIPPING_ZIPCODE'  => $mm_data['order_shipping_zipcode'],
			'MM_ORDER_SHIPPING_COUNTRY'  => $mm_data['order_shipping_country'],
			'MM_ORDER_AFFILIATE_ID'      => $mm_data['order_affiliate_id'],
			'MM_ORDER_SUBAFFILIATE_ID'   => $mm_data['order_subaffiliate_id'],
			'MM_ORDER_PRODUCTS'          => $products,
			'MM_ORDER_PRORATIONS'        => $prorations,
			'MM_ORDER_COUPONS'           => $coupons,
			'MM_ORDER_DATE'              => wp_date(
				sprintf( '%s %s', get_option( 'date_format' ), get_option( 'time_format' ) ),
				strtotime( $mm_data['order_date'] )
			),
			'MM_ORDER_TOTAL'             => $mm_data['order_total'],
			'MM_ORDER_SUBTOTAL'          => $mm_data['order_subtotal'],
			'MM_ORDER_DISCOUNT'          => $mm_data['order_discount'],
		);
	}

	/**
	 * @param $is_any
	 *
	 * @return array
	 */
	public function get_all_available_bundles( $is_any = false ) {
		$bundles = \MM_Bundle::getBundlesList();
		$options = array();
		if ( true === $is_any ) {
			$options[] = array(
				'text'  => esc_html__( 'Any bundle', 'uncanny-automator' ),
				'value' => '-1',
			);
		}
		foreach ( $bundles as $key => $bundle ) {
			$options[] = array(
				'text'  => $bundle,
				'value' => $key,
			);
		}

		return $options;
	}

	/**
	 * @param $is_any
	 *
	 * @return array
	 */
	public function get_all_membership_levels( $is_any = false ) {
		$membership_levels = \MM_MembershipLevel::getMembershipLevelsList();
		$options           = array();
		if ( true === $is_any ) {
			$options[] = array(
				'text'  => esc_html__( 'Any membership level', 'uncanny-automator' ),
				'value' => '-1',
			);
		}
		foreach ( $membership_levels as $key => $membership_level ) {
			$options[] = array(
				'text'  => $membership_level,
				'value' => $key,
			);
		}

		return $options;
	}

	/**
	 * @param $is_any
	 *
	 * @return array
	 */
	public function get_all_statuses( $is_any = false ) {
		$account_statuses = array(
			'active'               => 'Active',
			'canceled'             => 'Canceled',
			'locked'               => 'Locked',
			'paused'               => 'Paused',
			'overdue'              => 'Overdue',
			'pending_activation'   => 'Pending activation',
			'error'                => 'Error',
			'expired'              => 'Expired',
			'pending_cancellation' => 'Pending cancellation',
		);
		$options          = array();
		if ( true === $is_any ) {
			$options[] = array(
				'text'  => esc_html__( 'Any status', 'uncanny-automator' ),
				'value' => '-1',
			);
		}
		foreach ( $account_statuses as $key => $status ) {
			$options[] = array(
				'text'  => $status,
				'value' => $key,
			);
		}

		return $options;
	}

	/**
	 * @param $is_any
	 *
	 * @return array
	 */
	public function get_all_member_fields( $is_any = false ) {
		$options = array();
		if ( true === $is_any ) {
			$options[] = array(
				'text'  => esc_html__( 'Any field', 'uncanny-automator' ),
				'value' => '-1',
			);
		}

		$default_fields = $this->get_defaul_fields();
		foreach ( $default_fields as $id => $field ) {
			$options[] = array(
				'text'  => $field,
				'value' => $id,
			);
		}

		// custom fields
		$custom_fields = $this->get_custom_fields();
		foreach ( $custom_fields as $id => $custom_field ) {
			$options[] = array(
				'text'  => $custom_field,
				'value' => $id,
			);
		}

		return $options;
	}

	/**
	 * @param $mm_data
	 *
	 * @return array
	 */
	public function parse_custom_field_values( $mm_data ) {
		$token_values = array();
		// custom fields
		$custom_fields = $this->get_custom_fields();
		foreach ( $custom_fields as $id => $field ) {
			$token_values[ $id ] = ( $mm_data[ $id ] === 'mm_cb_on' ) ? 'Checked' : $mm_data[ $id ];
		}

		return $token_values;
	}

	/**
	 * @return array
	 */
	public function get_custom_fields() {
		$fields = array();
		// custom fields
		$custom_fields = \MM_CustomField::getCustomFieldsList();
		foreach ( $custom_fields as $id => $custom_field ) {
			$fields[ 'cf_' . $id ] = $custom_field;
		}

		return $fields;
	}

	/**
	 * @return string[]
	 */
	public function get_defaul_fields() {
		return array(
			'first_name'        => 'First Name',
			'last_name'         => 'Last Name',
			'email'             => 'Email',
			'username'          => 'Username',
			'phone'             => 'Phone',
			'billing_address'   => 'Billing Address',
			'billing_address2'  => 'Billing Address Line 2',
			'billing_city'      => 'Billing City',
			'billing_state'     => 'Billing State',
			'billing_zip'       => 'Billing Zip',
			'billing_country'   => 'Billing Country',
			'shipping_address'  => 'Shipping Address',
			'shipping_address2' => 'Shipping Address Line 2',
			'shipping_city'     => 'Shipping City',
			'shipping_state'    => 'Shipping State',
			'shipping_zip'      => 'Shipping Zip',
			'shipping_country'  => 'Shipping Country',
		);
	}
}
