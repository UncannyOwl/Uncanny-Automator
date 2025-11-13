<?php

namespace Uncanny_Automator\Integrations\SureCart;

class_alias( 'Uncanny_Automator\Integrations\SureCart\SureCart_Tokens_New_Framework', 'Uncanny_Automator\SureCart_Tokens_New_Framework' );

/**
 * Class SureCart_Tokens
 *
 * @package Uncanny_Automator
 */
class SureCart_Tokens_New_Framework {

	/**
	 * common_tokens
	 *
	 * @return array
	 */
	public function common_tokens() {

		$tokens = array(
			array(
				'tokenId'   => 'STORE_NAME',
				'tokenName' => esc_html_x( 'Store name', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'STORE_URL',
				'tokenName' => esc_html_x( 'Store URL', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
		);

		return apply_filters( 'automator_surecart_common_tokens_v2', $tokens );
	}

	/**
	 * Get product image data from SureCart product
	 *
	 * @param string $product_id The product ID
	 * @return array Array with 'id' and 'url' keys
	 */
	private function get_product_image_data( $product_id ) {
		$product_image_id  = '';
		$product_image_url = '';

		if ( empty( $product_id ) ) {
			return array(
				'id' => $product_image_id,
				'url' => $product_image_url,
			);
		}

		// Fetch fresh product data with all media relationships
		$fresh_product = \SureCart\Models\Product::with( array( 'featured_product_media', 'featured_product_media.media', 'product_medias', 'product_media.media' ) )->find( $product_id );

		if ( $fresh_product ) {
			// Try featured_product_media first
			if ( isset( $fresh_product->featured_product_media ) && ! empty( $fresh_product->featured_product_media ) ) {
				$featured_media = $fresh_product->featured_product_media;
				if ( isset( $featured_media->media ) ) {
					$product_image_id  = $featured_media->media->id ?? '';
					$product_image_url = $featured_media->media->url ?? '';
				}
			}
			// Fallback to first product_media
			elseif ( isset( $fresh_product->product_medias ) && ! empty( $fresh_product->product_medias->data ) ) {
				$first_media = $fresh_product->product_medias->data[0];
				if ( isset( $first_media->media ) ) {
					$product_image_id  = $first_media->media->id ?? '';
					$product_image_url = $first_media->media->url ?? '';
				}
			}
			// Fallback to WordPress gallery IDs from metadata
			elseif ( isset( $fresh_product->metadata->gallery_ids ) && ! empty( $fresh_product->metadata->gallery_ids ) ) {
				$gallery_ids = json_decode( $fresh_product->metadata->gallery_ids, true );
				if ( is_array( $gallery_ids ) && ! empty( $gallery_ids ) ) {
					// Use the first gallery ID as the featured image
					$wp_media_id       = $gallery_ids[0];
					$product_image_id  = $wp_media_id;
					$product_image_url = wp_get_attachment_image_url( $wp_media_id, 'full' );
				}
			}
		}

		return array(
			'id' => $product_image_id,
			'url' => $product_image_url,
		);
	}

	/**
	 * The product tokens.
	 *
	 * @return array[] The list of tokens where array key is the token identifier.
	 */
	public function product_tokens() {

		$tokens = array(
			array(
				'tokenId'   => 'PRODUCT_NAME',
				'tokenName' => esc_html_x( 'Product name', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'PRODUCT_ID',
				'tokenName' => esc_html_x( 'Product ID', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'PRODUCT_THUMB',
				'tokenName' => esc_html_x( 'Product image', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'PRODUCT_THUMB_ID',
				'tokenName' => esc_html_x( 'Product image ID', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'PRODUCT_PRICE',
				'tokenName' => esc_html_x( 'Product price', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'PRODUCT_PRICE_ID',
				'tokenName' => esc_html_x( 'Product price ID', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'PRODUCT_PAYMENT_TYPE',
				'tokenName' => esc_html_x( 'Product payment type', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'PRODUCT_TRIAL_DAYS',
				'tokenName' => esc_html_x( 'Product trial days', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'PRODUCT_DOWNLOAD_URL',
				'tokenName' => esc_html_x( 'Product download URL', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'PRODUCT_DOWNLOAD_LINK',
				'tokenName' => esc_html_x( 'Product download link', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'PRODUCT_DOWNLOAD_TITLE',
				'tokenName' => esc_html_x( 'Product download title', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'SUBSCRIPTION_ID',
				'tokenName' => esc_html_x( 'Subscription ID', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ORDER_ID',
				'tokenName' => esc_html_x( 'Order ID', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ORDER_DATE',
				'tokenName' => esc_html_x( 'Order date', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'date',
			),
			array(
				'tokenId'   => 'ORDER_STATUS',
				'tokenName' => esc_html_x( 'Order status', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ORDER_NUMBER',
				'tokenName' => esc_html_x( 'Order number', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ORDER_PAID_AMOUNT',
				'tokenName' => esc_html_x( 'Paid amount', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ORDER_SUBTOTAL',
				'tokenName' => esc_html_x( 'Order subtotal', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ORDER_TOTAL',
				'tokenName' => esc_html_x( 'Order total', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ORDER_DISCOUNT',
				'tokenName' => esc_html_x( 'Order discount', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'PAYMENT_METHOD',
				'tokenName' => esc_html_x( 'Payment metho', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ORDER_COUPON',
				'tokenName' => esc_html_x( 'Coupon code', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);

		return apply_filters( 'automator_surecart_product_tokens_v2', $tokens );
	}

	/**
	 *
	 * @return array[] The list of tokens where array key is the token identifier.
	 */
	public function order_tokens() {

		$tokens = array(
			array(
				'tokenId'   => 'ORDER_PRODUCT',
				'tokenName' => esc_html_x( 'Product name', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ORDER_PRODUCT_ID',
				'tokenName' => esc_html_x( 'Product ID', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ORDER_PRODUCT_THUMB',
				'tokenName' => esc_html_x( 'Product image', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ORDER_PRODUCT_THUMB_ID',
				'tokenName' => esc_html_x( 'Product image ID', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ORDER_PRODUCT_PRICE',
				'tokenName' => esc_html_x( 'Product price', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ORDER_PRODUCT_PAYMENT_TYPE',
				'tokenName' => esc_html_x( 'Product payment type', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ORDER_PRODUCT_TRIAL_DAYS',
				'tokenName' => esc_html_x( 'Product trial days', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ORDER_AMOUNT_DUE',
				'tokenName' => esc_html_x( 'Order amount due', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ORDER_APPLIED_BALANCE',
				'tokenName' => esc_html_x( 'Order Applied balance amount', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ORDER_CREDITED_BALANCE',
				'tokenName' => esc_html_x( 'Order credited balance amount', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ORDER_DISCOUNT_AMOUNT',
				'tokenName' => esc_html_x( 'Order discount amount', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ORDER_SUBTOTAL_AMOUNT',
				'tokenName' => esc_html_x( 'Order subtotal amount', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ORDER_PAYMENT_PROCESSOR',
				'tokenName' => esc_html_x( 'Order payment processor', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ORDER_PDF',
				'tokenName' => esc_html_x( 'Order PDF URL', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'ORDER_ID',
				'tokenName' => esc_html_x( 'Order ID', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ORDER_NUMBER',
				'tokenName' => esc_html_x( 'Order number', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);

		return apply_filters( 'automator_surecart_product_tokens_v2', $tokens );
	}

	/**
	 * shipping_tokens
	 *
	 * @return array
	 */
	public function shipping_tokens() {

		$tokens = array(
			array(
				'tokenId'   => 'SHIPPING_COUNTRY',
				'tokenName' => esc_html_x( 'Shipping country', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'SHIPPING_STATE',
				'tokenName' => esc_html_x( 'Shipping state', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'SHIPPING_CITY',
				'tokenName' => esc_html_x( 'Shipping city', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'SHIPPING_LINE_1',
				'tokenName' => esc_html_x( 'Shipping line 1', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'SHIPPING_LINE_2',
				'tokenName' => esc_html_x( 'Shipping line 2', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'SHIPPING_NAME',
				'tokenName' => esc_html_x( 'Shipping name', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'SHIPPING_POSTCODE',
				'tokenName' => esc_html_x( 'Shipping postcode', 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);

		return apply_filters( 'automator_surecart_shipping_tokens_v2', $tokens );
	}

	/**
	 * billing_tokens
	 *
	 * @return array
	 */
	public function billing_tokens() {

		$tokens = array(
			array(
				'tokenId'   => 'BILLING_NAME',
				'tokenName' => esc_html_x( "Customer's name", 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'BILLING_EMAIL',
				'tokenName' => esc_html_x( "Customer's email", 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'BILLING_PHONE',
				'tokenName' => esc_html_x( "Customer's phone", 'Surecart', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);

		return apply_filters( 'automator_surecart_billing_tokens_v2', $tokens );
	}

	/**
	 * Populate the token with actual values.
	 *
	 * @param $args
	 * @param $trigger
	 *
	 * @return array
	 */
	public function hydrate_product_tokens( $purchase ) {

		$purchase_data = $this->get_hydrated_purchase( $purchase->id );

		$price = $this->get_price( $purchase_data );

		$amount         = $this->get_amount( $price );
		$price_type     = $this->get_price_type( $price );
		$download_names = $this->get_download_names( $purchase_data );
		$download_urls  = $this->get_download_urls( $purchase_data );
		$download_links = $this->get_download_links( $purchase_data );

		$tokens = array();

		$chekout = $purchase_data->initial_order->checkout;

		// Get product image data
		$image_data        = $this->get_product_image_data( $purchase_data->product->id );
		$product_image_id  = $image_data['id'];
		$product_image_url = $image_data['url'];

		$parsed = array(
			'PRODUCT'                => $purchase_data->product->name,
			'PRODUCT_NAME'           => $purchase_data->product->name,
			'PRODUCT_ID'             => $purchase_data->product->id,
			'PRODUCT_THUMB_ID'       => $product_image_id,
			'PRODUCT_THUMB'          => $product_image_url,
			'PRODUCT_PRICE'          => $amount,
			'PRODUCT_PRICE_ID'       => isset( $price->id ) ? $price->id : '',
			'PRODUCT_PAYMENT_TYPE'   => $price_type,
			'PRODUCT_TRIAL_DAYS'     => empty( $price->trial_duration_days ) ? '' : $price->trial_duration_days,
			'PRODUCT_DOWNLOAD_URL'   => $download_urls,
			'PRODUCT_DOWNLOAD_LINK'  => $download_links,
			'PRODUCT_DOWNLOAD_TITLE' => $download_names,
			'ORDER_ID'               => $purchase_data->initial_order->id,
			'SUBSCRIPTION_ID'        => isset( $purchase_data->subscription->id ) ? $purchase_data->subscription->id : '',
			'ORDER_NUMBER'           => $purchase_data->initial_order->number,
			'ORDER_DATE'             => gmdate( get_option( 'date_format', 'F j, Y' ), $purchase_data->initial_order->created_at ),
			'ORDER_STATUS'           => $purchase_data->initial_order->status,
			'ORDER_PAID_AMOUNT'      => $this->format_amount( $chekout->charge->amount ),
			'ORDER_SUBTOTAL'         => $this->format_amount( $chekout->subtotal_amount ),
			'ORDER_TOTAL'            => $this->format_amount( $chekout->total_amount ),
			'PAYMENT_METHOD'         => isset( $chekout->payment_method->processor_type ) ? $chekout->payment_method->processor_type : '',
			'ORDER_COUPON'           => empty( $chekout->discount->coupon->name ) ? '' : $chekout->discount->coupon->name,
			'ORDER_DISCOUNT'         => $this->format_amount( $chekout->discount_amount ),
		);

		$tokens = array_merge( $tokens, $parsed );

		$shipping_tokens = $this->hydrate_shipping_tokens( $parsed, $purchase_data );
		$tokens          = array_merge( $tokens, $shipping_tokens );
		$billing_tokens  = $this->hydrate_billing_tokens( $parsed, $purchase_data );
		$tokens          = array_merge( $tokens, $billing_tokens );

		return apply_filters( 'automator_surecart_hydrate_product_tokens_v2', $tokens, $purchase );
	}

	/**
	 * Populate the token with actual values.
	 *
	 * @param $args
	 * @param $trigger
	 *
	 * @return array
	 */
	public function hydrate_order_tokens( $checkout ) {

		$checkout = \SureCart\Models\Checkout::with( array( 'purchases', 'purchase.product', 'purchase.line_items', 'line_item.price', 'payment_method' ) )->find( $checkout->id );

		foreach ( $checkout->purchases->data as $purchase_data ) {

			$product = $purchase_data->product;

			$product_names[] = $product->name;
			$product_ids[]   = $product->id;

			// Get product image data
			$image_data        = $this->get_product_image_data( $product->id );
			$product_image_id  = $image_data['id'];
			$product_image_url = $image_data['url'];

			if ( ! empty( $product_image_id ) ) {
				$product_thumbs[] = $product_image_id;
			}

			if ( ! empty( $product_image_url ) ) {
				$product_thumbs_url[] = $product_image_url;
			}

			if ( ! empty( $product->subscription ) ) {
				$product_subscription_id[] = $product->subscription;
			}

			$price                = $this->get_price( $purchase_data );
			$product_amount[]     = $this->get_amount( $price );
			$product_price_type[] = $this->get_price_type( $price );

			if ( ! empty( $price->trial_duration_days ) ) {
				$product_trial_days[] = $price->trial_duration_days;
			}
		}

		$output = array(
			'ORDER_PRODUCT'                 => implode( ', ', $product_names ),
			'ORDER_PRODUCT_ID'              => implode( ', ', $product_ids ),
			'ORDER_PRODUCT_THUMB'           => ! empty( $product_thumbs_url ) ? implode( ', ', $product_thumbs_url ) : '',
			'ORDER_PRODUCT_THUMB_ID'        => ! empty( $product_thumbs ) ? implode( ', ', $product_thumbs ) : '',
			'ORDER_PRODUCT_PRICE'           => implode( ', ', $product_amount ),
			'ORDER_PRODUCT_PAYMENT_TYPE'    => implode( ', ', $product_price_type ),
			'ORDER_PRODUCT_TRIAL_DAYS'      => ! empty( $product_trial_days ) ? implode( ', ', $product_trial_days ) : '',
			'ORDER_PRODUCT_SUBSCRIPTION_ID' => ! empty( $product_subscription_id ) ? implode( ', ', $product_subscription_id ) : '',
			'ORDER_AMOUNT_DUE'              => $this->format_amount( $checkout->amount_due ),
			'ORDER_APPLIED_BALANCE'         => $this->format_amount( $checkout->applied_balance_amount ),
			'ORDER_CREDITED_BALANCE'        => $this->format_amount( $checkout->credited_balance_amount ),
			'ORDER_DISCOUNT_AMOUNT'         => $this->format_amount( $checkout->discount_amount ),
			'ORDER_SUBTOTAL_AMOUNT'         => $this->format_amount( $checkout->subtotal_amount ),
			'ORDER_PAYMENT_PROCESSOR'       => $checkout->payment_method->processor_type,
			'ORDER_ID'                      => $checkout->order,
			'ORDER_NUMBER'                  => $checkout->number,
			'ORDER_PDF'                     => $checkout->pdf_url,
		);

		return apply_filters( 'automator_surecart_hydrate_order_tokens_v2', $output, $checkout );
	}

	/**
	 * Populate the token with actual values.
	 *
	 * @param $args
	 * @param $trigger
	 *
	 * @return array
	 */
	public function hydrate_common_tokens() {

		$account = \SureCart\Models\Account::find();

		$output = array(
			'STORE_NAME' => $account->name,
			'STORE_URL'  => $account->url,
		);

		return apply_filters( 'automator_surecart_hydrate_common_tokens_v2', $output );
	}

	/**
	 * hydrate_shipping_tokens
	 *
	 * @param  mixed $parsed
	 * @param  mixed $data
	 * @return array
	 */
	public function hydrate_shipping_tokens( $parsed, $data ) {

		$shipping_address = null;

		if ( is_a( $data, '\SureCart\Models\Purchase' ) && isset( $data->initial_order->checkout->shipping_address ) ) {
			$shipping_address = $data->initial_order->checkout->shipping_address;
		}

		if ( is_a( $data, '\SureCart\Models\Checkout' ) && isset( $data->shipping_address ) ) {
			$shipping_address = $data->shipping_address;
		}

		if ( null === $shipping_address ) {
			return $parsed;
		}

		$parsed = $parsed + array(
			'SHIPPING_COUNTRY'  => empty( $shipping_address->country ) ? '' : $shipping_address->country,
			'SHIPPING_STATE'    => empty( $shipping_address->state ) ? '' : $shipping_address->state,
			'SHIPPING_CITY'     => empty( $shipping_address->city ) ? '' : $shipping_address->city,
			'SHIPPING_LINE_1'   => empty( $shipping_address->line_1 ) ? '' : $shipping_address->line_1,
			'SHIPPING_LINE_2'   => empty( $shipping_address->line_2 ) ? '' : $shipping_address->line_2,
			'SHIPPING_POSTCODE' => empty( $shipping_address->postal_code ) ? '' : $shipping_address->postal_code,
			'SHIPPING_NAME'     => empty( $shipping_address->name ) ? '' : $shipping_address->name,
		);

		return apply_filters( 'automator_surecart_hydrate_shipping_tokens', $parsed, $data );
	}

	/**
	 * hydrate_billing_tokens
	 *
	 * @param  mixed $checkout
	 * @return array
	 */
	public function hydrate_billing_tokens( $checkout = null ) {

		if ( null === $checkout ) {
			return array();
		}

		$billing_tokens = array(
			'BILLING_NAME'  => empty( $checkout->name ) ? '' : $checkout->name,
			'BILLING_EMAIL' => empty( $checkout->email ) ? '' : $checkout->email,
			'BILLING_PHONE' => empty( $checkout->phone ) ? '' : $checkout->phone,
		);

		return apply_filters( 'automator_surecart_hydrate_billing_tokens_v2', $billing_tokens, $checkout );
	}

	/**
	 * get_price
	 *
	 * @param  object $purchase_data
	 * @return object
	 */
	public function get_price( $purchase_data ) {

		if ( empty( $purchase_data->line_items->data[0] ) ) {
			return;
		}

		$line_item = $purchase_data->line_items->data[0];

		return $line_item->price;
	}

	/**
	 * get_downloads
	 *
	 * @param  object $purchase_data
	 * @return object
	 */
	public function get_downloads( $purchase_data ) {

		if ( empty( $purchase_data->product->downloads->data ) ) {
			return;
		}

		return $purchase_data->product->downloads->data;
	}

	/**
	 * get_amount
	 *
	 * @param  object $price
	 * @return string
	 */
	public function get_amount( $price ) {

		$amount = $this->format_amount( $price->amount );

		$amount .= ' ' . $price->currency;

		$amount = apply_filters( 'automator_surecart_amount', $amount, $price );

		if ( ! empty( $price->recurring_interval ) ) {

			$interval = $this->get_interval_string( $price->recurring_interval, $price->recurring_interval_count );

			// translators: 1. recurring count 2. recurring interval.
			$amount .= sprintf( esc_html_x( 'every %1$d %2$s', 'Surecart', 'uncanny-automator' ), $price->recurring_interval_count, $interval );
		}

		if ( ! empty( $price->recurring_period_count ) ) {
			$interval = $this->get_interval_string( $price->recurring_interval, $price->recurring_period_count );
			// translators: 1. period count 2. recurring interval.
			$amount .= sprintf( ' for %1$d %2$ss', $price->recurring_period_count, $price->recurring_interval );
		}

		return apply_filters( 'automator_surecart_amount', $amount, $price );
	}

	/**
	 * get_price_type
	 *
	 * @param  object $price
	 * @return string
	 */
	public function get_price_type( $price ) {

		$plan = esc_html_x( 'One Time', 'Surecart', 'uncanny-automator' );

		if ( ! empty( $price->recurring_period_count ) ) {
			$plan = esc_html_x( 'Plan', 'Surecart', 'uncanny-automator' );
		}

		if ( ! empty( $price->recurring_interval ) ) {
			$plan = esc_html_x( 'Subscription', 'Surecart', 'uncanny-automator' );
		}

		return apply_filters( 'automator_surecart_price_type', $plan, $price );
	}

	/**
	 * get_download_names
	 *
	 * @param  object $purchase_data
	 * @return string
	 */
	public function get_download_names( $purchase_data ) {

		$downloads = $this->get_downloads( $purchase_data );

		if ( empty( $downloads ) ) {
			return '';
		}

		$download_names = array();

		foreach ( $downloads as $download ) {
			$download_names[] = $download->media->filename;
		}

		return implode( ', ', $download_names );
	}

	/**
	 * get_download_urls
	 *
	 * @param  object $purchase_data
	 * @return string
	 */
	public function get_download_urls( $purchase_data ) {

		$downloads = $this->get_downloads( $purchase_data );

		$download_urls = array();

		if ( empty( $downloads ) ) {
			return '';
		}

		foreach ( $downloads as $download ) {
			$expose_for      = apply_filters( 'automator_surecart_token_product_download_url_expiration', 24 * 60 * 60, $download, $purchase_data );
			$media           = \SureCart\Models\Media::where( array( 'expose_for' => $expose_for ) )->find( $download->media->id );
			$download_urls[] = $media->url;
		}

		return implode( ', ', $download_urls );
	}

	/**
	 * get_download_links
	 *
	 * @param  object $purchase_data
	 * @return string
	 */
	public function get_download_links( $purchase_data ) {

		$downloads = $this->get_downloads( $purchase_data );

		$download_links = array();

		if ( empty( $downloads ) ) {
			return '';
		}

		foreach ( $downloads as $download ) {
			$expose_for       = apply_filters( 'automator_surecart_product_download_url_expiration', 24 * 60 * 60, $download, $purchase_data );
			$media            = \SureCart\Models\Media::where( array( 'expose_for' => $expose_for ) )->find( $download->media->id );
			$format           = '<a href="%s" title="%s">%s</a>';
			$download_link    = sprintf( $format, $media->url, $media->filename, esc_html_x( 'Download', 'Surecart', 'uncanny-automator' ) );
			$download_links[] = apply_filters( 'automator_surecart_product_download_link', $download_link );
		}

		return implode( ', ', $download_links );
	}

	/**
	 * get_interval_string
	 *
	 * @param  string $interval
	 * @param  int $count
	 * @return string
	 */
	public function get_interval_string( $interval, $count ) {
		switch ( $interval ) {
			case 'day':
				return _n( 'day', 'days', $count, 'uncanny-automator' );
			case 'week':
				return _n( 'week', 'weeks', $count, 'uncanny-automator' );
			case 'month':
				return _n( 'month', 'months', $count, 'uncanny-automator' );
			case 'year':
				return _n( 'year', 'years', $count, 'uncanny-automator' );
		}
	}

	/**
	 * format_amount
	 *
	 * @param  mixed $amount
	 * @return mixed
	 */
	public function format_amount( $amount ) {
		return $amount / 100;
	}

	/**
	 * get_hydrated_purchase
	 *
	 * @param  mixed $id
	 * @return \SureCart\Models\Purchase
	 */
	public function get_hydrated_purchase( $id ) {
		return \SureCart\Models\Purchase::with( array( 'initial_order', 'order.checkout', 'checkout.shipping_address', 'checkout.payment_method', 'checkout.discount', 'discount.coupon', 'checkout.charge', 'product', 'product.downloads', 'download.media', 'license.activations', 'line_items', 'line_item.price', 'subscription' ) )->find( $id );
	}
}
