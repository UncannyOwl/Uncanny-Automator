<?php

namespace Uncanny_Automator;


/**
 * Class WP_USERROLEADDED
 * @package Uncanny_Automator
 */
class WP_USERROLEADDED {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'WP';

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
		$this->trigger_code = 'USERROLEADDED';
		$this->trigger_meta = 'WPROLE';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		global $uncanny_automator;

		$trigger = array(
			'author'              => $uncanny_automator->get_author_name( $this->trigger_code ),
			'support_link'        => $uncanny_automator->get_author_support_link( $this->trigger_code ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			'meta'                => $this->trigger_meta,
			/* translators: Logged-in trigger - WordPress Core */
			'sentence'            => sprintf( __( '{{A specific:%1$s}} role is added to the user', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - WordPress Core */
			'select_option_name'  => __( '{{A specific}} role is added to the user', 'uncanny-automator' ),
			'action'              => 'add_user_role',
			'priority'            => 90,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'add_user_role' ),
			'options_group'       => array(),
			'options'             => array(
				$uncanny_automator->helpers->recipe->wp->options->wp_user_roles(),
			),
		);

		$uncanny_automator->register->trigger( $trigger );
	}

	/**
	 * @param $user_id
	 * @param $role
	 * @param $old_roles
	 */
	public function add_user_role( $user_id, $role ) {
		global $uncanny_automator;

		$recipes            = $uncanny_automator->get->recipes_from_trigger_code( $this->trigger_code );
		$required_user_role = $uncanny_automator->get->meta_from_recipes( $recipes, $this->trigger_meta );

		if ( ! $recipes ) {
			return;
		}

		if ( ! $required_user_role ) {
			return;
		}

		$matched_recipe_ids = array();

		$user_obj = get_user_by( 'ID', (int) $user_id );

		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];
				//Add where option is set to Any post type
				if ( intval( '-1' ) === intval( $required_user_role[ $recipe_id ][ $trigger_id ] ) ) {
					$matched_recipe_ids[] = [
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					];
				}

				if ( user_can( $user_obj, $required_user_role[ $recipe_id ][ $trigger_id ] ) && (string) $role === (string) $required_user_role[ $recipe_id ][ $trigger_id ] ) {
					$matched_recipe_ids[] = [
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					];
				}
			}
		}

		if ( ! empty( $matched_recipe_ids ) ) {
			foreach ( $matched_recipe_ids as $matched_recipe_id ) {
				$pass_args = [
					'code'             => $this->trigger_code,
					'meta'             => $this->trigger_meta,
					'user_id'          => $user_obj->ID,
					'recipe_to_match'  => $matched_recipe_id['recipe_id'],
					'trigger_to_match' => $matched_recipe_id['trigger_id'],
					'ignore_post_id'   => true,
				];

				$results = $uncanny_automator->maybe_add_trigger_entry( $pass_args, false );
				if ( $results ) {
					foreach ( $results as $result ) {
						if ( true === $result['result'] ) {

							$trigger_meta = [
								'user_id'        => $user_id,
								'trigger_id'     => $result['args']['trigger_id'],
								'trigger_log_id' => $result['args']['get_trigger_id'],
								'run_number'     => $result['args']['run_number'],
							];
							$roles = [];
							foreach ( wp_roles()->roles as $role_name => $role_info ) {
								$roles[ $role_name ] = $role_info['name'];
							}
							$role_label = isset( $roles[ $role ] ) ? $roles[ $role ] : '';
							// Post Title Token
							$trigger_meta['meta_key']   = $result['args']['trigger_id'] . ':' . $this->trigger_code . ':WPROLE';
							$trigger_meta['meta_value'] = maybe_serialize( $role_label );
							$uncanny_automator->insert_trigger_meta( $trigger_meta );

							$uncanny_automator->maybe_trigger_complete( $result['args'] );
						}
					}
				}
			}
		}
	}

}