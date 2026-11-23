<?php

namespace Uncanny_Automator\Integrations\Easy_Digital_Downloads;

/**
 * Class Edd_Refund_Tokens
 *
 * The tokens of a refund, shared by the Free and Pro refund triggers. A refund event carries the
 * original order, the EDD refund record when EDD made one, the refund type and, for a Stripe
 * dashboard partial refund, the amount Stripe reported (see Edd_Refund_Dispatcher).
 *
 * @package Uncanny_Automator\Integrations\Easy_Digital_Downloads
 */
class Edd_Refund_Tokens {

	/**
	 * The helper that reads licences.
	 *
	 * @var Edd_Helpers
	 */
	private $helpers;

	/**
	 * Constructor.
	 *
	 * @param Edd_Helpers $helpers The EDD helper.
	 */
	public function __construct( Edd_Helpers $helpers ) {
		$this->helpers = $helpers;
	}

	/**
	 * Token definitions.
	 *
	 * @return array
	 */
	public function definitions() {

		$labels = array(
			'EDD_REFUND_TYPE'           => array( esc_html_x( 'Refund type', 'Easy Digital Downloads', 'uncanny-automator' ), 'text' ),
			'EDD_REFUND_TYPE_SLUG'      => array( esc_html_x( 'Refund type (full or partial)', 'Easy Digital Downloads', 'uncanny-automator' ), 'text' ),
			'EDD_REFUND_ID'             => array( esc_html_x( 'Refund ID', 'Easy Digital Downloads', 'uncanny-automator' ), 'int' ),
			'EDD_REFUND_AMOUNT'         => array( esc_html_x( 'Refund amount', 'Easy Digital Downloads', 'uncanny-automator' ), 'text' ),
			'EDD_REFUND_AMOUNT_NUMBER'  => array( esc_html_x( 'Refund amount (number)', 'Easy Digital Downloads', 'uncanny-automator' ), 'text' ),
			'EDD_REFUND_REASON'         => array( esc_html_x( 'Refund reason', 'Easy Digital Downloads', 'uncanny-automator' ), 'text' ),
			'EDD_REFUND_DATE'           => array( esc_html_x( 'Refund date', 'Easy Digital Downloads', 'uncanny-automator' ), 'date' ),
			'EDD_REFUND_ITEMS'          => array( esc_html_x( 'Refunded items', 'Easy Digital Downloads', 'uncanny-automator' ), 'text' ),
			'EDD_REFUND_TOTAL_REFUNDED' => array( esc_html_x( 'Total refunded so far', 'Easy Digital Downloads', 'uncanny-automator' ), 'text' ),
			'EDD_REFUND_REMAINING'      => array( esc_html_x( 'Amount not refunded', 'Easy Digital Downloads', 'uncanny-automator' ), 'text' ),
			'EDD_REFUND_GATEWAY'        => array( esc_html_x( 'Payment gateway', 'Easy Digital Downloads', 'uncanny-automator' ), 'text' ),
			'EDD_REFUND_GATEWAY_ID'     => array( esc_html_x( 'Payment gateway ID', 'Easy Digital Downloads', 'uncanny-automator' ), 'text' ),
			'EDD_ORDER_ID'              => array( esc_html_x( 'Order ID', 'Easy Digital Downloads', 'uncanny-automator' ), 'int' ),
			'EDD_ORDER_DATE'            => array( esc_html_x( 'Order date', 'Easy Digital Downloads', 'uncanny-automator' ), 'date' ),
			'EDD_ORDER_ITEMS'           => array( esc_html_x( 'Order items', 'Easy Digital Downloads', 'uncanny-automator' ), 'text' ),
			'EDD_ORDER_SUBTOTAL'        => array( esc_html_x( 'Order subtotal', 'Easy Digital Downloads', 'uncanny-automator' ), 'text' ),
			'EDD_ORDER_TAX'             => array( esc_html_x( 'Order tax', 'Easy Digital Downloads', 'uncanny-automator' ), 'text' ),
			'EDD_ORDER_TOTAL'           => array( esc_html_x( 'Order total', 'Easy Digital Downloads', 'uncanny-automator' ), 'text' ),
			'EDD_ORDER_CURRENCY'        => array( esc_html_x( 'Order currency', 'Easy Digital Downloads', 'uncanny-automator' ), 'text' ),
			'EDD_ORDER_URL'             => array( esc_html_x( 'Order URL (Admin)', 'Easy Digital Downloads', 'uncanny-automator' ), 'url' ),
			'EDD_ORDER_URL_USER'        => array( esc_html_x( 'Order URL (User)', 'Easy Digital Downloads', 'uncanny-automator' ), 'url' ),
			'EDD_DISCOUNT_CODES'        => array( esc_html_x( 'Discount codes used', 'Easy Digital Downloads', 'uncanny-automator' ), 'text' ),
			'EDD_LICENSE_KEY'           => array( esc_html_x( 'License key', 'Easy Digital Downloads', 'uncanny-automator' ), 'text' ),
			'EDD_CUSTOMER_ID'           => array( esc_html_x( 'Customer ID', 'Easy Digital Downloads', 'uncanny-automator' ), 'int' ),
			'EDD_CUSTOMER_NAME'         => array( esc_html_x( 'Customer name', 'Easy Digital Downloads', 'uncanny-automator' ), 'text' ),
			'EDD_CUSTOMER_EMAIL'        => array( esc_html_x( 'Customer email', 'Easy Digital Downloads', 'uncanny-automator' ), 'email' ),
		);

		$tokens = array();

		foreach ( $labels as $token_id => $definition ) {
			$tokens[ $token_id ] = array(
				'name'      => $definition[0],
				'type'      => $definition[1],
				'tokenId'   => $token_id,
				'tokenName' => $definition[0],
			);
		}

		return $tokens;
	}

