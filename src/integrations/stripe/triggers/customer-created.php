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
	 * Trigger code.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'CUST_CREATED';

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function setup_trigger() {
		$this->set_integration( 'STRIPE' );
		$this->set_trigger_code( self::TRIGGER_CODE );
		$this->set_is_login_required( false );
		$this->set_trigger_type( 'anonymous' );
		$this->set_support_link( Automator()->get_author_support_link( $this->trigger_code, 'integration/stripe/' ) );
		$this->set_sentence( esc_html_x( 'A customer is created', 'Stripe', 'uncanny-automator' ) );

		// Non-active state sentence to show
		$this->set_readable_sentence( esc_html_x( 'A customer is created', 'Stripe', 'uncanny-automator' ) );

		// Which do_action() fires this trigger.
		$this->add_action( Stripe_Webhooks::INCOMING_WEBHOOK_ACTION );
		$this->set_action_args_count( 1 );
	}

	/**
	 * Returns the trigger's tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {

		$customer_tokens = $this->helpers->tokens->customer_tokens();

		return array_merge(
			$tokens,
			$customer_tokens
		);
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
		if ( 'customer.created' !== $event['type'] ) {
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

		list( $event ) = $hook_args;

		$customer = $event['data']['object'];

		$customer_tokens = $this->helpers->tokens->hydrate_customer_tokens( $customer );

		return array_merge(
			$customer_tokens
		);
	}
}
