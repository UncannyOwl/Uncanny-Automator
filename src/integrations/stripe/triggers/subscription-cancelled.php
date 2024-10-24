<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Stripe;

/**
 * Class Subscription_Cancelled
 *
 * @package Uncanny_Automator
 */
class Subscription_Cancelled extends \Uncanny_Automator\Recipe\Trigger {

	protected $helpers;

	/**
	 * Trigger code.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'SUBSCRIPTION_CANCELLED';

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */

	public function setup_trigger() {

		$this->helpers = array_shift( $this->dependencies );

		$this->set_integration( 'STRIPE' );

		$this->set_trigger_code( self::TRIGGER_CODE );

		$this->set_is_login_required( false );

		$this->set_trigger_type( 'anonymous' );

		$this->set_support_link( Automator()->get_author_support_link( $this->trigger_code, 'integration/stripe/' ) );

		$this->set_sentence(
			sprintf(
			/* Translators: Trigger sentence */
				esc_attr__( 'A subscription is cancelled', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		// Non-active state sentence to show

		$this->set_readable_sentence( esc_attr__( 'A subscription is cancelled', 'uncanny-automator' ) );

		// Which do_action() fires this trigger.
		$this->add_action( Stripe_Webhook::INCOMING_WEBHOOK_ACTION );

		$this->set_action_args_count( 1 );
	}

	/**
	 * Returns the trigger's tokens.
	 *
	 * @return array
	 */

	public function define_tokens( $trigger, $tokens ) {

		$tokens[] = array(
			'tokenId'   => 'ID',
			'tokenName' => _x( 'ID', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'PRICE_ID',
			'tokenName' => _x( 'Price ID', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'AMOUNT',
			'tokenName' => _x( 'Amount', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'int',
		);

		$tokens[] = array(
			'tokenId'   => 'CURRENCY',
			'tokenName' => _x( 'Currency', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'PLAN_NAME',
			'tokenName' => _x( 'Plan name', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'PRODUCT_ID',
			'tokenName' => _x( 'Product ID', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'CUSTOMER_ID',
			'tokenName' => _x( 'Customer ID', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'INTERVAL',
			'tokenName' => _x( 'Interval', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'LATEST_INVOICE',
			'tokenName' => _x( 'Latest invoice', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
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
		if ( 'customer.subscription.deleted' !== $event['type'] ) {
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

		$subscription = $event['data']['object'];

		$plan = $subscription['plan'];

		$tokens = array(
			'ID'             => empty( $subscription['id'] ) ? '' : $subscription['id'],
			'PRICE_ID'       => empty( $plan['id'] ) ? '' : $plan['id'],
			'AMOUNT'         => empty( $plan['amount'] ) ? '' : $this->helpers->format_amount( $plan['amount'] ),
			'CURRENCY'       => empty( $plan['currency'] ) ? '' : $plan['currency'],
			'PLAN_NAME'      => empty( $plan['nickname'] ) ? '' : $plan['nickname'],
			'PRODUCT_ID'     => empty( $plan['product'] ) ? '' : $plan['product'],
			'CUSTOMER_ID'    => empty( $subscription['customer'] ) ? '' : $subscription['customer'],
			'INTERVAL'       => empty( $plan['interval'] ) ? '' : $plan['interval'],
			'LATEST_INVOICE' => empty( $subscription['latest_invoice'] ) ? '' : $subscription['latest_invoice'],
		);

		return $tokens;
	}
}
