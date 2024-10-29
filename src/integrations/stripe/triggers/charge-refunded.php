<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Stripe;

/**
 * Class Charge_Refunded
 *
 * @package Uncanny_Automator
 */
class Charge_Refunded extends \Uncanny_Automator\Recipe\Trigger {

	protected $helpers;

	/**
	 * Trigger code.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'CHARGE_REFUNDED';

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
				esc_attr__( 'A charge is refunded', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		// Non-active state sentence to show

		$this->set_readable_sentence( esc_attr__( 'A charge is refunded', 'uncanny-automator' ) );

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
			'tokenId'   => 'CHARGE_ID',
			'tokenName' => _x( 'Charge ID', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'CURRENCY',
			'tokenName' => _x( 'Currency', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'AMOUNT',
			'tokenName' => _x( 'Amount', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'int',
		);

		$tokens[] = array(
			'tokenId'   => 'PAYMENT_INTENT',
			'tokenName' => _x( 'Payment intent', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'CUSTOMER_ID',
			'tokenName' => _x( 'Customer ID', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'PAYMENT_METHOD',
			'tokenName' => _x( 'Payment method', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'INVOICE_ID',
			'tokenName' => _x( 'Invoice ID', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'RECEIPT_URL',
			'tokenName' => _x( 'Receipt URL', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$billing_tokens = $this->helpers->billing_tokens_definition();

		return array_merge( $tokens, $billing_tokens );
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
		if ( 'charge.refunded' !== $event['type'] ) {
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

		$charge = $event['data']['object'];

		$tokens = array(
			'CHARGE_ID'      => empty( $charge['id'] ) ? '' : $charge['id'],
			'CURRENCY'       => empty( $charge['currency'] ) ? '' : $charge['currency'],
			'AMOUNT'         => empty( $charge['amount_refunded'] ) ? '' : $this->helpers->format_amount( $charge['amount'] ),
			'PAYMENT_INTENT' => empty( $charge['payment_intent'] ) ? '' : $charge['payment_intent'],
			'CUSTOMER_ID'    => empty( $charge['customer'] ) ? '' : $charge['customer'],
			'PAYMENT_METHOD' => empty( $charge['payment_method'] ) ? '' : $charge['payment_method'],
			'INVOICE_ID'     => empty( $charge['invoice'] ) ? '' : $charge['invoice'],
			'RECEIPT_URL'    => empty( $charge['receipt_url'] ) ? '' : $charge['receipt_url'],
		);

		$billing_tokens = $this->helpers->hydrate_billing_tokens( $charge );

		return array_merge( $tokens, $billing_tokens );
	}
}
