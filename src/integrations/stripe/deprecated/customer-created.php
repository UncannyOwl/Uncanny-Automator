<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Stripe;

/**
 * Class Customer_Created
 *
 * @package Uncanny_Automator
 */
class Customer_Created_Deprecated extends \Uncanny_Automator\Recipe\Trigger {

	protected $helpers;

	/**
	 * Trigger code.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'CUSTOMER_CREATED';

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
				esc_attr__( 'A customer is created', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		// Non-active state sentence to show
		$this->set_readable_sentence( esc_attr__( 'A customer is created', 'uncanny-automator' ) );

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
			'tokenId'   => 'EMAIL',
			'tokenName' => _x( 'Email', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'email',
		);

		$tokens[] = array(
			'tokenId'   => 'ADDRESS_CITY',
			'tokenName' => _x( 'Address city', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'ADDRESS_LINE1',
			'tokenName' => _x( 'Address line 1', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'ADDRESS_LINE2',
			'tokenName' => _x( 'Address line 2', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'ADDRESS_POSTAL_CODE',
			'tokenName' => _x( 'Address postal code', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'ADDRESS_STATE',
			'tokenName' => _x( 'Address state', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'BALANCE',
			'tokenName' => _x( 'Balance', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'CREATED',
			'tokenName' => _x( 'Date and time created', 'Stripe', 'uncanny-automator' ),
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
			'tokenId'   => 'DISCOUNT',
			'tokenName' => _x( 'Discount', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'INVOICE_PREFIX',
			'tokenName' => _x( 'Invoice prefix', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'NAME',
			'tokenName' => _x( 'Name', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'NEXT_INVOICE_SEQUENCE',
			'tokenName' => _x( 'Next invoice sequence', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'PHONE',
			'tokenName' => _x( 'Phone', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'SHIPPING_NAME',
			'tokenName' => _x( 'Shipping name', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'SHIPPING_ADDRESS_LINE1',
			'tokenName' => _x( 'Shipping address line 1', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'SHIPPING_ADDRESS_LINE2',
			'tokenName' => _x( 'Shipping address line 2', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'SHIPPING_ADDRESS_CITY',
			'tokenName' => _x( 'Shipping address city', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'SHIPPING_ADDRESS_POSTAL_CODE',
			'tokenName' => _x( 'Shipping address postal code', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'SHIPPING_ADDRESS_STATE',
			'tokenName' => _x( 'Shipping address state', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'SHIPPING_PHONE',
			'tokenName' => _x( 'Shipping phone', 'Stripe', 'uncanny-automator' ),
			'tokenType' => 'string',
		);

		$tokens[] = array(
			'tokenId'   => 'TAX_EXEMPT',
			'tokenName' => _x( 'Tax exempt', 'Stripe', 'uncanny-automator' ),
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

		$tokens = array(
			'ID'                           => empty( $customer['id'] ) ? '' : $customer['id'],
			'EMAIL'                        => empty( $customer['email'] ) ? '' : $customer['email'],
			'ADDRESS_CITY'                 => empty( $customer['address']['city'] ) ? '' : $customer['address']['city'],
			'ADDRESS_LINE1'                => empty( $customer['address']['line1'] ) ? '' : $customer['address']['line1'],
			'ADDRESS_LINE2'                => empty( $customer['address']['line2'] ) ? '' : $customer['address']['line2'],
			'ADDRESS_POSTAL_CODE'          => empty( $customer['address']['postal_code'] ) ? '' : $customer['address']['postal_code'],
			'ADDRESS_STATE'                => empty( $customer['address']['state'] ) ? '' : $customer['address']['state'],
			'BALANCE'                      => empty( $customer['balance'] ) ? '' : $this->helpers->format_amount( $customer['balance'] ),
			'CREATED'                      => empty( $customer['created'] ) ? '' : $this->helpers->format_date( $customer['created'] ),
			'CURRENCY'                     => empty( $customer['currency'] ) ? '' : $customer['currency'],
			'DESCRIPTION'                  => empty( $customer['description'] ) ? '' : $customer['description'],
			'DISCOUNT'                     => empty( $customer['discount'] ) ? '' : $customer['discount'],
			'INVOICE_PREFIX'               => empty( $customer['invoice_prefix'] ) ? '' : $customer['invoice_prefix'],
			'NAME'                         => empty( $customer['name'] ) ? '' : $customer['name'],
			'NEXT_INVOICE_SEQUENCE'        => empty( $customer['next_invoice_sequence'] ) ? '' : $customer['next_invoice_sequence'],
			'PHONE'                        => empty( $customer['phone'] ) ? '' : $customer['phone'],
			'SHIPPING_NAME'                => empty( $customer['shipping']['name'] ) ? '' : $customer['shipping']['name'],
			'SHIPPING_ADDRESS_LINE1'       => empty( $customer['shipping']['address']['line1'] ) ? '' : $customer['shipping']['address']['line1'],
			'SHIPPING_ADDRESS_LINE2'       => empty( $customer['shipping']['address']['line2'] ) ? '' : $customer['shipping']['address']['line2'],
			'SHIPPING_ADDRESS_CITY'        => empty( $customer['shipping']['address']['city'] ) ? '' : $customer['shipping']['address']['city'],
			'SHIPPING_ADDRESS_POSTAL_CODE' => empty( $customer['shipping']['address']['postal_code'] ) ? '' : $customer['shipping']['address']['postal_code'],
			'SHIPPING_ADDRESS_STATE'       => empty( $customer['shipping']['address']['state'] ) ? '' : $customer['shipping']['address']['state'],
			'SHIPPING_PHONE'               => empty( $customer['shipping']['phone'] ) ? '' : $customer['shipping']['phone'],
			'TAX_EXEMPT'                   => empty( $customer['tax_exempt'] ) ? '' : $customer['tax_exempt'],
		);

		if ( ! empty( $tokens['created'] ) ) {
			$tokens['created'] = date_i18n( get_option( 'date_format' ), $tokens['created'] );
		}

		return $tokens;
	}
}
