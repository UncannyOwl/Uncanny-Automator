<?php

namespace Uncanny_Automator;

/**
 * Class ANON_WP_UPDATES_POST_IN_TAXONOMY
 *
 * @package Uncanny_Automator
 */
class ANON_WP_UPDATES_POST_IN_TAXONOMY {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WP';

	/**
	 * The trigger code.
	 *
	 * @var string
	 */
	private $trigger_code;

	/**
	 * The trigger meta.
	 *
	 * @var string
	 */
	private $trigger_meta;

	/**
	 * @var
	 */
	private $terms_list;
	/**
	 * @var
	 */
	private $taxonomy_list;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'ANON_POST_UPDATED_IN_TAXONOMY';
		$this->trigger_meta = 'WPTAXONOMIES';
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
	 *
	 * @throws \Exception
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/wordpress-core/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			'meta'                => $this->trigger_meta,
			'type'                => 'anonymous',
			/* translators: Everyone trigger - WordPress */
			'sentence'            => sprintf(
				esc_html_x( '{{A type of post:%1$s}} with {{a taxonomy term:%2$s}} in {{a taxonomy:%3$s}} is updated', 'WordPress', 'uncanny-automator' ),
				'WPPOSTTYPES:' . $this->trigger_meta,
				'WPTAXONOMIES:' . $this->trigger_meta,
				'WPTAXONOMYTERM:' . $this->trigger_meta
			),
			/* translators: Everyone trigger - WordPress */
			'select_option_name'  => esc_attr_x( 'A post in a taxonomy is updated', 'WordPress', 'uncanny-automator' ),
			'action'              => 'post_updated',
			'priority'            => 10,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'wp_post_updated' ),
			'options_callback'    => array( $this, 'load_options' ),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * load_options
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
				'use_zero_as_default' => intval( '-1' ),
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
	 * Validation function when the trigger action is hit
	 *
	 * @param $post_ID
	 * @param \WP_Post $post_after
	 * @param $post_before
	 */
	public function wp_post_updated( $post_ID, $post_after, $post_before ) {
		$include_non_public_posts = apply_filters( 'automator_wp_post_updates_include_non_public_posts', false, $post_ID );
		if ( false === $include_non_public_posts ) {
			$__object = get_post_type_object( $post_after->post_type );
			if ( false === $__object->public ) {
				return;
			}
		}
		$user_id = 0 !== $post_after->post_author ? $post_after->post_author : get_current_user_id();

		$recipes = Automator()->get->recipes_from_trigger_code( $this->trigger_code );

		$required_post_type = Automator()->get->meta_from_recipes( $recipes, 'WPPOSTTYPES' );

		$post = $post_after;

		$required_taxonomy = Automator()->get->meta_from_recipes( $recipes, 'WPTAXONOMIES' );

		$required_term = Automator()->get->meta_from_recipes( $recipes, 'WPTAXONOMYTERM' );

		$term_ids = array();

		$matched_recipe_ids = array();

		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];
				// is post type
				if (
					intval( '-1' ) === intval( $required_post_type[ $recipe_id ][ $trigger_id ] ) // any post type
					|| $post->post_type === $required_post_type[ $recipe_id ][ $trigger_id ] // specific post type
					|| empty( $required_post_type[ $recipe_id ][ $trigger_id ] ) // Backwards compatibility -- the trigger didnt have a post type selection pre 2.10
				) {

					// is post taxonomy
					if (
						// any taxonomy
						0 == $required_taxonomy[ $recipe_id ][ $trigger_id ] //phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
					) {

						$post_terms = $this->get_all_post_tax( $post_ID, $post->post_type, $recipe_id, $trigger_id );
						foreach ( $post_terms as $term ) {
							$this->terms_list[ $recipe_id ][ $trigger_id ][ $term->term_id ] = $term->name;
						}
						// any taxonomy also automatically means any term
						$matched_recipe_ids[] = array(
							'recipe_id'  => $recipe_id,
							'trigger_id' => $trigger_id,
							'post_id'    => $post_ID,
						);
					} else {
						$_tax_details = get_taxonomy( $required_taxonomy[ $recipe_id ][ $trigger_id ] );

						$this->taxonomy_list[ $recipe_id ][ $trigger_id ][ $_tax_details->name ] = $_tax_details->labels->singular_name;
						// specific taxonomy
						$post_terms = wp_get_post_terms( $post_ID, $required_taxonomy[ $recipe_id ][ $trigger_id ] );
						// is post term
						if (
							! empty( $post_terms ) // the taxonomy has terms
						) {

							// get all taxonomy term ids
							foreach ( $post_terms as $term ) {
								$term_ids[]                                                      = $term->term_id;
								$this->terms_list[ $recipe_id ][ $trigger_id ][ $term->term_id ] = $term->name;
							}
							$term_ids = array_map( 'absint', $term_ids );

							if (
								// any terms
								0 == $required_term[ $recipe_id ][ $trigger_id ] //phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
								|| in_array( absint( $required_term[ $recipe_id ][ $trigger_id ] ), $term_ids, true ) // specific term
							) {
								$matched_recipe_ids[] = array(
									'recipe_id'  => $recipe_id,
									'trigger_id' => $trigger_id,
									'post_id'    => $post_ID,
								);
							}
						}
					}
				}
			}
		}

		if ( ! empty( $matched_recipe_ids ) ) {
			foreach ( $matched_recipe_ids as $matched_recipe_id ) {
				$__recipe_id  = $matched_recipe_id['recipe_id'];
				$__trigger_id = $matched_recipe_id['trigger_id'];
				$pass_args    = array(
					'code'             => $this->trigger_code,
					'meta'             => $this->trigger_meta,
					'user_id'          => $user_id,
					'recipe_to_match'  => $matched_recipe_id['recipe_id'],
					'trigger_to_match' => $matched_recipe_id['trigger_id'],
					//'post_id'          => $post_ID,
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

							// post_id Token
							Automator()->db->token->save( 'post_id', $post_after->ID, $trigger_meta );

							if ( isset( $this->terms_list[ $__recipe_id ][ $__trigger_id ] ) ) {
								$terms = implode( ', ', $this->terms_list[ $__recipe_id ][ $__trigger_id ] );
								Automator()->db->token->save( 'WPTAXONOMYTERM', $terms, $trigger_meta );
							}

							if ( isset( $this->taxonomy_list[ $__recipe_id ][ $__trigger_id ] ) ) {
								$taxonomies = implode( ', ', $this->taxonomy_list[ $__recipe_id ][ $__trigger_id ] );
								Automator()->db->token->save( 'WPTAXONOMIES', $taxonomies, $trigger_meta );
							}

							Automator()->maybe_trigger_complete( $result['args'] );
						}
					}
				}
			}
		}
	}
}