	/**
	 * Token values for one refund event.
	 *
	 * @param int    $order_id  The original order.
	 * @param int    $refund_id The EDD refund record, 0 when EDD made none.
	 * @param string $type      "full" or "partial".
	 * @param string $amount    This refund's amount when no record exists and the gateway reported it, otherwise ''.
	 *
	 * @return array The full keyset, empty strings when the order is missing.
	 */
	public function values( $order_id, $refund_id, $type, $amount ) {

		$values = array_fill_keys( array_keys( $this->definitions() ), '' );
		$order  = function_exists( 'edd_get_order' ) ? edd_get_order( $order_id ) : false;

		if ( empty( $order ) ) {
			return $values;
		}

		$currency = (string) $order->currency;
		$refund   = $refund_id > 0 ? edd_get_order( $refund_id ) : false;

		// This refund's amount: the record when EDD made one, else what the gateway reported, else unknown.
		$refund_amount = null;
		if ( ! empty( $refund ) ) {
			$refund_amount = abs( (float) $refund->total );
		} elseif ( '' !== (string) $amount ) {
			$refund_amount = (float) $amount;
		}

		$recorded_total = 0.0;
		foreach ( (array) edd_get_order_refunds( $order->id ) as $record ) {
			$recorded_total += abs( (float) $record->total );
		}

		// A refund with no record is not in the recorded total yet.
		$total_refunded = empty( $refund ) && null !== $refund_amount ? $recorded_total + $refund_amount : $recorded_total;
		$amount_known   = null !== $refund_amount || $total_refunded > 0;

		$customer = $order->customer_id ? edd_get_customer( $order->customer_id ) : false;
		$items    = $this->helpers->get_refund_items( $order->id, $refund_id );

		$values['EDD_REFUND_TYPE']           = 'full' === $type ? esc_html_x( 'Full', 'Easy Digital Downloads', 'uncanny-automator' ) : esc_html_x( 'Partial', 'Easy Digital Downloads', 'uncanny-automator' );
		$values['EDD_REFUND_TYPE_SLUG']      = 'full' === $type ? 'full' : 'partial';
		$values['EDD_REFUND_ID']             = $refund_id;
		$values['EDD_REFUND_AMOUNT']         = null !== $refund_amount ? $this->money( $refund_amount, $currency ) : '';
		$values['EDD_REFUND_AMOUNT_NUMBER']  = null !== $refund_amount ? number_format( $refund_amount, 2, '.', '' ) : '';
		$values['EDD_REFUND_REASON']         = ! empty( $refund ) ? (string) edd_get_order_meta( $refund->id, 'reason', true ) : '';
		$values['EDD_REFUND_DATE']           = ! empty( $refund ) ? (string) $refund->date_created : '';
		$values['EDD_REFUND_ITEMS']          = implode( ', ', $this->item_names( $items ) );
		$values['EDD_REFUND_TOTAL_REFUNDED'] = $amount_known ? $this->money( $total_refunded, $currency ) : '';
		$values['EDD_REFUND_REMAINING']      = $amount_known ? $this->money( max( 0, (float) $order->total - $total_refunded ), $currency ) : '';
		$values['EDD_REFUND_GATEWAY']        = $this->gateway_label( $order->gateway );
		$values['EDD_REFUND_GATEWAY_ID']     = (string) $order->gateway;
		$values['EDD_ORDER_ID']              = (int) $order->id;
		$values['EDD_ORDER_DATE']            = (string) $order->date_created;
		$values['EDD_ORDER_ITEMS']           = implode( ', ', $this->item_names( $this->helpers->get_refund_items( $order->id, 0 ) ) );
		$values['EDD_ORDER_SUBTOTAL']        = $this->money( (float) $order->subtotal, $currency );
		$values['EDD_ORDER_TAX']             = $this->money( (float) $order->tax, $currency );
		$values['EDD_ORDER_TOTAL']           = $this->money( (float) $order->total, $currency );
		$values['EDD_ORDER_CURRENCY']        = $currency;
		$values['EDD_ORDER_URL']             = admin_url( 'edit.php?post_type=download&page=edd-payment-history&view=view-order-details&id=' . $order->id );
		$values['EDD_ORDER_URL_USER']        = (string) edd_get_receipt_page_uri( $order->id );
		$values['EDD_DISCOUNT_CODES']        = $this->discount_codes( $order );
		$values['EDD_LICENSE_KEY']           = (string) $this->helpers->get_licenses( $order->id );
		$values['EDD_CUSTOMER_ID']           = (int) $order->customer_id;
		$values['EDD_CUSTOMER_NAME']         = $customer ? (string) $customer->name : '';
		$values['EDD_CUSTOMER_EMAIL']        = (string) $order->email;

		return $values;
	}

