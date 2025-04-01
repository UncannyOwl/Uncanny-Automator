<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Stripe;

/**
 * Class Product_Refunded
 *
 * @package Uncanny_Automator
 */
class Product_Refunded extends \Uncanny_Automator\Recipe\Trigger {

	protected $helpers;

	/**
	 * Trigger code.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'PRODUCT_REFUNDED';

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */

	public function setup_trigger() {

		$this->helpers = array_shift( $this->dependencies );

		$this->set_integration( 'STRIPE' );

		$this->set_trigger_code( self::TRIGGER_CODE );

		$this->set_trigger_meta( 'PRICE_ID' );

		$this->set_is_login_required( false );

		$this->set_trigger_type( 'anonymous' );

		$this->set_support_link( Automator()->get_author_support_link( $this->trigger_code, 'integration/stripe/' ) );

		$this->set_sentence(
			sprintf(
				// translators: %s Stripe product name
				esc_attr_x( 'A payment for {{a product:%1$s}} is refunded', 'Stripe', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		// Non-active state sentence to show

		$this->set_readable_sentence( esc_attr_x( 'A payment for {{a product}} is refunded', 'Stripe', 'uncanny-automator' ) );

		// Which do_action() fires this trigger.
		$this->add_action( Stripe_Webhook::LINE_ITEM_REFUNDED_ACTION );

		$this->set_action_args_count( 3 );
	}
	/**
	 * Options.
	 *
	 * @return mixed
	 */
	public function options() {

		$prices = $this->helpers->api->get_prices_options( 'one_time' );

		array_unshift(
			$prices,
			array(
				'text'  => esc_html_x( 'Any', 'Stripe', 'uncanny-automator' ),
				'value' => '-1',
			)
		);

		$products = array(
			'option_code' => $this->get_trigger_meta(),
			'label'       => esc_html_x( 'Price', 'Stripe', 'uncanny-automator' ),
			'input_type'  => 'select',
			'required'    => true,
			'read_only'   => false,
			'options'     => $prices,
		);

		$metadata = array(
			'input_type'        => 'repeater',
			'option_code'       => 'METADATA',
			'label'             => esc_attr_x( 'Extract checkout metadata', 'Stripe', 'uncanny-automator' ),
			'relevant_tokens'   => array(),
			'required'          => false,
			'fields'            => array(
				array(
					'input_type'      => 'text',
					'option_code'     => 'KEY',
					'label'           => esc_attr_x( 'Metadata key', 'Stripe', 'uncanny-automator' ),
					'supports_tokens' => true,
					'required'        => false,
					'placeholder'     => esc_html_x( 'product', 'Stripe', 'uncanny-automator' ),
					'description'     => sprintf( '<i>%s</i>', esc_html_x( 'Separate keys with / to build nested data.', 'Stripe', 'uncanny-automator' ) ),
				),
			),
			/* translators: Non-personal infinitive verb */
			'add_row_button'    => esc_attr_x( 'Add a key', 'Stripe', 'uncanny-automator' ),
			/* translators: Non-personal infinitive verb */
			'remove_row_button' => esc_attr_x( 'Remove key', 'Stripe', 'uncanny-automator' ),
		);

		$custom_fields = array(
			'input_type'        => 'repeater',
			'option_code'       => 'CUSTOM_FIELDS',
			'label'             => esc_attr_x( 'Extract checkout custom fields', 'Stripe', 'uncanny-automator' ),
			'required'          => false,
			'relevant_tokens'   => array(),
			'fields'            => array(
				array(
					'input_type'      => 'text',
					'option_code'     => 'KEY',
					'label'           => esc_attr_x( 'Custom field key', 'Stripe', 'uncanny-automator' ),
					'supports_tokens' => true,
					'required'        => false,
					'placeholder'     => esc_html_x( 'product', 'Stripe', 'uncanny-automator' ),
				),
			),
			/* translators: Non-personal infinitive verb */
			'add_row_button'    => esc_attr_x( 'Add a field', 'Stripe', 'uncanny-automator' ),
			/* translators: Non-personal infinitive verb */
			'remove_row_button' => esc_attr_x( 'Remove field', 'Stripe', 'uncanny-automator' ),
		);

		return array(
			$products,
			$metadata,
			$custom_fields,
		);
	}

	/**
	 * Returns the trigger's tokens.
	 *
	 * @return array
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
	 * Validate the trigger.
	 *
	 * @param $args
	 *
	 * @return bool
	 */

	public function validate( $trigger, $hook_args ) {
		return true;
	}

	/**
	 * hydrate_tokens
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */

	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $line_item, $session, $request ) = $hook_args;

		$charge = $request['data']['object'];

		$charge_tokens = $this->helpers->tokens->hydrate_charge_tokens( $charge );

		$price   = $line_item['price'];
		$invoice = $session['invoice'];

		$product_tokens = array(
			'PRODUCT_ID' => empty( $price['product'] ) ? '' : $price['product'],
		);

		$list_item_tokens = $this->helpers->tokens->hydrate_line_item_tokens( $line_item );
		$price_tokens     = $this->helpers->tokens->hydrate_price_tokens( $price );

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
