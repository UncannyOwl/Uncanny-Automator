<?php

namespace Uncanny_Automator;

/**
 *
 * Class WP_POSTRECEIVESCOMMENT
 *
 * @package Uncanny_Automator
 */
class WP_POSTRECEIVESCOMMENT {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WP';

	/**
	 * @var string
	 */
	private $trigger_code = 'WPCOMMENTRECEIVED';

	/**
	 * @var string
	 */
	private $trigger_meta = 'USERSPOSTCOMMENT';

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->define_trigger();
	}

	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/wordpress-core/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - WordPress */
			'sentence'            => sprintf( esc_attr__( "{{A user's post:%1\$s}} receives a comment", 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - WordPress */
			'select_option_name'  => esc_attr__( "{{A user's post}} receives a comment", 'uncanny-automator' ),
			'action'              => 'comment_post',
			'priority'            => 90,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'post_receives_comment' ),
			'options_callback'    => array( $this, 'load_options' ),
		);

		Automator()->register->trigger( $trigger );

	}

	/**
	 * Method load_options.
	 *
	 * @return array
	 */
	public function load_options() {

		Automator()->helpers->recipe->wp->options->load_options = true;

		$all_post_types = Automator()->helpers->recipe->wp->options->get_post_types_options(
			null,
			'WPPOSTTYPES',
			array(
				'token'           => false,
				'is_ajax'         => true,
				'target_field'    => $this->trigger_meta,
				'relevant_tokens' => array(),
				'comments'        => true,
				'endpoint'        => 'select_all_post_from_SELECTEDPOSTTYPE',
			),
			false
		);

		$temp = array(
			'options_group' => array(
				$this->trigger_meta => array(
					$all_post_types,
					Automator()->helpers->recipe->field->select(
						array(
							'option_code'     => $this->trigger_meta,
							'label'           => esc_attr__( 'Post', 'uncanny-automator' ),
							'relevant_tokens' => array(),
						)
					),
				),
			),
		);

		return Automator()->utilities->keep_order_of_options( $temp );
	}

	/**
	 * Method post_receives_comment.
	 *
	 * @param $comment_id
	 * @param $comment_approved
	 * @param $commentdata
	 */
	public function post_receives_comment( $comment_id, $comment_approved, $commentdata ) {
		if ( isset( $commentdata['posted_by_automator'] ) ) {
			return;
		}
		$recipes            = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_post      = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$user_id            = get_post_field( 'post_author', (int) $commentdata['comment_post_ID'] );
		$user_obj           = get_user_by( 'ID', (int) $user_id );
		$matched_recipe_ids = array();

		//Add where option is set to Any post / specific post
		foreach ( $recipes as $recipe_id => $recipe ) {

			foreach ( $recipe['triggers'] as $trigger ) {

				$trigger_id = $trigger['ID'];

				// Check if 'Any' is passed as trigger condition.
				$is_any = ( - 1 === intval( $required_post[ $recipe_id ][ $trigger_id ] ) );

				// Check if trigger condition matches the comment id.
				$trigger_condition_matched_comment_id = ( intval( $required_post[ $recipe_id ][ $trigger_id ] ) === intval( $commentdata['comment_post_ID'] ) );

				if ( $is_any || $trigger_condition_matched_comment_id ) {

					$matched_recipe_ids[] = array(
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					);

				}
			}
		}

		if ( ! empty( $matched_recipe_ids ) ) {

			foreach ( $matched_recipe_ids as $matched_recipe_id ) {

				if ( ! Automator()->is_recipe_completed( $matched_recipe_id['recipe_id'], $user_id ) ) {

					$pass_args = array(
						'code'             => $this->trigger_code,
						'meta'             => $this->trigger_meta,
						'user_id'          => $user_id,
						'recipe_to_match'  => $matched_recipe_id['recipe_id'],
						'trigger_to_match' => $matched_recipe_id['trigger_id'],
						'post_id'          => $commentdata['comment_post_ID'],
					);

					$args = Automator()->maybe_add_trigger_entry( $pass_args, false );

					if ( $args ) {
						foreach ( $args as $result ) {
							if ( true === $result['result'] ) {
								$trigger_meta = array(
									'user_id'        => (int) $user_id,
									'trigger_id'     => $result['args']['trigger_id'],
									'trigger_log_id' => $result['args']['get_trigger_id'],
									'run_number'     => $result['args']['run_number'],
								);
								// Comment ID
								Automator()->db->token->save( 'comment_id', maybe_serialize( $comment_id ), $trigger_meta );

								Automator()->maybe_trigger_complete( $result['args'] );
							}
						}
					}
				}
			}
		}
	}
}
