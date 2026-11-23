<?php

namespace Uncanny_Automator\Integrations\Easy_Digital_Downloads;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class EDD_PRODUCTPURCHASEWITHPRICE
 *
 * @package Uncanny_Automator
 */
class EDD_PRODUCTPURCHASEWITHPRICE extends Trigger {

	/**
	 * Trigger code.
	 *
	 * @var string
	 */
	protected $trigger_code = 'EDD_PRODUCTPURCHASEWITHPRICE';

	/**
	 * Trigger meta.
	 *
	 * @var string
	 */
	protected $trigger_meta = 'EDDPRODUCT';

	/**
	 * Price option meta.
	 *
	 * @var string
	 */
	protected $price_option_meta = 'EDDPRICEOPTION';

	/**
	 * Set up Automator trigger.
	 */
	protected function setup_trigger() {
		$this->set_integration( 'EDD' );
		$this->set_trigger_code( $this->trigger_code );
		$this->set_trigger_meta( $this->trigger_meta );
		$this->set_trigger_type( 'user' );
		// No login check: Stripe and PayPal complete the order in a webhook with nobody signed in.
		// validate() takes the buyer from the order and rejects guest orders.
		$this->set_support_link( Automator()->get_author_support_link( $this->trigger_code, 'integration/easy-digital-downloads/' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: download, %2$s: price option
				esc_html_x( 'A user purchases {{a price option:%2$s}} of {{a download:%1$s}}', 'Easy Digital Downloads', 'uncanny-automator' ),
				$this->get_trigger_meta(),
				$this->price_option_meta . ':' . $this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'A user purchases {{a price option}} of {{a download}}', 'Easy Digital Downloads', 'uncanny-automator' ) );
		$this->add_action( 'edd_complete_purchase', 10, 3 );
	}



