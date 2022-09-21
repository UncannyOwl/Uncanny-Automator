<?php

namespace Uncanny_Automator;

use SureCart\Models\Purchase;

/**
 * Class SureCart_Tokens
 *
 * @package Uncanny_Automator
 */
class SureCart_Tokens {

	public function common_tokens() {

		$tokens = array(
			'STORE_NAME' => array(
				'name'         => __( 'Store name', 'uncanny-automator' ),
				'hydrate_with' => array( $this, 'hydrate_common_tokens' ),
			),
			'STORE_URL'  => array(
				'name'         => __( 'Store URL', 'uncanny-automator' ),
				'hydrate_with' => array( $this, 'hydrate_common_tokens' ),
			),
		);

		return apply_filters( 'automator_surecart_common_tokens', $tokens );
	}

	/**
	 * The product tokens.
	 *
	 * @return array[] The list of tokens where array key is the token identifier.
	 */
	public function product_tokens() {

		$tokens = array(
			'PRODUCT_ID'             => array(
				'name' => __( 'Product ID', 'uncanny-automator' ),
			),
			'PRODUCT_THUMB'          => array(
				'name' => __( 'Product image', 'uncanny-automator' ),
			),
			'PRODUCT_THUMB_ID'       => array(
				'name' => __( 'Product image ID', 'uncanny-automator' ),
			),
			'PRODUCT_PRICE'          => array(
				'name' => __( 'Product price', 'uncanny-automator' ),
			),
			'PRODUCT_PRICE_ID'       => array(
				'name' => __( 'Product price ID', 'uncanny-automator' ),
			),
			'PRODUCT_PAYMENT_TYPE'   => array(
				'name' => __( 'Product payment type', 'uncanny-automator' ),
			),
			'PRODUCT_TRIAL_DAYS'     => array(
				'name' => __( 'Product trial days', 'uncanny-automator' ),
			),
			'PRODUCT_DOWNLOAD_URL'   => array(
				'name' => __( 'Product download URL', 'uncanny-automator' ),
			),
			'PRODUCT_DOWNLOAD_LINK'  => array(
				'name' => __( 'Product download link', 'uncanny-automator' ),
			),
			'PRODUCT_DOWNLOAD_TITLE' => array(
				'name' => __( 'Product download title', 'uncanny-automator' ),
			),
			'SUBSCRIPTION_ID'        => array(
				'name' => __( 'Subscription ID', 'uncanny-automator' ),
			),
			'ORDER_ID'               => array(
				'name' => __( 'Order ID', 'uncanny-automator' ),
			),
			'ORDER_DATE'             => array(
				'name' => __( 'Order date', 'uncanny-automator' ),
			),
			'ORDER_STATUS'           => array(
				'name' => __( 'Order status', 'uncanny-automator' ),
			),
			'ORDER_NUMBER'           => array(
				'name' => __( 'Order number', 'uncanny-automator' ),
			),
			'ORDER_PAID_AMOUNT'      => array(
				'name' => __( 'Paid amount', 'uncanny-automator' ),
			),
			'ORDER_SUBTOTAL'         => array(
				'name' => __( 'Order subtotal', 'uncanny-automator' ),
			),
			'ORDER_TOTAL'            => array(
				'name' => __( 'Order total', 'uncanny-automator' ),
			),
			'ORDER_DISCOUNT'         => array(
				'name' => __( 'Order discount', 'uncanny-automator' ),
			),
			'PAYMENT_METHOD'         => array(
				'name' => __( 'Payment method', 'uncanny-automator' ),
			),
			'ORDER_COUPON'           => array(
				'name' => __( 'Coupon code', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'automator_surecart_product_tokens', $tokens );
	}

	/**
	 *
	 * @return array[] The list of tokens where array key is the token identifier.
	 */
	public function order_tokens() {

		$tokens = array(
			'ORDER_PRODUCT'                 => array(
				'name' => __( 'Product name', 'uncanny-automator' ),
			),
			'ORDER_PRODUCT_ID'              => array(
				'name' => __( 'Product ID', 'uncanny-automator' ),
			),
			'ORDER_PRODUCT_THUMB'           => array(
				'name' => __( 'Product image', 'uncanny-automator' ),
			),
			'ORDER_PRODUCT_THUMB_ID'        => array(
				'name' => __( 'Product image ID', 'uncanny-automator' ),
			),
			'ORDER_PRODUCT_PRICE'           => array(
				'name' => __( 'Product price', 'uncanny-automator' ),
			),
			'ORDER_PRODUCT_PAYMENT_TYPE'    => array(
				'name' => __( 'Product payment type', 'uncanny-automator' ),
			),
			'ORDER_PRODUCT_TRIAL_DAYS'      => array(
				'name' => __( 'Product trial days', 'uncanny-automator' ),
			),
			'ORDER_PRODUCT_SUBSCRIPTION_ID' => array(
				'name' => __( 'Product subscription ID', 'uncanny-automator' ),
			),
			'ORDER_AMOUNT_DUE'              => array(
				'name' => __( 'Order amount due', 'uncanny-automator' ),
			),
			'ORDER_APPLIED_BALANCE'         => array(
				'name' => __( 'Order Applied balance amount', 'uncanny-automator' ),
			),
			'ORDER_CREDITED_BALANCE'        => array(
				'name' => __( 'Order credited balance amount', 'uncanny-automator' ),
			),
			'ORDER_DISCOUNT_AMOUNT'         => array(
				'name' => __( 'Order discount amount', 'uncanny-automator' ),
			),
			'ORDER_SUBTOTAL_AMOUNT'         => array(
				'name' => __( 'Order subtotal amount', 'uncanny-automator' ),
			),
			'ORDER_UNIT_AMOUNT'             => array(
				'name' => __( 'Order unit amount', 'uncanny-automator' ),
			),
			'ORDER_PAYMENT_PROCESSOR'       => array(
				'name' => __( 'Order payment processor', 'uncanny-automator' ),
			),
			'ORDER_PDF'                     => array(
				'name' => __( 'Order PDF URL', 'uncanny-automator' ),
			),
			'ORDER_ID'                      => array(
				'name' => __( 'Order ID', 'uncanny-automator' ),
			),
			'ORDER_NUMBER'                  => array(
				'name' => __( 'Order number', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'automator_surecart_product_tokens', $tokens );
	}

	public function shipping_tokens() {

		$tokens = array(
			'SHIPPING_COUNTRY'  => array(
				'name' => __( 'Shipping country', 'uncanny-automator' ),
			),
			'SHIPPING_STATE'    => array(
				'name' => __( 'Shipping state', 'uncanny-automator' ),
			),
			'SHIPPING_CITY'     => array(
				'name' => __( 'Shipping city', 'uncanny-automator' ),
			),
			'SHIPPING_LINE_1'   => array(
				'name' => __( 'Shipping line 1', 'uncanny-automator' ),
			),
			'SHIPPING_LINE_2'   => array(
				'name' => __( 'Shipping line 2', 'uncanny-automator' ),
			),
			'SHIPPING_NAME'     => array(
				'name' => __( 'Shipping name', 'uncanny-automator' ),
			),
			'SHIPPING_POSTCODE' => array(
				'name' => __( 'Shipping postcode', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'automator_surecart_shipping_tokens', $tokens );
	}

	public function billing_tokens() {

		$tokens = array(
			'BILLING_NAME'  => array(
				'name' => __( 'Billing name', 'uncanny-automator' ),
			),
			'BILLING_EMAIL' => array(
				'name' => __( 'Billing email', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'automator_surecart_shipping_tokens', $tokens );
	}

	/**
	 * Populate the token with actual values.
	 *
	 * @param $args
	 * @param $trigger
	 *
	 * @return array
	 */
	public function hydrate_product_tokens( $parsed, $args, $trigger ) {

		$purchase = array_shift( $args['trigger_args'] );

		$purchase_data = $this->get_hydrated_purchase( $purchase->id );

		$price = $this->get_price( $purchase_data );

		$amount         = $this->get_amount( $price );
		$price_type     = $this->get_price_type( $price );
		$download_names = $this->get_download_names( $purchase_data );
		$download_urls  = $this->get_download_urls( $purchase_data );
		$download_links = $this->get_download_links( $purchase_data );

		$chekout = $purchase_data->initial_order->checkout;

		$parsed = $parsed + array(
			'PRODUCT'                => $purchase_data->product->name,
			'PRODUCT_ID'             => $purchase_data->product->id,
			'PRODUCT_THUMB_ID'       => isset( $purchase_data->product->image ) ? $purchase_data->product->image : '',
			'PRODUCT_THUMB'          => isset( $purchase_data->product->image_url ) ? $purchase_data->product->image_url : '',
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
			'ORDER_DATE'             => date( get_option( 'date_format', 'F j, Y' ), $purchase_data->initial_order->created_at ),
			'ORDER_STATUS'           => $purchase_data->initial_order->status,
			'ORDER_PAID_AMOUNT'      => $this->format_amount( $chekout->charge->amount ),
			'ORDER_SUBTOTAL'         => $this->format_amount( $chekout->subtotal_amount ),
			'ORDER_TOTAL'            => $this->format_amount( $chekout->total_amount ),
			'PAYMENT_METHOD'         => isset( $chekout->payment_method->processor_type ) ? $chekout->payment_method->processor_type : '',
			'ORDER_COUPON'           => empty( $chekout->discount->coupon->name ) ? '' : $chekout->discount->coupon->name,
			'ORDER_DISCOUNT'         => $this->format_amount( $chekout->discount_amount ),
		);

		$parsed = $this->hydrate_shipping_tokens( $parsed, $purchase_data );
		$parsed = $this->hydrate_billing_tokens( $parsed, $purchase_data );

		return apply_filters( 'automator_surecart_hydrate_product_tokens', $parsed, $args, $trigger );
	}

	/**
	 * Populate the token with actual values.
	 *
	 * @param $args
	 * @param $trigger
	 *
	 * @return array
	 */
	public function hydrate_order_tokens( $parsed, $args, $trigger ) {

		$checkout = array_shift( $args['trigger_args'] );

		$checkout = \SureCart\Models\Checkout::with( array( 'purchases', 'purchase.product', 'purchase.line_items', 'line_item.price', 'payment_method' ) )->find( $checkout->id );

		foreach ( $checkout->purchases->data as $purchase_data ) {

			$product = $purchase_data->product;

			$product_names[] = $product->name;
			$product_ids[]   = $product->id;

			if ( ! empty( $product->image ) ) {
				$product_thumbs[] = $product->image;
			}

			if ( ! empty( $product->image_url ) ) {
				$product_thumbs_url[] = $product->image_url;
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

		$parsed = $parsed + array(
			'ORDER_PRODUCT'                 => implode( ', ', $product_names ),
			'ORDER_PRODUCT_ID'              => implode( ', ', $product_ids ),
			'ORDER_PRODUCT_THUMB'           => ! empty( $product_thumbs ) ? implode( ', ', $product_thumbs ) : '',
			'ORDER_PRODUCT_THUMB_ID'        => ! empty( $product_tproduct_thumbs_urlhumbs ) ? implode( ', ', $product_thumbs_url ) : '',
			'ORDER_PRODUCT_PRICE'           => implode( ', ', $product_amount ),
			'ORDER_PRODUCT_PAYMENT_TYPE'    => implode( ', ', $product_price_type ),
			'ORDER_PRODUCT_TRIAL_DAYS'      => ! empty( $product_trial_days ) ? implode( ', ', $product_trial_days ) : '',
			'ORDER_PRODUCT_SUBSCRIPTION_ID' => ! empty( $product_subscription_id ) ? implode( ', ', $product_subscription_id ) : '',
			'ORDER_AMOUNT_DUE'              => $this->format_amount( $checkout->amount_due ),
			'ORDER_APPLIED_BALANCE'         => $this->format_amount( $checkout->applied_balance_amount ),
			'ORDER_CREDITED_BALANCE'        => $this->format_amount( $checkout->credited_balance_amount ),
			'ORDER_DISCOUNT_AMOUNT'         => $this->format_amount( $checkout->discount_amount ),
			'ORDER_SUBTOTAL_AMOUNT'         => $this->format_amount( $checkout->subtotal_amount ),
			'ORDER_UNIT_AMOUNT'             => '',
			'ORDER_PAYMENT_PROCESSOR'       => $checkout->payment_method->processor_type,
			'ORDER_ID'                      => $checkout->order,
			'ORDER_NUMBER'                  => $checkout->number,
			'ORDER_PDF'                     => $checkout->pdf_url,
		);

		return apply_filters( 'automator_surecart_hydrate_order_tokens', $parsed, $args, $trigger );
	}

	/**
	 * Populate the token with actual values.
	 *
	 * @param $args
	 * @param $trigger
	 *
	 * @return array
	 */
	public function hydrate_common_tokens( $token, $args, $trigger ) {

		$account = \SureCart\Models\Account::find();

		$output = '';

		switch ( $token['id'] ) {
			case 'STORE_NAME':
				$output = $account->name;
				break;
			case 'STORE_URL':
				$output = $account->url;
				break;
		}

		return apply_filters( 'automator_surecart_hydrate_common_tokens', $output, $token, $args, $trigger );
	}

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

	public function hydrate_billing_tokens( $parsed, $data ) {

		$checkout = null;

		if ( is_a( $data, '\SureCart\Models\Purchase' ) && isset( $data->initial_order->checkout ) ) {
			$checkout = $data->initial_order->checkout;
		}

		if ( is_a( $data, '\SureCart\Models\Checkout' ) ) {
			$checkout = $data;
		}

		if ( null === $checkout ) {
			return $parsed;
		}

		$parsed = $parsed + array(
			'BILLING_NAME'  => empty( $checkout->name ) ? '' : $checkout->name,
			'BILLING_EMAIL' => empty( $checkout->email ) ? '' : $checkout->email,
		);

		return apply_filters( 'automator_surecart_hydrate_billing_tokens', $parsed, $data );
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
			$amount .= sprintf( __( ' every %1$d %2$s', 'uncanny-automator' ), $price->recurring_interval_count, $interval );
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

		$plan = __( 'One Time', 'uncanny-automator' );

		if ( ! empty( $price->recurring_period_count ) ) {
			$plan = __( 'Plan', 'uncanny-automator' );
		}

		if ( ! empty( $price->recurring_interval ) ) {
			$plan = __( 'Subscription', 'uncanny-automator' );
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
			$download_link    = sprintf( $format, $media->url, $media->filename, __( 'Download', 'uncanny-automator' ) );
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

	public function format_amount( $amount ) {
		return $amount / 100;
	}

	public function get_hydrated_purchase( $id ) {
		return \SureCart\Models\Purchase::with( array( 'initial_order', 'order.checkout', 'checkout.shipping_address', 'checkout.payment_method', 'checkout.discount', 'discount.coupon', 'checkout.charge', 'product', 'product.downloads', 'download.media', 'license.activations', 'line_items', 'line_item.price', 'subscription' ) )->find( $id );
	}
}


