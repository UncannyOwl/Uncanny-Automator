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
			// translators: %1$s: post title, %2$s: number of times
			'sentence'            => sprintf( esc_attr_x( 'A user submits a comment on {{a post:%1$s}} {{a number of:%2$s}} time(s)', 'WordPress', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			'select_option_name'  => esc_attr_x( 'A user submits a comment on {{a post}}', 'WordPress', 'uncanny-automator' ),
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
							esc_html_x( 'Post', 'WordPress', 'uncanny-automator' ),
							array(),
							null,
							false,
							false,
							array(
								$this->trigger_meta    => esc_attr_x( 'Post title', 'WordPress', 'uncanny-automator' ),
								$this->trigger_meta . '_ID' => esc_attr_x( 'Post ID', 'WordPress', 'uncanny-automator' ),
								$this->trigger_meta . '_URL' => esc_attr_x( 'Post URL', 'WordPress', 'uncanny-automator' ),
								$this->trigger_meta . '_POSTNAME' => esc_attr_x( 'Post slug', 'WordPress', 'uncanny-automator' ),
								'POSTCONTENT'          => esc_attr_x( 'Post content', 'WordPress', 'uncanny-automator' ),
								$this->trigger_meta . '_EXCERPT' => esc_attr_x( 'Post excerpt', 'WordPress', 'uncanny-automator' ),
								'WPPOSTTYPES'          => esc_attr_x( 'Post type', 'WordPress', 'uncanny-automator' ),
								$this->trigger_meta . '_THUMB_ID' => esc_attr_x( 'Post featured image ID', 'WordPress', 'uncanny-automator' ),
								$this->trigger_meta . '_THUMB_URL' => esc_attr_x( 'Post featured image URL', 'WordPress', 'uncanny-automator' ),
								'POSTAUTHORFN'         => esc_attr_x( 'Post author first name', 'WordPress', 'uncanny-automator' ),
								'POSTAUTHORLN'         => esc_attr_x( 'Post author last name', 'WordPress', 'uncanny-automator' ),
								'POSTAUTHORDN'         => esc_attr_x( 'Post author display name', 'WordPress', 'uncanny-automator' ),
								'POSTAUTHOREMAIL'      => esc_attr_x( 'Post author email', 'WordPress', 'uncanny-automator' ),
								'POSTAUTHORURL'        => esc_attr_x( 'Post author URL', 'WordPress', 'uncanny-automator' ),
								'POSTCOMMENT_ID'       => esc_attr_x( 'Comment ID', 'WordPress', 'uncanny-automator' ),
								'POSTCOMMENTCONTENT'   => esc_attr_x( 'Comment content', 'WordPress', 'uncanny-automator' ),
								'POSTCOMMENTDATE'      => esc_attr_x( 'Comment submitted date', 'WordPress', 'uncanny-automator' ),
								'POSTCOMMENTEREMAIL'   => esc_attr_x( 'Commenter email', 'WordPress', 'uncanny-automator' ),
								'POSTCOMMENTERNAME'    => esc_attr_x( 'Commenter name', 'WordPress', 'uncanny-automator' ),
								'POSTCOMMENTERWEBSITE' => esc_attr_x( 'Commenter website', 'WordPress', 'uncanny-automator' ),
								'POSTCOMMENTSTATUS'    => esc_attr_x( 'Commenter status', 'WordPress', 'uncanny-automator' ),
								'POSTCOMMENTURL'       => esc_attr_x( 'Comment URL', 'WordPress', 'uncanny-automator' ),

							)
						),
					),
				),
			)
		);

		// Add Akismet checkbox if plugin is active
		if ( defined( 'AKISMET_VERSION' ) ) {
			$options['options_group'][ $this->trigger_meta ][] = array(
				'input_type'    => 'checkbox',
				'label'         => esc_attr_x( 'Trigger only if the comment passes Akismet spam filtering', 'WordPress', 'uncanny-automator' ),
				'option_code'   => 'AKISMET_CHECK',
				'is_toggle'     => true,
				'default_value' => false,
			);
		}

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
	 * Validation function when the trigger action is hit.
	 *
	 * @param int    $comment_id The comment ID.
	 * @param int|string $comment_approved 1 if the comment is approved, 0 if not, 'spam' if spam.
	 * @param array  $commentdata Comment data.
	 */
	public function submitted_comment( $comment_id, $comment_approved, $commentdata ) {
		if ( isset( $commentdata['posted_by_automator'] ) ) {
			return;
		}

		$user_id   = get_current_user_id();
		$post_type = get_post_type( $commentdata['comment_post_ID'] );

		// Retrieve matching recipes and conditions
		$recipes    = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$conditions = $this->match_condition( $commentdata['comment_post_ID'], $post_type, $recipes, $this->trigger_meta, $this->trigger_code );

		if ( ! $conditions ) {
			return;
		}

		// Loop through all recipes and their triggers
		foreach ( $recipes as $recipe_id => $recipe ) {
			if ( empty( $recipe['triggers'] ) ) {
				continue;
			}

			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];

				// Check if this trigger is in the matched conditions
				if ( ! isset( $conditions['recipe_ids'][ $recipe_id ] ) || $conditions['recipe_ids'][ $recipe_id ] !== $trigger_id ) {
					continue;
				}

				// Check if comment is spam based on trigger's meta
				if ( Automator()->helpers->recipe->wp->should_block_comment_by_akismet( $trigger, $comment_approved, $commentdata ) ) {
					continue;
				}

				// Skip if recipe is already completed
				if ( Automator()->is_recipe_completed( $recipe_id, $user_id ) ) {
					continue;
				}

				// Try to add a new trigger entry
				$args = array(
					'code'             => $this->trigger_code,
					'meta'             => $this->trigger_meta,
					'recipe_to_match'  => $recipe_id,
					'trigger_to_match' => $trigger_id,
					'post_id'          => $commentdata['comment_post_ID'],
					'user_id'          => $user_id,
				);

				$results = Automator()->maybe_add_trigger_entry( $args, false );

				if ( $results ) {
					foreach ( $results as $result ) {
						if ( true === $result['result'] ) {
							$trigger_meta = array(
								'user_id'        => $user_id,
								'trigger_id'     => $result['args']['trigger_id'],
								'trigger_log_id' => $result['args']['get_trigger_id'],
								'run_number'     => $result['args']['run_number'],
							);

							// Save the comment ID as token
							Automator()->db->token->save( 'comment_id', $comment_id, $trigger_meta );

							// Attempt to complete the trigger
							Automator()->maybe_trigger_complete( $result['args'] );
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
