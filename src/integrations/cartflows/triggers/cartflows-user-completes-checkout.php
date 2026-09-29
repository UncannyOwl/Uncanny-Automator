<?php

namespace Uncanny_Automator\Integrations\Cartflows;

use Uncanny_Automator\Integrations\Cartflows\Dispatchers\Cartflows_Checkout_Dispatcher;
use Uncanny_Automator\Integrations\Cartflows\Tokens\Cartflows_Tokens;
use WC_Order;

/**
 * Class Cartflows_User_Completes_Checkout
 *
 * Fires when an order placed through a CartFlows checkout step is paid for.
 * CartFlows emits no hook of its own for this, so the trigger listens on the
 * normalized
 * `automator_cartflows_checkout_completed( int $order_id, int $flow_id, int $checkout_id )`
 * action from Cartflows_Checkout_Dispatcher, which resolves payment across every
 * gateway type, fires once per order, and excludes optin submissions.
 *
 * Anonymous rather than user-scoped: funnels routinely take guest checkouts, and
 * the buyer is resolved from the order rather than the session. When the order
 * belongs to a registered customer that user is bound to the run.
 *
 * @package Uncanny_Automator\Integrations\Cartflows
 *
 * @property Cartflows_Helpers $item_helpers
 */
class Cartflows_User_Completes_Checkout extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 */
	public static function definition() {
		return self::new_definition( 'CARTFLOWS_CHECKOUT_COMPLETED', 'CARTFLOWS' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'CARTFLOWS_FLOW' )
			->hook( 'automator_cartflows_checkout_completed', 10, 3 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_is_login_required( false );
		$this->set_sentence(
			sprintf(
				/* translators: 1: CartFlows flow */
				esc_html_x( 'A checkout is completed in {{a flow:%1$s}}', 'CartFlows', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'A checkout is completed in {{a flow}}', 'CartFlows', 'uncanny-automator' ) );
	}

	/**
	 * WooCommerce is required — CartFlows checkout steps are WooCommerce
	 * checkouts, and this trigger reads a WooCommerce order.
	 *
	 * @return bool
	 */
	public function requirements_met() {
		return $this->item_helpers->woocommerce_active();
	}

	/**
	 * Trigger options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Flow', 'CartFlows', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'flows' ),
			),
		);
	}

	/**
	 * Validate trigger and bind the buyer.
	 *
	 * @param array $trigger   The trigger.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		$order_id = isset( $hook_args[0] ) ? absint( $hook_args[0] ) : 0;
		$flow_id  = isset( $hook_args[1] ) ? absint( $hook_args[1] ) : 0;

		if ( 0 === $order_id || 0 === $flow_id ) {
			return false;
		}

		$selected = (string) ( $trigger['meta'][ $this->get_trigger_meta() ] ?? Cartflows_Helpers::ANY );

		if ( Cartflows_Helpers::ANY !== $selected && absint( $selected ) !== $flow_id ) {
			return false;
		}

		$this->set_user_id( $this->resolve_user_id( $order_id ) );

		return true;
	}

	/**
	 * Resolve the buyer: the order's customer, falling back to whoever is logged
	 * in. Genuine guest checkouts resolve to 0, which anonymous recipes accept.
	 *
	 * @param int $order_id The order ID.
	 *
	 * @return int
	 */
	private function resolve_user_id( $order_id ) {

		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;

		if ( $order instanceof WC_Order && 0 !== absint( $order->get_customer_id() ) ) {
			return absint( $order->get_customer_id() );
		}

		return absint( get_current_user_id() );
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
		return array_merge( $tokens, ( new Cartflows_Tokens() )->order_tokens() );
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

		return ( new Cartflows_Tokens() )->hydrate_order_tokens(
			isset( $hook_args[0] ) ? absint( $hook_args[0] ) : 0,
			isset( $hook_args[1] ) ? absint( $hook_args[1] ) : 0,
			isset( $hook_args[2] ) ? absint( $hook_args[2] ) : 0
		);
	}
}
