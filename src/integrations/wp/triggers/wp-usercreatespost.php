<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Recipe\Log_Properties;

/**
 *
 */
class WP_USERCREATESPOST {

	use Log_Properties;

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

	/**
	 *  Property child_term_log_properties
	 *
	 * @var array
	 */
	private $child_term_log_properties = array();

	/**
	 * @var string
	 */
	private $action_hook;

	/**
	 * @var int
	 */
	private $internal_post_id = 288662867; // Automator in numbers

	/**
	 * WP_USERCREATESPOST constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->trigger_code = 'USERSPOST';
		$this->trigger_meta = 'WPPOSTTYPES';
		$this->action_hook  = 'automator_userspost_posts_published';

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

		// Legacy support for posts published in a specific post type
		add_action( 'uoa_wp_after_insert_post', array( $this, 'post_published' ), 99, 1 );

		// New support for posts published in a specific post type
		add_action( $this->action_hook, array( $this, 'posts_published' ), 99 );
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function define_trigger() {
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
			'select_option_name'  => esc_attr__( 'A user publishes a post in a taxonomy', 'uncanny-automator' ),
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
	 * @return array
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
						Automator()->helpers->recipe->field->select_field( 'WPTAXONOMYTERM', esc_attr__( 'Term', 'uncanny-automator' ) ),
						Automator()->helpers->recipe->wp->options->conditional_child_taxonomy_checkbox(),
					),
				),
			)
		);

		return $options;
	}

	/**
	 * @return void
	 */
	public function posts_published() {
		$post_metas = Wp_Helpers::get_pending_posts( $this->action_hook );
		if ( empty( $post_metas ) ) {
			return;
		}

		$post_ids = array();

		// Process each post ID
		foreach ( $post_metas as $post_meta ) {
			$post_id = absint( $post_meta->meta_value );

			if ( in_array( $post_id, $post_ids, true ) ) {
				Wp_Helpers::delete_post_after_trigger( $post_meta );
				continue;
			}

			$post_ids[] = $post_id;

			if ( Wp_Helpers::delete_post_after_trigger( $post_meta ) ) {
				$this->post_published( $post_id );
			}
		}

	}

	/**
	 * @param $post_id
	 * @param $post
	 * @param $update
	 * @param $post_before
	 *
	 * @return void|null
	 */
	public function schedule_a_post( $post_id, $post, $update, $post_before ) {
		// only run when posts
		// are published first time
		if ( ! Automator()->utilities->is_wp_post_being_published( $post, $post_before ) ) {
			return;
		}

		$cron_enabled = apply_filters( 'automator_wp_user_creates_post_cron_enabled', true, $post_id, $post, $update, $post_before, $this );

		// Allow people to disable cron processing.
		if ( false === $cron_enabled ) {
			// Immediately run post_publised if cron not enabled.
			return $this->post_published( $post_id );
		}

		// Add the post ID to the post meta table.
		Wp_Helpers::add_pending_post( $post_id, $this->action_hook );
	}

	/**
	 * Fires when a post is transitioned from one status to another.
	 * Fires when a post is transitioned from one status to another.
	 *
	 * @param $post_id
	 *
	 * @return void
	 */
	public function post_published( $post_id ) {
		// Check if the post has already been postponed to avoid duplicate recipe runs.
		if ( $this->maybe_post_postponed( $post_id ) ) {
			return;
		}

		$post                      = get_post( $post_id );
		$this->post                = $post;
		$user_id                   = absint( isset( $post->post_author ) ? $post->post_author : 0 );
		$recipes                   = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_post_type        = Automator()->get->meta_from_recipes( $recipes, 'WPPOSTTYPES' );
		$required_post_taxonomy    = Automator()->get->meta_from_recipes( $recipes, 'WPTAXONOMIES' );
		$required_post_term        = Automator()->get->meta_from_recipes( $recipes, 'WPTAXONOMYTERM' );
		$include_taxonomy_children = Automator()->get->meta_from_recipes( $recipes, 'WPTAXONOMIES_CHILDREN' );
		$include_taxonomy_children = ! empty( $include_taxonomy_children ) ? $include_taxonomy_children : array();

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
			Wp_Helpers::requeue_post( $post_id, $this->action_hook );
			return;
		}

