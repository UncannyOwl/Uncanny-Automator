<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Stripe;

/**
 * Class Customer_Created
 *
 * @package Uncanny_Automator
 *
 * @property Stripe_App_Helpers $helpers
 */
class Customer_Created extends \Uncanny_Automator\Recipe\App_Trigger {

	/**
	 * Static trigger definition for lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'CUST_CREATED', 'STRIPE' )
			->trigger_type( 'anonymous' )
			->hook( Stripe_Webhooks::INCOMING_WEBHOOK_ACTION );
	}

	/**
	 * Register the trigger's integration, code, type, sentences, and webhook action.
	 *
	 * @return void
	 */
	public function setup_trigger() {
		$this->set_is_login_required( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->trigger_code, 'integration/stripe/' ) );
		$this->set_readable_sentence( esc_html_x( 'A customer is created', 'Stripe', 'uncanny-automator' ) );
		$this->set_sentence( esc_html_x( 'A customer is created', 'Stripe', 'uncanny-automator' ) );
	}

	/**
	 * Append the customer token definitions to the trigger's token list.
	 *
	 * @param array $trigger The trigger's configuration.
	 * @param array $tokens  The tokens already registered for the trigger.
	 *
	 * @return array The merged token definitions.
	 */
	public function define_tokens( $trigger, $tokens ) {

		$customer_tokens = $this->helpers->tokens->customer_tokens();

		return array_merge(
			$tokens,
			$customer_tokens
		);
	}

	/**
	 * Confirm the incoming webhook event is a Stripe customer.created event.
	 *
	 * @param array $trigger   The trigger's configuration.
	 * @param array $hook_args The hook arguments, where the first element is the Stripe event.
	 *
	 * @return bool True when the event type is customer.created, false otherwise.
	 */
	public function validate( $trigger, $hook_args ) {

		list( $event ) = $hook_args;

		// Check that the event has the correct type
		if ( 'customer.created' !== $event['type'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Populate the customer tokens with values from the webhook event's customer object.
	 *
	 * @param array $trigger   The trigger's configuration.
	 * @param array $hook_args The hook arguments, where the first element is the Stripe event.
	 *
	 * @return array The hydrated token values keyed by token id.
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $event ) = $hook_args;

		$customer = $event['data']['object'];

		$customer_tokens = $this->helpers->tokens->hydrate_customer_tokens( $customer );

		return array_merge(
			$customer_tokens
		);
	}
}
