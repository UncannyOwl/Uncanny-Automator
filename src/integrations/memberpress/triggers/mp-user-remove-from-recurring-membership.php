<?php

namespace Uncanny_Automator;

/**
 * Class MP_USER_REMOVE_FROM_RECURRING_MEMBERSHIP
 *
 * @package Uncanny_Automator
 */
class MP_USER_REMOVE_FROM_RECURRING_MEMBERSHIP {

	/**
	 * Integration code
	 *
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
		$this->trigger_code = 'USERREMOVEFROMRECURRINGMEMBERSHIP';
		$this->trigger_meta = 'MPPRODUCT';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {
		$trigger = array(
			'author'              => Automator()->get_author_name(),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/memberpress/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - MemberPress */
			'sentence'            => sprintf( esc_attr__( 'A user is removed from {{a recurring subscription product:%1$s}}', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - MemberPress */
			'select_option_name'  => esc_attr__( 'A user is removed from {{a recurring subscription product}}', 'uncanny-automator' ),
			'action'              => 'mepr_subscription_deleted',
			'priority'            => 1,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'mp_user_removed_from_recurring' ),
			'options_callback'    => array( $this, 'load_options' ),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * @return array[]
	 */
	public function load_options() {
		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					Automator()->helpers->recipe->memberpress->options->all_memberpress_products_recurring(
						null,
						$this->trigger_meta,
						array(
							'uo_include_any' => true,
						)
					),
				),
			)
		);
	}

	/**
	 * @param \MeprSubscription $sub
	 */
	public function mp_user_removed_from_recurring( \MeprSubscription $sub ) {

		error_log( 'mkk-- ' . print_r( $sub, true ) . PHP_EOL );

		$product_id = $sub->product_id;
		$user_id    = absint( $sub->user_id );

		$recipes = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		if ( empty( $recipes ) ) {
			return;
		}

		$required_product   = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$matched_recipe_ids = array();
		//Add where option is set to Any product
		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];
				if ( absint( $required_product[ $recipe_id ][ $trigger_id ] ) === $product_id || intval( '-1' ) === intval( $required_product[ $recipe_id ][ $trigger_id ] ) ) {
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
			$recipe_args = array(
				'code'             => $this->trigger_code,
				'meta'             => $this->trigger_meta,
				'user_id'          => $user_id,
				'recipe_to_match'  => $matched_recipe_id['recipe_id'],
				'trigger_to_match' => $matched_recipe_id['trigger_id'],
				'ignore_post_id'   => true,
				'is_signed_in'     => true,
			);

			$results = Automator()->maybe_add_trigger_entry( $recipe_args, false );
			if ( empty( $results ) ) {
				continue;
			}
			foreach ( $results as $result ) {
				if ( true === $result['result'] ) {
					$trigger_meta = array(
						'user_id' => $user_id,
					);

					$trigger_id     = absint( $result['args']['trigger_id'] );
					$trigger_log_id = absint( $result['args']['trigger_log_id'] );
					$run_number     = absint( $result['args']['run_number'] );

					$trigger_meta['meta_key']   = $this->trigger_meta;
					$trigger_meta['meta_value'] = $product_id;
					Automator()->db->trigger->add_meta( $trigger_id, $trigger_log_id, $run_number, $trigger_meta );

					Automator()->process->user->maybe_trigger_complete( $result['args'] );
				}
			}
		}
	}

}