	/**
	 * Format an amount with the order's currency.
	 *
	 * @param float  $amount   The amount.
	 * @param string $currency The currency code.
	 *
	 * @return string
	 */
	private function money( $amount, $currency ) {
		return edd_currency_filter( edd_format_amount( $amount ), $currency );
	}

	/**
	 * The names of order items.
	 *
	 * @param array $items Order items.
	 *
	 * @return string[]
	 */
	private function item_names( $items ) {

		$names = array();

		foreach ( $items as $item ) {
			$name = (string) $item->product_name;

			if ( null !== $item->price_id && '' !== (string) $item->price_id ) {
				$option = edd_get_price_option_name( $item->product_id, $item->price_id );
				$name   = '' !== (string) $option ? $name . ' - ' . $option : $name;
			}

			$names[] = $name;
		}

		return $names;
	}

	/**
	 * The admin label of a gateway, the ID when EDD has none.
	 *
	 * @param string $gateway The gateway ID.
	 *
	 * @return string
	 */
	private function gateway_label( $gateway ) {
		return function_exists( 'edd_get_gateway_admin_label' ) ? (string) edd_get_gateway_admin_label( $gateway ) : (string) $gateway;
	}

	/**
	 * The discount codes used on an order, comma-separated.
	 *
	 * @param object $order The order.
	 *
	 * @return string
	 */
	private function discount_codes( $order ) {

		$discounts = $order->get_discounts();

		return $discounts ? implode( ', ', wp_list_pluck( $discounts, 'description' ) ) : '';
	}
}
