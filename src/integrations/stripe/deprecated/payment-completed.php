<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Stripe;

/**
 * Class Payment_Completed
 *
 * @package Uncanny_Automator
 */
class Payment_Completed extends \Uncanny_Automator\Recipe\Trigger {

	protected $helpers;

	/**
	 * Trigger code.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'PAYMENT_COMPLETED';

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */

	public function setup_trigger() {

		$this->helpers = array_shift( $this->dependencies );

		$this->set_is_deprecated( true );

		$this->set_integration( 'STRIPE' );

		$this->set_trigger_code( self::TRIGGER_CODE );

		$this->set_is_login_required( false );

		$this->set_trigger_type( 'anonymous' );

		$this->set_support_link( Automator()->get_author_support_link( $this->trigger_code, 'integration/stripe/' ) );

		$this->set_sentence(
			sprintf(
			/* Translators: Trigger sentence */
				esc_attr__( 'A payment is completed', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		// Non-active state sentence to show

		$this->set_readable_sentence( esc_attr__( 'A payment is completed', 'uncanny-automator' ) );

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
			'tokenId'   => 'AMOUNT',
			'tokenName' => _x( 'Amount', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'CURRENCY',
			'tokenName' => _x( 'Currency', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'DESCRIPTION',
			'tokenName' => _x( 'Description', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'CUSTOMER_ID',
			'tokenName' => _x( 'Customer ID', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'INVOICE_ID',
			'tokenName' => _x( 'Invoice ID', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'PAYMENT_METHOD',
			'tokenName' => _x( 'Payment method', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'PAYMENT_STATUS',
			'tokenName' => _x( 'Payment status', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'RECEIPT_URL',
			'tokenName' => _x( 'Receipt URL', 'Stripe', 'uncanny-automator' ),
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
		if ( 'payment_intent.succeeded' !== $event['type'] ) {
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

		$payment_intent = $event['data']['object'];

		$tokens = array(
			'ID'             => empty( $payment_intent['id'] ) ? '' : $payment_intent['id'],
			'AMOUNT'         => empty( $payment_intent['amount'] ) ? '' : $this->helpers->format_amount( $payment_intent['amount'] ),
			'CURRENCY'       => empty( $payment_intent['currency'] ) ? '' : $payment_intent['currency'],
			'DESCRIPTION'    => empty( $payment_intent['description'] ) ? '' : $payment_intent['description'],
			'CUSTOMER_ID'    => empty( $payment_intent['customer'] ) ? '' : $payment_intent['customer'],
			'INVOICE_ID'     => empty( $payment_intent['invoice'] ) ? '' : $payment_intent['invoice'],
			'PAYMENT_METHOD' => empty( $payment_intent['payment_method'] ) ? '' : $payment_intent['payment_method'],
			'PAYMENT_STATUS' => empty( $payment_intent['status'] ) ? '' : $payment_intent['status'],
			'RECEIPT_URL'    => empty( $payment_intent['charges']['data'][0]['receipt_url'] ) ? '' : $payment_intent['charges']['data'][0]['receipt_url'],
		);

		return $tokens;
	}
}
