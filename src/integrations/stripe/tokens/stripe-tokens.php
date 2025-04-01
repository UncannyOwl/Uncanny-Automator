<?php
namespace Uncanny_Automator\Integrations\Stripe;

/**
 * Class Stripe_Tokens
 *
 * @package Uncanny_Automator\Integrations\Stripe
 */
class Stripe_Tokens {

	/**
	 * Customer tokens
	 *
	 * @return array
	 */
	public function customer_tokens() {

		$customer_tokens = array();

		$customer_tokens[] = array(
			'tokenId'   => 'CUSTOMER_ID',
			'tokenName' => esc_html_x( 'Customer ID', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$customer_tokens[] = array(
			'tokenId'   => 'CUSTOMER_EMAIL',
			'tokenName' => esc_html_x( 'Customer email', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'email',
		);

		$customer_tokens[] = array(
			'tokenId'   => 'CUSTOMER_ADDRESS_CITY',
			'tokenName' => esc_html_x( 'Address city', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$customer_tokens[] = array(
			'tokenId'   => 'CUSTOMER_ADDRESS_LINE1',
			'tokenName' => esc_html_x( 'Address line 1', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$customer_tokens[] = array(
			'tokenId'   => 'CUSTOMER_ADDRESS_LINE2',
			'tokenName' => esc_html_x( 'Address line 2', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$customer_tokens[] = array(
			'tokenId'   => 'CUSTOMER_ADDRESS_POSTAL_CODE',
			'tokenName' => esc_html_x( 'Address postal code', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$customer_tokens[] = array(
			'tokenId'   => 'CUSTOMER_ADDRESS_STATE',
			'tokenName' => esc_html_x( 'Address state', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$customer_tokens[] = array(
			'tokenId'   => 'CUSTOMER_ADDRESS_COUNTRY',
			'tokenName' => esc_html_x( 'Address country', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$customer_tokens[] = array(
			'tokenId'   => 'CUSTOMER_BALANCE',
			'tokenName' => esc_html_x( 'Customer balance', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$customer_tokens[] = array(
			'tokenId'   => 'CUSTOMER_CREATED',
			'tokenName' => esc_html_x( 'Customer date and time created', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$customer_tokens[] = array(
			'tokenId'   => 'CUSTOMER_NAME',
			'tokenName' => esc_html_x( 'Customer name', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$customer_tokens[] = array(
			'tokenId'   => 'CUSTOMER_PHONE',
			'tokenName' => esc_html_x( 'Customer phone', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$customer_tokens[] = array(
			'tokenId'   => 'CUSTOMER_TAX_EXEMPT',
			'tokenName' => esc_html_x( 'Tax exempt', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		// Add other customer tokens as necessary
		return $customer_tokens;
	}

	/**
	 * Shipping tokens
	 *
	 * @return array
	 */
	public function shipping_tokens() {

		$shipping_tokens = array();

		$shipping_tokens[] = array(
			'tokenId'   => 'SHIPPING_NAME',
			'tokenName' => esc_html_x( 'Shipping name', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$shipping_tokens[] = array(
			'tokenId'   => 'SHIPPING_ADDRESS_LINE1',
			'tokenName' => esc_html_x( 'Shipping address line 1', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$shipping_tokens[] = array(
			'tokenId'   => 'SHIPPING_ADDRESS_LINE2',
			'tokenName' => esc_html_x( 'Shipping address line 2', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$shipping_tokens[] = array(
			'tokenId'   => 'SHIPPING_ADDRESS_CITY',
			'tokenName' => esc_html_x( 'Shipping address city', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$shipping_tokens[] = array(
			'tokenId'   => 'SHIPPING_ADDRESS_POSTAL_CODE',
			'tokenName' => esc_html_x( 'Shipping address postal code', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$shipping_tokens[] = array(
			'tokenId'   => 'SHIPPING_ADDRESS_STATE',
			'tokenName' => esc_html_x( 'Shipping address state', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$shipping_tokens[] = array(
			'tokenId'   => 'SHIPPING_PHONE',
			'tokenName' => esc_html_x( 'Shipping phone', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		return $shipping_tokens;
	}

	/**
	 * Line item tokens
	 *
	 * @return array
	 */
	public function line_item_tokens() {

		$line_item_tokens = array();

		$line_item_tokens[] = array(
			'tokenId'   => 'LINE_ITEM_QUANTITY',
			'tokenName' => esc_html_x( 'Item quantity', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'integer',
		);

		$line_item_tokens[] = array(
			'tokenId'   => 'LINE_ITEM_AMOUNT_DISCOUNT',
			'tokenName' => esc_html_x( 'Item discount amount', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$line_item_tokens[] = array(
			'tokenId'   => 'LINE_ITEM_AMOUNT_SUBTOTAL',
			'tokenName' => esc_html_x( 'Item subtotal amount', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$line_item_tokens[] = array(
			'tokenId'   => 'LINE_ITEM_AMOUNT_TAX',
			'tokenName' => esc_html_x( 'Item tax amount', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$line_item_tokens[] = array(
			'tokenId'   => 'LINE_ITEM_AMOUNT_TOTAL',
			'tokenName' => esc_html_x( 'Item total amount', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$line_item_tokens[] = array(
			'tokenId'   => 'LINE_ITEM_CURRENCY',
			'tokenName' => esc_html_x( 'Item currency', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$line_item_tokens[] = array(
			'tokenId'   => 'LINE_ITEM_DESCRIPTION',
			'tokenName' => esc_html_x( 'Item description', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		return $line_item_tokens;
	}

	/**
	 * Price tokens
	 *
	 * @return array
	 */
	public function price_tokens() {

		$price_tokens = array();

		$price_tokens[] = array(
			'tokenId'   => 'PRICE_ID',
			'tokenName' => esc_html_x( 'Price ID', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$price_tokens[] = array(
			'tokenId'   => 'PRICE_TYPE',
			'tokenName' => esc_html_x( 'Price type', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$price_tokens[] = array(
			'tokenId'   => 'PRICE_BILLING_SCHEME',
			'tokenName' => esc_html_x( 'Price billing scheme', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$price_tokens[] = array(
			'tokenId'   => 'PRICE_CREATED',
			'tokenName' => esc_html_x( 'Price created', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'date',
		);

		$price_tokens[] = array(
			'tokenId'   => 'PRICE_CURRENCY',
			'tokenName' => esc_html_x( 'Price currency', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$price_tokens[] = array(
			'tokenId'   => 'PRICE_UNIT_AMOUNT',
			'tokenName' => esc_html_x( 'Price unit amount', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$price_tokens[] = array(
			'tokenId'   => 'PRICE_METADATA',
			'tokenName' => esc_html_x( 'Price metadata', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$price_tokens[] = array(
			'tokenId'   => 'PRICE_RECURRING_INTERVAL',
			'tokenName' => esc_html_x( 'Price recurring interval', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$price_tokens[] = array(
			'tokenId'   => 'PRICE_RECURRING_INTERVAL_COUNT',
			'tokenName' => esc_html_x( 'Price recurring interval count', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'integer',
		);

		$price_tokens[] = array(
			'tokenId'   => 'PRICE_RECURRING_TRIAL_PERIOD_DAYS',
			'tokenName' => esc_html_x( 'Price recurring trial period days', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'integer',
		);

		return $price_tokens;
	}

	/**
	 * Product tokens
	 *
	 * @return array
	 */
	public function product_tokens() {

		$product_tokens = array();

		$product_tokens[] = array(
			'tokenId'   => 'PRODUCT_ID',
			'tokenName' => esc_html_x( 'Product ID', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$product_tokens[] = array(
			'tokenId'   => 'PRODUCT_NAME',
			'tokenName' => esc_html_x( 'Product name', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$product_tokens[] = array(
			'tokenId'   => 'PRODUCT_DESCRIPTION',
			'tokenName' => esc_html_x( 'Product description', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$product_tokens[] = array(
			'tokenId'   => 'PRODUCT_CREATED',
			'tokenName' => esc_html_x( 'Product created', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'date',
		);

		$product_tokens[] = array(
			'tokenId'   => 'PRODUCT_IMAGES',
			'tokenName' => esc_html_x( 'Product images', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$product_tokens[] = array(
			'tokenId'   => 'PRODUCT_METADATA',
			'tokenName' => esc_html_x( 'Product metadata', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$product_tokens[] = array(
			'tokenId'   => 'PRODUCT_URL',
			'tokenName' => esc_html_x( 'Product URL', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$product_tokens[] = array(
			'tokenId'   => 'PRODUCT_TYPE',
			'tokenName' => esc_html_x( 'Product type', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$product_tokens[] = array(
			'tokenId'   => 'PRODUCT_TAX_CODE',
			'tokenName' => esc_html_x( 'Product tax code', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$product_tokens[] = array(
			'tokenId'   => 'PRODUCT_UNIT_LABEL',
			'tokenName' => esc_html_x( 'Product unit label', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$product_tokens[] = array(
			'tokenId'   => 'PRODUCT_SHIPPABLE',
			'tokenName' => esc_html_x( 'Product shippable', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$product_tokens[] = array(
			'tokenId'   => 'PRODUCT_STATEMENT_DESCRIPTOR',
			'tokenName' => esc_html_x( 'Product statement descriptor', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		return $product_tokens;
	}

	/**
	 * Custom data tokens
	 *
	 * @param array $data_keys
	 * @param string $token_prefix
	 * @param string $token_name
	 * @return array
	 */
	public function custom_data_tokens( $data_keys, $token_prefix, $token_name ) {

		$tokens = array();

		foreach ( $data_keys as $key ) {

			$tokens[] = array(
				'tokenId'   => $token_prefix . '_' . $key['KEY'],
				'tokenName' => $token_name . $key['KEY'],
				'tokenType' => 'text',
			);
		}

		return $tokens;
	}

	/**
	 * Billing tokens
	 *
	 * @return array
	 */
	public function billing_tokens() {

		$tokens = array();

		$tokens[] = array(
			'tokenId'   => 'BILLING_EMAIL',
			'tokenName' => esc_html_x( 'Billing email', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'email',
		);

		$tokens[] = array(
			'tokenId'   => 'BILLING_NAME',
			'tokenName' => esc_html_x( 'Billing name', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'BILLING_PHONE',
			'tokenName' => esc_html_x( 'Billing phone', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'BILLING_ADDRESS_LINE1',
			'tokenName' => esc_html_x( 'Billing address line 1', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'BILLING_ADDRESS_LINE2',
			'tokenName' => esc_html_x( 'Billing address line 2', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'BILLING_ADDRESS_CITY',
			'tokenName' => esc_html_x( 'Billing address city', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'BILLING_ADDRESS_STATE',
			'tokenName' => esc_html_x( 'Billing address state', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'BILLING_ADDRESS_COUNTRY',
			'tokenName' => esc_html_x( 'Billing address country', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'BILLING_ADDRESS_POSTAL_CODE',
			'tokenName' => esc_html_x( 'Billing address postal code', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		return $tokens;
	}

	/**
	 * Invoice tokens
	 *
	 * @return array
	 */
	public function invoice_tokens() {

		$invoice_tokens = array();

		$invoice_tokens[] = array(
			'tokenId'   => 'INVOICE_ID',
			'tokenName' => esc_html_x( 'Invoice ID', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$invoice_tokens[] = array(
			'tokenId'   => 'INVOICE_HOSTED_URL',
			'tokenName' => esc_html_x( 'Hosted invoice URL', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$invoice_tokens[] = array(
			'tokenId'   => 'INVOICE_PDF_URL',
			'tokenName' => esc_html_x( 'Invoice PDF URL', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$invoice_tokens[] = array(
			'tokenId'   => 'INVOICE_STATUS',
			'tokenName' => esc_html_x( 'Invoice status', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		return $invoice_tokens;
	}

	/**
	 * Charge tokens
	 *
	 * @return array
	 */
	public function charge_tokens() {

		$charge_tokens = array();

		$charge_tokens[] = array(
			'tokenId'   => 'CHARGE_ID',
			'tokenName' => esc_html_x( 'Charge ID', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$charge_tokens[] = array(
			'tokenId'   => 'CHARGE_CURRENCY',
			'tokenName' => esc_html_x( 'Charge currency', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$charge_tokens[] = array(
			'tokenId'   => 'CHARGE_DESCRIPTION',
			'tokenName' => esc_html_x( 'Charge description', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$charge_tokens[] = array(
			'tokenId'   => 'CHARGE_PAYMNET_METHOD',
			'tokenName' => esc_html_x( 'Charge paymnet method ID', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$charge_tokens[] = array(
			'tokenId'   => 'CHARGE_BALANCE_TRANSACTION',
			'tokenName' => esc_html_x( 'Charge balance trasnaction', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$charge_tokens[] = array(
			'tokenId'   => 'CHARGE_AMOUNT',
			'tokenName' => esc_html_x( 'Charge amount', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$charge_tokens[] = array(
			'tokenId'   => 'CHARGE_CAPTURED_AMOUNT',
			'tokenName' => esc_html_x( 'Captured amount', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$charge_tokens[] = array(
			'tokenId'   => 'CHARGE_REFUNDED_AMOUNT',
			'tokenName' => esc_html_x( 'Refunded amount', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$charge_tokens[] = array(
			'tokenId'   => 'CHARGE_STATUS',
			'tokenName' => esc_html_x( 'Charge status', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$charge_tokens[] = array(
			'tokenId'   => 'CHARGE_RECEIPT_URL',
			'tokenName' => esc_html_x( 'Charge receipt URL', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		return $charge_tokens;
	}

	/**
	 * Hydrate invoice tokens
	 *
	 * @param array $invoice
	 * @return array
	 */
	public function hydrate_invoice_tokens( $invoice ) {

		$tokens = array(
			'INVOICE_ID'         => $this->maybe_add( $invoice, 'id' ),
			'INVOICE_STATUS'     => $this->maybe_add( $invoice, 'status' ),
			'INVOICE_HOSTED_URL' => $this->maybe_add( $invoice, 'hosted_invoice_url' ),
			'INVOICE_PDF_URL'    => $this->maybe_add( $invoice, 'invoice_pdf' ),
		);

		return $tokens;
	}

	/**
	 * Hydrate customer tokens
	 *
	 * @param array $customer
	 * @return array
	 */
	public function hydrate_customer_tokens( $customer ) {

		$address = empty( $customer['address'] ) ? array() : $customer['address'];

		$customer_tokens = array(
			'CUSTOMER_ID'                  => $this->maybe_add( $customer, 'id' ),
			'CUSTOMER_EMAIL'               => $this->maybe_add( $customer, 'email' ),
			'CUSTOMER_ADDRESS_CITY'        => $this->maybe_add( $address, 'city' ),
			'CUSTOMER_ADDRESS_LINE1'       => $this->maybe_add( $address, 'line1' ),
			'CUSTOMER_ADDRESS_LINE2'       => $this->maybe_add( $address, 'line2' ),
			'CUSTOMER_ADDRESS_POSTAL_CODE' => $this->maybe_add( $address, 'postal_code' ),
			'CUSTOMER_ADDRESS_STATE'       => $this->maybe_add( $address, 'state' ),
			'CUSTOMER_ADDRESS_COUNTRY'     => $this->maybe_add( $address, 'country' ),
			'CUSTOMER_BALANCE'             => $this->maybe_add( $customer, 'balance', 'amount' ),
			'CUSTOMER_CREATED'             => $this->maybe_add( $customer, 'created', 'date' ),
			'CUSTOMER_DISCOUNT'            => $this->maybe_add( $customer, 'discount' ),
			'CUSTOMER_NAME'                => $this->maybe_add( $customer, 'name' ),
			'CUSTOMER_PHONE'               => $this->maybe_add( $customer, 'phone' ),
			'CUSTOMER_SHIPPING_NAME'       => $this->maybe_add( $customer['shipping'], 'name' ),
			'CUSTOMER_SHIPPING_PHONE'      => $this->maybe_add( $customer['shipping'], 'phone' ),
			'CUSTOMER_TAX_EXEMPT'          => $this->maybe_add( $customer, 'tax_exempt' ),
		);

		if ( ! empty( $tokens['CUSTOMER_CREATED'] ) ) {
			$customer_tokens['created'] = date_i18n( get_option( 'date_format' ), $customer_tokens['CUSTOMER_CREATED'] );
		}

		return $customer_tokens;
	}

	/**
	 * Hydrate shipping tokens
	 *
	 * @param array $customer
	 * @return array
	 */
	public function hydrate_shipping_tokens( $customer ) {

		$shipping_tokens = array();

		if ( empty( $customer['shipping_address'] ) ) {
			return $shipping_tokens;
		}

		$shipping_address = $customer['shipping_address'];

		$shipping_tokens = array_merge(
			$shipping_tokens,
			array(
				'SHIPPING_ADDRESS_LINE1'       => $this->maybe_add( $shipping_address, 'line1' ),
				'SHIPPING_ADDRESS_LINE2'       => $this->maybe_add( $shipping_address, 'line2' ),
				'SHIPPING_ADDRESS_CITY'        => $this->maybe_add( $shipping_address, 'city' ),
				'SHIPPING_ADDRESS_POSTAL_CODE' => $this->maybe_add( $shipping_address, 'postal_code' ),
				'SHIPPING_ADDRESS_STATE'       => $this->maybe_add( $shipping_address, 'state' ),
			)
		);

		return $shipping_tokens;
	}

	/**
	 * Hydrate line item tokens
	 *
	 * @param array $line_item
	 * @return array
	 */
	public function hydrate_line_item_tokens( $line_item ) {

		$tokens = array(
			'LINE_ITEM_QUANTITY'        => $this->maybe_add( $line_item, 'quantity' ),
			'LINE_ITEM_AMOUNT_DISCOUNT' => $this->maybe_add( $line_item, 'amount_discount', 'amount' ),
			'LINE_ITEM_AMOUNT_SUBTOTAL' => $this->maybe_add( $line_item, 'amount_subtotal', 'amount' ),
			'LINE_ITEM_AMOUNT_TAX'      => $this->maybe_add( $line_item, 'amount_tax', 'amount' ),
			'LINE_ITEM_AMOUNT_TOTAL'    => $this->maybe_add( $line_item, 'amount_total', 'amount' ),
			'LINE_ITEM_CURRENCY'        => $this->maybe_add( $line_item, 'currency' ),
			'LINE_ITEM_DESCRIPTION'     => $this->maybe_add( $line_item, 'description' ),
		);

		return $tokens;
	}

	/**
	 * Hydrate price tokens
	 *
	 * @param array $price
	 * @return array
	 */
	public function hydrate_price_tokens( $price ) {

		$tokens = array(
			'PRICE_ID'             => $this->maybe_add( $price, 'id' ),
			'PRICE_TYPE'           => $this->maybe_add( $price, 'type' ),
			'PRICE_BILLING_SCHEME' => $this->maybe_add( $price, 'billing_scheme' ),
			'PRICE_CREATED'        => $this->maybe_add( $price, 'created', 'date' ),
			'PRICE_CURRENCY'       => $this->maybe_add( $price, 'currency' ),
			'PRICE_METADATA'       => $this->maybe_add( $price, 'metadata' ),
			'PRICE_NICKNAME'       => $this->maybe_add( $price, 'nickname' ),
			'PRICE_TAX_BEHAVIOR'   => $this->maybe_add( $price, 'tax_behavior' ),
			'PRICE_UNIT_AMOUNT'    => $this->maybe_add( $price, 'unit_amount', 'amount' ),
		);

		if ( empty( $price['recurring'] ) ) {
			return $tokens;
		}

		$recurring = $price['recurring'];

		$tokens = array_merge(
			$tokens,
			array(
				'PRICE_RECURRING_INTERVAL'          => $this->maybe_add( $recurring, 'interval' ),
				'PRICE_RECURRING_INTERVAL_COUNT'    => $this->maybe_add( $recurring, 'interval_count' ),
				'PRICE_RECURRING_TRIAL_PERIOD_DAYS' => $this->maybe_add( $recurring, 'trial_period_days' ),
			)
		);

		return $tokens;
	}

	/**
	 * Hydrate product tokens
	 *
	 * @param array $product
	 * @return array
	 */
	public function hydrate_product_tokens( $product ) {

		$tokens = array(
			'PRODUCT_ID'                   => $this->maybe_add( $product, 'id' ),
			'PRODUCT_NAME'                 => $this->maybe_add( $product, 'name' ),
			'PRODUCT_CREATED'              => $this->maybe_add( $product, 'created', 'date' ),
			'PRODUCT_DESCRIPTION'          => $this->maybe_add( $product, 'description' ),
			'PRODUCT_IMAGES'               => $this->maybe_add( $product, 'images' ),
			'PRODUCT_METADATA'             => $this->maybe_add( $product, 'metadata' ),
			'PRODUCT_SHIPPABLE'            => $this->maybe_add( $product, 'shippable' ),
			'PRODUCT_STATEMENT_DESCRIPTOR' => $this->maybe_add( $product, 'statement_descriptor' ),
			'PRODUCT_TAX_CODE'             => $this->maybe_add( $product, 'tax_code' ),
			'PRODUCT_TYPE'                 => $this->maybe_add( $product, 'type' ),
			'PRODUCT_UNIT_LABEL'           => $this->maybe_add( $product, 'unit_label' ),
			'PRODUCT_UPDATED'              => $this->maybe_add( $product, 'updated', 'date' ),
			'PRODUCT_URL'                  => $this->maybe_add( $product, 'url' ),
		);

		return $tokens;
	}

	/**
	 * hydrate_billing_tokens
	 *
	 * @param  array $charge
	 * @return array
	 */
	public function hydrate_billing_tokens( $charge ) {

		$tokens = array(
			'BILLING_EMAIL'               => empty( $charge['billing_details']['email'] ) ? '' : $charge['billing_details']['email'],
			'BILLING_NAME'                => empty( $charge['billing_details']['name'] ) ? '' : $charge['billing_details']['name'],
			'BILLING_PHONE'               => empty( $charge['billing_details']['phone'] ) ? '' : $charge['billing_details']['phone'],
			'BILLING_ADDRESS_LINE1'       => empty( $charge['billing_details']['address']['line1'] ) ? '' : $charge['billing_details']['address']['line1'],
			'BILLING_ADDRESS_LINE2'       => empty( $charge['billing_details']['address']['line2'] ) ? '' : $charge['billing_details']['address']['line2'],
			'BILLING_ADDRESS_CITY'        => empty( $charge['billing_details']['address']['city'] ) ? '' : $charge['billing_details']['address']['city'],
			'BILLING_ADDRESS_STATE'       => empty( $charge['billing_details']['address']['state'] ) ? '' : $charge['billing_details']['address']['state'],
			'BILLING_ADDRESS_COUNTRY'     => empty( $charge['billing_details']['address']['country'] ) ? '' : $charge['billing_details']['address']['country'],
			'BILLING_ADDRESS_POSTAL_CODE' => empty( $charge['billing_details']['address']['postal_code'] ) ? '' : $charge['billing_details']['address']['postal_code'],
		);

		return $tokens;
	}

	/**
	 * Hydrate metadata tokens
	 *
	 * @param array $keys
	 * @param array $session
	 * @param string $token_prefix
	 * @return array
	 */
	public function hydrate_metadata_tokens( $keys, $session, $token_prefix ) {
		$tokens = array();

		foreach ( $keys as $key ) {

			$metadata_key = $key['KEY'];

			if ( ! isset( $session['metadata'][ $metadata_key ] ) ) {
				continue;
			}

			$tokens[ $token_prefix . '_' . $metadata_key ] = $session['metadata'][ $metadata_key ];
		}

		return $tokens;
	}

	/**
	 * Hydrate custom fields tokens
	 *
	 * @param array $keys
	 * @param array $data
	 * @param string $token_prefix
	 * @return array
	 */
	public function hydrate_custom_fields_tokens( $keys, $data, $token_prefix ) {

		$tokens = array();

		foreach ( $keys as $key ) {

			foreach ( $data['custom_fields'] as $field ) {

				if ( $field['key'] !== $key['KEY'] ) {
					continue;
				}

				$field_type = $field['type'];

				$tokens[ $token_prefix . '_' . $key['KEY'] ] = $field[ $field_type ]['value'];
			}
		}

		return $tokens;
	}

	/**
	 * Hydrate charge tokens
	 *
	 * @param array $charge
	 * @return array
	 */
	public function hydrate_charge_tokens( $charge ) {

		$tokens = array(
			'CHARGE_ID'              => $this->maybe_add( $charge, 'id' ),
			'CHARGE_CREATED'         => $this->maybe_add( $charge, 'created', 'date' ),
			'CHARGE_CURRENCY'        => $this->maybe_add( $charge, 'currency' ),
			'CHARGE_DESCRIPTION'     => $this->maybe_add( $charge, 'description' ),
			'CHARGE_PAYMENT_METHOD'  => $this->maybe_add( $charge, 'payment_method' ),
			'CHARGE_STATUS'          => $this->maybe_add( $charge, 'status' ),
			'CHARGE_AMOUNT'          => $this->format_amount( $this->maybe_add( $charge, 'amount' ) ),
			'CHARGE_REFUNDED_AMOUNT' => $this->format_amount( $this->maybe_add( $charge, 'amount_refunded' ) ),
			'CHARGE_CAPTURED_AMOUNT' => $this->format_amount( $this->maybe_add( $charge, 'amount_captured' ) ),
			'CHARGE_RECEIPT_URL'     => $this->maybe_add( $charge, 'receipt_url' ),
		);

		return $tokens;
	}

	/**
	 * Maybe add token value
	 *
	 * @param array $data
	 * @param string $key
	 * @param string $format
	 * @return string
	 */
	public function maybe_add( $data, $key, $format = 'text' ) {

		if ( empty( $data[ $key ] ) ) {
			return '';
		}

		$value = $data[ $key ];

		if ( 'text' === $format ) {
			return $value;
		}

		if ( 'amount' === $format ) {
			return $this->format_amount( $value );
		}

		if ( 'date' === $format ) {
			return $this->format_date( $value );
		}

		if ( ! is_array( $value ) ) {
			return $value;
		}

		// If the array is empty, return empty string
		if ( empty( $value ) ) {
			return '';
		}

		// If the array flat, implode
		if ( count( $value ) === count( $value, COUNT_RECURSIVE ) ) {
			return implode( ', ', $value );
		}

		// If the array is multidimensional, encode it
		return wp_json_encode( $value );
	}


	/**
	 * Format amount
	 *
	 * @param int $cents
	 * @return string
	 */
	public function format_amount( $cents ) {
		return number_format( $cents / 100, 2 );
	}

	/**
	 * Format date
	 *
	 * @param int $timestamp
	 * @return string
	 */
	public function format_date( $timestamp ) {

		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );

		return date_i18n( $date_format . ' ' . $time_format, $timestamp );
	}
}
