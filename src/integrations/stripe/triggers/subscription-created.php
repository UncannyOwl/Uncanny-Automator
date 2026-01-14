<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Stripe;

/**
 * Class Subscription_Created
 *
 * @package Uncanny_Automator
 *
 * @property Stripe_App_Helpers $helpers
 * @property Stripe_Api_Caller $api
 */
class Subscription_Created extends \Uncanny_Automator\Recipe\App_Trigger {

	/**
	 * Trigger code.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'SUB_CREATED';

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
		$this->set_sentence( sprintf( esc_html_x( '{{A subscription:%1$s}} is created', 'Stripe', 'uncanny-automator' ), $this->get_trigger_meta() ) );

		// Non-active state sentence to show

		$this->set_readable_sentence( esc_html_x( '{{A subscription}} is created', 'Stripe', 'uncanny-automator' ) );

		// Which do_action() fires this trigger.
		$this->add_action( Stripe_Webhooks::INCOMING_WEBHOOK_ACTION );

		$this->set_action_args_count( 1 );
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

		list( $event ) = $hook_args;

		// Check that the event has the correct type
		if ( 'customer.subscription.created' !== $event['type'] ) {
			return false;
		}

		$selected_price = $trigger['meta'][ $this->get_trigger_meta() ];
		$subscription   = $event['data']['object'];

		// Use helper method to check if subscription contains the selected price
		// This method supports both modern 'price' and legacy 'plan' objects
		// and handles multi-item subscriptions
		return $this->helpers->subscription_contains_price( $subscription, $selected_price );
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

		list( $event ) = $hook_args;

		$subscription_id = $event['data']['object']['id'];

		$response = $this->api->get_subscription( $subscription_id );

		$subscription = $response['data']['subscription'];

		$price   = $subscription['items']['data'][0]['price'];
		$product = $subscription['plan']['product'];
		$invoice = $subscription['latest_invoice'];

		$price_tokens   = $this->helpers->tokens->hydrate_price_tokens( $price );
		$product_tokens = $this->helpers->tokens->hydrate_product_tokens( $product );
		$invoice_tokens = $this->helpers->tokens->hydrate_invoice_tokens( $invoice );

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
