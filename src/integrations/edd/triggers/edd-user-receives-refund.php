<?php

namespace Uncanny_Automator\Integrations\Easy_Digital_Downloads;

use Uncanny_Automator\Integrations\Easy_Digital_Downloads\Dispatchers\Edd_Refund_Dispatcher;
use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Edd_User_Receives_Refund
 *
 * Fires when a customer is refunded, on any gateway and whether the refund was made on the EDD
 * order screen or in Stripe or PayPal. The refund type (full or partial) and the amount are tokens.
 * Edd_Refund_Dispatcher turns EDD's three refund paths into one event.
 *
 * The run belongs to the order's customer (record owner, R1), which is bound with no one signed in.
 * A guest order has no customer and is ignored.
 *
 * @package Uncanny_Automator\Integrations\Easy_Digital_Downloads
 * @since 8.1.0
 *
 * @property Edd_Helpers $item_helpers
 */
class Edd_User_Receives_Refund extends Trigger {

	/**
	 * Trigger definition.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'EDD_USER_RECEIVES_REFUND', 'EDD' )
			->trigger_meta( 'EDDREFUNDGATEWAY' )
			->hook( 'automator_edd_order_refunded', 10, 4 )
			->dispatcher( Edd_Refund_Dispatcher::class );
	}

	/**
	 * Set up the trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_is_login_required( false );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: payment gateway
				esc_html_x( 'A user receives a refund for an order paid with {{a payment gateway:%1$s}}', 'Easy Digital Downloads', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'A user receives a refund for an order paid with {{a payment gateway}}', 'Easy Digital Downloads', 'uncanny-automator' ) );
	}

	/**
	 * Options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Payment gateway', 'Easy Digital Downloads', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => $this->item_helpers->get_refund_gateway_options(),
				'relevant_tokens' => array(),
			),
		);
	}

	/**
	 * Validate the trigger and bind the order's customer.
	 *
	 * @param array $trigger   The trigger.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		$order_id = isset( $hook_args[0] ) ? absint( $hook_args[0] ) : 0;
		$order    = $order_id > 0 && function_exists( 'edd_get_order' ) ? edd_get_order( $order_id ) : false;

		if ( empty( $order ) ) {
			return false;
		}

		$selected = (string) ( $trigger['meta'][ $this->get_trigger_meta() ] ?? '-1' );

		if ( '-1' !== $selected && (string) $order->gateway !== $selected ) {
			return false;
		}

		$user_id = absint( $order->user_id );

		if ( 0 === $user_id || ! automator_can_bind_user( $user_id ) ) {
			return false;
		}

		$this->set_user_id( $user_id );

		return true;
	}

	/**
	 * Define tokens.
	 *
	 * @param array $trigger The trigger.
	 * @param array $tokens  The tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge( $tokens, $this->item_helpers->refund_tokens() );
	}

	/**
	 * Hydrate tokens.
	 *
	 * @param array $trigger   The trigger.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		return $this->item_helpers->refund_token_values(
			isset( $hook_args[0] ) ? $hook_args[0] : 0,
			isset( $hook_args[1] ) ? $hook_args[1] : 0,
			isset( $hook_args[2] ) ? $hook_args[2] : 'partial',
			isset( $hook_args[3] ) ? $hook_args[3] : ''
		);
	}
}
