<?php

namespace Uncanny_Automator;

/**
 * Class BP_ACTIVITYSTRM
 * @package uncanny_automator
 */
class BP_ACTIVITYSTRM {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'BP';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'BPACTIVITYSTRM';
		$this->trigger_meta = 'BPUSERS';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		global $uncanny_automator;

		$bp_users_args = array(
			'uo_include_any' => true,
		);

		$trigger = array(
			'author'              => $uncanny_automator->get_author_name(),
			'support_link'        => $uncanny_automator->get_author_support_link(),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* Translators: 1:BuddyPress Users */
			'sentence'            => sprintf( __( '{{A user:%1$s}} posts activity to their stream', 'uncanny-automator' ), $this->trigger_meta ),
			'select_option_name'  => __( '{{A user}} posts activity to their stream', 'uncanny-automator' ),
			'action'              => 'bp_activity_posted_update',
			'priority'            => 10,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'bp_activity_posted_update' ),
			'options'             => [
				$uncanny_automator->helpers->recipe->buddypress->options->all_buddypress_users( null, 'BPUSERS', $bp_users_args ),
			],
		);

		$uncanny_automator->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $content
	 * @param $user_id
	 * @param $activity_id
	 */
	public function bp_activity_posted_update( $content, $user_id, $activity_id ) {

		global $uncanny_automator;

		$recipes            = $uncanny_automator->get->recipes_from_trigger_code( $this->trigger_code );
		$required_users     = $uncanny_automator->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$matched_recipe_ids = [];


		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];
				if ( intval( '-1' ) === intval( $required_users[ $recipe_id ][ $trigger_id ] ) || intval( $user_id ) === intval( $required_users[ $recipe_id ][ $trigger_id ] ) ) {
					$matched_recipe_ids[] = [
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					];
				}
			}
		}

		if ( ! empty( $matched_recipe_ids ) ) {
			foreach ( $matched_recipe_ids as $matched_recipe_id ) {
				$args = [
					'code'             => $this->trigger_code,
					'meta'             => $this->trigger_meta,
					'user_id'          => $user_id,
					'recipe_to_match'  => $matched_recipe_id['recipe_id'],
					'trigger_to_match' => $matched_recipe_id['trigger_id'],
					'ignore_post_id'   => true,
				];
				$uncanny_automator->maybe_add_trigger_entry( $args );
			}
		}
	}
}
