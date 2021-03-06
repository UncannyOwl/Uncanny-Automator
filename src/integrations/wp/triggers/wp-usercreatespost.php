<?php

namespace Uncanny_Automator;

class WP_USERCREATESPOST {

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
		$this->trigger_code = 'USERSPOST';
		$this->trigger_meta = 'WPPOSTTYPES';
		if ( is_admin() && empty( $_POST ) ) {
			add_action( 'init', array( $this, 'define_trigger' ), 99 );
		} else {
			$this->define_trigger();
		}
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$all_post_types = Automator()->helpers->recipe->wp->options->all_post_types(
			null,
			'WPPOSTTYPES',
			array(
				'token'        => false,
				'is_ajax'      => true,
				'target_field' => 'WPTAXONOMIES',
				'endpoint'     => 'select_post_type_taxonomies',
			)
		);

		// now get regular post types.
		$args = array(
			'public'   => true,
			'_builtin' => true,
		);

		$options      = array();
		$options['0'] = __( 'Any post type', 'uncanny-automator' );
		$post_types   = get_post_types( $args, 'object' );
		if ( ! empty( $post_types ) ) {
			foreach ( $post_types as $post_type ) {
				$options[ $post_type->name ] = esc_html( $post_type->labels->singular_name );
			}
		}
		$options                   = array_merge( $options, $all_post_types['options'] );
		$all_post_types['options'] = $options;