	/**
	 * Options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->get_item_helpers()->all_edd_downloads( esc_html_x( 'Download', 'Easy Digital Downloads', 'uncanny-automator' ), $this->get_trigger_meta(), true, false ),
			array(
				'option_code'     => $this->price_option_meta,
				'label'           => esc_html_x( 'Price option', 'Easy Digital Downloads', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'ajax'            => array(
					'event'         => 'parent_fields_change',
					'endpoint'      => 'automator_edd_price_options_handler',
					'listen_fields' => array( $this->get_trigger_meta() ),
				),
			),
		);
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
			'EDD_DOWNLOAD_TITLE'              => array(
				'name'      => esc_html_x( 'Download title', 'Easy Digital Downloads', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'EDD_DOWNLOAD_TITLE',
				'tokenName' => esc_html_x( 'Download title', 'Easy Digital Downloads', 'uncanny-automator' ),
			),
			'EDD_DOWNLOAD_ID'                 => array(
				'name'      => esc_html_x( 'Download ID', 'Easy Digital Downloads', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'EDD_DOWNLOAD_ID',
				'tokenName' => esc_html_x( 'Download ID', 'Easy Digital Downloads', 'uncanny-automator' ),
			),
			'EDD_DOWNLOAD_URL'                => array(
				'name'      => esc_html_x( 'Download URL', 'Easy Digital Downloads', 'uncanny-automator' ),
				'type'      => 'url',
				'tokenId'   => 'EDD_DOWNLOAD_URL',
				'tokenName' => esc_html_x( 'Download URL', 'Easy Digital Downloads', 'uncanny-automator' ),
			),
			'EDD_DOWNLOAD_FEATURED_IMAGE_ID'  => array(
				'name'      => esc_html_x( 'Download featured image ID', 'Easy Digital Downloads', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'EDD_DOWNLOAD_FEATURED_IMAGE_ID',
				'tokenName' => esc_html_x( 'Download featured image ID', 'Easy Digital Downloads', 'uncanny-automator' ),
			),
			'EDD_DOWNLOAD_FEATURED_IMAGE_URL' => array(
				'name'      => esc_html_x( 'Download featured image URL', 'Easy Digital Downloads', 'uncanny-automator' ),
				'type'      => 'url',
				'tokenId'   => 'EDD_DOWNLOAD_FEATURED_IMAGE_URL',
				'tokenName' => esc_html_x( 'Download featured image URL', 'Easy Digital Downloads', 'uncanny-automator' ),
			),
			'EDD_PRICE_OPTION_NAME'           => array(
				'name'      => esc_html_x( 'Price option name', 'Easy Digital Downloads', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'EDD_PRICE_OPTION_NAME',
				'tokenName' => esc_html_x( 'Price option name', 'Easy Digital Downloads', 'uncanny-automator' ),
			),
			'EDD_PRICE_OPTION_ID'             => array(
				'name'      => esc_html_x( 'Price option ID', 'Easy Digital Downloads', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'EDD_PRICE_OPTION_ID',
				'tokenName' => esc_html_x( 'Price option ID', 'Easy Digital Downloads', 'uncanny-automator' ),
			),
			'EDD_ORDER_ID'                    => array(
				'name'      => esc_html_x( 'Order ID', 'Easy Digital Downloads', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'EDD_ORDER_ID',
				'tokenName' => esc_html_x( 'Order ID', 'Easy Digital Downloads', 'uncanny-automator' ),
			),
			'EDD_ORDER_URL'                   => array(
				'name'      => esc_html_x( 'Order URL (Admin)', 'Easy Digital Downloads', 'uncanny-automator' ),
				'type'      => 'url',
				'tokenId'   => 'EDD_ORDER_URL',
				'tokenName' => esc_html_x( 'Order URL (Admin)', 'Easy Digital Downloads', 'uncanny-automator' ),
			),
			'EDD_ORDER_URL_USER'              => array(
				'name'      => esc_html_x( 'Order URL (User)', 'Easy Digital Downloads', 'uncanny-automator' ),
				'type'      => 'url',
				'tokenId'   => 'EDD_ORDER_URL_USER',
				'tokenName' => esc_html_x( 'Order URL (User)', 'Easy Digital Downloads', 'uncanny-automator' ),
			),
			'EDD_ORDER_DISCOUNTS'             => array(
				'name'      => esc_html_x( 'Order discounts', 'Easy Digital Downloads', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'EDD_ORDER_DISCOUNTS',
				'tokenName' => esc_html_x( 'Order discounts', 'Easy Digital Downloads', 'uncanny-automator' ),
			),
			'EDD_ORDER_SUBTOTAL'              => array(
				'name'      => esc_html_x( 'Order subtotal', 'Easy Digital Downloads', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'EDD_ORDER_SUBTOTAL',
				'tokenName' => esc_html_x( 'Order subtotal', 'Easy Digital Downloads', 'uncanny-automator' ),
			),
			'EDD_ORDER_TAX'                   => array(
				'name'      => esc_html_x( 'Order tax', 'Easy Digital Downloads', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'EDD_ORDER_TAX',
				'tokenName' => esc_html_x( 'Order tax', 'Easy Digital Downloads', 'uncanny-automator' ),
			),
			'EDD_ORDER_TOTAL'                 => array(
				'name'      => esc_html_x( 'Order total', 'Easy Digital Downloads', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'EDD_ORDER_TOTAL',
				'tokenName' => esc_html_x( 'Order total', 'Easy Digital Downloads', 'uncanny-automator' ),
			),
			'EDD_PAYMENT_METHOD'              => array(
				'name'      => esc_html_x( 'Payment method', 'Easy Digital Downloads', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'EDD_PAYMENT_METHOD',
				'tokenName' => esc_html_x( 'Payment method', 'Easy Digital Downloads', 'uncanny-automator' ),
			),
			'EDD_DISCOUNT_CODES'              => array(
				'name'      => esc_html_x( 'Discount codes used', 'Easy Digital Downloads', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'EDD_DISCOUNT_CODES',
				'tokenName' => esc_html_x( 'Discount codes used', 'Easy Digital Downloads', 'uncanny-automator' ),
			),
			'EDD_LICENSE_KEY'                 => array(
				'name'      => esc_html_x( 'License key', 'Easy Digital Downloads', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'EDD_LICENSE_KEY',
				'tokenName' => esc_html_x( 'License key', 'Easy Digital Downloads', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Validate the trigger.
	 *
	 * The buyer comes from the order, not from the session: Stripe and PayPal complete the
	 * order in a webhook with nobody signed in. A guest order has no user and is rejected.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		$order = $this->get_order( $hook_args );
		if ( null === $order ) {
			return false;
		}

		$user_id = absint( $order->user_id );
		if ( 0 === $user_id ) {
			return false;
		}

		if ( null === $this->find_matching_item( $trigger, $order ) ) {
			return false;
		}

		$this->set_user_id( $user_id );

		return true;
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

		$order = $this->get_order( $hook_args );
		if ( null === $order ) {
			return array();
		}

		$order_id      = $hook_args[0];
		$matching_item = $this->find_matching_item( $trigger, $order );

		if ( null === $matching_item ) {
			return array();
		}

		$download_id     = $matching_item->product_id;
		$price_option_id = $matching_item->price_id;
		$download_post   = get_post( $download_id );

		// Get price option name
		$price_option_name = '';
		if ( null !== $price_option_id ) {
			$price_option_name = edd_get_price_option_name( $download_id, $price_option_id );
		}

		// Get featured image
		$featured_image_id  = get_post_thumbnail_id( $download_id );
		$featured_image_url = $featured_image_id ? wp_get_attachment_url( $featured_image_id ) : '';

		// Get order URL (Admin)
		$order_url = admin_url( 'edit.php?post_type=download&page=edd-payment-history&view=view-order-details&id=' . $order_id );

		// Get order URL (User) - receipt page
		$order_url_user = edd_get_receipt_page_uri( $order_id );

		$discounts = $order->get_discounts();

		$tokens = array(
			'EDD_DOWNLOAD_TITLE'              => $download_post ? $download_post->post_title : '',
			'EDD_DOWNLOAD_ID'                 => $download_id,
			'EDD_ORDER_ID'                    => $order_id,
			'EDD_DOWNLOAD_URL'                => get_permalink( $download_id ),
			'EDD_DOWNLOAD_FEATURED_IMAGE_ID'  => $featured_image_id,
			'EDD_DOWNLOAD_FEATURED_IMAGE_URL' => $featured_image_url,
			'EDD_PRICE_OPTION_NAME'           => $price_option_name,
			'EDD_PRICE_OPTION_ID'             => $price_option_id,
			'EDD_ORDER_URL'                   => $order_url,
			'EDD_ORDER_URL_USER'              => $order_url_user,
			'EDD_ORDER_DISCOUNTS'             => number_format( floatval( $matching_item->discount ), 2 ),
			'EDD_ORDER_SUBTOTAL'              => number_format( floatval( $order->subtotal ), 2 ),
			'EDD_ORDER_TAX'                   => number_format( floatval( $order->tax ), 2 ),
			'EDD_ORDER_TOTAL'                 => number_format( floatval( $order->total ), 2 ),
			'EDD_PAYMENT_METHOD'              => $order->gateway,
			'EDD_DISCOUNT_CODES'              => $discounts ? implode( ', ', wp_list_pluck( $discounts, 'description' ) ) : '',
			'EDD_LICENSE_KEY'                 => $this->get_item_helpers()->get_licenses( $order_id ),
		);

		return $tokens;
	}

	/**
	 * Get the order EDD completed.
	 *
	 * EDD passes the order ID first; the payment and customer that follow are not needed.
	 *
	 * @param array $hook_args The edd_complete_purchase arguments.
	 *
	 * @return \EDD\Orders\Order|null Null when EDD 3.0+ is absent, the order is missing or it has no items.
	 */
	private function get_order( $hook_args ) {

		if ( empty( $hook_args ) || ! isset( $hook_args[0] ) || ! function_exists( 'edd_get_order' ) ) {
			return null;
		}

		$order = edd_get_order( $hook_args[0] );

		if ( empty( $order ) || empty( $order->items ) ) {
			return null;
		}

		return $order;
	}