		// Match terms with current $post
		$terms_recipe = $this->get_recipes_term_matches( $required_post_term, $required_post_taxonomy, $post, $include_taxonomy_children );

		// No terms found, bail
		if ( empty( $terms_recipe ) ) {
			Wp_Helpers::requeue_post( $post_id, $this->action_hook );
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
	 * @param      $post
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

	/**
	 * @param $post_id
	 * @param bool $legacy
	 *
	 * @return bool
	 */
	private function maybe_post_postponed( $post_id ) {

		$post         = get_post( $post_id );
		$cron_enabled = apply_filters( 'automator_wp_user_creates_post_cron_enabled', true, $post_id, $post, false, $post, $this );

		// Allow people to disable cron processing.
		if ( false === $cron_enabled ) {
			// Immediately run post_publised if cron not enabled.
			return false;
		}

		return Wp_Helpers::maybe_post_postponed( $post_id, $this->action_hook );
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

					if ( isset( $this->child_term_log_properties[ $recipe_id ] ) ) {
						if ( isset( $this->child_term_log_properties[ $recipe_id ][ $trigger_id ] ) ) {
							$this->set_trigger_log_properties( $this->child_term_log_properties[ $recipe_id ][ $trigger_id ] );
						}
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
	 * @param $include_term_children
	 *
	 * @return array
	 */
	private function get_recipes_term_matches( $required_post_term, $required_post_taxonomy, $post, $include_term_children ) {
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

				// Check if we should be including Children of the selected term.
				$include_children = isset( $include_term_children[ $recipe_id ] ) ? $include_term_children[ $recipe_id ] : array();
				$include_children = isset( $include_children[ $trigger_id ] ) ? $include_children[ $trigger_id ] : false;
				$include_children = filter_var( strtolower( $include_children ), FILTER_VALIDATE_BOOLEAN );

				// if the term is specific then tax and post type are also specified
				$post_terms = $this->get_taxonomy( $post_id, $required_post_taxonomy[ $recipe_id ][ $trigger_id ] );

				if ( empty( $post_terms ) ) {
					continue;
				}

				// check if the post has the required term
				$post_term_ids = array_map( 'absint', array_column( $post_terms, 'term_id' ) );
				if ( ! in_array( absint( $required_post_term[ $recipe_id ][ $trigger_id ] ), $post_term_ids, true ) ) {
					$child_term = false;
					if ( $include_children ) {
						$child_term = Automator()->helpers->recipe->wp->options->get_term_child_of(
							$post_terms,
							$required_post_term[ $recipe_id ][ $trigger_id ],
							$required_post_taxonomy[ $recipe_id ][ $trigger_id ],
							$post_id
						);
					}

					// Term or Child Term not found.
					if ( empty( $child_term ) ) {
						continue;
					} else {
						$this->set_child_matched_log_properties( $recipe_id, $trigger_id, $child_term );
					}
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

	/**
	 * Set matched child term info to log properties.
	 *
	 * @param $recipe_id
	 * @param $trigger_id
	 * @param $child_term
	 *
	 * @return void
	 */
	private function set_child_matched_log_properties( $recipe_id, $trigger_id, $child_term ) {
		if ( ! isset( $this->child_term_log_properties[ $recipe_id ] ) ) {
			$this->child_term_log_properties[ $recipe_id ] = array();
		}
		$this->child_term_log_properties[ $recipe_id ][ $trigger_id ] = array(
			'type'       => 'string',
			'label'      => _x( 'Matched Child Term', 'WordPress', 'uncanny-automator' ),
			'value'      => $child_term->term_id . '( ' . $child_term->name . ' )',
			'attributes' => array(),
		);
	}
}
