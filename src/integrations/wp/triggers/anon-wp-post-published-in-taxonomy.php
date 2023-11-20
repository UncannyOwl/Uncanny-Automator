<?php

namespace Uncanny_Automator;

/**
 * Class ANON_WP_POST_PUBLISHED_IN_TAXONOMY
 *
 * @package Uncanny_Automator
 */
class ANON_WP_POST_PUBLISHED_IN_TAXONOMY {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WP';

	/**
	 * Property trigger_code
	 *
	 * @var string
	 */
	private $trigger_code;

	/**
	 * Property trigger_meta
	 *
	 * @var string
	 */
	private $trigger_meta;

	/**
	 * Property trigger_meta_log
	 *
	 * @var array
	 */
	private $trigger_meta_log;

	/**
	 * Property array
	 *
	 * @var array
	 */

	private $result;

	/**
	 * Property post
	 *
	 * @var \WP_Post
	 */
	private $post;

	/**
	 * Property terms_list
	 *
	 * @var array
	 */
	private $terms_list;

	/**
	 * Property taxonomy_list
	 *
	 * @var array
	 */
	private $taxonomy_list = array();

	/**
	 * Property match_recipes
	 *
	 * @var array
	 */
	private $matched_recipes = array();


	public function __construct() {
		$this->trigger_code = 'WP_POST_PUBLISHED_IN_TAXONOMY';
		$this->trigger_meta = 'WPPOSTTYPES';

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
		add_action( 'uoa_wp_after_insert_post', array( $this, 'post_published' ), 99, 1 );
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 *
	 * @return void
	 */
	public function define_trigger() {
		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/wordpress-core/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			'type'                => 'anonymous',
			'sentence'            => sprintf(
			/* translators: Logged-in trigger - WordPress */
				esc_attr_x( '{{A type of post:%1$s}} with {{a taxonomy term:%2$s}} in {{a taxonomy:%3$s}} is published', 'WordPress', 'uncanny-automator' ),
				$this->trigger_meta,
				'WPTAXONOMYTERM:' . $this->trigger_meta,
				'WPTAXONOMIES:' . $this->trigger_meta
			),
			/* translators: Logged-in trigger - WordPress */
			'select_option_name'  => esc_attr_x( 'A post in a taxonomy is published', 'WordPress', 'uncanny-automator' ),
			'action'              => 'wp_after_insert_post',
			'priority'            => 90,
			'accepted_args'       => 4,
			'validation_function' => array( $this, 'schedule_a_post' ),
			'options_callback'    => array( $this, 'load_options' ),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * Method load_options.
	 *
	 * @return void
	 */
	public function load_options() {

		Automator()->helpers->recipe->wp->options->load_options = true;

		$all_post_types = Automator()->helpers->recipe->wp->options->all_post_types(
			null,
			'WPPOSTTYPES',
			array(
				'token'               => false,
				'is_ajax'             => true,
				'target_field'        => 'WPTAXONOMIES',
				'endpoint'            => 'select_post_type_taxonomies',
				'use_zero_as_default' => true,
			//              'default_value'       => 'post',
			)
		);

		$options = Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => array(
					$this->trigger_meta => array(
						$all_post_types,
						/* translators: Noun */
						Automator()->helpers->recipe->field->select_field_ajax(
							'WPTAXONOMIES',
							esc_attr_x( 'Taxonomy', 'WordPress', 'uncanny-automator' ),
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
						Automator()->helpers->recipe->field->select_field( 'WPTAXONOMYTERM', esc_attr_x( 'Term', 'WordPress', 'uncanny-automator' ) ),
					),
				),
			)
		);

		return $options;
	}

	/**
	 * @param $post_id
	 * @param $post
	 * @param $update
	 * @param $post_before
	 *
	 * @return bool|void|\WP_Error|null
	 */
	public function schedule_a_post( $post_id, $post, $update, $post_before ) {
		// only run when posts
		// are published first time
		if ( ! Automator()->utilities->is_wp_post_being_published( $post, $post_before ) ) {
			return;
		}

		$cron_enabled = apply_filters( 'automator_wp_user_creates_post_cron_enabled', '__return_true', $post_id, $post, $update, $post_before, $this );

		// Allow people to disable cron processing.
		if ( false === $cron_enabled ) {
			// Immediately run post_publised if cron not enabled.
			return $this->post_published( $post_id );
		}

		if ( wp_next_scheduled( 'uoa_wp_after_insert_post', array( $post_id ) ) ) {
			return;
		}

		// Scheduling for 5 sec so that all tax/terms are stored
		return wp_schedule_single_event(
			apply_filters( 'automator_schedule_a_post_time', time() + 5, $post_id, $post, $update, $post_before ),
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
		$user_id                = absint( isset( $post->post_author ) ? $post->post_author : 0 );
		$recipes                = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_post_type     = Automator()->get->meta_from_recipes( $recipes, 'WPPOSTTYPES' );
		$required_post_taxonomy = Automator()->get->meta_from_recipes( $recipes, 'WPTAXONOMIES' );
		$required_post_term     = Automator()->get->meta_from_recipes( $recipes, 'WPTAXONOMYTERM' );

		// no recipes found, bail
		if ( empty( $recipes ) ) {
			return;
		}

		// Trigger Post types no found
		if ( empty( $required_post_type ) ) {
			return;
		}

		// Trigger taxonomy not found
		if ( empty( $required_post_taxonomy ) ) {
			return;
		}

		// Term taxonomy not found
		if ( empty( $required_post_term ) ) {
			return;
		}

		$user_obj = get_user_by( 'ID', (int) $post->post_author );
		// Author doesn't exist anymore
		if ( ! $user_obj instanceof \WP_User ) {
			return;
		}

		// Match recipe types with current $post
		$post_type_recipes = $this->get_recipes_post_type_matches( $recipes, $required_post_type, $post );

		// No post type matched, bail
		if ( empty( $post_type_recipes ) ) {
			return;
		}

		// Match taxonomy types with current $post
		$taxonomy_recipes = $this->get_recipes_taxonomy_matches( $required_post_taxonomy, $post );

		// No taxonomies found, bail
		if ( empty( $taxonomy_recipes ) ) {
			return;
		}

		// Match terms with current $post
		$terms_recipe = $this->get_recipes_term_matches( $required_post_term, $required_post_taxonomy, $post );

		// No terms found, bail
		if ( empty( $terms_recipe ) ) {
			return;
		}

		// Find common recipes between post type + taxonomies + terms
		$matched = array_intersect( $post_type_recipes, $taxonomy_recipes, $terms_recipe );
		// Empty, bail
		if ( empty( $matched ) ) {
			return;
		}
		// build matched recipes ids array
		$matched_recipe_ids = $this->get_matched_recipes( $matched );

		if ( empty( $matched_recipe_ids ) ) {
			return;
		}

		// Complete trigger
		$this->complete_trigger( $matched_recipe_ids, $user_id, $post );
	}

	/**
	 * @param $matched_recipe_ids
	 * @param $user_id
	 * @param $post
	 *
	 * @return void
	 */
	private function complete_trigger( $matched_recipe_ids, $user_id, $post ) {

		foreach ( $matched_recipe_ids as $recipe_trigger ) {

			foreach ( $recipe_trigger as $recipe_id => $trigger_id ) {

				$recipe_id  = absint( $recipe_id );
				$trigger_id = absint( $trigger_id );
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
					$this->store_tokens( $result, $user_id, $post, $recipe_id, $trigger_id );
					Automator()->maybe_trigger_complete( $result['args'] );
				}
			}
		}
	}

	/**
	 * @param $result
	 * @param $user_id
	 * @param $post
	 * @param $recipe_id
	 * @param $trigger_id
	 *
	 * @return void
	 */
	private function store_tokens( $result, $user_id, $post, $recipe_id, $trigger_id ) {
		$trigger_meta = array(
			'user_id'        => $user_id,
			'trigger_id'     => $result['args']['trigger_id'],
			'trigger_log_id' => $result['args']['trigger_log_id'],
			'run_number'     => $result['args']['run_number'],
		);

		// post_id Token
		Automator()->db->token->save( 'post_id', $post->ID, $trigger_meta );

		if ( isset( $this->terms_list[ $recipe_id ][ $trigger_id ] ) ) {
			$terms = implode( ', ', $this->terms_list[ $recipe_id ][ $trigger_id ] );
			Automator()->db->token->save( 'WPTAXONOMYTERM', $terms, $trigger_meta );
		}

		if ( isset( $this->taxonomy_list[ $recipe_id ][ $trigger_id ] ) ) {
			$taxonomies = implode( ', ', $this->taxonomy_list[ $recipe_id ][ $trigger_id ] );
			Automator()->db->token->save( 'WPTAXONOMIES', $taxonomies, $trigger_meta );
		}

		$this->trigger_meta_log = $trigger_meta;
		$this->result           = $result;
	}

	/**
	 * Identify recipes that match criteria based on post type
	 *
	 * @param $recipes
	 * @param $required_post_type
	 * @param $post
	 *
	 * @return array
	 */
	private function get_recipes_post_type_matches( $recipes, $required_post_type, $post ) {
		$matched = array();
		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = absint( $trigger['ID'] );
				// required post type
				if ( '0' === (string) $required_post_type[ $recipe_id ][ $trigger_id ] || (string) $post->post_type === (string) $required_post_type[ $recipe_id ][ $trigger_id ] ) {
					$matched[]                           = $recipe_id;
					$this->matched_recipes[ $recipe_id ] = $recipe;
				}
			}
		}

