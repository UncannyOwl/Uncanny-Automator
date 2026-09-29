<?php

namespace Uncanny_Automator\Integrations\Cartflows;

use Uncanny_Automator\Integrations\Cartflows\Dispatchers\Cartflows_Checkout_Dispatcher;
use Uncanny_Automator\Integrations\Cartflows\Tokens\Cartflows_Tokens;
use WC_Order;

/**
 * Class Cartflows_User_Purchases_Product
 *
 * Fires when a specific product bought through a CartFlows checkout step is paid
 * for. Shares Cartflows_Checkout_Dispatcher's normalized event with the plain
 * checkout trigger — so it inherits the same payment resolution and one-per-order
 * guard — and adds a line-item filter on top.
 *
 * Matches on both product ID and variation ID, so selecting a variation matches
 * only that variation while selecting the parent matches every variation of it.
 *
 * @package Uncanny_Automator\Integrations\Cartflows
 *
 * @property Cartflows_Helpers $item_helpers
 */
class Cartflows_User_Purchases_Product extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 */
	public static function definition() {
		return self::new_definition( 'CARTFLOWS_PRODUCT_PURCHASED', 'CARTFLOWS' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'CARTFLOWS_PRODUCT' )
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
				/* translators: 1: Product, 2: Flow */
				esc_html_x( '{{A product:%1$s}} is purchased in {{a flow:%2$s}}', 'CartFlows', 'uncanny-automator' ),
				$this->get_trigger_meta(),
				'CARTFLOWS_FLOW:' . $this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( '{{A product}} is purchased in {{a flow}}', 'CartFlows', 'uncanny-automator' ) );
	}

	/**
	 * WooCommerce is required.
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
				'label'           => esc_html_x( 'Product', 'CartFlows', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'products' ),
			),
			array(
				'option_code'     => 'CARTFLOWS_FLOW',
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

		$selected_flow = (string) ( $trigger['meta']['CARTFLOWS_FLOW'] ?? Cartflows_Helpers::ANY );

		if ( Cartflows_Helpers::ANY !== $selected_flow && absint( $selected_flow ) !== $flow_id ) {
			return false;
		}

		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;

		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		$selected_product = (string) ( $trigger['meta'][ $this->get_trigger_meta() ] ?? Cartflows_Helpers::ANY );

		if ( ! $this->order_has_product( $order, $selected_product ) ) {
			return false;
		}

		$user_id = 0 !== absint( $order->get_customer_id() )
			? absint( $order->get_customer_id() )
			: absint( get_current_user_id() );

		$this->set_user_id( $user_id );

		return true;
	}

	/**
	 * Whether the order contains the selected product.
	 *
	 * @param WC_Order $order    The order.
	 * @param string   $selected The selected product ID, or the "Any" sentinel.
	 *
	 * @return bool
	 */
	private function order_has_product( $order, $selected ) {

		$items = $order->get_items();

		if ( Cartflows_Helpers::ANY === $selected ) {
			return ! empty( $items );
		}

		$selected = absint( $selected );

		foreach ( $items as $item ) {
			if ( absint( $item->get_product_id() ) === $selected || absint( $item->get_variation_id() ) === $selected ) {
				return true;
			}
		}

		return false;
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
