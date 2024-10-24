<?php

namespace Uncanny_Automator\Integrations\Stripe;

class Create_Payment_Link extends \Uncanny_Automator\Recipe\Action {

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
		$this->set_action_code( 'CREATE_PAYMENT_LINK' );
		$this->set_action_meta( 'PRODUCT' );

		$this->set_requires_user( false );

		/* translators: %1$s Contact Email */
		$this->set_sentence( sprintf( esc_attr_x( 'Create a payment link for {{a product:%1$s}}', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_attr_x( 'Create a payment link for {{a product}}', 'uncanny-automator' ) );

	}

	/**
	 * options
	 *
	 * @return array
	 */
	public function options() {

		$products_repeater = array(
			'option_code'       => 'PRICES',
			'input_type'        => 'repeater',
			'label'             => __( 'Items', 'uncanny-automator' ),
			'required'          => true,
			'fields'            => array(
				array(
					'option_code' => 'PRICE',
					'label'       => __( 'Product and price', 'uncanny-automator' ),
					'input_type'  => 'select',
					'required'    => true,
					'read_only'   => false,
					'options'     => $this->helpers->api->get_prices_options(),
				),
				Automator()->helpers->recipe->field->text(
					array(
						'option_code' => 'QUANTITY',
						'label'       => __( 'Quantity', 'uncanny-automator' ),
						'input_type'  => 'text',
						'tokens'      => true,
						'default'     => 1,
					)
				),
			),
			'add_row_button'    => __( 'Add product', 'uncanny-automator' ),
			'remove_row_button' => __( 'Remove product', 'uncanny-automator' ),
			'hide_actions'      => false,
			'relevant_tokens'   => array(),
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

		$custom_fields = array(
			'option_code'     => 'CUSTOM_FIELDS',
			'input_type'      => 'repeater',
			'label'           => _x( 'Custom fields', 'Stripe', 'uncanny-automator' ),
			'required'        => false,
			'description'     => _x( 'Collect additional information from your customer using custom fields. Up to 3 fields are supported.', 'Stripe', 'uncanny-automator' ),
			'fields'          => array(
				array(
					'input_type'  => 'text',
					'option_code' => 'FIELD_KEY',
					'label'       => _x( 'Field key', 'Stripe', 'uncanny-automator' ),
					'required'    => true,
				),
				array(
					'input_type'  => 'text',
					'option_code' => 'FIELD_LABEL',
					'label'       => _x( 'Field label', 'Stripe', 'uncanny-automator' ),
					'required'    => true,
				),
				array(
					'input_type'            => 'select',
					'option_code'           => 'FIELD_TYPE',
					'label'                 => _x( 'Field type', 'Stripe', 'uncanny-automator' ),
					'required'              => true,
					'supports_tokens'       => false,
					'supports_custom_value' => false,
					'options'               => array(
						array(
							'text'  => 'Text',
							'value' => 'text',
						),
						array(
							'text'  => 'Number',
							'value' => 'numeric',
						),
					),
				),
				array(
					'input_type'  => 'checkbox',
					'option_code' => 'FIELD_OPTIONAL',
					'label'       => _x( 'Optional', 'Stripe', 'uncanny-automator' ),
				),
			),
			'relevant_tokens' => array(),
		);

		// Custom params.
		$additional_params = array(
			'option_code'     => 'ADD_PARAMS',
			'input_type'      => 'repeater',
			'label'           => _x( 'Additional parameters', 'Stripe', 'uncanny-automator' ),
			'required'        => false,
			'description'     => sprintf(
			/* translators: %1$s: [delete], %2$s opening anchor tag, %3$s: closing anchor tag */
				_x( 'Please visit %1$sStripe documentation article%2$s for the full list of available parameters', 'Stripe', 'uncanny-automator' ),
				'<a href="https://docs.stripe.com/api/payment-link/create?lang=php" target="_blank">',
				'</a>'
			),
			'fields'          => array(
				array(
					'input_type'  => 'select',
					'option_code' => 'PARAM_NAME',
					'label'       => _x( 'Parameter', 'Stripe', 'uncanny-automator' ),
					'description' => _x( 'e.g. custom_text.after_submit.message', 'Stripe', 'uncanny-automator' ),
					'required'    => true,
					'options'     => $this->additional_params(),
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
			$products_repeater,
			$metadata,
			$additional_params,
			$custom_fields,
		);
	}

	/**
	 * define_tokens
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'LINK' => array(
				'name' => __( 'Link', 'uncanny-automator' ),
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

		$prices = json_decode( $this->get_parsed_meta_value( 'PRICES' ), true );

		$payment_link = array(
			'line_items' => array(),
		);

		foreach ( $prices as $price ) {

			$payment_link['line_items'][] = array(
				'price'    => $price['PRICE'],
				'quantity' => $price['QUANTITY'],
			);
		}

		$metadata = json_decode( $this->get_parsed_meta_value( 'METADATA', '' ), true );

		foreach ( $metadata as $meta ) {
			$payment_link['metadata'][ $meta['KEY'] ] = $meta['VALUE'];
		}

		$add_params = json_decode( $this->get_parsed_meta_value( 'ADD_PARAMS', '' ), true );

		$temp_array = array();

		foreach ( $add_params as $param ) {
			$temp_array[ $param['PARAM_NAME'] ] = $param['PARAM_VALUE'];
		}

		$temp_array = $this->helpers->dots_to_array( $temp_array );

		$payment_link = array_merge( $payment_link, $temp_array );

		$payment_link = $this->helpers->unset_empty_recursively( $payment_link );

		$custom_fields = json_decode( $this->get_parsed_meta_value( 'CUSTOM_FIELDS', '' ), true );

		foreach ( $custom_fields as $meta ) {

			$field = array(
				'key'      => $meta['FIELD_KEY'],
				'type'     => $meta['FIELD_TYPE'],
				'optional' => $meta['FIELD_OPTIONAL'],
			);

			if ( ! empty( $meta['FIELD_LABEL'] ) ) {
				$field['label'] = array(
					'custom' => $meta['FIELD_LABEL'],
					'type'   => 'custom',
				);
			}

			$payment_link['custom_fields'][] = $field;
		}

		$response = $this->helpers->api->create_payment_link( $payment_link, $action_data );

		if ( empty( $response['data']['payment_link']['url'] ) ) {

			$error = _x( 'Link could not be created', 'Stripe', 'uncanny-automator' );

			throw new \Exception( $error );
		}

		$this->hydrate_tokens(
			array(
				'LINK' => $response['data']['payment_link']['url'],
			)
		);

		return true;
	}

	public function additional_params() {

		$params = apply_filters(
			'automator_stripe_create_payment_params',
			array(
				'after_completion.type',
				'after_completion.redirect.url',
				'after_completion.hosted_confirmation.custom_message',
				'allow_promotion_codes',
				'application_fee_amount',
				'application_fee_percent',
				'automatic_tax.enabled',
				'automatic_tax.liability.type',
				'automatic_tax.liability.account',
				'billing_address_collection',
				'consent_collection.payment_method_reuse_agreement.position',
				'consent_collection.promotions',
				'consent_collection.terms_of_service',
				'currency',
				'custom_text.after_submit.message',
				'custom_text.shipping_address.message',
				'custom_text.submit.message',
				'custom_text.terms_of_service_acceptance.message',
				'customer_creation',
				'inactive_message',
				'invoice_creation.enabled',
				'invoice_creation.account_tax_ids',
				'invoice_creation.invoice_data.custom_fields.name',
				'invoice_creation.invoice_data.custom_fields.value',
				'invoice_creation.invoice_data.description',
				'invoice_creation.invoice_data.footer',
				'invoice_creation.invoice_data.issuer.type',
				'invoice_creation.invoice_data.issuer.account',
				'invoice_creation.invoice_data.metadata',
				'invoice_creation.invoice_data.rendering_options.amount_tax_display',
				'on_behalf_of',
				'payment_intent_data.capture_method',
				'payment_intent_data.description',
				'payment_intent_data.description',
				'payment_intent_data.setup_future_usage',
				'payment_intent_data.metadata',
				'payment_intent_data.setup_future_usage',
				'payment_intent_data.statement_descriptor',
				'payment_intent_data.statement_descriptor_suffix',
				'payment_intent_data.transfer_group',
				'payment_method_collection',
				'payment_method_types',
				'phone_number_collection.enabled',
				'restrictions.minimum_amount',
				'restrictions.completed_sessions.limit',
				'shipping_address_collection.allowed_countries',
				'shipping_options.shipping_rate',
				'submit_type',
				'subscription_data.description',
				'subscription_data.invoice_settings.issuer.type',
				'subscription_data.invoice_settings.issuer.account',
				'subscription_data.metadata',
				'subscription_data.trial_period_days',
				'subscription_data.trial_settings.end_behavior.missing_payment_method',
				'tax_id_collection.enabled',
				'transfer_data.destination',
				'transfer_data.amount',
			)
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