	/**
	 * Find the first order item that matches the selected download and price option.
	 *
	 * @param array             $trigger The trigger data.
	 * @param \EDD\Orders\Order $order   The order.
	 *
	 * @return \EDD\Orders\Order_Item|null
	 */
	private function find_matching_item( $trigger, $order ) {

		$selected_download     = $trigger['meta'][ $this->trigger_meta ] ?? '';
		$selected_price_option = $this->get_selected_price_option( $trigger );

		foreach ( $order->items as $item ) {
			if ( $this->id_matches( $selected_download, $item->product_id ) && $this->id_matches( $selected_price_option, $item->price_id ) ) {
				return $item;
			}
		}

		return null;
	}

	/**
	 * Whether a selected ID matches an ID on the order item.
	 *
	 * The recipe stores IDs as strings and EDD may return either type, so both sides
	 * are compared as integers. "Any" (-1) matches everything; an empty selection or an
	 * item without the ID (a download with no price options has a null price ID) matches nothing else.
	 *
	 * @param mixed $selected The ID from the trigger options.
	 * @param mixed $actual   The ID on the order item.
	 *
	 * @return bool
	 */
	private function id_matches( $selected, $actual ) {

		if ( ! is_numeric( $selected ) ) {
			return false;
		}

		if ( -1 === intval( $selected ) ) {
			return true;
		}

		return is_numeric( $actual ) && absint( $selected ) === absint( $actual );
	}

	/**
	 * Get the selected price option from the trigger's options.
	 *
	 * @param array $trigger The trigger data.
	 *
	 * @return string The selected price option, empty when none is stored.
	 */
	private function get_selected_price_option( $trigger ) {

		// "0" is a real price option (the first), so test for an empty string, not empty().
		if ( isset( $trigger['meta'][ $this->price_option_meta ] ) && '' !== $trigger['meta'][ $this->price_option_meta ] ) {
			return $trigger['meta'][ $this->price_option_meta ];
		}

		// Recipes saved with the compound key from the sentence.
		$compound_key = $this->price_option_meta . ':' . $this->get_trigger_meta();

		return $trigger['meta'][ $compound_key ] ?? '';
	}
}
