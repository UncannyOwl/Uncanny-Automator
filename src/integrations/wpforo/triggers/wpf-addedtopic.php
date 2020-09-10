<?php

namespace Uncanny_Automator;

/**
 * Class WPF_ADDEDTOPIC
 * @package Uncanny_Automator
 */
class WPF_ADDEDTOPIC {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'WPFORO';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {

		$this->trigger_code = 'ADDEDTOPIC';
		$this->trigger_meta = 'WPFFORUMID';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		global $uncanny_automator;

		$forums = WPF()->forum->get_forums( [ 'type' => 'forum' ] );

		$forum_options = [ 0 => 'Any Forum' ];
		foreach ( $forums as $forum ) {
			$forum_options[ $forum['forumid'] ] = $forum['title'];
		}

		$option = [
			'option_code' => $this->trigger_meta,
			'label'       =>  esc_attr__( 'Forums', 'uncanny-automator' ),
			'input_type'  => 'select',
			'required'    => true,
			'options'     => $forum_options,
		];

		$trigger = array(
			'author'              => $uncanny_automator->get_author_name( $this->trigger_code ),
			'support_link'        => $uncanny_automator->get_author_support_link( $this->trigger_code ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - wpForo */
			'sentence'            => sprintf(  esc_attr__( 'A user creates a topic in {{a forum:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - wpForo */
			'select_option_name'  =>  esc_attr__( 'A user creates a {{new topic}}', 'uncanny-automator' ),
			'action'              => 'wpforo_after_add_topic',
			'priority'            => 5,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'added_topic' ),
			'options'             => [
				$option,
				$uncanny_automator->helpers->recipe->options->number_of_times(),
			],
		);

		$uncanny_automator->register->trigger( $trigger );

		return;
	}

	public function added_topic( $args ) {

		global $uncanny_automator;

		if ( isset( $args['forumid'] ) ) {
			$forum_id = absint( $args['forumid'] );
		} else {
			return;
		}

		// Get all recipes that have the "$this->trigger_code = 'ADDEDTOPIC'" trigger
		$recipes = $uncanny_automator->get->recipes_from_trigger_code( $this->trigger_code );
		// Get the specific WPFFORUMID meta data from the recipes
		$recipe_trigger_meta_data = $uncanny_automator->get->meta_from_recipes( $recipes, 'WPFFORUMID' );
		$matched_recipe_ids       = [];

		// Loop through recipe
		foreach ( $recipe_trigger_meta_data as $recipe_id => $trigger_meta ) {
			// Loop through recipe WPFFORUMID trigger meta data
			foreach ( $trigger_meta as $trigger_id => $required_forum_id ) {
				if (
					0 === absint( $required_forum_id ) || // Any forum is set as the option
					$forum_id === absint( $required_forum_id ) // Match specific forum
				) {
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
					'user_id'          => get_current_user_id(),
					'recipe_to_match'  => $matched_recipe_id['recipe_id'],
					'trigger_to_match' => $matched_recipe_id['trigger_id'],
					'ignore_post_id'   => true,
				];

				$args = $uncanny_automator->maybe_add_trigger_entry( $pass_args, false );

				if ( $args ) {
					foreach ( $args as $result ) {
						if ( true === $result['result'] ) {
							$uncanny_automator->maybe_trigger_complete( $result['args'] );
						}
					}
				}
			}

		}
	}
}
