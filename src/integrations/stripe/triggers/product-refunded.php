<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Stripe;

/**
 * Class Product_Refunded
 *
 * @package Uncanny_Automator
 *
 * @property Stripe_App_Helpers $helpers
 * @property Stripe_Api_Caller $api
 */
class Product_Refunded extends \Uncanny_Automator\Recipe\App_Trigger {

	/**
	 * Static trigger definition for lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'PRODUCT_REFUNDED', 'STRIPE' )
			->trigger_meta( 'PRICE_ID' )
			->trigger_type( 'anonymous' )
			->hook( Stripe_Webhooks::LINE_ITEM_REFUNDED_ACTION, 10, 3 );
	}

	/**
	 * Register the trigger's integration, code, meta, type, sentences, and webhook action.
	 *
	 * @return void
	 */
	public function setup_trigger() {
		$this->set_is_login_required( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->trigger_code, 'integration/stripe/' ) );
		$this->set_readable_sentence( esc_html_x( 'A payment for {{a product}} is refunded', 'Stripe', 'uncanny-automator' ) );
		// translators: %1$s is the Stripe product name
		$this->set_sentence( sprintf( esc_html_x( 'A payment for {{a product:%1$s}} is refunded', 'Stripe', 'uncanny-automator' ), $this->get_trigger_meta() ) );
	}
	/**
	 * Build the price selector and the checkout metadata and custom-field repeater fields.
	 *
	 * @return array The trigger's field definitions.
	 */
	public function options() {

		$products = array(
			'option_code' => $this->get_trigger_meta(),
			'label'       => esc_html_x( 'Price', 'Stripe', 'uncanny-automator' ),
			'input_type'  => 'select',
			'required'    => true,
			'read_only'   => false,
			'options'     => array(),
			'remote_data' => $this->helpers->remote_data_load_config( 'onetime_prices' ),
		);

		$metadata = array(
			'input_type'        => 'repeater',
			'option_code'       => 'METADATA',
			'label'             => esc_html_x( 'Extract checkout metadata', 'Stripe', 'uncanny-automator' ),
			'relevant_tokens'   => array(),
			'required'          => false,
			'fields'            => array(
				array(
					'input_type'      => 'text',
					'option_code'     => 'KEY',
					'label'           => esc_html_x( 'Metadata key', 'Stripe', 'uncanny-automator' ),
					'supports_tokens' => true,
					'required'        => false,
					'placeholder'     => esc_html_x( 'product', 'Stripe', 'uncanny-automator' ),
					'description'     => sprintf( '<i>%s</i>', esc_html_x( 'Separate keys with / to build nested data.', 'Stripe', 'uncanny-automator' ) ),
				),
			),
			'add_row_button'    => esc_html_x( 'Add a key', 'Stripe', 'uncanny-automator' ),
			'remove_row_button' => esc_html_x( 'Remove key', 'Stripe', 'uncanny-automator' ),
		);

		$custom_fields = array(
			'input_type'        => 'repeater',
			'option_code'       => 'CUSTOM_FIELDS',
			'label'             => esc_html_x( 'Extract checkout custom fields', 'Stripe', 'uncanny-automator' ),
			'required'          => false,
			'relevant_tokens'   => array(),
			'fields'            => array(
				array(
					'input_type'      => 'text',
					'option_code'     => 'KEY',
					'label'           => esc_html_x( 'Custom field key', 'Stripe', 'uncanny-automator' ),
					'supports_tokens' => true,
					'required'        => false,
					'placeholder'     => esc_html_x( 'product', 'Stripe', 'uncanny-automator' ),
				),
			),
			'add_row_button'    => esc_html_x( 'Add a field', 'Stripe', 'uncanny-automator' ),
			'remove_row_button' => esc_html_x( 'Remove field', 'Stripe', 'uncanny-automator' ),
		);

		return array(
			$products,
			$metadata,
			$custom_fields,
		);
	}

	/**
	 * Assemble the charge, line item, price, product, customer, shipping, invoice and configured
	 * metadata/custom-field token definitions exposed by this trigger.
	 *
	 * @param array $trigger The trigger's configuration, including saved METADATA and CUSTOM_FIELDS meta.
	 * @param array $tokens  The tokens already registered for the trigger.
	 *
	 * @return array The merged token definitions.
	 */
	public function define_tokens( $trigger, $tokens ) {

		$charge_tokens    = $this->helpers->tokens->charge_tokens();
		$line_item_tokens = $this->helpers->tokens->line_item_tokens();
		$price_tokens     = $this->helpers->tokens->price_tokens();

		// Remove the first element, which is PRICE_ID because it will be defined by the framework
		array_shift( $price_tokens );

		$product_tokens = array(
			array(
				'tokenId'   => 'PRODUCT_ID',
				'tokenName' => esc_html_x( 'Product ID', 'Stripe', 'uncanny-automator' ),
				'tokenType' => 'string',
			),
		);

		$customer_tokens = $this->helpers->tokens->customer_tokens();
		$shipping_tokens = $this->helpers->tokens->shipping_tokens();
		$invoice_tokens  = $this->helpers->tokens->invoice_tokens();

		$metadata_keys   = array();
		$metadata_tokens = array();

		if ( ! empty( $trigger['meta']['METADATA'] ) ) {
			$metadata_keys   = json_decode( $trigger['meta']['METADATA'], true );
			$metadata_tokens = $this->helpers->tokens->custom_data_tokens( $metadata_keys, 'METADATA', esc_html_x( 'Metadata key: ', 'Stripe', 'uncanny-automator' ) );
		}

		$custom_fields_tokens = array();

		if ( ! empty( $trigger['meta']['CUSTOM_FIELDS'] ) ) {
			$custom_fields        = json_decode( $trigger['meta']['CUSTOM_FIELDS'], true );
			$custom_fields_tokens = $this->helpers->tokens->custom_data_tokens( $custom_fields, 'CUSTOM_FIELD', esc_html_x( 'Custom field key: ', 'Stripe', 'uncanny-automator' ) );
		}

		$tokens = array_merge(
			$charge_tokens,
			$line_item_tokens,
			$price_tokens,
			$product_tokens,
			$customer_tokens,
			$shipping_tokens,
			$invoice_tokens,
			$metadata_tokens,
			$custom_fields_tokens
		);

		return $tokens;
	}

	/**
	 * Confirm the refunded line item is a one-time price matching the selected price (or any one-time price).
	 *
	 * @param array $trigger   The trigger's configuration, including the selected PRICE_ID meta.
	 * @param array $hook_args The hook arguments, where the first element is the Stripe line item.
	 *
	 * @return bool True when the line item's price matches the trigger's selection, false otherwise.
	 */
	public function validate( $trigger, $hook_args ) {

		$selected_price = $trigger['meta'][ $this->get_trigger_meta() ];

		$line_item = array_shift( $hook_args );

		if ( 'item' !== $line_item['object'] ) {
			return false;
		}

		if ( empty( $line_item['price']['id'] ) ) {
			return false;
		}

		$price = $line_item['price'];

		// If any product is selected
		if ( '-1' === $selected_price && 'one_time' === $price['type'] ) {
			return true;
		}

		if ( $selected_price !== $price['id'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Populate the charge, line item, price, product, customer, shipping, invoice and
	 * metadata/custom-field tokens from the refunded line item, session, and webhook request.
	 *
	 * @param array $trigger   The trigger's configuration, including saved METADATA and CUSTOM_FIELDS meta.
	 * @param array $hook_args The hook arguments: the line item, checkout session, and webhook request.
	 *
	 * @return array The hydrated token values keyed by token id.
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $line_item, $session, $request ) = $hook_args;

		$charge = $request['data']['object'];

		$charge_tokens = $this->helpers->tokens->hydrate_charge_tokens( $charge );

		$price   = $line_item['price'];
		$product = $price['product'];
		$invoice = $session['invoice'];

		$list_item_tokens = $this->helpers->tokens->hydrate_line_item_tokens( $line_item );
		$price_tokens     = $this->helpers->tokens->hydrate_price_tokens( $price );
		$product_tokens   = $this->helpers->tokens->hydrate_product_tokens( $product );

		$invoice_tokens = $this->helpers->tokens->hydrate_invoice_tokens( $invoice );

		$customer = $session['customer_details'];

		$customer_tokens = $this->helpers->tokens->hydrate_customer_tokens( $customer );
		$shipping_tokens = $this->helpers->tokens->hydrate_shipping_tokens( $customer );

		$metadata_keys   = json_decode( $trigger['meta']['METADATA'], true );
		$metadata_tokens = $this->helpers->tokens->hydrate_metadata_tokens( $metadata_keys, $session, 'METADATA' );

		$custom_fields        = json_decode( $trigger['meta']['CUSTOM_FIELDS'], true );
		$custom_fields_tokens = $this->helpers->tokens->hydrate_custom_fields_tokens( $custom_fields, $session, 'CUSTOM_FIELD' );

		$tokens = array_merge(
			$charge_tokens,
			$list_item_tokens,
			$price_tokens,
			$product_tokens,
			$customer_tokens,
			$shipping_tokens,
			$invoice_tokens,
			$metadata_tokens,
			$custom_fields_tokens
		);

		return $tokens;
	}
}
