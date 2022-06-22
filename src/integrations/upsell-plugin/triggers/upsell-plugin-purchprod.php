<?php

namespace Uncanny_Automator;

/**
 * Class UPSELL_PLUGIN_PURCHPROD
 *
 * @package Uncanny_Automator
 */
class UPSELL_PLUGIN_PURCHPROD {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'UPSELLPLUGIN';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'USPURCHPROD';
		$this->trigger_meta = 'USPRODUCT';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/upsell-plugin/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - Upsell */
			'sentence'            => sprintf( esc_attr__( 'A user purchases {{a product:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - Upsell */
			'select_option_name'  => esc_attr__( 'A user purchases {{a product}}', 'uncanny-automator' ),
			'action'              => 'upsell_order_status_completed',
			'priority'            => 99,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'upsell_order_completed' ),
			'options'             => array(
				Automator()->helpers->recipe->upsell_plugin->options->all_upsell_products( esc_attr__( 'Product', 'uncanny-automator' ) ),
				Automator()->helpers->recipe->options->number_of_times(),
			),
		);

		Automator()->register->trigger( $trigger );

		return;

	}

	public function upsell_order_completed( $order ) {

		if ( ! $order ) {
			return;
		}

		if ( 'completed' !== $order->status() ) {
			return;
		}

		if ( true === apply_filters( 'automator_upsell_order_use_current_logged_user', false, $order ) ) {
			$user_id = get_current_user_id();
		} else {
			$customer = get_user_by_email( $order->customer_email );
			$user_id  = ( ! empty( $customer ) ) ? $customer->ID : 0;
		}

		if ( 0 === $user_id ) {
			// Its a logged in recipe and
			// user ID is 0. Skip process
			return;
		}

		$recipes            = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_product   = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$matched_recipe_ids = array();

		//Add where option is set to Any product
		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];//return early for all products
				if ( isset( $required_product[ $recipe_id ] ) && isset( $required_product[ $recipe_id ][ $trigger_id ] ) ) {
					if ( - 1 === intval( $required_product[ $recipe_id ][ $trigger_id ] ) ) {
						$matched_recipe_ids[] = array(
							'recipe_id'  => $recipe_id,
							'trigger_id' => $trigger_id,
						);

						break;
					}
				}
			}
		}
		$items       = $order->items();
		$product_ids = array();

		foreach ( $items as $index => $item ) {
			$product_ids[] = $item['id'];
		}

		//Add where Product ID is set for trigger
		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];//return early for all products
				if ( isset( $required_product[ $recipe_id ] ) && isset( $required_product[ $recipe_id ][ $trigger_id ] ) ) {
					if ( in_array( $required_product[ $recipe_id ][ $trigger_id ], $product_ids ) ) {
						$matched_recipe_ids[] = array(
							'recipe_id'  => $recipe_id,
							'trigger_id' => $trigger_id,
						);
					}
				}
			}
		}

		if ( ! empty( $matched_recipe_ids ) ) {
			foreach ( $matched_recipe_ids as $matched_recipe_id ) {
				$pass_args = array(
					'code'             => $this->trigger_code,
					'meta'             => $this->trigger_meta,
					'user_id'          => $user_id,
					'recipe_to_match'  => $matched_recipe_id['recipe_id'],
					'trigger_to_match' => $matched_recipe_id['trigger_id'],
					'ignore_post_id'   => true,
					'is_signed_in'     => true,
				);

				$args = Automator()->maybe_add_trigger_entry( $pass_args, false );

				//Adding an action to save order id in trigger meta
				do_action( 'uap_wc_trigger_save_meta', $order->id, $matched_recipe_id['recipe_id'], $args, 'product' );

				if ( $args ) {
					foreach ( $args as $result ) {
						if ( true === $result['result'] ) {
							Automator()->maybe_trigger_complete( $result['args'] );
						}
					}
				}
			}
		}

		return;
	}

}
