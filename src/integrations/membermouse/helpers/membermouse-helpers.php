<?php

namespace Uncanny_Automator\Integrations\MemberMouse;

/**
 * Class Membermouse_Helper
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
				'tokenName' => __( 'Member ID', 'uncanny_automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'MM_MEMBER_USERNAME',
				'tokenName' => __( 'Member username', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_MEMBER_EMAIL',
				'tokenName' => __( 'Member email', 'uncanny_automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'MM_MEMBER_FIRST_NAME',
				'tokenName' => __( 'Member first name', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_MEMBER_LAST_NAME',
				'tokenName' => __( 'Member last name', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_MEMBER_PHONE',
				'tokenName' => __( 'Member phone', 'uncanny_automator' ),
				'tokenType' => 'tel',
			),
			array(
				'tokenId'   => 'MM_MEMBER_STATUS',
				'tokenName' => __( 'Member status', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_MEMBER_MEMBERSHIP_LEVEL',
				'tokenName' => __( 'Membership level', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_MEMBER_SINCE',
				'tokenName' => __( 'Member since', 'uncanny_automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'MM_MEMBER_BILLING_ADDRESS',
				'tokenName' => __( 'Billing address', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_MEMBER_BILLING_ADDRESS2',
				'tokenName' => __( 'Billing address2', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_MEMBER_BILLING_CITY',
				'tokenName' => __( 'Billing city', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_MEMBER_BILLING_STATE',
				'tokenName' => __( 'Billing state', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_MEMBER_BILLING_ZIPCODE',
				'tokenName' => __( 'Billing zipcode', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_MEMBER_BILLING_COUNTRY',
				'tokenName' => __( 'Billing country', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_MEMBER_SHIPPING_ADDRESS',
				'tokenName' => __( 'Shipping address', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_MEMBER_SHIPPING_ADDRESS2',
				'tokenName' => __( 'Shipping address2', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_MEMBER_SHIPPING_CITY',
				'tokenName' => __( 'Shipping city', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_MEMBER_SHIPPING_STATE',
				'tokenName' => __( 'Shipping state', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_MEMBER_SHIPPING_ZIPCODE',
				'tokenName' => __( 'Shipping zipcode', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_MEMBER_SHIPPING_COUNTRY',
				'tokenName' => __( 'Shipping country', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
		);

		if ( true === $include_cf ) {
			$custom_fields = $this->get_custom_fields();
			foreach ( $custom_fields as $id => $custom_field ) {
				$tokens[] = array(
					'tokenId'   => $id,
					'tokenName' => __( $custom_field, 'uncanny_automator' ),
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
				'tokenName' => __( 'Bundle ID', 'uncanny_automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'MM_BUNDLE_NAME',
				'tokenName' => __( 'Bundle name', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_BUNDLE_STATUS',
				'tokenName' => __( 'Bundle status', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_BUNDLE_TYPE',
				'tokenName' => __( 'Bundle type', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_BUNDLE_ACTIVE_DAYS',
				'tokenName' => __( 'Day(s) with bundle', 'uncanny_automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'MM_BUNDLE_APPLIED_DATE',
				'tokenName' => __( 'Bundle applied date', 'uncanny_automator' ),
				'tokenType' => 'datetime',
			),
			array(
				'tokenId'   => 'MM_BUNDLE_EXPIRY_DATE',
				'tokenName' => __( 'Bundle expires on', 'uncanny_automator' ),
				'tokenType' => 'datetime',
			),
			array(
				'tokenId'   => 'MM_BUNDLE_CANCEL_DATE',
				'tokenName' => __( 'Bundle cancels on', 'uncanny_automator' ),
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
				'tokenName' => __( 'Product ID', 'uncanny_automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'MM_PRODUCT_REFERENCE_KEY',
				'tokenName' => __( 'Product reference key', 'uncanny_automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'MM_PRODUCT_NAME',
				'tokenName' => __( 'Product name', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_PRODUCT_SKU',
				'tokenName' => __( 'Product SKU', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_PRODUCT_DESCRIPTION',
				'tokenName' => __( 'Product description', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_PRODUCT_PRICE',
				'tokenName' => __( 'Product price', 'uncanny_automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'MM_PRODUCT_CURRENCY',
				'tokenName' => __( 'Product currency', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_PRODUCT_IS_SHIPPABLE',
				'tokenName' => __( 'Is shippable product?', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_PRODUCT_REBILL_PERIOD',
				'tokenName' => __( 'Subscription rebill period', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_PRODUCT_REBILL_FREQUENCY',
				'tokenName' => __( 'Subscription rebill frequency', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_PRODUCT_LIMIT_PAYMENTS',
				'tokenName' => __( 'Has payment limit?', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_PRODUCT_NUMBER_OF_PAYMENTS',
				'tokenName' => __( 'Payment plan limit', 'uncanny_automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'MM_PRODUCT_HAS_TRAIL',
				'tokenName' => __( 'Has trial?', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_PRODUCT_TRAIL_LIMIT',
				'tokenName' => __( 'Has trial limit?', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_PRODUCT_TRAIL_LIMIT_ALT_PRODUCT_ID',
				'tokenName' => __( 'Alternative product ID', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_PRODUCT_TRAIL_AMOUNT',
				'tokenName' => __( 'Trial amount', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_PRODUCT_TRAIL_DURATION',
				'tokenName' => __( 'Trial duration', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_PRODUCT_TRAIL_FREQUENCY',
				'tokenName' => __( 'Trial frequency', 'uncanny_automator' ),
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
				'tokenName' => __( 'Order number', 'uncanny_automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'MM_ORDER_TRANSACTION_ID',
				'tokenName' => __( 'Transaction ID', 'uncanny_automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'MM_ORDER_IP_ADDRESS',
				'tokenName' => __( 'IP address', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_ORDER_SHIPPING_METHOD',
				'tokenName' => __( 'Shipping method', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_ORDER_SHIPPING',
				'tokenName' => __( 'Shipping charges', 'uncanny_automator' ),
				'tokenType' => 'float',
			),
			array(
				'tokenId'   => 'MM_ORDER_BILLING_ADDRESS',
				'tokenName' => __( 'Billing address', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_ORDER_BILLING_ADDRESS2',
				'tokenName' => __( 'Billing address - Line 2', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_ORDER_BILLING_CITY',
				'tokenName' => __( 'Billing city', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_ORDER_BILLING_STATE',
				'tokenName' => __( 'Billing state', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_ORDER_BILLING_ZIPCODE',
				'tokenName' => __( 'Billing zipcode', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_ORDER_BILLING_COUNTRY',
				'tokenName' => __( 'Billing country', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_ORDER_SHIPPING_ADDRESS',
				'tokenName' => __( 'Shipping address', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_ORDER_SHIPPING_ADDRESS2',
				'tokenName' => __( 'Shipping address - Line 2', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_ORDER_SHIPPING_CITY',
				'tokenName' => __( 'Shipping city', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_ORDER_SHIPPING_STATE',
				'tokenName' => __( 'Shipping state', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_ORDER_SHIPPING_ZIPCODE',
				'tokenName' => __( 'Shipping zipcode', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_ORDER_SHIPPING_COUNTRY',
				'tokenName' => __( 'Shipping country', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MM_ORDER_AFFILIATE_ID',
				'tokenName' => __( 'Affiliate ID', 'uncanny_automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'MM_ORDER_SUBAFFILIATE_ID',
				'tokenName' => __( 'Sub affiliate ID', 'uncanny_automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'MM_ORDER_PRODUCTS',
				'tokenName' => __( 'Order product(s)', 'uncanny_automator' ),
				'tokenType' => 'text',
			),
			//          array(
			//              'tokenId'   => 'MM_ORDER_PRORATIONS',
			//              'tokenName' => __( 'Order propations', 'uncanny_automator' ),
			//              'tokenType' => 'text',
			//          ),
				array(
					'tokenId'   => 'MM_ORDER_COUPONS',
					'tokenName' => __( 'Order coupon(s)', 'uncanny_automator' ),
					'tokenType' => 'text',
				),
			array(
				'tokenId'   => 'MM_ORDER_DATE',
				'tokenName' => __( 'Order date', 'uncanny_automator' ),
				'tokenType' => 'datetime',
			),
			array(
				'tokenId'   => 'MM_ORDER_TOTAL',
				'tokenName' => __( 'Order total', 'uncanny_automator' ),
				'tokenType' => 'float',
			),
			array(
				'tokenId'   => 'MM_ORDER_SUBTOTAL',
				'tokenName' => __( 'Order subtotal', 'uncanny_automator' ),
				'tokenType' => 'float',
			),
			array(
				'tokenId'   => 'MM_ORDER_DISCOUNT',
				'tokenName' => __( 'Order discount', 'uncanny_automator' ),
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
			'MM_ORDER_DATE'              => date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $mm_data['order_date'] ) ),
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
				'text'  => __( 'Any bundle', 'uncanny-automator' ),
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
				'text'  => __( 'Any membership level', 'uncanny-automator' ),
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
				'text'  => __( 'Any status', 'uncanny-automator' ),
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
				'text'  => __( 'Any field', 'uncanny-automator' ),
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
