<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Stripe;

/**
 * Class Subscription_Payment_Failed
 *
 * @package Uncanny_Automator
 *
 * @property Stripe_App_Helpers $helpers
 * @property Stripe_Api_Caller $api
 */
class Subscription_Payment_Failed extends \Uncanny_Automator\Recipe\App_Trigger {

	/**
	 * Static trigger definition for lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'SUB_PAYMENT_FAILED', 'STRIPE' )
			->trigger_meta( 'PRICE_ID' )
			->trigger_type( 'anonymous' )
			->hook( Stripe_Webhooks::INVOICE_ITEM_PAYMENT_FAILED_ACTION, 10, 3 );
	}

	/**
	 * Register the trigger's integration, code, meta, type, sentences, and webhook action.
	 *
	 * @return void
	 */
	public function setup_trigger() {
		$this->set_is_login_required( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->trigger_code, 'integration/stripe/' ) );
		$this->set_readable_sentence( esc_html_x( '{{A subscription}} payment fails', 'Stripe', 'uncanny-automator' ) );
		// translators: %1$s is the subscription product name
		$this->set_sentence( sprintf( esc_html_x( '{{A subscription:%1$s}} payment fails', 'Stripe', 'uncanny-automator' ), $this->get_trigger_meta() ) );
	}

	/**
	 * Build the price selector and the subscription metadata repeater fields.
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
			'remote_data' => $this->helpers->remote_data_load_config( 'recurring_prices' ),
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
			'add_row_button'    => esc_html_x( 'Add a key', 'Stripe', 'uncanny-automator' ),
			'remove_row_button' => esc_html_x( 'Remove key', 'Stripe', 'uncanny-automator' ),
		);

		return array(
			$products,
			$metadata,
		);
	}


	/**
	 * Assemble the price, product, customer, invoice and configured metadata token definitions
	 * exposed by this trigger.
	 *
	 * @param array $trigger The trigger's configuration, including saved METADATA meta.
	 * @param array $tokens  The tokens already registered for the trigger.
	 *
	 * @return array The merged token definitions.
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
	 * Confirm the failed invoice line item matches the selected price (or any price).
	 *
	 * @param array $trigger   The trigger's configuration, including the selected PRICE_ID meta.
	 * @param array $hook_args The hook arguments: the line item, invoice, and webhook request.
	 *
	 * @return bool True when the line item's price matches the trigger's selection, false otherwise.
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
	 * Resolve the subscription from the invoice or line item, fetch it from the API, and populate
	 * the price, product, customer, invoice and metadata tokens from it.
	 *
	 * @param array $trigger   The trigger's configuration, including saved METADATA meta.
	 * @param array $hook_args The hook arguments: the line item, invoice, and webhook request.
	 *
	 * @return array The hydrated token values keyed by token id.
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $line_item, $invoice, $request ) = $hook_args;

		// Extract price and product IDs from line item (handles multiple structures)
		$price_id   = $this->helpers->get_line_item_price_id( $line_item );
		$product_id = $this->helpers->get_line_item_product_id( $line_item );

		// Get subscription from invoice or parent details
		$subscription_id = null;
		if ( ! empty( $invoice['subscription'] ) ) {
			$subscription_id = $invoice['subscription'];
		} elseif ( ! empty( $line_item['parent']['subscription_item_details']['subscription'] ) ) {
			$subscription_id = $line_item['parent']['subscription_item_details']['subscription'];
		}

		// Fetch full subscription data to get expanded price/product/customer objects
		$response = $this->api->get_subscription( $subscription_id );

		$subscription = $response['data']['subscription'];

		// Get price and product from subscription items (expanded objects)
		$price = $subscription['items']['data'][0]['price'] ?? array( 'id' => $price_id );

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
