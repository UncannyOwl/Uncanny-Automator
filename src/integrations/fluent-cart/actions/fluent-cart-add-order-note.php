<?php

namespace Uncanny_Automator\Integrations\Fluent_Cart;

use Uncanny_Automator\Recipe\Action;

/**
 * Class FLUENT_CART_ADD_ORDER_NOTE
 *
 * @package Uncanny_Automator\Integrations\Fluent_Cart
 *
 * @property Fluent_Cart_Helpers $item_helpers
 */
class Fluent_Cart_Add_Order_Note extends Action {

	/**
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'FLUENT_CART' );
		$this->set_action_code( 'FLUENT_CART_ADD_ORDER_NOTE' );
		$this->set_action_meta( 'FLUENT_CART_ORDER' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				/* translators: %1$s: Order ID */
				esc_html_x( 'Add a note to {{an order:%1$s}}', 'FluentCart', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Add a note to {{an order}}', 'FluentCart', 'uncanny-automator' ) );
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
			array(
				'option_code'     => 'FLUENT_CART_NOTE',
				'label'           => esc_html_x( 'Note', 'FluentCart', 'uncanny-automator' ),
				'input_type'      => 'textarea',
				'required'        => true,
				'supports_tokens' => true,
			),
		);
	}

	/**
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'FLUENT_CART_ORDER_ID' => array(
				'name' => esc_html_x( 'Order ID', 'FluentCart', 'uncanny-automator' ),
				'type' => 'int',
			),
			'FLUENT_CART_NOTE'     => array(
				'name' => esc_html_x( 'Note', 'FluentCart', 'uncanny-automator' ),
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

		$order_id = absint( isset( $parsed[ $this->get_action_meta() ] ) ? $parsed[ $this->get_action_meta() ] : 0 );
		$note     = isset( $parsed['FLUENT_CART_NOTE'] ) ? wp_kses_post( $parsed['FLUENT_CART_NOTE'] ) : '';

		if ( 0 === $order_id || '' === trim( $note ) ) {
			$this->add_log_error( esc_html_x( 'Missing order ID or note.', 'FluentCart', 'uncanny-automator' ) );
			return false;
		}

		if ( ! class_exists( '\FluentCart\App\Models\Order' ) || ! function_exists( 'fluent_cart_add_log' ) ) {
			$this->add_log_error( esc_html_x( 'FluentCart is not available.', 'FluentCart', 'uncanny-automator' ) );
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

		fluent_cart_add_log(
			esc_html_x( 'Note', 'FluentCart', 'uncanny-automator' ),
			$note,
			'info',
			array(
				'module_name' => 'order',
				'module_id'   => $order_id,
				'log_type'    => 'activity',
				'user_id'     => $user_id,
			)
		);

		$this->hydrate_tokens(
			array(
				'FLUENT_CART_ORDER_ID' => $order_id,
				'FLUENT_CART_NOTE'     => $note,
			)
		);

		return true;
	}
}
