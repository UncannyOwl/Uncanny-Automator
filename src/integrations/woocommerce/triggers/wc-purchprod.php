<?php

namespace Uncanny_Automator;

/**
 * Class WC_PURCHPROD
 * @package uncanny_automator
 */
class WC_PURCHPROD {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'WC';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'WCPURCHPROD';
		$this->trigger_meta = 'WOOPRODUCT';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		global $uncanny_automator;

		$options = $uncanny_automator->helpers->recipe->woocommerce->options->all_wc_products( __( 'Product', 'uncanny-automator' ) );

		$options['options'] = array( '-1' => __( 'Any product', 'uncanny-automator' ) ) + $options['options'];

		$trigger = array(
			'author'              => $uncanny_automator->get_author_name( $this->trigger_code ),
			'support_link'        => $uncanny_automator->get_author_support_link( $this->trigger_code ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - WooCommerce */
			'sentence'            => sprintf( __( 'A user purchases {{a product:%1$s}} {{a number of:%2$s}} times', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - WooCommerce */
			'select_option_name'  => __( 'A user purchases {{a product}}', 'uncanny-automator' ),
			'action'              => [
				'woocommerce_order_status_completed',
				'woocommerce_thankyou',
				'woocommerce_payment_complete',
			],
			'priority'            => 9999,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'payment_completed' ),
			'options'             => [
				$uncanny_automator->helpers->recipe->options->number_of_times(),
				$options,
			],
		);

		$uncanny_automator->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $order_id
	 */
	public function payment_completed( $order_id ) {
		global $uncanny_automator;

		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		if ( 'completed' !== $order->get_status() ) {
			return;
		}

		$user_id            = $order->get_user_id();
		$recipes            = $uncanny_automator->get->recipes_from_trigger_code( $this->trigger_code );
		$required_product   = $uncanny_automator->get->meta_from_recipes( $recipes, 'WOOPRODUCT' );
		$matched_recipe_ids = [];

		//Add where option is set to Any product
		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];//return early for all products
				if ( - 1 === intval( $required_product[ $recipe_id ][ $trigger_id ] ) ) {
					$matched_recipe_ids[] = [
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					];

					break;
				}
			}
		}
		$items       = $order->get_items();
		$product_ids = array();
		foreach ( $items as $item ) {
			$product_ids[] = $item->get_product_id();
		}
		//Add where Product ID is set for trigger
		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];//return early for all products
				if ( in_array( $required_product[ $recipe_id ][ $trigger_id ], $product_ids ) ) {
					$matched_recipe_ids[] = [
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					];
				}
			}
		}
		if ( ! empty( $matched_recipe_ids ) ) {
			foreach ( $matched_recipe_ids as $matched_recipe_id ) {
				$args = [
					'code'             => $this->trigger_code,
					'meta'             => $this->trigger_meta,
					'user_id'          => $user_id,
					'recipe_to_match'  => $matched_recipe_id['recipe_id'],
					'trigger_to_match' => $matched_recipe_id['trigger_id'],
					'ignore_post_id'   => true,
				];
				$uncanny_automator->maybe_add_trigger_entry( $args );
			}
		}

		return;
	}
}