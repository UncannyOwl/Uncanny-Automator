<?php

namespace Uncanny_Automator;

/**
 * Class WP_SUBMITCOMMENT
 *
 * @package Uncanny_Automator
 */
class WP_SUBMITCOMMENT {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WP';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'WPSUBMITCOMMENT';
		$this->trigger_meta = 'WPPOSTCOMMENTS';
		if ( Automator()->helpers->recipe->is_edit_page() ) {
			add_action(
				'wp_loaded',
				function () {
					$this->define_trigger();
				},
				99
			);

			return;
		}
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/wordpress-core/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - WordPress */
			'sentence'            => sprintf( esc_attr__( 'A user submits a comment on {{a post:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - WordPress */
			'select_option_name'  => esc_attr__( 'A user submits a comment on {{a post}}', 'uncanny-automator' ),
			'action'              => 'comment_post',
			'priority'            => 90,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'submitted_comment' ),
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

		$all_post_types = Automator()->helpers->recipe->wp->options->all_post_types(
			null,
			'WPPOSTTYPES',
			array(
				'token'        => false,
				'is_ajax'      => true,
				'target_field' => $this->trigger_meta,
				'endpoint'     => 'select_all_post_from_SELECTEDPOSTTYPE',
				'comments'     => true,
			)
		);

		$options = Automator()->utilities->keep_order_of_options(
			array(
				'options'       => array(
					Automator()->helpers->recipe->options->number_of_times(),
				),
				'options_group' => array(
					$this->trigger_meta => array(
						$all_post_types,
						Automator()->helpers->recipe->field->select_field(
							$this->trigger_meta,
							esc_html__( 'Post', 'uncanny-automator' ),
							array(),
							null,
							false,
							false,
							array(
								$this->trigger_meta    => esc_attr__( 'Post title', 'uncanny-automator' ),
								$this->trigger_meta . '_ID' => esc_attr__( 'Post ID', 'uncanny-automator' ),
								$this->trigger_meta . '_URL' => esc_attr__( 'Post URL', 'uncanny-automator' ),
								$this->trigger_meta . '_POSTNAME' => esc_attr__( 'Post slug', 'uncanny-automator' ),
								'POSTCONTENT'          => esc_attr__( 'Post content', 'uncanny-automator' ),
								$this->trigger_meta . '_EXCERPT' => esc_attr__( 'Post excerpt', 'uncanny-automator' ),
								'WPPOSTTYPES'          => esc_attr__( 'Post type', 'uncanny-automator' ),
								$this->trigger_meta . '_THUMB_ID' => esc_attr__( 'Post featured image ID', 'uncanny-automator' ),
								$this->trigger_meta . '_THUMB_URL' => esc_attr__( 'Post featured image URL', 'uncanny-automator' ),
								'POSTAUTHORFN'         => esc_attr__( 'Post author first name', 'uncanny-automator' ),
								'POSTAUTHORLN'         => esc_attr__( 'Post author last name', 'uncanny-automator' ),
								'POSTAUTHORDN'         => esc_attr__( 'Post author display name', 'uncanny-automator' ),
								'POSTAUTHOREMAIL'      => esc_attr__( 'Post author email', 'uncanny-automator' ),
								'POSTAUTHORURL'        => esc_attr__( 'Post author URL', 'uncanny-automator' ),
								'POSTCOMMENT_ID'       => esc_attr__( 'Comment ID', 'uncanny-automator' ),
								'POSTCOMMENTCONTENT'   => esc_attr__( 'Comment content', 'uncanny-automator' ),
								'POSTCOMMENTDATE'      => esc_attr__( 'Comment submitted date', 'uncanny-automator' ),
								'POSTCOMMENTEREMAIL'   => esc_attr__( 'Commenter email', 'uncanny-automator' ),
								'POSTCOMMENTERNAME'    => esc_attr__( 'Commenter name', 'uncanny-automator' ),
								'POSTCOMMENTERWEBSITE' => esc_attr__( 'Commenter website', 'uncanny-automator' ),
								'POSTCOMMENTSTATUS'    => esc_attr__( 'Commenter status', 'uncanny-automator' ),
								'POSTCOMMENTURL'       => esc_attr__( 'Comment URL', 'uncanny-automator' ),

							)
						),
					),
				),
			)
		);

		return $options;
	}

