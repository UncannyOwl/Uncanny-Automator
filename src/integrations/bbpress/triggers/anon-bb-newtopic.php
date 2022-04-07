<?php

namespace Uncanny_Automator;

/**
 * Class ANON_BB_NEWTOPIC
 *
 * @package Uncanny_Automator
 */
class ANON_BB_NEWTOPIC {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'BB';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'ANONBBNEWTOPIC';
		$this->trigger_meta = 'BBFORUMS';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {
		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/bbpress/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - bbPress */
			'sentence'            => sprintf( esc_attr__( 'A guest creates a topic in {{a forum:%1$s}}', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - bbPress */
			'select_option_name'  => esc_attr__( 'A guest creates a topic in {{a forum}}', 'uncanny-automator' ),
			'action'              => 'bbp_new_topic',
			'priority'            => 10,
			'accepted_args'       => 4,
			'type'                => 'anonymous',
			'validation_function' => array( $this, 'bbp_new_topic' ),
			'options'             => array(
				Automator()->helpers->recipe->bbpress->options->list_bbpress_forums( null, $this->trigger_meta, true ),
			),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $topic_id
	 * @param $forum_id
	 * @param $anonymous_data
	 * @param $topic_author
	 */
	public function bbp_new_topic( $topic_id, $forum_id, $anonymous_data, $topic_author ) {

		$user_id            = get_current_user_id();
		$recipes            = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_forum     = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$matched_recipe_ids = array();

		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];//return early for all products
				if ( isset( $required_forum[ $recipe_id ] ) && isset( $required_forum[ $recipe_id ][ $trigger_id ] ) ) {
					//Add where option is set to Any Forum
					if ( - 1 === intval( $required_forum[ $recipe_id ][ $trigger_id ] )
					     || $required_forum[ $recipe_id ][ $trigger_id ] == $forum_id ) {
						$matched_recipe_ids[] = array(
							'recipe_id'  => $recipe_id,
							'trigger_id' => $trigger_id,
						);
					}
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
					'post_id'          => $forum_id,
				);

				$args = Automator()->maybe_add_trigger_entry( $pass_args, false );

				if ( $args ) {
					foreach ( $args as $result ) {
						if ( true === $result['result'] ) {

							$trigger_meta = array(
								'user_id'        => $user_id,
								'trigger_id'     => $result['args']['trigger_id'],
								'trigger_log_id' => $result['args']['get_trigger_id'],
								'run_number'     => $result['args']['run_number'],
							);

							$trigger_meta['meta_key']   = 'ANONYMOUS_EMAIL';
							$trigger_meta['meta_value'] = maybe_serialize( $anonymous_data['bbp_anonymous_email'] );
							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = 'BBTOPIC_ID';
							$trigger_meta['meta_value'] = maybe_serialize( $topic_id );
							Automator()->insert_trigger_meta( $trigger_meta );

							Automator()->maybe_trigger_complete( $result['args'] );
						}
					}
				}
			}
		}

		return;
	}

}
