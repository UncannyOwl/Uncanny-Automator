<?php

namespace Uncanny_Automator\Integrations\Stripe;

/**
 * Class Create_Payment_Link
 *
 * @package Uncanny_Automator
 *
 * @property Stripe_App_Helpers $helpers
 * @property Stripe_Api_Caller $api
 */
class Create_Payment_Link extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Register the action's integration, code, meta, and sentences.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'STRIPE' );
		$this->set_action_code( 'CREATE_PAYMENT_LINK' );
		$this->set_action_meta( 'PRODUCT' );

		$this->set_requires_user( false );

		// translators: %1$s is the product name
		$this->set_sentence( sprintf( esc_html_x( 'Create a payment link for {{a product:%1$s}}', 'Stripe', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Create a payment link for {{a product}}', 'Stripe', 'uncanny-automator' ) );
	}

	/**
	 * Build the action's option fields for the recipe builder.
	 *
	 * @return array The action's field definitions.
	 */
	public function options() {

		$products_repeater = array(
			'option_code'       => 'PRICES',
			'input_type'        => 'repeater',
			'label'             => esc_html_x( 'Items', 'Stripe', 'uncanny-automator' ),
			'required'          => true,
			'fields'            => $this->helpers->get_payment_link_row_fields(),
			'remote_data'       => $this->helpers->remote_data_load_config( 'payment_link_fields' ),
			'add_row_button'    => esc_html_x( 'Add product', 'Stripe', 'uncanny-automator' ),
			'remove_row_button' => esc_html_x( 'Remove product', 'Stripe', 'uncanny-automator' ),
			'hide_actions'      => false,
			'relevant_tokens'   => array(),
		);

		$skip_empty_items = array(
			'option_code'     => 'SKIP_EMPTY_ITEMS',
			'label'           => esc_html_x( 'Skip empty products', 'Stripe', 'uncanny-automator' ),
			'input_type'      => 'checkbox',
			'required'        => false,
			'default_value'   => false,
			'description'     => esc_html_x( 'When enabled, products with a quantity less than 1, or with no price, are skipped instead of causing an error. Useful when product values are populated dynamically using tokens.', 'Stripe', 'uncanny-automator' ),
			'relevant_tokens' => array(),
		);

		$metadata = array(
			'option_code'     => 'METADATA',
			'input_type'      => 'repeater',
			'label'           => esc_html_x( 'Metadata', 'Stripe', 'uncanny-automator' ),
			'required'        => false,
			'description'     => sprintf(
				/* translators: %1$s: [delete], %2$s opening anchor tag, %3$s: closing anchor tag */
				esc_html_x( 'Set of %1$skey-value-pairs%2$s that you can attach to an object. This can be useful for storing additional information about the object in a structured format.', 'Stripe', 'uncanny-automator' ),
				'<a href="https://docs.stripe.com/api/metadata" target="_blank">',
				'</a>'
			),
			'fields'          => array(
				array(
					'input_type'  => 'text',
					'option_code' => 'KEY',
					'label'       => esc_html_x( 'Key', 'Stripe', 'uncanny-automator' ),
					'description' => esc_html_x( 'e.g. order_id', 'Stripe', 'uncanny-automator' ),
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

		$custom_fields = array(
			'option_code'     => 'CUSTOM_FIELDS',
			'input_type'      => 'repeater',
			'label'           => esc_html_x( 'Custom fields', 'Stripe', 'uncanny-automator' ),
			'required'        => false,
			'description'     => esc_html_x( 'Collect additional information from your customer using custom fields. Up to 3 fields are supported.', 'Stripe', 'uncanny-automator' ),
			'fields'          => array(
				array(
					'input_type'  => 'text',
					'option_code' => 'FIELD_KEY',
					'label'       => esc_html_x( 'Field key', 'Stripe', 'uncanny-automator' ),
					'required'    => true,
				),
				array(
					'input_type'  => 'text',
					'option_code' => 'FIELD_LABEL',
					'label'       => esc_html_x( 'Field label', 'Stripe', 'uncanny-automator' ),
					'required'    => true,
				),
				array(
					'input_type'            => 'select',
					'option_code'           => 'FIELD_TYPE',
					'label'                 => esc_html_x( 'Field type', 'Stripe', 'uncanny-automator' ),
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
					'label'       => esc_html_x( 'Optional', 'Stripe', 'uncanny-automator' ),
				),
			),
			'relevant_tokens' => array(),
		);

		// Custom params.
		$additional_params = array(
			'option_code'     => 'ADD_PARAMS',
			'input_type'      => 'repeater',
			'label'           => esc_html_x( 'Additional parameters', 'Stripe', 'uncanny-automator' ),
			'required'        => false,
			'description'     => sprintf(
				/* translators: %1$s: [delete], %2$s opening anchor tag, %3$s: closing anchor tag */
				esc_html_x( 'Please visit %1$sStripe documentation article%2$s for the full list of available parameters', 'Stripe', 'uncanny-automator' ),
				'<a href="https://docs.stripe.com/api/payment-link/create?lang=php" target="_blank">',
				'</a>'
			),
			'fields'          => array(
				array(
					'input_type'  => 'select',
					'option_code' => 'PARAM_NAME',
					'label'       => esc_html_x( 'Parameter', 'Stripe', 'uncanny-automator' ),
					'description' => esc_html_x( 'e.g. custom_text.after_submit.message', 'Stripe', 'uncanny-automator' ),
					'required'    => true,
					'options'     => $this->additional_params(),
				),
				array(
					'input_type'      => 'text',
					'option_code'     => 'PARAM_VALUE',
					'label'           => esc_html_x( 'Value', 'Stripe', 'uncanny-automator' ),
					'supports_tokens' => true,
					'required'        => true,
				),
			),
			'relevant_tokens' => array(),
		);

		return array(
			$products_repeater,
			$skip_empty_items,
			$metadata,
			$additional_params,
			$custom_fields,
		);
	}

	/**
	 * Declare the tokens this action exposes to later recipe items.
	 *
	 * @return array Token definitions keyed by token id.
	 */
	public function define_tokens() {
		return array(
			'LINK' => array(
				'name' => esc_html_x( 'Link', 'Stripe', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Build a Stripe payment link from the configured products and store its URL token.
	 *
	 * @param int   $user_id     The user the recipe is running for.
	 * @param array $action_data The action's stored configuration.
	 * @param int   $recipe_id   The recipe id.
	 * @param array $args        Runtime args for the current recipe run.
	 * @param array $parsed      Token-parsed values for the action.
	 *
	 * @return bool True once the payment link is created.
	 * @throws \Exception If no valid products are configured or the Stripe request fails.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$skip_empty_items = ! empty( $this->get_parsed_meta_value( 'SKIP_EMPTY_ITEMS', '' ) );
		$prices           = json_decode( $this->get_parsed_meta_value( 'PRICES' ), true );

		if ( empty( $prices ) || ! is_array( $prices ) ) {
			throw new \Exception(
				esc_html_x( 'At least one product is required', 'Stripe', 'uncanny-automator' )
			);
		}

		$payment_link = array(
			'line_items' => array(),
		);

		foreach ( $prices as $price ) {

			// Get the price ID, checking for custom value
			$price_id = isset( $price['PRICE'] ) ? trim( $price['PRICE'] ) : '';

			// If custom value is used, get it from PRICE_custom
			if ( 'automator_custom_value' === $price_id && isset( $price['PRICE_custom'] ) ) {
				$price_id = trim( $price['PRICE_custom'] );
			}

			// Parse using Automator's text parser to resolve tokens
			$price_id = Automator()->parse->text( $price_id, $recipe_id, $user_id, $args );
			$price_id = trim( $price_id );

			$quantity_default = $skip_empty_items ? '0' : '1';
			$quantity         = isset( $price['QUANTITY'] ) ? trim( $price['QUANTITY'] ) : $quantity_default;

			// When enabled, skip products with a quantity below 1 or no price so
			// token-driven rows can be conditionally included without erroring.
			if ( $skip_empty_items && ( intval( $quantity ) < 1 || '' === $price_id ) ) {
				continue;
			}

			// Validate that price ID is not empty
			if ( empty( $price_id ) ) {
				throw new \Exception(
					esc_html_x( 'Price ID cannot be empty', 'Stripe', 'uncanny-automator' )
				);
			}

			$payment_link['line_items'][] = array(
				'price'    => $price_id,
				'quantity' => max( 1, intval( $quantity ) ),
			);
		}

		if ( empty( $payment_link['line_items'] ) ) {
			$message = $skip_empty_items
				? esc_html_x( 'All products were empty and skipped. At least one valid product is required.', 'Stripe', 'uncanny-automator' )
				: esc_html_x( 'At least one product is required', 'Stripe', 'uncanny-automator' );
			throw new \Exception( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$metadata = json_decode( $this->get_parsed_meta_value( 'METADATA', '' ), true );

		if ( ! empty( $metadata ) && is_array( $metadata ) ) {
			foreach ( $metadata as $meta ) {
				$payment_link['metadata'][ $meta['KEY'] ] = $meta['VALUE'];
			}
		}

		$add_params = json_decode( $this->get_parsed_meta_value( 'ADD_PARAMS', '' ), true );

		$temp_array = array();

		if ( ! empty( $add_params ) && is_array( $add_params ) ) {
			foreach ( $add_params as $param ) {
				$temp_array[ $param['PARAM_NAME'] ] = $param['PARAM_VALUE'];
			}
		}

		$temp_array = $this->helpers->explode_fields( $temp_array, $this->multiselect_fields() );
		$temp_array = $this->helpers->parse_metadata_fields( $temp_array );
		$temp_array = $this->helpers->dots_to_array( $temp_array );

		$payment_link = array_merge( $payment_link, $temp_array );
		$payment_link = $this->helpers->unset_empty_recursively( $payment_link );

		$custom_fields = json_decode( $this->get_parsed_meta_value( 'CUSTOM_FIELDS', '' ), true );

		if ( ! empty( $custom_fields ) && is_array( $custom_fields ) ) {
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
		}

		$response = $this->api->create_payment_link( $payment_link, $action_data );

		if ( empty( $response['data']['payment_link']['url'] ) ) {
			throw new \Exception(
				esc_html_x( 'Link could not be created', 'Stripe', 'uncanny-automator' )
			);
		}

		$this->hydrate_tokens(
			array(
				'LINK' => $response['data']['payment_link']['url'],
			)
		);

		return true;
	}

	/**
	 * Field paths whose comma-separated values are exploded into arrays.
	 *
	 * @return array Option-path strings, filterable.
	 */
	public function multiselect_fields() {

		$fields = array(
			'payment_method_types',
			'shipping_address_collection.allowed_countries',
			'invoice_creation.invoice_data.account_tax_ids',
		);

		return apply_filters( 'automator_stripe_create_payment_link_multiselect_fields', $fields );
	}

	/**
	 * Build the select options for the additional Stripe payment-link parameters.
	 *
	 * @return array List of { text, value } option arrays, filterable.
	 */
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
				'invoice_creation.invoice_data.account_tax_ids',
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
				'payment_intent_data.setup_future_usage',
				'payment_intent_data.metadata',
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

		$options = array();
		foreach ( $params as $param ) {
			$options[] = array(
				'text'  => $param,
				'value' => $param,
			);
		}

		return $options;
	}
}