	/**
	 * Method plugins_loaded.
	 *
	 * @return void
	 */
	public function plugins_loaded() {
		$this->define_trigger();
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param int $comment_id The comment ID.
	 * @param int|string $comment_approved 1 if the comment is approved, 0 if not, 'spam' if spam.
	 * @param array $commentdata Comment data.
	 */
	public function submitted_comment( $comment_id, $comment_approved, $commentdata ) {
		if ( isset( $commentdata['posted_by_automator'] ) ) {
			return;
		}
		$user_id   = get_current_user_id();
		$post_type = get_post_type( $commentdata['comment_post_ID'] );
		// We need backword compatibility along with new change
		$recipes    = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$conditions = $this->match_condition( $commentdata['comment_post_ID'], $post_type, $recipes, $this->trigger_meta, $this->trigger_code );

		if ( ! $conditions ) {
			return;
		}
		$post_type = get_post_type_object( $post_type );

		if ( ! empty( $conditions ) ) {
			foreach ( $conditions['recipe_ids'] as $recipe_id => $trigger_id ) {
				if ( ! Automator()->is_recipe_completed( $recipe_id, $user_id ) ) {
					$args = array(
						'code'             => $this->trigger_code,
						'meta'             => $this->trigger_meta,
						'recipe_to_match'  => $recipe_id,
						'trigger_to_match' => $trigger_id,
						'post_id'          => $commentdata['comment_post_ID'],
						'user_id'          => $user_id,
					);

					$arr = Automator()->maybe_add_trigger_entry( $args, false );
					if ( $arr ) {
						foreach ( $arr as $result ) {
							if ( true === $result['result'] ) {
								$trigger_meta = array(
									'user_id'        => $user_id,
									'trigger_id'     => $result['args']['trigger_id'],
									'trigger_log_id' => $result['args']['get_trigger_id'],
									'run_number'     => $result['args']['run_number'],
								);

								// Comment ID
								Automator()->db->token->save( 'comment_id', $comment_id, $trigger_meta );

								Automator()->maybe_trigger_complete( $result['args'] );
							}
						}
					}
				}
			}
		}

	}

	/**
	 * Matching Form id because its not an integer.
	 *
	 * @param array $post_id .
	 * @param array $recipes .
	 * @param string $trigger_meta .
	 * @param string $trigger_code .
	 *
	 * @return array|bool
	 */
	public function match_condition( $post_id, $post_type, $recipes = null, $trigger_meta = null, $trigger_code = null ) {

		if ( null === $recipes ) {
			return false;
		}

		$recipe_ids      = array();
		$match_post_id   = $post_id;
		$match_post_type = $post_type;
		foreach ( $recipes as $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				if ( ! key_exists( 'WPPOSTTYPES', $trigger['meta'] ) ) {
					if ( key_exists( $trigger_meta, $trigger['meta'] ) && ( (string) $trigger['meta'][ $trigger_meta ] === (string) $match_post_id || '-1' === (string) $trigger['meta'][ $trigger_meta ] ) ) {
						$recipe_ids[ $recipe['ID'] ] = $trigger['ID'];
					}
				} else {
					if ( key_exists( $trigger_meta, $trigger['meta'] )
						 && ( (string) $trigger['meta'][ $trigger_meta ] === (string) $match_post_id || '-1' === (string) $trigger['meta'][ $trigger_meta ] )
						 && ( (string) $trigger['meta']['WPPOSTTYPES'] === (string) $match_post_type || '-1' === (string) $trigger['meta']['WPPOSTTYPES'] )
					) {
						$recipe_ids[ $recipe['ID'] ] = $trigger['ID'];
					}
				}
			}
		}

		if ( ! empty( $recipe_ids ) ) {
			return array(
				'recipe_ids' => $recipe_ids,
				'result'     => true,
			);
		}

		return false;
	}
}
