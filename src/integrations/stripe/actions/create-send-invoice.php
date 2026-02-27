<?php

namespace Uncanny_Automator\Integrations\Stripe;

/**
 * Class Create_Send_Invoice
 *
 * @package Uncanny_Automator
 *
 * @property Stripe_App_Helpers $helpers
 * @property Stripe_Api_Caller $api
 */
class Create_Send_Invoice extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * setup_action
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'STRIPE' );
		$this->set_action_code( 'CREATE_SEND_INVOICE' );
		$this->set_action_meta( 'CUSTOMER_EMAIL' );

		$this->set_requires_user( false );

		// translators: %1$s is the customer email
		$this->set_sentence( sprintf( esc_html_x( 'Create and send an invoice to {{a customer:%1$s}}', 'Stripe', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Create and send an invoice to {{a customer}}', 'Stripe', 'uncanny-automator' ) );
	}

	/**
	 * options
	 *
	 * @return array
	 */
	public function options() {

		$customer_email = array(
			'option_code'     => 'CUSTOMER_EMAIL',
			'label'           => esc_html_x( 'Customer email', 'Stripe', 'uncanny-automator' ),
			'input_type'      => 'email',
			'required'        => true,
			'supports_tokens' => true,
			'description'     => esc_html_x( 'Email address of the customer. If the customer does not exist, a new customer will be created.', 'Stripe', 'uncanny-automator' ),
		);

		$customer_name = array(
			'option_code'     => 'CUSTOMER_NAME',
			'label'           => esc_html_x( 'Customer name', 'Stripe', 'uncanny-automator' ),
			'input_type'      => 'text',
			'required'        => false,
			'supports_tokens' => true,
			'description'     => esc_html_x( 'Name of the customer. Used when creating a new customer.', 'Stripe', 'uncanny-automator' ),
		);

		$line_items = array(
			'option_code'       => 'LINE_ITEMS',
			'input_type'        => 'repeater',
			'label'             => esc_html_x( 'Line items', 'Stripe', 'uncanny-automator' ),
			'required'          => true,
			'description'       => esc_html_x( 'Add one or more line items to the invoice.', 'Stripe', 'uncanny-automator' ),
			'fields'            => array(
				array(
					'option_code'     => 'ITEM_DESCRIPTION',
					'label'           => esc_html_x( 'Description', 'Stripe', 'uncanny-automator' ),
					'input_type'      => 'text',
					'required'        => true,
					'supports_tokens' => true,
				),
				array(
					'option_code'     => 'ITEM_AMOUNT',
					'label'           => esc_html_x( 'Amount', 'Stripe', 'uncanny-automator' ),
					'input_type'      => 'text',
					'required'        => true,
					'supports_tokens' => true,
					'description'     => esc_html_x( 'Amount in currency units (e.g., 10.00 for $10.00)', 'Stripe', 'uncanny-automator' ),
				),
				array(
					'option_code'     => 'ITEM_QUANTITY',
					'label'           => esc_html_x( 'Quantity', 'Stripe', 'uncanny-automator' ),
					'input_type'      => 'text',
					'required'        => false,
					'supports_tokens' => true,
					'default'         => '1',
				),
			),
			'add_row_button'    => esc_html_x( 'Add line item', 'Stripe', 'uncanny-automator' ),
			'remove_row_button' => esc_html_x( 'Remove line item', 'Stripe', 'uncanny-automator' ),
			'hide_actions'      => false,
			'relevant_tokens'   => array(),
		);

		$currency = array(
			'option_code'           => 'CURRENCY',
			'label'                 => esc_html_x( 'Currency', 'Stripe', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => false,
			'supports_custom_value' => true,
			'description'           => esc_html_x( 'Three-letter ISO currency code (e.g., usd, eur, gbp). Defaults to your Stripe account currency.', 'Stripe', 'uncanny-automator' ),
			'options'               => $this->get_currency_options(),
		);

		$days_until_due = array(
			'option_code'     => 'DAYS_UNTIL_DUE',
			'label'           => esc_html_x( 'Days until due', 'Stripe', 'uncanny-automator' ),
			'input_type'      => 'text',
			'required'        => false,
			'supports_tokens' => true,
			'default'         => '30',
			'description'     => esc_html_x( 'Number of days until the invoice is due. Only applies when collection method is "Send invoice".', 'Stripe', 'uncanny-automator' ),
		);

		$collection_method = array(
			'option_code' => 'COLLECTION_METHOD',
			'label'       => esc_html_x( 'Collection method', 'Stripe', 'uncanny-automator' ),
			'input_type'  => 'select',
			'required'    => false,
			'default'     => 'send_invoice',
			'description' => esc_html_x( 'How to collect payment for this invoice.', 'Stripe', 'uncanny-automator' ),
			'options'     => array(
				array(
					'text'  => esc_html_x( 'Send invoice (email invoice to customer)', 'Stripe', 'uncanny-automator' ),
					'value' => 'send_invoice',
				),
				array(
					'text'  => esc_html_x( 'Charge automatically (charge default payment method)', 'Stripe', 'uncanny-automator' ),
					'value' => 'charge_automatically',
				),
			),
		);

		$memo = array(
			'option_code'     => 'MEMO',
			'label'           => esc_html_x( 'Memo', 'Stripe', 'uncanny-automator' ),
			'input_type'      => 'textarea',
			'required'        => false,
			'supports_tokens' => true,
			'description'     => esc_html_x( 'An arbitrary string attached to the invoice. Often used for internal notes or a description.', 'Stripe', 'uncanny-automator' ),
		);

		$footer = array(
			'option_code'     => 'FOOTER',
			'label'           => esc_html_x( 'Footer', 'Stripe', 'uncanny-automator' ),
			'input_type'      => 'text',
			'required'        => false,
			'supports_tokens' => true,
			'description'     => esc_html_x( 'Footer to be displayed on the invoice.', 'Stripe', 'uncanny-automator' ),
		);

		$auto_send = array(
			'option_code' => 'AUTO_SEND',
			'label'       => esc_html_x( 'Send invoice immediately', 'Stripe', 'uncanny-automator' ),
			'input_type'  => 'checkbox',
			'required'    => false,
			'default_value' => true,
			'description' => esc_html_x( 'When enabled, the invoice will be finalized and sent to the customer immediately. When disabled, the invoice will be created as a draft. Note: Invoice URL, PDF, and number tokens are only populated when sending immediately.', 'Stripe', 'uncanny-automator' ),
		);

		$metadata = array(
			'option_code'     => 'METADATA',
			'input_type'      => 'repeater',
			'label'           => esc_html_x( 'Metadata', 'Stripe', 'uncanny-automator' ),
			'required'        => false,
			'description'     => sprintf(
				// translators: %1$s opening anchor tag, %2$s closing anchor tag
				esc_html_x( 'Set of %1$skey-value pairs%2$s that you can attach to the invoice.', 'Stripe', 'uncanny-automator' ),
				'<a href="https://docs.stripe.com/api/metadata" target="_blank">',
				'</a>'
			),
			'fields'          => array(
				array(
					'input_type'  => 'text',
					'option_code' => 'KEY',
					'label'       => esc_html_x( 'Key', 'Stripe', 'uncanny-automator' ),
					'required'    => true,
				),
				array(
					'input_type'      => 'text',
					'option_code'     => 'VALUE',
					'label'           => esc_html_x( 'Value', 'Stripe', 'uncanny-automator' ),
					'supports_tokens' => true,
					'required'        => true,
				),
			),
			'relevant_tokens' => array(),
		);

		return array(
			$customer_email,
			$customer_name,
			$line_items,
			$currency,
			$days_until_due,
			$collection_method,
			$memo,
			$footer,
			$auto_send,
			$metadata,
		);
	}

	/**
	 * Get currency options
	 *
	 * @return array
	 */
	private function get_currency_options() {

		$currencies = array(
			'usd' => 'USD - US Dollar',
			'eur' => 'EUR - Euro',
			'gbp' => 'GBP - British Pound',
			'cad' => 'CAD - Canadian Dollar',
			'aud' => 'AUD - Australian Dollar',
			'jpy' => 'JPY - Japanese Yen',
			'chf' => 'CHF - Swiss Franc',
			'nzd' => 'NZD - New Zealand Dollar',
			'inr' => 'INR - Indian Rupee',
			'brl' => 'BRL - Brazilian Real',
			'mxn' => 'MXN - Mexican Peso',
			'sgd' => 'SGD - Singapore Dollar',
			'hkd' => 'HKD - Hong Kong Dollar',
			'sek' => 'SEK - Swedish Krona',
			'nok' => 'NOK - Norwegian Krone',
			'dkk' => 'DKK - Danish Krone',
			'pln' => 'PLN - Polish Zloty',
			'czk' => 'CZK - Czech Koruna',
			'ils' => 'ILS - Israeli New Shekel',
			'zar' => 'ZAR - South African Rand',
		);

		$options = array(
			array(
				'text'  => esc_html_x( 'Account default', 'Stripe', 'uncanny-automator' ),
				'value' => '',
			),
		);

		foreach ( $currencies as $code => $name ) {
			$options[] = array(
				'text'  => $name,
				'value' => $code,
			);
		}

		return $options;
	}

	/**
	 * define_tokens
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'INVOICE_ID'     => array(
				'name' => esc_html_x( 'Invoice ID', 'Stripe', 'uncanny-automator' ),
				'type' => 'text',
			),
			'INVOICE_NUMBER' => array(
				'name' => esc_html_x( 'Invoice number', 'Stripe', 'uncanny-automator' ),
				'type' => 'text',
			),
			'INVOICE_URL'    => array(
				'name' => esc_html_x( 'Invoice URL', 'Stripe', 'uncanny-automator' ),
				'type' => 'url',
			),
			'INVOICE_PDF'    => array(
				'name' => esc_html_x( 'Invoice PDF URL', 'Stripe', 'uncanny-automator' ),
				'type' => 'url',
			),
			'INVOICE_STATUS' => array(
				'name' => esc_html_x( 'Invoice status', 'Stripe', 'uncanny-automator' ),
				'type' => 'text',
			),
			'INVOICE_TOTAL'  => array(
				'name' => esc_html_x( 'Invoice total (in currency units)', 'Stripe', 'uncanny-automator' ),
				'type' => 'text',
			),
			'CUSTOMER_ID'    => array(
				'name' => esc_html_x( 'Customer ID', 'Stripe', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Process action
	 *
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		// Resolve auto_send before building the payload so unset_empty_recursively
		// cannot silently drop it when the checkbox is unchecked (value = '').
		$auto_send = ! empty( $this->get_parsed_meta_value( 'AUTO_SEND', '' ) );

		// Build the invoice data
		$invoice_data = array(
			'customer_email'    => $this->get_parsed_meta_value( 'CUSTOMER_EMAIL', '' ),
			'customer_name'     => $this->get_parsed_meta_value( 'CUSTOMER_NAME', '' ),
			'currency'          => $this->get_parsed_meta_value( 'CURRENCY', '' ),
			'days_until_due'    => $this->get_parsed_meta_value( 'DAYS_UNTIL_DUE', '30' ),
			'collection_method' => $this->get_parsed_meta_value( 'COLLECTION_METHOD', 'send_invoice' ),
			'memo'              => $this->get_parsed_meta_value( 'MEMO', '' ),
			'footer'            => $this->get_parsed_meta_value( 'FOOTER', '' ),
			'line_items'        => array(),
			'metadata'          => array(),
		);

		// Validate customer email
		if ( empty( $invoice_data['customer_email'] ) ) {
			throw new \Exception(
				esc_html_x( 'Customer email is required', 'Stripe', 'uncanny-automator' )
			);
		}

		// Validate and coerce days_until_due for send_invoice collection method
		if ( 'send_invoice' === $invoice_data['collection_method'] ) {
			if ( ! is_numeric( $invoice_data['days_until_due'] ) || (int) $invoice_data['days_until_due'] < 1 ) {
				throw new \Exception(
					esc_html_x( 'Days until due must be a positive number', 'Stripe', 'uncanny-automator' )
				);
			}
			$invoice_data['days_until_due'] = (int) $invoice_data['days_until_due'];
		} else {
			unset( $invoice_data['days_until_due'] );
		}

		// Parse line items
		$line_items = json_decode( $this->get_parsed_meta_value( 'LINE_ITEMS', '[]' ), true );

		if ( empty( $line_items ) || ! is_array( $line_items ) ) {
			throw new \Exception(
				esc_html_x( 'At least one line item is required', 'Stripe', 'uncanny-automator' )
			);
		}

		foreach ( $line_items as $item ) {

			$description = isset( $item['ITEM_DESCRIPTION'] ) ? trim( $item['ITEM_DESCRIPTION'] ) : '';
			$amount      = isset( $item['ITEM_AMOUNT'] ) ? $item['ITEM_AMOUNT'] : '';
			$quantity    = isset( $item['ITEM_QUANTITY'] ) ? $item['ITEM_QUANTITY'] : '1';

			if ( empty( $description ) ) {
				throw new \Exception(
					esc_html_x( 'Line item description is required', 'Stripe', 'uncanny-automator' )
				);
			}

			if ( '' === $amount || ! is_numeric( $amount ) ) {
				throw new \Exception(
					esc_html_x( 'Line item amount must be a valid number', 'Stripe', 'uncanny-automator' )
				);
			}

			// Convert amount to smallest currency unit (zero-decimal currencies are already in base unit)
			$amount_cents = $this->helpers->is_zero_decimal_currency( $invoice_data['currency'] )
				? (int) round( floatval( $amount ) )
				: (int) round( floatval( $amount ) * 100 );

			if ( $amount_cents <= 0 ) {
				throw new \Exception(
					esc_html_x( 'Line item amount must be greater than zero', 'Stripe', 'uncanny-automator' )
				);
			}

			$invoice_data['line_items'][] = array(
				'description' => $description,
				'amount'      => $amount_cents,
				'quantity'    => max( 1, intval( $quantity ) ),
			);
		}

		// Parse metadata
		$metadata = json_decode( $this->get_parsed_meta_value( 'METADATA', '[]' ), true );

		if ( ! empty( $metadata ) && is_array( $metadata ) ) {
			foreach ( $metadata as $meta ) {
				if ( ! empty( $meta['KEY'] ) ) {
					$invoice_data['metadata'][ $meta['KEY'] ] = $meta['VALUE'] ?? '';
				}
			}
		}

		// Clean up empty values
		$invoice_data = $this->helpers->unset_empty_recursively( $invoice_data );

		// Assign after cleanup so false is never treated as empty and dropped.
		$invoice_data['auto_send'] = $auto_send;

		// Make the API request
		$response = $this->api->create_invoice( $invoice_data, $action_data );

		if ( empty( $response['data']['invoice']['id'] ) ) {
			$error_message = $response['data']['error'] ?? esc_html_x( 'Invoice could not be created', 'Stripe', 'uncanny-automator' );
			throw new \Exception( $error_message );
		}

		$invoice = $response['data']['invoice'];

		// Hydrate tokens with the response data
		$this->hydrate_tokens(
			array(
				'INVOICE_ID'     => $invoice['id'] ?? '',
				'INVOICE_NUMBER' => $invoice['number'] ?? '',
				'INVOICE_URL'    => $invoice['hosted_invoice_url'] ?? '',
				'INVOICE_PDF'    => $invoice['invoice_pdf'] ?? '',
				'INVOICE_STATUS' => $invoice['status'] ?? '',
				'INVOICE_TOTAL'  => $this->helpers->format_amount( $invoice['total'] ?? 0, $invoice['currency'] ?? '' ),
				'CUSTOMER_ID'    => $invoice['customer'] ?? '',
			)
		);

		return true;
	}
}
