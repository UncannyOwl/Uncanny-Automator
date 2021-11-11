<?php

namespace Uncanny_Automator;

/**
 *
 */
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
	 * @var array
	 */
	private $trigger_meta_log;
	/**
	 * @var array
	 */
	private $result;
	/**
	 * @var \WP_Post
	 */
	private $post;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'USERSPOST';
		$this->trigger_meta = 'WPPOSTTYPES';
		if ( is_admin() && empty( $_POST ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			add_action( 'init', array( $this, 'define_trigger' ), 99 );
		} else {
			$this->define_trigger();
		}

		add_action( 'uoa_wp_after_insert_post', array( $this, 'post_published' ), 99, 1 );
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

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/wordpress-core/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			'sentence'            => sprintf(
			/* translators: Logged-in trigger - WordPress */
				__( 'A user publishes a {{type of:%1$s}} post with {{a taxonomy term:%2$s}} in {{a taxonomy:%3$s}}', 'uncanny-automator' ),
				$this->trigger_meta,
				'WPTAXONOMYTERM:' . $this->trigger_meta,
				'WPTAXONOMIES:' . $this->trigger_meta
			),
			/* translators: Logged-in trigger - WordPress */
			'select_option_name'  => esc_attr__( 'A user publishes a {{type of}} post with {{a taxonomy term}} in {{a taxonomy}}', 'uncanny-automator' ),
			'action'              => 'wp_after_insert_post',
			'priority'            => 90,
			'accepted_args'       => 4,
			'validation_function' => array( $this, 'schedule_a_post' ),
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
						)
					),
					Automator()->helpers->recipe->field->select_field( 'WPTAXONOMYTERM', esc_attr__( 'Taxonomy term', 'uncanny-automator' ) ),
				),
			),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * @param $post_id
	 * @param $post
	 * @param $update
	 * @param $post_before
	 */
	public function schedule_a_post( $post_id, $post, $update, $post_before ) {
		if ( ! empty( $post_before ) && 'publish' === $post_before->post_status ) {
			return;
		}

		if ( 'publish' !== $post->post_status ) {
			return;
		}

		if ( wp_next_scheduled( 'uoa_wp_after_insert_post', array( $post_id ) ) ) {
			return;
		}

		wp_schedule_single_event(
			apply_filters( 'automator_schedule_a_post_time', time() + 2, $post_id, $post, $update, $post_before ),
			'uoa_wp_after_insert_post',
			array(
				$post_id,
			)
		);
	}

	/**
	 * Fires when a post is transitioned from one status to another.
	 *
	 * @param $post_id
	 */
	public function post_published( $post_id ) {
		$post                   = get_post( $post_id );
		$this->post             = $post;
		$user_id                = (int) $post->post_author;
		$recipes                = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_post_type     = Automator()->get->meta_from_recipes( $recipes, 'WPPOSTTYPES' );
		$required_post_taxonomy = Automator()->get->meta_from_recipes( $recipes, 'WPTAXONOMIES' );
		$required_post_term     = Automator()->get->meta_from_recipes( $recipes, 'WPTAXONOMYTERM' );
		$matched_recipe_ids     = array();
		$terms_list             = array();
		$taxonomy_names         = array();

		if ( empty( $recipes ) ) {
			return;
		}

		if ( empty( $required_post_type ) ) {
			return;
		}

		if ( empty( $required_post_taxonomy ) ) {
			return;
		}

		if ( empty( $required_post_term ) ) {
			return;
		}

		$user_obj = get_user_by( 'ID', (int) $post->post_author );
		if ( ! $user_obj instanceof \WP_User ) {
			return;
		}
		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_matched = true;
				$trigger_id      = absint( $trigger['ID'] );
				// required post type
				if ( '0' !== $required_post_type[ $recipe_id ][ $trigger_id ] ) {
					if ( (string) $post->post_type !== (string) $required_post_type[ $recipe_id ][ $trigger_id ] ) {
						$trigger_matched = false;
					}
				}
				// Post type not matched, bail.
				if ( ! $trigger_matched ) {
					continue;
				}

				// if a specific taxonomy
				if ( '0' !== $required_post_taxonomy[ $recipe_id ][ $trigger_id ] ) {
					// let check if the post has any term in the selected taxonomy
					$post_terms = wp_get_post_terms( $post_id, $required_post_taxonomy[ $recipe_id ][ $trigger_id ] );
					if ( empty( $post_terms ) ) {
						$trigger_matched = false;
						//continue;
					} else {
						$trigger_matched = true;
						// All Post Terms for specific taxonomy
						$terms = wp_get_post_terms( $post->ID, $required_post_taxonomy[ $recipe_id ][ $trigger_id ] );

						foreach ( $terms as $term ) {
							if ( ! array_key_exists( $term->term_id, $terms_list[ $recipe_id ][ $trigger_id ] ) ) {
								$terms_list[ $recipe_id ][ $trigger_id ][ $term->term_id ] = $term->name;
							}
						}
					}
				}

				if ( ! $trigger_matched ) {
					continue;
				}

				// Check for a specific term in a taxonomy
				if ( '0' !== $required_post_term[ $recipe_id ][ $trigger_id ] ) {
					// if the term is specific then tax and post type are also specific
					// check if the post has the required term
					$post_terms = wp_get_post_terms( $post_id, $required_post_taxonomy[ $recipe_id ][ $trigger_id ] );
					if ( empty( $post_terms ) ) {
						$trigger_matched = false;
					} else {
						$post_term_ids = array_map( 'absint', array_column( $post_terms, 'term_id' ) );
						if ( ! in_array( intval( $required_post_term[ $recipe_id ][ $trigger_id ] ), $post_term_ids, true ) ) {
							$trigger_matched = false;
							//continue;
						} else {
							$trigger_matched = true;
							// Specific Term
							$term = get_term( $required_post_term[ $recipe_id ][ $trigger_id ] );
							if ( ! array_key_exists( $term->term_id, $terms_list[ $recipe_id ][ $trigger_id ] ) ) {
								$terms_list[ $recipe_id ][ $trigger_id ][ $term->term_id ] = $term->name;
							}
						}
					}
				}

				if ( ! $trigger_matched ) {
					continue;
				}

				// If any term / taxonomny
				if ( '0' === $required_post_taxonomy[ $recipe_id ][ $trigger_id ] && '0' === $required_post_term[ $recipe_id ][ $trigger_id ] ) {
					$taxonomies = get_object_taxonomies( $post->post_type, 'object' );

					foreach ( $taxonomies as $taxonomy ) {
						// All Post Terms for specific taxonomy
						$terms = wp_get_post_terms( $post->ID, $taxonomy->name );
						if ( empty( $terms ) ) {
							continue;
						}
						foreach ( $terms as $term ) {
							if ( ! array_key_exists( $term->term_id, $terms_list[ $recipe_id ][ $trigger_id ] ) ) {
								$terms_list[ $recipe_id ][ $trigger_id ][ $term->term_id ] = $term->name;
							}
						}
					}
				}

				if ( '0' !== $required_post_taxonomy[ $recipe_id ][ $trigger_id ] && '0' === $required_post_term[ $recipe_id ][ $trigger_id ] ) {
					// All Post Terms for specific taxonomy
					$terms = wp_get_post_terms( $post->ID, $required_post_taxonomy[ $recipe_id ][ $trigger_id ] );
					if ( empty( $terms ) ) {
						continue;
					}
					foreach ( $terms as $term ) {
						if ( ! array_key_exists( $term->term_id, $terms_list[ $recipe_id ][ $trigger_id ] ) ) {
							$terms_list[ $recipe_id ][ $trigger_id ][ $term->term_id ] = $term->name;
						}
					}
				}

				if ( $trigger_matched ) {
					// All fields are set to "any" by deductive reasoning
					// Matched the post term
					$matched_recipe_ids[] = array(
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					);
				}
			}
		}

		if ( empty( $matched_recipe_ids ) ) {
			return;
		}

		foreach ( $matched_recipe_ids as $matched_recipe_id ) {
			$recipe_id  = absint( $matched_recipe_id['recipe_id'] );
			$trigger_id = absint( $matched_recipe_id['trigger_id'] );
			$pass_args  = array(
				'code'             => $this->trigger_code,
				'meta'             => $this->trigger_meta,
				'user_id'          => $user_id,
				'recipe_to_match'  => $recipe_id,
				'trigger_to_match' => $trigger_id,
				'ignore_post_id'   => true,
				'is_signed_in'     => true,
			);

			$args = Automator()->maybe_add_trigger_entry( $pass_args, false );
			if ( empty( $args ) ) {
				continue;
			}
			foreach ( $args as $result ) {
				if ( false === $result['result'] ) {
					continue;
				}

				$trigger_meta = array(
					'user_id'        => $user_id,
					'trigger_id'     => $result['args']['trigger_id'],
					'trigger_log_id' => $result['args']['trigger_log_id'],
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

				$this->trigger_meta_log = $trigger_meta;
				$this->result           = $result;

				if ( defined( 'REST_REQUEST' ) ) {
					add_action(
						"rest_after_insert_{$post->post_type}",
						array(
							$this,
							'store_thumbnail',
						),
						10,
						3
					);
				} else {
					$this->store_thumbnail( $post );
				}

				if ( isset( $terms_list[ $recipe_id ][ $trigger_id ] ) ) {
					$trigger_meta['meta_key']   = $result['args']['trigger_id'] . ':' . $this->trigger_code . ':WPTAXONOMYTERM';
					$trigger_meta['meta_value'] = maybe_serialize( implode( ', ', $terms_list[ $recipe_id ][ $trigger_id ] ) );
					Automator()->insert_trigger_meta( $trigger_meta );
				}
				Automator()->maybe_trigger_complete( $result['args'] );
			}
		}
	}

	/**
	 * @param $post
	 * @param null $request
	 * @param null $creating
	 */
	public function store_thumbnail( $post, $request = null, $creating = null ) {

		// Post Featured Image URL
		$this->trigger_meta_log['meta_key']   = $this->result['args']['trigger_id'] . ':' . $this->trigger_code . ':POSTIMAGEURL';
		$this->trigger_meta_log['meta_value'] = maybe_serialize( get_the_post_thumbnail_url( $this->post->ID, 'full' ) );
		Automator()->insert_trigger_meta( $this->trigger_meta_log );

		// Post Featured Image ID
		$this->trigger_meta_log['meta_key']   = $this->result['args']['trigger_id'] . ':' . $this->trigger_code . ':POSTIMAGEID';
		$this->trigger_meta_log['meta_value'] = maybe_serialize( get_post_thumbnail_id( $this->post->ID ) );
		Automator()->insert_trigger_meta( $this->trigger_meta_log );

	}

}
