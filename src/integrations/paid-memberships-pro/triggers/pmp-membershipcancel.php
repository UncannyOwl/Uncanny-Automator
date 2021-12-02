<?php

namespace Uncanny_Automator;

/**
 * Class PMP_CANCELMEMBERSHIP
 *
 * @package Uncanny_Automator
 */
class PMP_MEMBERSHIPCANCEL {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'PMP';

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
		$this->trigger_code = 'PMPMEMBERSHIPCANCEL';
		$this->trigger_meta = 'PMPMEMBERSHIP';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$options = Automator()->helpers->recipe->paid_memberships_pro->options->all_memberships( esc_attr__( 'Membership', 'uncanny-automator' ) );

		$options['options'] = array( '-1' => esc_attr__( 'Any membership', 'uncanny-automator' ) ) + $options['options'];

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/paid-memberships-pro/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - Paid Memberships Pro */
			'sentence'            => sprintf( esc_attr__( 'A user cancels {{a membership:%1$s}}', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - Paid Memberships Pro */
			'select_option_name'  => esc_attr__( 'A user cancels {{a membership}}', 'uncanny-automator' ),
			'action'              => 'pmpro_after_change_membership_level',
			'priority'            => 99,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'pmpro_subscription_cancelled' ),
			'options'             => array(
				$options,
			),
		);

		Automator()->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $level_id
	 * @param $user_id
	 * @param $cancel_level
	 */
	public function pmpro_subscription_cancelled( $level_id, $user_id, $cancel_level ) {

		if ( 0 !== absint( $level_id ) ) {
			return;
		}

		if ( 0 === absint( $level_id ) && is_numeric( $cancel_level ) ) {
			$recipes             = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
			$required_memerbship = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
			$matched_recipe_ids  = array();

			//Add where option is set to Any membership
			foreach ( $recipes as $recipe_id => $recipe ) {
				foreach ( $recipe['triggers'] as $trigger ) {
					$trigger_id = $trigger['ID'];//return early for all memberships
					if ( - 1 === intval( $required_memerbship[ $recipe_id ][ $trigger_id ] ) ) {
						$matched_recipe_ids[] = array(
							'recipe_id'  => $recipe_id,
							'trigger_id' => $trigger_id,
						);

						break;
					}
				}
			}

			//Add where Membership ID is set for trigger
			foreach ( $recipes as $recipe_id => $recipe ) {
				foreach ( $recipe['triggers'] as $trigger ) {
					$trigger_id = $trigger['ID'];//return early for all memberships
					if ( $required_memerbship[ $recipe_id ][ $trigger_id ] == $cancel_level ) {
						$matched_recipe_ids[] = array(
							'recipe_id'  => $recipe_id,
							'trigger_id' => $trigger_id,
						);
					}
				}
			}

			if ( ! empty( $matched_recipe_ids ) ) {
				foreach ( $matched_recipe_ids as $matched_recipe_id ) {
					$args   = array(
						'code'             => $this->trigger_code,
						'meta'             => $this->trigger_meta,
						'user_id'          => $user_id,
						'recipe_to_match'  => $matched_recipe_id['recipe_id'],
						'trigger_to_match' => $matched_recipe_id['trigger_id'],
						'ignore_post_id'   => true,
					);
					$result = Automator()->maybe_add_trigger_entry( $args, false );

					if ( $result ) {
						foreach ( $result as $r ) {
							if ( true === $r['result'] ) {
								do_action( 'uap_save_pmp_membership_level', $cancel_level, $r['args'], $user_id, $this->trigger_meta );
								Automator()->maybe_trigger_complete( $r['args'] );
							}
						}
					}
				}
			}
		}

		return;
	}
}
