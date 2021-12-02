<?php

namespace Uncanny_Automator;

/**
 * Class WCM_ADDUSER
 *
 * @package Uncanny_Automator
 */
class WCM_ADDUSER {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WCMEMBERSHIPS';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'WCMUSERADDED';
		$this->trigger_meta = 'WCMMEMBERSHIPPLAN';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/woocommerce-memberships/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - WooCommerce Memberships */
			'sentence'            => sprintf( esc_attr__( 'A user is added to {{a membership plan:%1$s}}', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - WooCommerce Memberships */
			'select_option_name'  => esc_attr__( 'A user is added to {{a membership plan}}', 'uncanny-automator' ),
			'action'              => 'wc_memberships_user_membership_saved',
			'priority'            => 99,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'wc_user_added_to_membership_plan' ),
			'options'             => array(
				Automator()->helpers->recipe->wc_memberships->options->wcm_get_all_membership_plans(
					null,
					$this->trigger_meta,
					array( 'is_any' => true )
				),
			),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * @param $membership_plan
	 * @param $data
	 */
	public function wc_user_added_to_membership_plan( $membership_plan, $data ) {

		if ( 0 === $data['user_id'] ) {
			// Its a logged in recipe and
			// user ID is 0. Skip process
			return;
		}

		// If membership is active only.
		$user_membership = wc_memberships_get_user_membership( $data['user_membership_id'] );
		if ( ! $user_membership->is_active() ) {
			return;
		}

		$recipes            = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_plan      = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$matched_recipe_ids = array();
		$order_id           = '';

		//Add where option is set to Any product
		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];//return early for all products
				if ( isset( $required_plan[ $recipe_id ] ) && isset( $required_plan[ $recipe_id ][ $trigger_id ] ) ) {
					if ( intval( '-1' ) === intval( $required_plan[ $recipe_id ][ $trigger_id ] ) || absint( $membership_plan->id ) === absint( $required_plan[ $recipe_id ][ $trigger_id ] ) ) {
						$matched_recipe_ids[] = array(
							'recipe_id'  => $recipe_id,
							'trigger_id' => $trigger_id,
						);
					}
				}
			}
		}

		$membership_plan_type = get_post_meta( $membership_plan->id, '_access_method', true );

		if ( ! empty( $matched_recipe_ids ) ) {
			foreach ( $matched_recipe_ids as $matched_recipe_id ) {
				$pass_args = array(
					'code'             => $this->trigger_code,
					'meta'             => $this->trigger_meta,
					'user_id'          => $data['user_id'],
					'recipe_to_match'  => $matched_recipe_id['recipe_id'],
					'trigger_to_match' => $matched_recipe_id['trigger_id'],
					'ignore_post_id'   => true,
					'is_signed_in'     => true,
					'post_id'          => $membership_plan->id,
				);

				$args = Automator()->maybe_add_trigger_entry( $pass_args, false );
				if ( $args ) {
					foreach ( $args as $result ) {
						if ( true === $result['result'] ) {

							if ( 'purchase' === $membership_plan_type ) {
								$order_id     = get_post_meta( $data['user_membership_id'], '_order_id', true );
								$trigger_meta = array(
									'user_id'        => $data['user_id'],
									'trigger_id'     => $result['args']['trigger_id'],
									'trigger_log_id' => $result['args']['get_trigger_id'],
									'run_number'     => $result['args']['run_number'],
								);

								$trigger_meta['meta_key']   = 'WCMPLANORDERID';
								$trigger_meta['meta_value'] = maybe_serialize( $order_id );
								Automator()->insert_trigger_meta( $trigger_meta );
							}

							Automator()->maybe_trigger_complete( $result['args'] );
						}
					}
				}
			}
		}
	}

}
