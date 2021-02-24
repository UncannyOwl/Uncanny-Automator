<?php

namespace Uncanny_Automator;

/**
 * Class MP_PURCHASEPRODUCTRECURRING
 * @package Uncanny_Automator
 */
class MP_PURCHASEPRODUCTRECURRING {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'MP';

	/**
	 * @var string
	 */
	private $trigger_code;
	/**
	 * @var string
	 */
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'MPPURCHASEPRODUCTRECURRING';
		$this->trigger_meta = 'MPPRODUCT';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		global $uncanny_automator;

		$trigger = array(
			'author'              => $uncanny_automator->get_author_name(),
			'support_link'        => $uncanny_automator->get_author_support_link(),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - MemberPress */
			'sentence'            => sprintf( esc_attr__( 'A user purchases {{a recurring subscription product:%1$s}}', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - MemberPress */
			'select_option_name'  => esc_attr__( 'A user purchases {{a recurring subscription product}}', 'uncanny-automator' ),
			'action'              => 'mepr-event-transaction-completed',
			'priority'            => 20,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'mp_product_purchased' ),
			'options'             => [
				$uncanny_automator->helpers->recipe->memberpress->options->all_memberpress_products_recurring( null, $this->trigger_meta, [ 'uo_include_any' => true ] ),
			],
		);

		$uncanny_automator->register->trigger( $trigger );
	}

	/**
	 * @param \MeprEvent $event
	 */
	public function mp_product_purchased( \MeprEvent $event ) {

		global $uncanny_automator;

		/** @var \MeprTransaction $transaction */
		$transaction = $event->get_data();
		/** @var \MeprProduct $product */
		$product    = $transaction->product();
		$product_id = $product->ID;
		$user_id    = absint( $transaction->user()->ID );
		if ( 'lifetime' === (string) $product->period_type ) {
			return;
		}

		$recipes = $uncanny_automator->get->recipes_from_trigger_code( $this->trigger_code );
		if ( empty( $recipes ) ) {
			return;
		}
		$required_product   = $uncanny_automator->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$matched_recipe_ids = array();
		//Add where option is set to Any product
		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];//return early for all products
				if ( absint( $required_product[ $recipe_id ][ $trigger_id ] ) === $product_id || intval( '-1' ) === intval( $required_product[ $recipe_id ][ $trigger_id ] ) ) {
					$matched_recipe_ids[] = [
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					];
				}
			}
		}
		if ( empty( $matched_recipe_ids ) ) {
			return;
		}
		foreach ( $matched_recipe_ids as $matched_recipe_id ) {
			$recipe_args = [
				'code'             => $this->trigger_code,
				'meta'             => $this->trigger_meta,
				'user_id'          => $user_id,
				'recipe_to_match'  => $matched_recipe_id['recipe_id'],
				'trigger_to_match' => $matched_recipe_id['trigger_id'],
				'ignore_post_id'   => true,
				'is_signed_in'     => true,
			];

			$results = $uncanny_automator->maybe_add_trigger_entry( $recipe_args, false );
			if ( empty( $results ) ) {
				continue;
			}
			foreach ( $results as $result ) {
				if ( true === $result['result'] ) {
					$trigger_meta = [
						'user_id'        => $user_id,
						'trigger_id'     => $result['args']['trigger_id'],
						'trigger_log_id' => $result['args']['get_trigger_id'],
						'run_number'     => $result['args']['run_number'],
					];

					$trigger_meta['meta_key']   = $this->trigger_meta;
					$trigger_meta['meta_value'] = $product_id;
					$uncanny_automator->insert_trigger_meta( $trigger_meta );
					update_user_meta( $user_id, 'MPPRODUCT', $product_id );

					$uncanny_automator->maybe_trigger_complete( $result['args'] );
				}
			}
		}
	}
}
