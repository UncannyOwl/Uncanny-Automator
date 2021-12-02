<?php

namespace Uncanny_Automator;

/**
 * Class BDB_ACTIVITYSTRM
 *
 * @package Uncanny_Automator
 */
class BDB_ACTIVITYSTRM {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'BDB';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'BDBACTIVITYSTRM';
		$this->trigger_meta = 'BDBUSERS';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$bp_users_args = array(
			'uo_include_any' => true,
		);

		$trigger = array(
			'author'              => Automator()->get_author_name(),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/buddyboss/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - BuddyPress */
			'sentence'            => sprintf( esc_attr__( '{{A user:%1$s}} posts activity to their stream', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - BuddyPress */
			'select_option_name'  => esc_attr__( '{{A user}} posts activity to their stream', 'uncanny-automator' ),
			'action'              => 'bp_activity_posted_update',
			'priority'            => 10,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'bp_activity_posted_update' ),
			'options'             => array(
				Automator()->helpers->recipe->buddyboss->options->all_buddyboss_users( null, 'BDBUSERS', $bp_users_args ),
			),
		);

		Automator()->register->trigger( $trigger );

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
		$recipes            = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_users     = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$matched_recipe_ids = array();

		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];
				if ( intval( '-1' ) === intval( $required_users[ $recipe_id ][ $trigger_id ] ) || intval( $user_id ) === intval( $required_users[ $recipe_id ][ $trigger_id ] ) ) {
					$matched_recipe_ids[] = array(
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					);
				}
			}
		}

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

				$returns = Automator()->maybe_add_trigger_entry( $args, false );

				if ( $returns ) {
					foreach ( $returns as $result ) {
						if ( true === $result['result'] ) {

							$trigger_meta = array(
								'user_id'        => $user_id,
								'trigger_id'     => $result['args']['trigger_id'],
								'trigger_log_id' => $result['args']['get_trigger_id'],
								'run_number'     => $result['args']['run_number'],
							);

							// ACTIVITY_ID Token
							$trigger_meta['meta_key']   = 'ACTIVITY_ID';
							$trigger_meta['meta_value'] = $activity_id;
							Automator()->insert_trigger_meta( $trigger_meta );

							// ACTIVITY_URL Token
							$trigger_meta['meta_key']   = 'ACTIVITY_URL';
							$trigger_meta['meta_value'] = bp_core_get_user_domain( $user_id ) . 'activity';
							Automator()->insert_trigger_meta( $trigger_meta );

							// ACTIVITY_STREAM_URL Token
							$trigger_meta['meta_key']   = 'ACTIVITY_STREAM_URL';
							$trigger_meta['meta_value'] = bp_core_get_user_domain( $user_id ) . 'activity/' . $activity_id;
							Automator()->insert_trigger_meta( $trigger_meta );

							// ACTIVITY_CONTENT Token
							$trigger_meta['meta_key']   = 'ACTIVITY_CONTENT';
							$trigger_meta['meta_value'] = $content;
							Automator()->insert_trigger_meta( $trigger_meta );

							Automator()->maybe_trigger_complete( $result['args'] );
						}
					}
				}
			}
		}
	}
}
