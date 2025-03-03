<?php

namespace Uncanny_Automator\Integrations\Stripe;

class Create_Customer extends \Uncanny_Automator\Recipe\Action {

	protected $helpers;

	protected $dependencies;

	/**
	 * setup_action
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->helpers = array_shift( $this->dependencies );

		// Define the Actions's info
		$this->set_integration( 'STRIPE' );
		$this->set_action_code( 'CREATE_CUSTOMER' );
		$this->set_action_meta( 'EMAIL' );

		$this->set_requires_user( false );

		/* translators: %1$s Contact Email */
		$this->set_sentence( sprintf( esc_attr_x( 'Create {{a customer:%1$s}}', 'Stripe', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_attr_x( 'Create {{a customer}}', 'Stripe', 'uncanny-automator' ) );
	}

	/**
	 * options
	 *
	 * @return array
	 */
	public function options() {

		$email = array(
			'option_code' => 'EMAIL',
			'label'       => esc_html__( 'Email', 'uncanny-automator' ),
			'input_type'  => 'email',
			'required'    => true,
			'description' => esc_html__( 'Email address of the customer', 'uncanny-automator' ),
		);

		$name = array(
			'option_code' => 'NAME',
			'label'       => esc_html__( 'Name', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => false,
			'description' => esc_html__( "The customer's full name or business name.", 'uncanny-automator' ),
		);

		$phone = array(
			'option_code' => 'PHONE',
			'label'       => esc_html__( 'Phone', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => false,
			'description' => esc_html__( "The customer's phone number.", 'uncanny-automator' ),
		);

		$description = array(
			'option_code' => 'DESCRIPTION',
			'label'       => esc_html__( 'Description', 'uncanny-automator' ),
			'input_type'  => 'textarea',
			'required'    => false,
			'description' => esc_html__( 'An arbitrary string that you can attach to a customer object. It is displayed alongside the customer in the dashboard.', 'uncanny-automator' ),
		);

		$line1 = array(
			'option_code' => 'ADDRESS_LINE1',
			'label'       => esc_html__( 'Address line 1', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => false,
			'description' => esc_html__( 'Address line 1 (e.g., street, PO Box, or company name).', 'uncanny-automator' ),
		);

		$line2 = array(
			'option_code' => 'ADDRESS_LINE2',
			'label'       => esc_html__( 'Address line 2', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => false,
			'description' => esc_html__( 'Address line 2 (e.g., apartment, suite, unit, or building).', 'uncanny-automator' ),
		);

		$city = array(
			'option_code' => 'ADDRESS_CITY',
			'label'       => esc_html__( 'City', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => false,
			'description' => esc_html__( 'City, district, suburb, town, or village.', 'uncanny-automator' ),
		);

		$state = array(
			'option_code' => 'ADDRESS_STATE',
			'label'       => esc_html__( 'State', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => false,
			'description' => esc_html__( 'State, county, province, or region.', 'uncanny-automator' ),
		);

		$country = array(
			'option_code' => 'ADDRESS_COUNTRY',
			'label'       => esc_html__( 'Country', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => false,
			'description' => sprintf(
			/* translators: %1$s: [delete], %2$s opening anchor tag, %3$s: closing anchor tag */
				_x( 'Two-letter country code (%1$sISO 3166-1 alpha-2%2$s)', 'Stripe', 'uncanny-automator' ),
				'<a href="https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2" target="_blank">',
				'</a>'
			),
		);

		$postal_code = array(
			'option_code' => 'ADDRESS_POSTAL_CODE',
			'label'       => esc_html__( 'Postal code', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => false,
			'description' => esc_html__( 'ZIP or postal code.', 'uncanny-automator' ),
		);

		$payment_method = array(
			'option_code' => 'PAYMENT_METHOD',
			'label'       => esc_html__( 'Payment method', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => false,
			'description' => esc_html__( 'The ID of the PaymentMethod to attach to the customer.', 'uncanny-automator' ),
		);

		$shipping_line1 = array(
			'option_code' => 'SHIPPING_LINE1',
			'label'       => esc_html__( 'Shipping line 1', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => false,
			'description' => esc_html__( 'Address line 1 (e.g., street, PO Box, or company name).', 'uncanny-automator' ),
		);

		$shipping_line2 = array(
			'option_code' => 'SHIPPING_LINE2',
			'label'       => esc_html__( 'Shipping line 2', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => false,
			'description' => esc_html__( 'Address line 2 (e.g., apartment, suite, unit, or building).', 'uncanny-automator' ),
		);

		$shipping_city = array(
			'option_code' => 'SHIPPING_CITY',
			'label'       => esc_html__( 'Shipping city', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => false,
			'description' => esc_html__( 'City, district, suburb, town, or village.', 'uncanny-automator' ),
		);

		$shipping_state = array(
			'option_code' => 'SHIPPING_STATE',
			'label'       => esc_html__( 'Shipping state', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => false,
			'description' => esc_html__( 'State, county, province, or region.', 'uncanny-automator' ),
		);

		$shipping_country = array(
			'option_code' => 'SHIPPING_COUNTRY',
			'label'       => esc_html__( 'Shipping country', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => false,
			'description' => sprintf(
			/* translators: %1$s: [delete], %2$s opening anchor tag, %3$s: closing anchor tag */
				_x( 'Two-letter country code (%1$sISO 3166-1 alpha-2%2$s)', 'Stripe', 'uncanny-automator' ),
				'<a href="https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2" target="_blank">',
				'</a>'
			),
		);

		$shipping_postal_code = array(
			'option_code' => 'SHIPPING_POSTAL_CODE',
			'label'       => esc_html__( 'Shipping postal code', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => false,
			'description' => esc_html__( 'ZIP or postal code.', 'uncanny-automator' ),
		);

		$shipping_name = array(
			'option_code' => 'SHIPPING_NAME',
			'label'       => esc_html__( 'Shipping name', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => false,
			'description' => esc_html__( 'Customer name.', 'uncanny-automator' ),
		);

		$shipping_phone = array(
			'option_code' => 'SHIPPING_PHONE',
			'label'       => esc_html__( 'Shipping phone', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => false,
			'description' => esc_html__( 'Customer phone (including extension).', 'uncanny-automator' ),
		);

		$invoice_custom_fields = array(
			'option_code'     => 'INVOICE_CUSTOM_FIELDS',
			'input_type'      => 'repeater',
			'label'           => _x( 'Invoice custom fields', 'Stripe', 'uncanny-automator' ),
			'required'        => false,
			'description'     => _x( 'The list of up to 4 default custom fields to be displayed on invoices for this customer. When updating, pass an empty string to remove previously-defined fields.', 'Stripe', 'uncanny-automator' ),
			'fields'          => array(
				array(
					'input_type'  => 'text',
					'option_code' => 'PARAM_NAME',
					'label'       => _x( 'Parameter', 'Stripe', 'uncanny-automator' ),
					'description' => _x( 'The name of the custom field. This may be up to 40 characters.', 'Stripe', 'uncanny-automator' ),
					'required'    => true,
				),
				array(
					'input_type'      => 'text',
					'option_code'     => 'PARAM_VALUE',
					'label'           => _x( 'Value', 'Stripe', 'uncanny-automator' ),
					'description'     => _x( 'The value of the custom field. This may be up to 140 characters.', 'Stripe', 'uncanny-automator' ),
					'supports_tokens' => true,
					'required'        => true,
				),
			),
			'relevant_tokens' => array(),
		);

		$tax_id_data = array(
			'option_code'     => 'TAX_ID_DATA',
			'input_type'      => 'repeater',
			'label'           => _x( 'Tax ID data', 'Stripe', 'uncanny-automator' ),
			'required'        => false,
			'description'     => _x( 'The customer’s tax ID number.', 'Stripe', 'uncanny-automator' ),
			'fields'          => array(
				array(
					'input_type'  => 'text',
					'option_code' => 'KEY',
					'label'       => _x( 'Key', 'Stripe', 'uncanny-automator' ),
					'description' => _x( 'e.g. us_ein', 'Stripe', 'uncanny-automator' ),
					'required'    => true,
				),
				array(
					'input_type'      => 'text',
					'option_code'     => 'VALUE',
					'label'           => _x( 'Value', 'Stripe', 'uncanny-automator' ),
					'supports_tokens' => true,
					'required'        => true,
				),
			),
			'relevant_tokens' => array(),
		);

		$metadata = array(
			'option_code'     => 'METADATA',
			'input_type'      => 'repeater',
			'label'           => _x( 'Metadata', 'Stripe', 'uncanny-automator' ),
			'required'        => false,
			'description'     => sprintf(
			/* translators: %1$s: [delete], %2$s opening anchor tag, %3$s: closing anchor tag */
				_x( 'Set of %1$skey-value-pairs%2$s that you can attach to an object. This can be useful for storing additional information about the object in a structured format.', 'Stripe', 'uncanny-automator' ),
				'<a href="https://docs.stripe.com/api/metadata" target="_blank">',
				'</a>'
			),
			'fields'          => array(
				array(
					'input_type'  => 'text',
					'option_code' => 'KEY',
					'label'       => _x( 'Key', 'Stripe', 'uncanny-automator' ),
					'description' => _x( 'e.g. order_id', 'Stripe', 'uncanny-automator' ),
					'required'    => true,
				),
				array(
					'input_type'      => 'text',
					'option_code'     => 'VALUE',
					'label'           => _x( 'Value', 'Stripe', 'uncanny-automator' ),
					'supports_tokens' => true,
					'required'        => true,
				),
			),
			'relevant_tokens' => array(),
		);

		$additional_params = array(
			'option_code'     => 'ADD_PARAMS',
			'input_type'      => 'repeater',
			'label'           => _x( 'Additional parameters', 'Stripe', 'uncanny-automator' ),
			'required'        => false,
			'description'     => sprintf(
			/* translators: %1$s: [delete], %2$s opening anchor tag, %3$s: closing anchor tag */
				_x( 'Please visit %1$sStripe documentation article%2$s for the full list of available parameters', 'Stripe', 'uncanny-automator' ),
				'<a href="https://docs.stripe.com/api/customers/create" target="_blank">',
				'</a>'
			),
			'fields'          => array(
				array(
					'input_type'  => 'select',
					'option_code' => 'PARAM_NAME',
					'label'       => _x( 'Parameter', 'Stripe', 'uncanny-automator' ),
					'description' => _x( 'e.g. tax.ip_address', 'Stripe', 'uncanny-automator' ),
					'options'     => $this->additional_options(),
					'required'    => true,
				),
				array(
					'input_type'      => 'text',
					'option_code'     => 'PARAM_VALUE',
					'label'           => _x( 'Value', 'Stripe', 'uncanny-automator' ),
					'supports_tokens' => true,
					'required'        => true,
				),
			),
			'relevant_tokens' => array(),
		);

		return array(
			$email,
			$name,
			$phone,
			$description,
			$line1,
			$line2,
			$city,
			$state,
			$country,
			$postal_code,
			$payment_method,
			$shipping_line1,
			$shipping_line2,
			$shipping_city,
			$shipping_state,
			$shipping_country,
			$shipping_postal_code,
			$shipping_name,
			$shipping_phone,
			$invoice_custom_fields,
			$tax_id_data,
			$metadata,
			$additional_params,
		);
	}

	/**
	 * define_tokens
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'CUSTOMER_ID' => array(
				'name' => _x( 'Customer ID', 'Stripe', 'uncanny-automator' ),
				'type' => 'text',
			),
			'CREATED'     => array(
				'name' => _x( 'Date and time created', 'Stripe', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 * @param $parsed
	 *
	 * @return null
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$customer = array(
			'email'       => $this->get_parsed_meta_value( 'EMAIL', '' ),
			'name'        => $this->get_parsed_meta_value( 'NAME', '' ),
			'phone'       => $this->get_parsed_meta_value( 'PHONE', '' ),
			'description' => $this->get_parsed_meta_value( 'DESCRIPTION', '' ),
			'address'     => array(
				'line1'       => $this->get_parsed_meta_value( 'ADDRESS_LINE1', '' ),
				'line2'       => $this->get_parsed_meta_value( 'ADDRESS_LINE2', '' ),
				'city'        => $this->get_parsed_meta_value( 'ADDRESS_CITY', '' ),
				'state'       => $this->get_parsed_meta_value( 'ADDRESS_STATE', '' ),
				'country'     => $this->get_parsed_meta_value( 'ADDRESS_COUNTRY', '' ),
				'postal_code' => $this->get_parsed_meta_value( 'ADDRESS_POSTAL_CODE', '' ),
			),
			'shipping'    => array(

				'address' => array(
					'line1'       => $this->get_parsed_meta_value( 'SHIPPING_LINE1', '' ),
					'line2'       => $this->get_parsed_meta_value( 'SHIPPING_LINE2', '' ),
					'city'        => $this->get_parsed_meta_value( 'SHIPPING_CITY', '' ),
					'state'       => $this->get_parsed_meta_value( 'SHIPPING_STATE', '' ),
					'country'     => $this->get_parsed_meta_value( 'SHIPPING_COUNTRY', '' ),
					'postal_code' => $this->get_parsed_meta_value( 'SHIPPING_POSTAL_CODE', '' ),
				),
				'name'    => $this->get_parsed_meta_value( 'SHIPPING_NAME', '' ),
				'phone'   => $this->get_parsed_meta_value( 'SHIPPING_PHONE', '' ),
			),
		);

		$metadata = json_decode( $this->get_parsed_meta_value( 'METADATA', '' ), true );

		foreach ( $metadata as $meta ) {
			$customer['metadata'][ $meta['KEY'] ] = $meta['VALUE'];
		}

		$add_params = json_decode( $this->get_parsed_meta_value( 'ADD_PARAMS', '' ), true );

		$temp_array = array();

		foreach ( $add_params as $param ) {
			$temp_array[ $param['PARAM_NAME'] ] = $param['PARAM_VALUE'];
		}

		$temp_array = $this->helpers->dots_to_array( $temp_array );

		$customer = array_merge( $customer, $temp_array );

		$customer = $this->helpers->unset_empty_recursively( $customer );

		$response = $this->helpers->api->create_customer( $customer, $action_data );

		if ( empty( $response['data']['customer']['id'] ) ) {

			$error = _x( 'Customer could not be created', 'Stripe', 'uncanny-automator' );

			throw new \Exception( esc_html( $error ) );
		}

		$this->hydrate_tokens(
			array(
				'CUSTOMER_ID' => $response['data']['customer']['id'],
				'CREATED'     => $this->helpers->format_date( $response['data']['customer']['created'] ),
			)
		);

		return true;
	}

	/**
	 * additional_options
	 *
	 * @return array
	 */
	public function additional_options() {

		$options = array();

		$params = array(
			'balance',
			'cash_balance.settings.reconciliation_mode',
			'coupon',
			'invoice_prefix',
			'invoice_settings.default_payment_method',
			'invoice_settings.footer',
			'invoice_settings.rendering_options.amount_tax_display',
			'next_invoice_sequence',
			'preferred_locales',
			'promotion_code',
			'source',
			'tax.ip_address',
			'tax.validate_location',
			'tax_exempt',
			'test_clock',
		);

		foreach ( $params as $param ) {
			$options[] = array(
				'text'  => $param,
				'value' => $param,
			);
		}

		return $options;
	}
}