		$relevant_tokens = array(
			'POSTTITLE'   => __( 'Post title', 'uncanny-automator' ),
			'POSTID'      => __( 'Post ID', 'uncanny-automator' ),
			'POSTURL'     => __( 'Post URL', 'uncanny-automator' ),
			'POSTCONTENT' => __( 'Post content', 'uncanny-automator' ),
		);

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/wordpress-core/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			'sentence'            => sprintf(
			/* translators: Logged-in trigger - WordPress */
				__( 'A user publishes a {{type of:%1$s}} post with {{a taxonomy term:%2$s}} in {{a taxonomy:%3$s}}', 'uncanny-automator' ),
				$this->trigger_meta,
				'WPTAXONOMYTERM' . ':' . $this->trigger_meta,
				'WPTAXONOMIES' . ':' . $this->trigger_meta
			),
			/* translators: Logged-in trigger - WordPress */
			'select_option_name'  => esc_attr__( 'A user publishes a {{type of}} post with {{a taxonomy term}} in {{a taxonomy}}', 'uncanny-automator' ),
			'action'              => 'wp_after_insert_post',
			'priority'            => 90,
			'accepted_args'       => 4,
			'validation_function' => array( $this, 'post_published' ),
			'options'             => array(),
			'options_group'       => array(
				$this->trigger_meta => array(
					$all_post_types,
					/* translators: Noun */
					Automator()->helpers->recipe->field->select_field_ajax(
						'WPTAXONOMIES',
						esc_attr__( 'Taxonomy', 'uncanny-automator' ),
						array(),
						'',
						'',
						false,
						true,
						array(
							'target_field' => 'WPTAXONOMYTERM',
							'endpoint'     => 'select_terms_for_selected_taxonomy',
						),
						$relevant_tokens
					),
					Automator()->helpers->recipe->field->select_field( 'WPTAXONOMYTERM', esc_attr__( 'Taxonomy term', 'uncanny-automator' ) ),
				),
			),
		);

		Automator()->register->trigger( $trigger );

		return;
	}

	/**
	 * Fires when a post is transitioned from one status to another.
	 *
	 * @param string $new_status New post status.
	 * @param string $old_status Old post status.
	 * @param \WP_Post $post Post object.
	 */
	public function post_published( $post_id, $post, $update, $post_before ) {
		// Bailout if status is not 'draft' to 'publish'.
		$is_draft_to_publish = Automator()->helpers->recipe->wp->is_draft_to_publish( $post->post_status, $post_before->post_status, $post );
		if ( ! $is_draft_to_publish ) {
			return false;
		}

		$user_id                = get_current_user_id();
		$recipes                = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_post_type     = Automator()->get->meta_from_recipes( $recipes, 'WPPOSTTYPES' );
		$required_post_taxonomy = Automator()->get->meta_from_recipes( $recipes, 'WPTAXONOMIES' );
		$required_post_term     = Automator()->get->meta_from_recipes( $recipes, 'WPTAXONOMYTERM' );

		$matched_recipe_ids = array();
		$terms_list         = array();
		$taxonomy_names     = array();

		$user_obj = get_user_by( 'ID', (int) $post->post_author );

		if ( ! is_wp_error( $user_obj ) ) {
			foreach ( $recipes as $recipe_id => $recipe ) {
				foreach ( $recipe['triggers'] as $trigger ) {

					$trigger_id = $trigger['ID'];

					$is_any_term = false;
					$is_any_tax  = false;

					if ( ! isset( $required_post_term[ $recipe_id ] ) && ! isset( $required_post_taxonomy[ $recipe_id ] ) && ! isset( $required_post_type[ $recipe_id ] ) ) {
						continue;
					}
					if ( isset( $required_post_term[ $recipe_id ][ $trigger_id ] ) && ! isset( $required_post_taxonomy[ $recipe_id ][ $trigger_id ] ) && ! isset( $required_post_type[ $recipe_id ][ $trigger_id ] ) ) {
						continue;
					}
					if ( '0' == $required_post_term[ $recipe_id ][ $trigger_id ] ) {
						$is_any_term = true;
					}
					if ( '0' == $required_post_taxonomy[ $recipe_id ][ $trigger_id ] ) {
						$is_any_tax = true;
					}
					if ( '0' != (string) $required_post_type[ $recipe_id ][ $trigger_id ] && $post->post_type !== $required_post_type[ $recipe_id ][ $trigger_id ] ) {
						continue;
					}

					if ( $is_any_tax && $is_any_term ) {
						// Any taxonomy and any term
						$matched_recipe_ids[] = array(
							'recipe_id'  => $recipe_id,
							'trigger_id' => $trigger_id,
						);
					} elseif ( $is_any_tax && ! $is_any_term ) {
						// Any taxonomy but specific term
						if (
						has_term(
							$required_post_term[ $recipe_id ][ $trigger_id ],
							null,
							$post
						)
						) {
							// Matched the post term
							$matched_recipe_ids[] = array(
								'recipe_id'  => $recipe_id,
								'trigger_id' => $trigger_id,
							);

							// Specific Term
							$term         = get_term( $required_post_term[ $recipe_id ][ $trigger_id ] );
							$terms_list[] = $term->name;
						}
					} elseif ( ! $is_any_tax && $is_any_term ) {
						// Specific taxonomy but any term
						if (
						has_term(
							null,
							$required_post_taxonomy[ $recipe_id ][ $trigger_id ],
							$post
						)
						) {
							// Matched the post term
							$matched_recipe_ids[] = array(
								'recipe_id'  => $recipe_id,
								'trigger_id' => $trigger_id,
							);

							// Specific Term
							$term         = get_term( $required_post_term[ $recipe_id ][ $trigger_id ] );
							$terms_list[] = $term->name;
						}
					} elseif ( ! $is_any_tax && ! $is_any_term ) {
						// Specific taxonomy and term
						if (
						has_term(
							$required_post_term[ $recipe_id ][ $trigger_id ],
							$required_post_taxonomy[ $recipe_id ][ $trigger_id ],
							$post
						)
						) {
							// Matched the post term
							$matched_recipe_ids[] = array(
								'recipe_id'  => $recipe_id,
								'trigger_id' => $trigger_id,
							);

							// Specific Term
							$term         = get_term( $required_post_term[ $recipe_id ][ $trigger_id ] );
							$terms_list[] = $term->name;
						}
					}

					$taxonomies = get_object_taxonomies( $post->post_type, 'object' );

					foreach ( $taxonomies as $taxonomy ) {
						$taxonomy_names [] = $taxonomy->name;
					}

					// All Post Terms for specific taxonomy
					$terms = wp_get_post_terms( $post->ID, $taxonomy_names );

					foreach ( $terms as $term ) {
						$terms_list [] = $term->name;
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
					'ignore_post_id'   => true,
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

							// Post Title Token
							$trigger_meta['meta_key']   = $result['args']['trigger_id'] . ':' . $this->trigger_code . ':POSTTITLE';
							$trigger_meta['meta_value'] = maybe_serialize( $post->post_title );
							Automator()->insert_trigger_meta( $trigger_meta );

							// Post ID Token
							$trigger_meta['meta_key']   = $result['args']['trigger_id'] . ':' . $this->trigger_code . ':POSTID';
							$trigger_meta['meta_value'] = maybe_serialize( $post->ID );
							Automator()->insert_trigger_meta( $trigger_meta );

							// Post URL Token
							$trigger_meta['meta_key']   = $result['args']['trigger_id'] . ':' . $this->trigger_code . ':POSTURL';
							$trigger_meta['meta_value'] = maybe_serialize( get_permalink( $post->ID ) );
							Automator()->insert_trigger_meta( $trigger_meta );

							// Post Content Token
							$trigger_meta['meta_key']   = $result['args']['trigger_id'] . ':' . $this->trigger_code . ':POSTCONTENT';
							$trigger_meta['meta_value'] = maybe_serialize( $post->post_content );
							Automator()->insert_trigger_meta( $trigger_meta );

							// Post Type Token
							$trigger_meta['meta_key']   = $result['args']['trigger_id'] . ':' . $this->trigger_code . ':WPPOSTTYPES';
							$trigger_meta['meta_value'] = maybe_serialize( $post->post_type );
							Automator()->insert_trigger_meta( $trigger_meta );

							// Post terms Token
							// All Post Terms for specific taxonomy
							$terms           = wp_get_post_terms( $post->ID, $taxonomy_names );
							$terms_list_save = array();
							foreach ( $terms as $term ) {
								$terms_list_save [] = $term->name;
							}
							$trigger_meta['meta_key']   = $result['args']['trigger_id'] . ':' . $this->trigger_code . ':WPTAXONOMYTERM';
							$trigger_meta['meta_value'] = maybe_serialize( implode( ', ', $terms_list_save ) );
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
