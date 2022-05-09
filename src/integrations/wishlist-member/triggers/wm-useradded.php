<?php

namespace Uncanny_Automator;

/**
 * Class WM_USERADDED
 *
 * @package Uncanny_Automator
 */
class WM_USERADDED {
	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WISHLISTMEMBER';

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
		$this->trigger_code = 'WMUSERADDEDTO';
		$this->trigger_meta = 'WMMEMBERSHIPLEVELS';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/wishlist-member/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - Wishlist Member */
			'sentence'            => sprintf( esc_attr__( 'A user is added to {{a membership level:%1$s}}', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - Wishlist Member */
			'select_option_name'  => esc_attr__( 'A user is added to {{a membership level}}', 'uncanny-automator' ),
			'action'              => 'wishlistmember_add_user_levels',
			'priority'            => 99,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'add_user_to_membership_level' ),
			'options'             => array(
				Automator()->helpers->recipe->wishlist_member->options->wm_get_all_membership_levels( null, $this->trigger_meta ),
			),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * @param $user_id
	 * @param $new_levels
	 * @param $old_levels
	 */
	public function add_user_to_membership_level( $user_id, $new_levels, $old_levels ) {

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( empty( $user_id ) ) {
			return;
		}

		$recipes            = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_level     = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$matched_recipe_ids = array();

		//Add where Membership Level is set for trigger
		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];//return early for all products
				if ( in_array( $required_level[ $recipe_id ][ $trigger_id ], $new_levels ) ) { //phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
					$matched_recipe_ids[] = array(
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					);
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

				if ( $args ) {
					foreach ( $args as $result ) {
						if ( true === $result['result'] ) {
							Automator()->maybe_trigger_complete( $result['args'] );
						}
					}
				}
			}
		}
	}
}
