<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Stripe;

/**
 * Class Subscription_Paid
 *
 * @package Uncanny_Automator
 *
 * @property Stripe_App_Helpers $helpers
 * @property Stripe_Api_Caller $api
 */
class Subscription_Paid extends \Uncanny_Automator\Recipe\App_Trigger {

	/**
	 * Trigger code.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'SUB_PAID';

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */

	public function setup_trigger() {

		$this->set_integration( 'STRIPE' );

		$this->set_trigger_code( self::TRIGGER_CODE );

		$this->set_trigger_meta( 'PRICE_ID' );

		$this->set_is_login_required( false );

		$this->set_trigger_type( 'anonymous' );

		$this->set_support_link( Automator()->get_author_support_link( $this->trigger_code, 'integration/stripe/' ) );

		// translators: %1$s is the subscription product name
		$this->set_sentence( sprintf( esc_html_x( '{{A subscription:%1$s}} is paid', 'Stripe', 'uncanny-automator' ), $this->get_trigger_meta() ) );

		// Non-active state sentence to show

		$this->set_readable_sentence( esc_html_x( '{{A subscription}} is paid', 'Stripe', 'uncanny-automator' ) );

		// Which do_action() fires this trigger.
		$this->add_action( Stripe_Webhooks::INVOICE_ITEM_PAID_ACTION );

		$this->set_action_args_count( 3 );
	}

	/**
	 * options
	 *
	 * @return array
	 */
	public function options() {

		$prices = $this->api->get_prices_options( 'recurring' );

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
			'label'             => esc_html_x( 'Extract subscription metadata', 'Stripe', 'uncanny-automator' ),
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
			/* translators: Non-personal infinitive verb */
			'add_row_button'    => esc_html_x( 'Add a key', 'Stripe', 'uncanny-automator' ),
			/* translators: Non-personal infinitive verb */
			'remove_row_button' => esc_html_x( 'Remove key', 'Stripe', 'uncanny-automator' ),
		);

		return array(
			$products,
			$metadata,
		);
	}


	/**
	 * Returns the trigger's tokens.
	 *
	 * @return array
	 */

	public function define_tokens( $trigger, $tokens ) {

		$price_tokens = $this->helpers->tokens->price_tokens();

		// Remove the first element, which is PRICE_ID because it will be define by the framework
		array_shift( $price_tokens );

		$product_tokens = $this->helpers->tokens->product_tokens();

		$customer_tokens = $this->helpers->tokens->customer_tokens();

		$invoice_tokens = $this->helpers->tokens->invoice_tokens();

		$metadata_keys   = array();
		$metadata_tokens = array();

		if ( ! empty( $trigger['meta']['METADATA'] ) ) {
			$metadata_keys   = json_decode( $trigger['meta']['METADATA'], true );
			$metadata_tokens = $this->helpers->tokens->custom_data_tokens( $metadata_keys, 'METADATA', esc_html_x( 'Metadata key: ', 'Stripe', 'uncanny-automator' ) );
		}

		$tokens = array_merge(
			$price_tokens,
			$product_tokens,
			$customer_tokens,
			$invoice_tokens,
			$metadata_tokens,
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

		list( $line_item, $invoice, $request ) = $hook_args;

		$selected_price = $trigger['meta'][ $this->get_trigger_meta() ];

		// If any product is selected
		if ( '-1' === $selected_price ) {
			return true;
		}

		// Extract price ID using helper method (handles multiple data structures)
		$price_id = $this->helpers->get_line_item_price_id( $line_item );

		if ( empty( $price_id ) ) {
			return false;
		}

		// Check if the price matches
		return $selected_price === $price_id;
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

		list( $line_item, $invoice, $request ) = $hook_args;

		// Extract price and product IDs from line item (handles multiple structures)
		$price_id   = $this->helpers->get_line_item_price_id( $line_item );
		$product_id = $this->helpers->get_line_item_product_id( $line_item );

		// Get subscription from parent details or line item
		$subscription_id = null;
		if ( ! empty( $line_item['parent']['subscription_item_details']['subscription'] ) ) {
			$subscription_id = $line_item['parent']['subscription_item_details']['subscription'];
		} elseif ( ! empty( $line_item['subscription'] ) ) {
			$subscription_id = $line_item['subscription'];
		}

		// Fetch full subscription data to get expanded price/product/customer objects
		$response = $this->api->get_subscription( $subscription_id );

		$subscription = $response['data']['subscription'];

		// Get price and product from subscription items (expanded objects)
		$price   = $subscription['items']['data'][0]['price'] ?? array( 'id' => $price_id );

		// Get product - API expands both plan.product (legacy) and items.data.price.product (modern)
		$product = $price['product'] ?? $subscription['plan']['product'] ?? $product_id;

		$invoice_id = is_array( $invoice ) ? $invoice['id'] : $invoice;

		$price_tokens   = $this->helpers->tokens->hydrate_price_tokens( $price );
		$product_tokens = $this->helpers->tokens->hydrate_product_tokens( $product );
		$invoice_tokens = $this->helpers->tokens->hydrate_invoice_tokens( $invoice_id );

		$customer = $subscription['customer'];

		$customer_tokens = $this->helpers->tokens->hydrate_customer_tokens( $customer );

		$metadata_keys   = json_decode( $trigger['meta']['METADATA'], true );
		$metadata_tokens = $this->helpers->tokens->hydrate_metadata_tokens( $metadata_keys, $subscription, 'METADATA' );

		$tokens = array_merge(
			$price_tokens,
			$product_tokens,
			$customer_tokens,
			$invoice_tokens,
			$metadata_tokens
		);

		return $tokens;
	}
}