		return array_unique( $matched );
	}

	/**
	 * Identify recipes that match criteria based on taxonomy
	 *
	 * @param $required_post_taxonomy
	 * @param $post
	 *
	 * @return array
	 */
	private function get_recipes_taxonomy_matches( $required_post_taxonomy, $post ) {
		$matched = array();
		$post_id = $post->ID;

		foreach ( $this->matched_recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = absint( $trigger['ID'] );
				if ( ! isset( $required_post_taxonomy[ $recipe_id ] ) ) {
					continue;
				}
				if ( ! isset( $required_post_taxonomy[ $recipe_id ][ $trigger_id ] ) ) {
					continue;
				}
				// if any taxonomy
				if ( '0' === (string) $required_post_taxonomy[ $recipe_id ][ $trigger_id ] ) {
					$post_terms = $this->get_all_post_tax( $post_id, $post->post_type, $recipe_id, $trigger_id );
					foreach ( $post_terms as $term ) {
						$this->terms_list[ $recipe_id ][ $trigger_id ][ $term->term_id ] = $term->name;
					}

					$matched[] = $recipe_id;
					continue;
				}
				// let's check if the post has any taxonomy in the selected taxonomy
				$post_terms = $this->get_taxonomy( $post_id, $required_post_taxonomy[ $recipe_id ][ $trigger_id ] );

				if ( empty( $post_terms ) ) {
					continue;
				}
				$matched[] = $recipe_id;
				foreach ( $post_terms as $term ) {
					$this->terms_list[ $recipe_id ][ $trigger_id ][ $term->term_id ] = $term->name;

					$_taxonomy = get_taxonomy( $term->taxonomy );
					if ( ! empty( $_taxonomy ) ) {
						$this->taxonomy_list[ $recipe_id ][ $trigger_id ][ $_taxonomy->name ] = $_taxonomy->labels->singular_name;
					}
				}
			}
		}

		return array_unique( $matched );
	}

	/**
	 * Identify recipes that match criteria based on term
	 *
	 * @param $required_post_term
	 * @param $required_post_taxonomy
	 * @param $post
	 *
	 * @return array
	 */
	private function get_recipes_term_matches( $required_post_term, $required_post_taxonomy, $post ) {
		$matched = array();
		$post_id = $post->ID;

		foreach ( $this->matched_recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = absint( $trigger['ID'] );
				if ( ! isset( $required_post_term[ $recipe_id ] ) ) {
					continue;
				}
				if ( ! isset( $required_post_term[ $recipe_id ][ $trigger_id ] ) ) {
					continue;
				}
				// if any term
				if ( '0' === (string) $required_post_term[ $recipe_id ][ $trigger_id ] ) {
					$matched[] = $recipe_id;
					continue;
				}
				if ( ! isset( $required_post_taxonomy[ $recipe_id ] ) ) {
					continue;
				}
				if ( ! isset( $required_post_taxonomy[ $recipe_id ][ $trigger_id ] ) ) {
					continue;
				}
				// if the term is specific then tax and post type are also specified
				$post_terms = $this->get_taxonomy( $post_id, $required_post_taxonomy[ $recipe_id ][ $trigger_id ] );

				if ( empty( $post_terms ) ) {
					continue;
				}
				// check if the post has the required term
				$post_term_ids = array_map( 'absint', array_column( $post_terms, 'term_id' ) );
				if ( ! in_array( absint( $required_post_term[ $recipe_id ][ $trigger_id ] ), $post_term_ids, true ) ) {
					continue;
				}
				$matched[] = $recipe_id;

				// Specific Term
				$term = get_term( $required_post_term[ $recipe_id ][ $trigger_id ] );
				if ( ! array_key_exists( $term->term_id, $this->terms_list[ $recipe_id ][ $trigger_id ] ) ) {
					$this->terms_list[ $recipe_id ][ $trigger_id ][ $term->term_id ] = $term->name;
				}
			}
		}

		return array_unique( $matched );
	}

	/**
	 * @param $post_id
	 * @param $tax
	 *
	 * @return array|\WP_Error
	 */
	private function get_taxonomy( $post_id, $tax = '' ) {
		return wp_get_post_terms( $post_id, $tax );
	}

	/**
	 * @param $matched
	 *
	 * @return array
	 */
	private function get_matched_recipes( $matched ) {
		if ( empty( $matched ) || empty( $this->matched_recipes ) ) {
			return array();
		}
		$matched_recipe_ids = array();
		foreach ( $this->matched_recipes as $recipe_id => $recipe ) {
			$recipe_id = absint( $recipe_id );
			if ( ! in_array( $recipe_id, $matched, true ) ) {
				continue;
			}
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id                         = absint( $trigger['ID'] );
				$matched_recipe_ids[][ $recipe_id ] = $trigger_id;
			}
		}

		return $matched_recipe_ids;
	}

	/**
	 * Used when Any taxonomy, Any terms are used
	 *
	 * @param $post_id
	 * @param $post_type
	 * @param $recipe_id
	 * @param $trigger_id
	 *
	 * @return array
	 */
	private function get_all_post_tax( $post_id, $post_type, $recipe_id, $trigger_id ) {
		$all_terms  = array();
		$post_type  = get_post_type_object( $post_type );
		$taxonomies = get_object_taxonomies( $post_type->name, 'object' );
		if ( empty( $taxonomies ) ) {
			return $all_terms;
		}
		foreach ( $taxonomies as $taxonomy ) {
			$post_terms = wp_get_post_terms( $post_id, $taxonomy->name );
			if ( empty( $post_terms ) ) {
				continue;
			}
			$this->taxonomy_list[ $recipe_id ][ $trigger_id ][ $taxonomy->name ] = $taxonomy->labels->singular_name;
			foreach ( $post_terms as $term ) {
				$all_terms[] = $term;
			}
		}

		return $all_terms;
	}

}
