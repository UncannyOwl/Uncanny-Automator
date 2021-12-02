<?php

namespace Uncanny_Automator;

use MemberOrder;

/**
 * Class PMP_MEMBERSHIPEXPIRES
 *
 * @package Uncanny_Automator
 */
class PMP_MEMBERSHIPEXPIRES {

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
		$this->trigger_code = 'PMPMEMBERSHIPEXPIRES';
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
			'sentence'            => sprintf( esc_attr__( "A user's subscription to {{a membership:%1\$s}} expires", 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - Paid Memberships Pro */
			'select_option_name'  => esc_attr__( "A user's subscription to {{a membership}} expires", 'uncanny-automator' ),
			'action'              => 'pmpro_membership_post_membership_expiry',
			'priority'            => 100,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'pmpro_subscription_expired' ),
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
	 * @param MemberOrder $morder
	 */

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 * @param $membership_id
	 */
	public function pmpro_subscription_expired( $user_id, $membership_id ) {

		if ( empty( $user_id ) || empty( $membership_id ) ) {
			return;
		}

		if ( is_numeric( $membership_id ) ) {
			$recipes             = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
			$required_membership = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
			$matched_recipe_ids  = array();

			//Add where option is set to Any membership
			foreach ( $recipes as $recipe_id => $recipe ) {
				foreach ( $recipe['triggers'] as $trigger ) {
					$trigger_id = $trigger['ID'];//return early for all memberships
					if ( - 1 === intval( $required_membership[ $recipe_id ][ $trigger_id ] ) ) {
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
					if ( (int) $required_membership[ $recipe_id ][ $trigger_id ] === (int) $membership_id ) {
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
						'is_signed_in'     => true,
					);
					$result = Automator()->maybe_add_trigger_entry( $args, false );

					if ( $result ) {
						foreach ( $result as $r ) {
							if ( true === $r['result'] ) {
								do_action( 'uap_save_pmp_membership_level', $membership_id, $r['args'], $user_id, $this->trigger_meta );
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
