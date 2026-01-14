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

		// Hook to process delayed cancellation verification
		add_action( 'automator_pmpro_process_cancellation', array( $this, 'process_cancellation' ), 10, 4 );
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/paid-memberships-pro/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - Paid Memberships Pro */
			'sentence'            => sprintf( esc_attr__( 'A user cancels {{a membership:%1$s}}', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - Paid Memberships Pro */
			'select_option_name'  => esc_attr__( 'A user cancels {{a membership}}', 'uncanny-automator' ),
			'action'              => 'pmpro_before_change_membership_level',
			'priority'            => 99,
			'accepted_args'       => 4,
			'validation_function' => array(
				$this,
				'validate_cancellation',
			),
			'options_callback'    => array( $this, 'load_options' ),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * @return array[]
	 */
	public function load_options() {

		$options = Automator()->helpers->recipe->paid_memberships_pro->options->all_memberships( esc_attr__( 'Membership', 'uncanny-automator' ) );

		$options['options'] = array( '-1' => esc_attr__( 'Any membership', 'uncanny-automator' ) ) + $options['options'];

		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					$options,
				),
			)
		);
	}

	/**
	 * Validate cancellation - entry point from PMPro hook.
	 *
	 * Handles scheduling logic for WooCommerce Subscriptions integration.
	 * Schedules delayed verification if WCS is active, otherwise processes immediately.
	 *
	 * @since 6.8.1
	 *
	 * @param int   $level_id The level ID (0 for cancellation)
	 * @param int   $user_id User ID
	 * @param array $old_levels User's old membership levels
	 * @param int   $cancel_level The level being cancelled
	 */
	public function validate_cancellation( $level_id, $user_id, $old_levels, $cancel_level ) {

		if ( 0 !== absint( $level_id ) || ! is_numeric( $cancel_level ) ) {
			return;
		}

		// Check if WooCommerce Subscriptions is active
		// If yes, delay processing to verify this is actual cancellation not renewal
		if ( class_exists( 'WC_Subscriptions' ) ) {
			// Check if event already scheduled for this user/level combo
			$args = array( $level_id, $user_id, $old_levels, $cancel_level );
			if ( ! wp_next_scheduled( 'automator_pmpro_process_cancellation', $args ) ) {
				wp_schedule_single_event( time() + 60, 'automator_pmpro_process_cancellation', $args );
			}
			return;
		}

		// No WooCommerce Subscriptions, process immediately
		$this->pmpro_subscription_cancelled( $user_id, $cancel_level );
	}

	/**
	 * Process the cancellation trigger.
	 *
	 * @since 6.8.1
	 *
	 * @param int $user_id User ID
	 * @param int $cancel_level The level being cancelled
	 */
	private function pmpro_subscription_cancelled( $user_id, $cancel_level ) {
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

	/**
	 * Process delayed cancellation verification.
	 *
	 * Called 60 seconds after initial cancellation. Verifies if level is still
	 * cancelled, then processes the trigger.
	 *
	 * @since 6.8.1
	 *
	 * @param int   $level_id The level ID (0 for cancellation)
	 * @param int   $user_id User ID
	 * @param array $old_levels User's old membership levels
	 * @param int   $cancel_level The level being cancelled
	 */
	public function process_cancellation( $level_id, $user_id, $old_levels, $cancel_level ) {

		// Verify user still does NOT have this membership level
		if ( function_exists( 'pmpro_hasMembershipLevel' ) && pmpro_hasMembershipLevel( $cancel_level, $user_id ) ) {
			// User has the level again - was temporary (renewal)
			return;
		}

		// Still cancelled - process the trigger
		$this->pmpro_subscription_cancelled( $user_id, $cancel_level );
	}

}
