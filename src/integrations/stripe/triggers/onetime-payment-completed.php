<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Stripe;

/**
 * Class Onetime_Payment_Completed
 *
 * @package Uncanny_Automator
 */
class Onetime_Payment_Completed extends \Uncanny_Automator\Recipe\Trigger {

	protected $helpers;

	/**
	 * Trigger code.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'ONETIME_PAYMENT_COMPLETED';

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
			/* Translators: Product name */
				esc_attr__( 'One-time payment for {{a product:%1$s}} is completed', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		// Non-active state sentence to show

		$this->set_readable_sentence( esc_attr__( 'One-time payment for {{a product}} is completed', 'uncanny-automator' ) );

		// Which do_action() fires this trigger.
		$this->add_action( Stripe_Webhook::LINE_ITEM_PAID_ACTION );

		$this->set_action_args_count( 2 );
	}

	/**
	 * options
	 *
	 * @return array
	 */
	public function options() {

		$prices = $this->helpers->api->get_prices_options( 'one_time' );

		array_unshift(
			$prices,
			array(
				'text'  => _x( 'Any', 'Stripe', 'uncanny-automator' ),
				'value' => '-1',
			)
		);

		$products = array(
			'option_code' => $this->get_trigger_meta(),
			'label'       => esc_html__( 'Price', 'uncanny-automator' ),
			'input_type'  => 'select',
			'required'    => true,
			'read_only'   => false,
			'options'     => $prices,
		);

		$metadata = array(
			'input_type'        => 'repeater',
			'option_code'       => 'METADATA',
			'label'             => esc_attr__( 'Extract checkout metadata', 'uncanny-automator' ),
			'relevant_tokens'   => array(),
			'required'          => false,
			'fields'            => array(
				array(
					'input_type'      => 'text',
					'option_code'     => 'KEY',
					'label'           => esc_attr__( 'Metadata key', 'uncanny-automator' ),
					'supports_tokens' => true,
					'required'        => false,
					'placeholder'     => esc_html__( 'product', 'uncanny-automator' ),
					'description'     => sprintf( '<i>%s</i>', esc_html__( 'Separate keys with / to build nested data.', 'uncanny-automator' ) ),
				),
			),
			/* translators: Non-personal infinitive verb */
			'add_row_button'    => esc_attr__( 'Add a key', 'uncanny-automator' ),
			/* translators: Non-personal infinitive verb */
			'remove_row_button' => esc_attr__( 'Remove key', 'uncanny-automator' ),
		);

		$custom_fields = array(
			'input_type'        => 'repeater',
			'option_code'       => 'CUSTOM_FIELDS',
			'label'             => esc_attr__( 'Extract checkout custom fields', 'uncanny-automator' ),
			'required'          => false,
			'relevant_tokens'   => array(),
			'fields'            => array(
				array(
					'input_type'      => 'text',
					'option_code'     => 'KEY',
					'label'           => esc_attr__( 'Custom field key', 'uncanny-automator' ),
					'supports_tokens' => true,
					'required'        => false,
					'placeholder'     => esc_html__( 'product', 'uncanny-automator' ),
				),
			),
			/* translators: Non-personal infinitive verb */
			'add_row_button'    => esc_attr__( 'Add a field', 'uncanny-automator' ),
			/* translators: Non-personal infinitive verb */
			'remove_row_button' => esc_attr__( 'Remove field', 'uncanny-automator' ),
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

		$list_item_tokens = $this->helpers->tokens->line_item_tokens();
		$price_tokens     = $this->helpers->tokens->price_tokens();

		// Remove the first element, which is PRICE_ID because it will be define by the framework
		array_shift( $price_tokens );

		$product_tokens  = $this->helpers->tokens->product_tokens();
		$customer_tokens = $this->helpers->tokens->customer_tokens();
		$shipping_tokens = $this->helpers->tokens->shipping_tokens();
		$invoice_tokens  = $this->helpers->tokens->invoice_tokens();

		$metadata_keys   = array();
		$metadata_tokens = array();

		if ( ! empty( $trigger['meta']['METADATA'] ) ) {
			$metadata_keys   = json_decode( $trigger['meta']['METADATA'], true );
			$metadata_tokens = $this->helpers->tokens->custom_data_tokens( $metadata_keys, 'METADATA', _x( 'Metadata key: ', 'Stripe', 'uncanny-automator' ) );
		}

		$custom_fields_tokens = array();

		if ( ! empty( $trigger['meta']['CUSTOM_FIELDS'] ) ) {
			$custom_fields        = json_decode( $trigger['meta']['CUSTOM_FIELDS'], true );
			$custom_fields_tokens = $this->helpers->tokens->custom_data_tokens( $custom_fields, 'CUSTOM_FIELD', _x( 'Custom field key: ', 'Stripe', 'uncanny-automator' ) );
		}

		$tokens = array_merge(
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

	/**
	 * Validate the trigger.
	 *
	 * @param $args
	 *
	 * @return bool
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
	 * hydrate_tokens
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */

	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $list_item, $session ) = $hook_args;

		$price   = $list_item['price'];
		$product = $price['product'];
		$invoice = $session['invoice'];

		$list_item_tokens = $this->helpers->tokens->hydrate_line_item_tokens( $list_item );
		$price_tokens     = $this->helpers->tokens->hydrate_price_tokens( $price );
		$product_tokens   = $this->helpers->tokens->hydrate_product_tokens( $product );
		$invoice_tokens   = $this->helpers->tokens->hydrate_invoice_tokens( $invoice );

		$customer = $session['customer'];

		$customer_tokens = $this->helpers->tokens->hydrate_customer_tokens( $customer );
		$shipping_tokens = $this->helpers->tokens->hydrate_shipping_tokens( $customer );

		$metadata_keys   = json_decode( $trigger['meta']['METADATA'], true );
		$metadata_tokens = $this->helpers->tokens->hydrate_metadata_tokens( $metadata_keys, $session, 'METADATA' );

		$custom_fields        = json_decode( $trigger['meta']['CUSTOM_FIELDS'], true );
		$custom_fields_tokens = $this->helpers->tokens->hydrate_custom_fields_tokens( $custom_fields, $session, 'CUSTOM_FIELD' );

		$tokens = array_merge(
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
