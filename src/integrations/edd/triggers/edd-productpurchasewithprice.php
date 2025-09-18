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
		$this->set_is_login_required( true );
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
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		if ( empty( $hook_args ) || count( $hook_args ) < 3 ) {
			return false;
		}

		$order_id = $hook_args[0];
		$payment  = isset( $hook_args[1] ) ? $hook_args[1] : null;
		$customer = isset( $hook_args[2] ) ? $hook_args[2] : null;

		// Get order items from the new EDD 3.0+ structure
		$order = edd_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		$order_items = $order->items;
		if ( empty( $order_items ) ) {
			return false;
		}

		$user_id = $order->user_id;

		// Check if user is logged in
		if ( ! $user_id ) {
			return false;
		}

		// Set user ID for the trigger
		$this->set_user_id( $user_id );

		// Get selected download and price option from trigger
		$selected_download     = $trigger['meta'][ $this->trigger_meta ];
		$selected_price_option = $this->get_selected_price_option( $trigger );

		foreach ( $order_items as $item ) {
			$download_id     = $item->product_id;
			$price_option_id = $item->price_id;

			// Check if download matches (allow "Any download" option (-1) or specific download)
			$download_matches = ( intval( '-1' ) === intval( $selected_download ) || $selected_download === $download_id );

			// Check if price option matches (allow "Any price option" option (-1) or specific price option)
			$price_option_matches = ( intval( '-1' ) === intval( $selected_price_option ) || $selected_price_option === $price_option_id );

			// Both download and price option must match
			if ( $download_matches && $price_option_matches ) {
				return true;
			}
		}

		return false;
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

		$order_id = $hook_args[0];
		$payment  = isset( $hook_args[1] ) ? $hook_args[1] : null;
		$customer = isset( $hook_args[2] ) ? $hook_args[2] : null;

		// Get order items from the new EDD 3.0+ structure
		$order = edd_get_order( $order_id );
		if ( ! $order ) {
			return array();
		}

		$order_items = $order->items;

		// Find the matching item for token data
		$selected_download     = $trigger['meta'][ $this->trigger_meta ];
		$selected_price_option = $this->get_selected_price_option( $trigger );

		$matching_item = null;

		foreach ( $order_items as $item ) {
			$download_id     = $item->product_id;
			$price_option_id = $item->price_id;

			$download_matches     = ( intval( '-1' ) === intval( $selected_download ) || $selected_download === $download_id );
			$price_option_matches = ( intval( '-1' ) === intval( $selected_price_option ) || $selected_price_option === $price_option_id );

			if ( $download_matches && $price_option_matches ) {
				$matching_item = $item;
				break;
			}
		}

		if ( ! $matching_item ) {
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
			'EDD_DISCOUNT_CODES'              => $order->get_discounts() ? implode( ', ', wp_list_pluck( $order->get_discounts(), 'description' ) ) : '',
			'EDD_LICENSE_KEY'                 => $this->get_item_helpers()->get_licenses( $order_id ),
		);

		return $tokens;
	}

	/**
	 * Get selected price option with fallback logic.
	 *
	 * @param array $trigger The trigger data.
	 * @return string The selected price option.
	 */
	private function get_selected_price_option( $trigger ) {
		$selected_price_option = $trigger['meta'][ $this->price_option_meta ] ?? '';

		// Fallback: try to get price option from different possible keys
		if ( empty( $selected_price_option ) ) {
			if ( isset( $trigger['meta']['EDDPRICEOPTION'] ) ) {
				$selected_price_option = $trigger['meta']['EDDPRICEOPTION'];
			} elseif ( isset( $trigger['meta'][ 'EDDPRICEOPTION:' . $this->get_trigger_meta() ] ) ) {
				$selected_price_option = $trigger['meta'][ 'EDDPRICEOPTION:' . $this->get_trigger_meta() ];
			}
		}

		return $selected_price_option;
	}
}
