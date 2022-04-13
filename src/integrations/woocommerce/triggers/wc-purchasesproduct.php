<?php

namespace Uncanny_Automator;

use WC_Order_Item_Product;

/**
 * Class WC_PURCHASESPRODUCT
 *
 * @package Uncanny_Automator
 */
class WC_PURCHASESPRODUCT {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WC';

	/**
	 * @var string
	 */
	private $trigger_code;
	/**
	 * @var string
	 */
	private $trigger_meta;
	/**
	 * @var string
	 */
	private $trigger_condition;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code      = 'WCPURCHASESPRODUCT';
		$this->trigger_meta      = 'WOOPRODUCT';
		$this->trigger_condition = 'TRIGGERCOND';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/woocommerce/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - WooCommerce */
			'sentence'            => sprintf( esc_attr__( 'A user {{completes, pays for, lands on a thank you page for:%3$s}} an order with {{a product:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES', $this->trigger_condition ),
			/* translators: Logged-in trigger - WooCommerce */
			'select_option_name'  => esc_attr__( 'A user {{completes, pays for, lands on a thank you page for}} an order with {{a product}}', 'uncanny-automator' ),
			'action'              => array(
				'woocommerce_order_status_completed',
				'woocommerce_thankyou',
				'woocommerce_payment_complete',
			),
			'priority'            => 99,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'payment_completed' ),
			'options_callback'    => array( $this, 'load_options' ),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * @return array
	 */
	public function load_options() {
		Automator()->helpers->recipe->woocommerce->options->load_options = true;

		$options            = Automator()->helpers->recipe->woocommerce->options->all_wc_products( esc_attr__( 'Product', 'uncanny-automator' ) );
		$options['options'] = array( '-1' => esc_attr__( 'Any product', 'uncanny-automator' ) ) + $options['options'];
		$trigger_condition  = Automator()->helpers->recipe->woocommerce->get_woocommerce_trigger_conditions( $this->trigger_condition );
		$options_array      = array(
			'options' => array(
				Automator()->helpers->recipe->options->number_of_times(),
				$options,
				$trigger_condition,
			),
		);

		return Automator()->utilities->keep_order_of_options( $options_array );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $order_id
	 */
	public function payment_completed( $order_id ) {
		if ( empty( $order_id ) || 0 === $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$user_id = $order->get_user_id();
		if ( 0 === $user_id ) {
			// Its a logged in recipe and
			// user ID is 0. Skip process
			return;
		}

		$recipes            = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_product   = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$trigger_condition  = Automator()->get->meta_from_recipes( $recipes, $this->trigger_condition );
		$matched_recipe_ids = array();
		$trigger_cond_ids   = array();

		if ( empty( $recipes ) ) {
			return;
		}

		if ( empty( $required_product ) ) {
			return;
		}

		if ( empty( $trigger_condition ) ) {
			return;
		}
		//Add where Product ID is set for trigger
		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];//return early for all products
				if ( ! isset( $trigger_condition[ $recipe_id ] ) ) {
					continue;
				}
				if ( ! isset( $trigger_condition[ $recipe_id ][ $trigger_id ] ) ) {
					continue;
				}
				if ( (string) current_action() === (string) $trigger_condition[ $recipe_id ][ $trigger_id ] ) {
					$trigger_cond_ids[] = $recipe_id;
				}
			}
		}

		if ( empty( $trigger_cond_ids ) ) {
			return;
		}

		if ( 'woocommerce_order_status_completed' === (string) current_action() ) {
			if ( 'completed' !== $order->get_status() ) {
				return;
			}
		}

		$items       = $order->get_items();
		$product_ids = array();
		/** @var WC_Order_Item_Product $item */
		foreach ( $items as $item ) {
			$product_ids[] = $item->get_product_id();
		}
		//Add where Product ID is set for trigger
		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				if ( ! in_array( $recipe_id, $trigger_cond_ids, false ) ) {
					continue;
				}
				$trigger_id = $trigger['ID'];//return early for all products
				if ( ! isset( $required_product[ $recipe_id ] ) ) {
					continue;
				}
				if ( ! isset( $required_product[ $recipe_id ][ $trigger_id ] ) ) {
					continue;
				}
				if ( intval( '-1' ) === intval( $required_product[ $recipe_id ][ $trigger_id ] ) || in_array( $required_product[ $recipe_id ][ $trigger_id ], $product_ids ) ) {
					$matched_recipe_ids[] = array(
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					);
				}
			}
		}

		if ( empty( $matched_recipe_ids ) ) {
			return;
		}

		foreach ( $matched_recipe_ids as $matched_recipe_id ) {
			$pass_args = array(
				'code'             => $this->trigger_code,
				'meta'             => $this->trigger_meta,
				'user_id'          => $user_id,
				'recipe_to_match'  => $matched_recipe_id['recipe_id'],
				'trigger_to_match' => $matched_recipe_id['trigger_id'],
				'ignore_post_id'   => true,
			);

			$args = Automator()->process->user->maybe_add_trigger_entry( $pass_args, false );

			//Adding an action to save order id in trigger meta
			do_action( 'uap_wc_trigger_save_meta', $order_id, $matched_recipe_id['recipe_id'], $args, 'product' );

			if ( $args ) {
				foreach ( $args as $result ) {
					if ( true === $result['result'] ) {
						Automator()->process->user->maybe_trigger_complete( $result['args'] );
					}
				}
			}
		}
	}
}
