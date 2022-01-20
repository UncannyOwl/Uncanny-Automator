<?php

namespace Uncanny_Automator;

/**
 * Class WPF_ADDEDTOPIC
 *
 * @package Uncanny_Automator
 */
class WPF_ADDEDTOPIC {

	/**
	 * Integration code
	 *
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

		$forums = WPF()->forum->get_forums( array( 'type' => 'forum' ) );

		$forum_options = array( 0 => 'Any Forum' );
		foreach ( $forums as $forum ) {
			$forum_options[ $forum['forumid'] ] = $forum['title'];
		}

		$forum_relevant_tokens = array(
			'WPFORO_FORUM'         => __( 'Forum title', 'uncanny-automator' ),
			'WPFORO_FORUM_ID'      => __( 'Forum ID', 'uncanny-automator' ),
			'WPFORO_FORUM_URL'     => __( 'Forum URL', 'uncanny-automator' ),
			'WPFORO_TOPIC'         => __( 'Topic title', 'uncanny-automator' ),
			'WPFORO_TOPIC_ID'      => __( 'Topic ID', 'uncanny-automator' ),
			'WPFORO_TOPIC_URL'     => __( 'Topic URL', 'uncanny-automator' ),
			'WPFORO_TOPIC_CONTENT' => __( 'Topic content', 'uncanny-automator' ),
		);

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/wpforo/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - wpForo */
			'sentence'            => sprintf( esc_attr__( 'A user creates a topic in {{a forum:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - wpForo */
			'select_option_name'  => esc_attr__( 'A user creates a new topic in {{a forum}}', 'uncanny-automator' ),
			'action'              => 'wpforo_after_add_topic',
			'priority'            => 5,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'added_topic' ),
			'options'             => array(
				Automator()->helpers->recipe->field->select_field_args(
					array(
						'option_code'     => $this->trigger_meta,
						'options'         => $forum_options,
						'label'           => esc_attr__( 'Forums', 'uncanny-automator' ),
						'required'        => true,
						'token_name'      => 'Forum ID',
						'relevant_tokens' => $forum_relevant_tokens,
					)
				),
				Automator()->helpers->recipe->options->number_of_times(),
			),
		);

		Automator()->register->trigger( $trigger );

		return;
	}

	public function added_topic( $args ) {

		if ( isset( $args['forumid'] ) ) {
			$forum_id = absint( $args['forumid'] );
		} else {
			return;
		}

		if ( isset( $args['topicid'] ) ) {
			$topic_id = absint( $args['topicid'] );
		} else {
			return;
		}

		// Get all recipes that have the "$this->trigger_code = 'ADDEDTOPIC'" trigger
		$recipes = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		// Get the specific WPFFORUMID meta data from the recipes
		$recipe_trigger_meta_data = Automator()->get->meta_from_recipes( $recipes, 'WPFFORUMID' );
		$matched_recipe_ids       = array();

		// Loop through recipe
		foreach ( $recipe_trigger_meta_data as $recipe_id => $trigger_meta ) {
			// Loop through recipe WPFFORUMID trigger meta data
			foreach ( $trigger_meta as $trigger_id => $required_forum_id ) {
				if (
					0 === absint( $required_forum_id ) || // Any forum is set as the option
					$forum_id === absint( $required_forum_id ) // Match specific forum
				) {
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
					'user_id'          => get_current_user_id(),
					'recipe_to_match'  => $matched_recipe_id['recipe_id'],
					'trigger_to_match' => $matched_recipe_id['trigger_id'],
					'ignore_post_id'   => true,
				);

				$args = Automator()->maybe_add_trigger_entry( $pass_args, false );

				if ( $args ) {
					foreach ( $args as $result ) {
						if ( true === $result['result'] ) {

							$trigger_meta = array(
								'user_id'        => get_current_user_id(),
								'trigger_id'     => $result['args']['trigger_id'],
								'trigger_log_id' => $result['args']['get_trigger_id'],
								'run_number'     => $result['args']['run_number'],
							);

							$trigger_meta['meta_key']   = 'WPFORO_TOPIC_ID';
							$trigger_meta['meta_value'] = $topic_id;

							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = 'WPFORO_TOPIC_FORUM_ID';
							$trigger_meta['meta_value'] = $forum_id;

							Automator()->insert_trigger_meta( $trigger_meta );

							Automator()->maybe_trigger_complete( $result['args'] );
						}
					}
				}
			}
		}
	}
}
