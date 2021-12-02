<?php

namespace Uncanny_Automator;

/**
 * Class UM_USERROLECHANGE
 *
 * @package Uncanny_Automator
 */
class UM_USERROLECHANGE {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'UM';

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
		$this->trigger_code = 'UMUSERROLECHANGE';
		$this->trigger_meta = 'WPROLE';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/ultimate-member/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - Ultimate Member */
			'sentence'            => sprintf( esc_attr__( "A user's role changes to {{a specific role:%1\$s}}", 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - Ultimate Member */
			'select_option_name'  => esc_attr__( "A user's role changes to {{a specific role}}", 'uncanny-automator' ),
			'action'              => 'set_user_role',
			'priority'            => 99,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'set_user_role' ),
			'options'             => array(
				Automator()->helpers->recipe->wp->options->wp_user_roles(),
			),
		);

		Automator()->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 * @param $role
	 * @param $old_roles
	 */
	public function set_user_role( $user_id, $role, $old_roles ) {

		$matched_recipe_ids = $this->match_condition( $role );
		if ( ! empty( $matched_recipe_ids ) ) {
			foreach ( $matched_recipe_ids as $matched_recipe_id ) {
				$args = array(
					'code'             => $this->trigger_code,
					'meta'             => $this->trigger_meta,
					'user_id'          => $user_id,
					'recipe_to_match'  => $matched_recipe_id['recipe_id'],
					'trigger_to_match' => $matched_recipe_id['trigger_id'],
					'ignore_post_id'   => true,
				);

				if ( isset( Automator()->process ) && isset( Automator()->process->user ) && Automator()->process->user instanceof Automator_Recipe_Process_User ) {
					Automator()->process->user->maybe_add_trigger_entry( $args );
				} else {
					Automator()->maybe_add_trigger_entry( $args );
				}
			}
		}

	}

	/**
	 * @param $role
	 *
	 * @return array
	 */
	public function match_condition( $role ) {

		$recipes            = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_role      = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$matched_recipe_ids = array();

		//Add where option is set to Any product
		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];//return early for all products
				if ( - 1 === intval( $required_role[ $recipe_id ][ $trigger_id ] ) ) {
					$matched_recipe_ids[] = array(
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					);

					break;
				}
			}
		}

		//Add where Product ID is set for trigger
		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];//return early for all products
				if ( $required_role[ $recipe_id ][ $trigger_id ] == $role ) {
					$matched_recipe_ids[] = array(
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					);
				}
			}
		}

		return $matched_recipe_ids;
	}
}
