<?php

namespace Uncanny_Automator\Integrations\Edd_Recurring_Integration;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class EDD_USER_SUBSCRIBES_TO_DOWNLOAD
 *
 * @package Uncanny_Automator\Integrations\Edd_Recurring_Integration
 * @method \Uncanny_Automator\Integrations\Edd_Recurring_Integration\Edd_Recurring_Helpers get_item_helpers()
 */
class EDD_USER_SUBSCRIBES_TO_DOWNLOAD extends Trigger {

	/**
	 * Trigger code
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'EDDR_SUBSCRIBES';

	/**
	 * Trigger meta
	 *
	 * @var string
	 */
	const TRIGGER_META = 'EDDR_PRODUCTS';

	/**
	 * Set up Automator trigger.
	 */
	protected function setup_trigger() {

		$this->set_integration( 'EDD_RECURRING' );
		$this->set_trigger_code( self::TRIGGER_CODE );
		$this->set_trigger_meta( self::TRIGGER_META );
		// translators: %1$s: Download
		$this->set_sentence( sprintf( esc_html_x( 'A user subscribes to {{a download:%1$s}}', 'Easy Digital Downloads - Recurring Payments', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'A user subscribes to {{a download}}', 'Easy Digital Downloads - Recurring Payments', 'uncanny-automator' ) );
		$this->add_action( 'edd_recurring_post_record_signup', 10, 3 );
	}

	/**
	 * Options. We don't need `load_options` here, since we're using abstract method.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->get_item_helpers()->all_recurring_edd_downloads( esc_html_x( 'Download', 'EDD - Recurring Payments', 'uncanny-automator' ), $this->get_trigger_meta(), true ),
		);
	}

	/**
	 * Validate the trigger.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		if ( empty( $hook_args ) || count( $hook_args ) < 2 || ! isset( $hook_args[1] ) ) {
			return false;
		}

		if ( ! isset( $trigger['meta'][ self::TRIGGER_META ] ) ) {
			return false;
		}

		$selected_product_id = $trigger['meta'][ self::TRIGGER_META ];
		$subscription        = $hook_args[1];
		$download_id         = $subscription['id'];

		// Set user ID for the trigger
		$user_id = get_current_user_id();
		if ( $user_id ) {
			$this->set_user_id( $user_id );
		}

		if ( intval( '-1' ) !== intval( $selected_product_id ) && absint( $selected_product_id ) !== absint( $download_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Define tokens.
	 *
	 * @param array $trigger
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array(
			'EDDR_PRODUCTS_DISCOUNT_CODES'  => array(
				'name'      => esc_html_x( 'Discount codes used', 'EDD - Recurring Payments', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'EDDR_PRODUCTS_DISCOUNT_CODES',
				'tokenName' => esc_html_x( 'Discount codes used', 'EDD - Recurring Payments', 'uncanny-automator' ),
			),
			'EDDR_PRODUCTS'                 => array(
				'name'      => esc_html_x( 'Download title', 'EDD - Recurring Payments', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'EDDR_PRODUCTS',
				'tokenName' => esc_html_x( 'Download title', 'EDD - Recurring Payments', 'uncanny-automator' ),
			),
			'EDDR_PRODUCTS_ID'              => array(
				'name'      => esc_html_x( 'Download ID', 'EDD - Recurring Payments', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'EDDR_PRODUCTS_ID',
				'tokenName' => esc_html_x( 'Download ID', 'EDD - Recurring Payments', 'uncanny-automator' ),
			),
			'EDDR_PRODUCTS_URL'             => array(
				'name'      => esc_html_x( 'Download URL', 'EDD - Recurring Payments', 'uncanny-automator' ),
				'type'      => 'url',
				'tokenId'   => 'EDDR_PRODUCTS_URL',
				'tokenName' => esc_html_x( 'Download URL', 'EDD - Recurring Payments', 'uncanny-automator' ),
			),
			'EDDR_PRODUCTS_THUMB_ID'        => array(
				'name'      => esc_html_x( 'Download featured image ID', 'EDD - Recurring Payments', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'EDDR_PRODUCTS_THUMB_ID',
				'tokenName' => esc_html_x( 'Download featured image ID', 'EDD - Recurring Payments', 'uncanny-automator' ),
			),
			'EDDR_PRODUCTS_THUMB_URL'       => array(
				'name'      => esc_html_x( 'Download featured image URL', 'EDD - Recurring Payments', 'uncanny-automator' ),
				'type'      => 'url',
				'tokenId'   => 'EDDR_PRODUCTS_THUMB_URL',
				'tokenName' => esc_html_x( 'Download featured image URL', 'EDD - Recurring Payments', 'uncanny-automator' ),
			),
			'EDDR_PRODUCTS_LICENSE_KEY'     => array(
				'name'      => esc_html_x( 'License key', 'EDD - Recurring Payments', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'EDDR_PRODUCTS_LICENSE_KEY',
				'tokenName' => esc_html_x( 'License key', 'EDD - Recurring Payments', 'uncanny-automator' ),
			),
			'EDDR_PRODUCTS_ORDER_DISCOUNTS' => array(
				'name'      => esc_html_x( 'Order discounts', 'EDD - Recurring Payments', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'EDDR_PRODUCTS_ORDER_DISCOUNTS',
				'tokenName' => esc_html_x( 'Order discounts', 'EDD - Recurring Payments', 'uncanny-automator' ),
			),
			'EDDR_PRODUCTS_ORDER_SUBTOTAL'  => array(
				'name'      => esc_html_x( 'Order subtotal', 'EDD - Recurring Payments', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'EDDR_PRODUCTS_ORDER_SUBTOTAL',
				'tokenName' => esc_html_x( 'Order subtotal', 'EDD - Recurring Payments', 'uncanny-automator' ),
			),
			'EDDR_PRODUCTS_ORDER_TAX'       => array(
				'name'      => esc_html_x( 'Order tax', 'EDD - Recurring Payments', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'EDDR_PRODUCTS_ORDER_TAX',
				'tokenName' => esc_html_x( 'Order tax', 'EDD - Recurring Payments', 'uncanny-automator' ),
			),
			'EDDR_PRODUCTS_ORDER_TOTAL'     => array(
				'name'      => esc_html_x( 'Order total', 'EDD - Recurring Payments', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'EDDR_PRODUCTS_ORDER_TOTAL',
				'tokenName' => esc_html_x( 'Order total', 'EDD - Recurring Payments', 'uncanny-automator' ),
			),
			'EDDR_PRODUCTS_PAYMENT_METHOD'  => array(
				'name'      => esc_html_x( 'Payment method', 'EDD - Recurring Payments', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'EDDR_PRODUCTS_PAYMENT_METHOD',
				'tokenName' => esc_html_x( 'Payment method', 'EDD - Recurring Payments', 'uncanny-automator' ),
			),
			'EDDR_PERIOD'                   => array(
				'name'      => esc_html_x( 'Recurring period', 'EDD - Recurring Payments', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'EDDR_PERIOD',
				'tokenName' => esc_html_x( 'Recurring period', 'EDD - Recurring Payments', 'uncanny-automator' ),
			),
			'EDDR_SIGN_UP_FEE'              => array(
				'name'      => esc_html_x( 'Signup fee', 'Easy Digital Downloads - Recurring Payments', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'EDDR_SIGN_UP_FEE',
				'tokenName' => esc_html_x( 'Signup fee', 'Easy Digital Downloads - Recurring Payments', 'uncanny-automator' ),
			),
			'EDDR_TIMES'                    => array(
				'name'      => esc_html_x( 'Times', 'EDD - Recurring Payments', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'EDDR_TIMES',
				'tokenName' => esc_html_x( 'Times', 'EDD - Recurring Payments', 'uncanny-automator' ),
			),
			'EDDR_FREE_TRAIL_PERIOD'        => array(
				'name'      => esc_html_x( 'Free trial period', 'EDD - Recurring Payments', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'EDDR_FREE_TRAIL_PERIOD',
				'tokenName' => esc_html_x( 'Free trial period', 'EDD - Recurring Payments', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Hydrate tokens.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		if ( empty( $hook_args ) || count( $hook_args ) < 3 ) {
			return array();
		}

		list( $subscription_object, $subscription, $payment_object ) = $hook_args;
		$purchase_data = $payment_object->purchase_data;

		// Get license key if Software Licensing plugin is active
		$license_key = '';
		if ( class_exists( '\EDD_Software_Licensing' ) ) {
			$edd_helper  = new \Uncanny_Automator\Integrations\Easy_Digital_Downloads\Edd_Helpers();
			$license_key = $edd_helper->get_licenses( $subscription_object->parent_payment_id );
		}

		$token_values = array(
			'EDDR_PRODUCTS_DISCOUNT_CODES'  => $purchase_data['user_info']['discount'],
			'EDDR_PRODUCTS'                 => $subscription['name'],
			'EDDR_PRODUCTS_ID'              => $subscription['id'],
			'EDDR_PRODUCTS_URL'             => get_permalink( $subscription['id'] ),
			'EDDR_PRODUCTS_THUMB_ID'        => get_post_thumbnail_id( $subscription['id'] ),
			'EDDR_PRODUCTS_THUMB_URL'       => get_the_post_thumbnail_url( $subscription['id'] ),
			'EDDR_PRODUCTS_LICENSE_KEY'     => $license_key,
			'EDDR_PRODUCTS_ORDER_DISCOUNTS' => number_format( $purchase_data['discount'], 2 ),
			'EDDR_PRODUCTS_ORDER_SUBTOTAL'  => number_format( $purchase_data['subtotal'], 2 ),
			'EDDR_PRODUCTS_ORDER_TAX'       => number_format( $purchase_data['tax'], 2 ),
			'EDDR_PRODUCTS_ORDER_TOTAL'     => number_format( $purchase_data['price'], 2 ),
			'EDDR_PRODUCTS_PAYMENT_METHOD'  => edd_get_payment_gateway( $subscription_object->parent_payment_id ),
			'EDDR_PERIOD'                   => $subscription['period'],
			'EDDR_SIGN_UP_FEE'              => number_format( $subscription['signup_fee'], 2 ),
			'EDDR_TIMES'                    => $subscription['frequency'],
			'EDDR_FREE_TRAIL_PERIOD'        => $subscription_object->trial_period,
		);

		return $token_values;
	}
}
