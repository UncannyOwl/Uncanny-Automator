<?php

namespace Uncanny_Automator\Integrations\Fluent_Cart;

use Uncanny_Automator\Recipe\Action;

/**
 * Class FLUENT_CART_SET_ORDER_STATUS
 *
 * @package Uncanny_Automator\Integrations\Fluent_Cart
 *
 * @property Fluent_Cart_Helpers $item_helpers
 */
class Fluent_Cart_Set_Order_Status extends Action {

	/**
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'FLUENT_CART' );
		$this->set_action_code( 'FLUENT_CART_SET_ORDER_STATUS' );
		$this->set_action_meta( 'FLUENT_CART_ORDER' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				/* translators: %1$s: Order ID, %2$s: Status */
				esc_html_x( "Set {{an order:%1\$s}} to {{a status:%2\$s}}", 'FluentCart', 'uncanny-automator' ),
				$this->get_action_meta(),
				'FLUENT_CART_ORDER_STATUS:' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( "Set {{an order}} to {{a status}}", 'FluentCart', 'uncanny-automator' ) );
	}

	/**
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_action_meta(),
				'label'           => esc_html_x( 'Order ID', 'FluentCart', 'uncanny-automator' ),
				'input_type'      => 'text',
				'required'        => true,
				'supports_tokens' => true,
			),
			$this->item_helpers->order_status_field_strict( 'FLUENT_CART_ORDER_STATUS' ),
		);
	}

	/**
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'FLUENT_CART_ORDER_ID'   => array(
				'name' => esc_html_x( 'Order ID', 'FluentCart', 'uncanny-automator' ),
				'type' => 'int',
			),
			'FLUENT_CART_OLD_STATUS' => array(
				'name' => esc_html_x( 'Old order status', 'FluentCart', 'uncanny-automator' ),
				'type' => 'text',
			),
			'FLUENT_CART_NEW_STATUS' => array(
				'name' => esc_html_x( 'New order status', 'FluentCart', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$order_id   = absint( isset( $parsed[ $this->get_action_meta() ] ) ? $parsed[ $this->get_action_meta() ] : 0 );
		$new_status = isset( $parsed['FLUENT_CART_ORDER_STATUS'] ) ? sanitize_text_field( $parsed['FLUENT_CART_ORDER_STATUS'] ) : '';

		if ( 0 === $order_id || '' === $new_status ) {
			$this->add_log_error( esc_html_x( 'Missing order ID or status.', 'FluentCart', 'uncanny-automator' ) );
			return false;
		}

		if ( ! class_exists( '\FluentCart\App\Models\Order' ) || ! class_exists( '\FluentCart\App\Helpers\StatusHelper' ) ) {
			$this->add_log_error( esc_html_x( 'FluentCart is not available.', 'FluentCart', 'uncanny-automator' ) );
			return false;
		}

		// StatusHelper::changeOrderStatus() performs no validation of its own — it
		// writes whatever string it is handed straight to the column and always
		// reports success, so an unrecognised status would silently corrupt the
		// order. Reject it here instead. Skipped when the status list is
		// unavailable, since we then have nothing to validate against.
		$valid_statuses = $this->item_helpers->get_order_statuses();

		if ( ! empty( $valid_statuses ) && ! array_key_exists( $new_status, $valid_statuses ) ) {
			$this->add_log_error(
				sprintf(
					/* translators: %s: Order status */
					esc_html_x( '"%s" is not a valid FluentCart order status.', 'FluentCart', 'uncanny-automator' ),
					$new_status
				)
			);
			return false;
		}

		$order = \FluentCart\App\Models\Order::query()->find( $order_id );

		if ( empty( $order ) ) {
			$this->add_log_error(
				sprintf(
					/* translators: %d: Order ID */
					esc_html_x( 'Order #%d was not found.', 'FluentCart', 'uncanny-automator' ),
					$order_id
				)
			);
			return false;
		}

		$old_status = $order->status;

		// Preserve the order's existing payment fields — only the status changes.
		$status_helper = new \FluentCart\App\Helpers\StatusHelper();
		$status_helper->setOrder( $order )->changeOrderStatus(
			$new_status,
			$order->payment_status,
			$order->payment_method_title,
			$order->payment_method
		);

		$this->hydrate_tokens(
			array(
				'FLUENT_CART_ORDER_ID'   => $order_id,
				'FLUENT_CART_OLD_STATUS' => $old_status,
				'FLUENT_CART_NEW_STATUS' => $new_status,
			)
		);

		return true;
	}
}
