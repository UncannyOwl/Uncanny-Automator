<?php

namespace Uncanny_Automator\Integrations\Wp;

use Uncanny_Automator\Recipe\Log_Properties;

/**
 * Class ANON_WP_POST_PUBLISHED_IN_TAXONOMY
 *
 * @package Uncanny_Automator
 * @property Wp_Helpers $item_helpers
 */
class ANON_WP_POST_PUBLISHED_IN_TAXONOMY extends \Uncanny_Automator\Recipe\Trigger {

	use Log_Properties;

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'WP_POST_PUBLISHED_IN_TAXONOMY', 'WP' )
			->trigger_meta( 'WPPOSTTYPES' )
			->trigger_type( 'anonymous' )
			->hook( 'wp_after_insert_post', 90, 4 );
	}

	/**
	 * Setup trigger configuration.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type are auto-applied from definition().
		$this->set_is_login_required( false );
		// translators: %1$s Post type, %2$s Taxonomy term, %3$s Taxonomy.
		$this->set_sentence(
			sprintf(
				esc_html_x( '{{A type of post:%1$s}} with {{a taxonomy term:%2$s}} in {{a taxonomy:%3$s}} is published', 'WordPress', 'uncanny-automator' ),
				$this->get_trigger_meta(),
				'WPTAXONOMYTERM:' . $this->get_trigger_meta(),
				'WPTAXONOMIES:' . $this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'A post in a taxonomy is published', 'WordPress', 'uncanny-automator' ) );
		$this->set_loopable_tokens( Wp_Helpers::common_trigger_loopable_tokens() );
	}

	/**
	 * Define trigger options.
	 *
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code'           => 'WPPOSTTYPES',
				'label'                 => esc_html_x( 'Post type', 'WordPress', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => array(),
				'supports_custom_value' => false,
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'post_types_with_taxonomies' ),
			),
			array(
				'option_code'           => 'WPTAXONOMIES',
				'label'                 => esc_html_x( 'Taxonomy', 'WordPress', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => array(),
				'supports_custom_value' => false,
				'remote_data'           => $this->item_helpers->remote_data_parent_config( 'taxonomies_by_type', array( 'WPPOSTTYPES' ) ),
			),
			array(
				'option_code'           => 'WPTAXONOMYTERM',
				'label'                 => esc_html_x( 'Term', 'WordPress', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => array(),
				'supports_custom_value' => false,
				'remote_data'           => $this->item_helpers->remote_data_parent_config( 'terms_by_taxonomy', array( 'WPTAXONOMIES' ) ),
			),
			array(
				'option_code' => 'WPTAXONOMIES_CHILDREN',
				'label'       => esc_html_x( 'Include child terms', 'WordPress', 'uncanny-automator' ),
				'input_type'  => 'checkbox',
				'required'    => false,
				'description' => esc_html_x( 'If checked, children of the selected term will also trigger the recipe.', 'WordPress', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Define available tokens.
	 *
	 * @param array $trigger The trigger settings.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge(
			Wp_Shared_Tokens::post_core_tokens(),
			Wp_Shared_Tokens::post_featured_image_tokens(),
			Wp_Shared_Tokens::post_author_tokens(),
			Wp_Shared_Tokens::post_date_tokens(),
			Wp_Shared_Tokens::post_taxonomy_tokens(),
			Wp_Shared_Tokens::post_taxonomy_loopable_tokens()
		);
	}

	/**
	 * Validate trigger against hook arguments.
	 *
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		list( $post_id, $post, $update, $post_before ) = $hook_args;

		// Only run when posts are published for the first time.
		if ( ! \Automator()->utilities->is_wp_post_being_published( $post, $post_before ) ) {
			return false;
		}

		// Deprecated since 7.0 - cron-based processing replaced by wp_after_insert_post.
		apply_filters_deprecated( 'automator_wp_user_creates_post_cron_enabled', array( true, $post_id, $post, $update, $post_before, $this ), '7.1' );

		// Deduplication check.
		$transient_key = 'automator_trigger_' . $this->get_trigger_code() . '_' . $post_id;
		if ( false !== get_transient( $transient_key ) ) {
			return false;
		}
		set_transient( $transient_key, true, 10 );

		// Author must exist.
		$user_id = absint( isset( $post->post_author ) ? $post->post_author : 0 );
		$user    = get_user_by( 'ID', $user_id );
		if ( ! $user instanceof \WP_User ) {
			return false;
		}

		// Match post type. UI stores `-1` for "Any post type".
		$selected_post_type = (string) ( $trigger['meta']['WPPOSTTYPES'] ?? '-1' );
		if ( -1 !== intval( $selected_post_type ) && (string) $post->post_type !== $selected_post_type ) {
			return false;
		}

		// Match taxonomy. UI stores `-1` for "Any taxonomy".
		$selected_taxonomy = (string) ( $trigger['meta']['WPTAXONOMIES'] ?? '-1' );
		$any_taxonomy      = ( -1 === intval( $selected_taxonomy ) );
		if ( $any_taxonomy ) {
			// Any taxonomy -- post must have at least one term in any taxonomy.
			$has_terms  = false;
			$taxonomies = get_object_taxonomies( $post->post_type, 'objects' );
			if ( ! empty( $taxonomies ) ) {
				foreach ( $taxonomies as $tax_obj ) {
					$terms = wp_get_post_terms( $post_id, $tax_obj->name );
					if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
						$has_terms = true;
						break;
					}
				}
			}
			if ( ! $has_terms ) {
				return false;
			}
		} else {
			// Specific taxonomy -- post must have terms in it.
			$post_terms = wp_get_post_terms( $post_id, $selected_taxonomy );
			if ( empty( $post_terms ) || is_wp_error( $post_terms ) ) {
				return false;
			}
		}

		// Match term. UI stores `-1` for "Any term".
		$selected_term = (string) ( $trigger['meta']['WPTAXONOMYTERM'] ?? '-1' );
		if ( -1 !== intval( $selected_term ) ) {
			// Specific term selected.
			if ( $any_taxonomy ) {
				// Any taxonomy but specific term -- look across all taxonomies.
				$found_term = false;
				$taxonomies = get_object_taxonomies( $post->post_type, 'objects' );
				if ( ! empty( $taxonomies ) ) {
					foreach ( $taxonomies as $tax_obj ) {
						$terms    = wp_get_post_terms( $post_id, $tax_obj->name );
						$term_ids = array_map( 'absint', array_column( $terms, 'term_id' ) );
						if ( in_array( absint( $selected_term ), $term_ids, true ) ) {
							$found_term = true;
							break;
						}
					}
				}

				if ( ! $found_term ) {
					$found_term = $this->maybe_match_child_term( $trigger, $post_id, $selected_taxonomy, $selected_term, $any_taxonomy );
				}

				if ( ! $found_term ) {
					return false;
				}
			} else {
				// Specific taxonomy and specific term.
				$post_terms = wp_get_post_terms( $post_id, $selected_taxonomy );
				$term_ids   = array_map( 'absint', array_column( $post_terms, 'term_id' ) );

				if ( ! in_array( absint( $selected_term ), $term_ids, true ) ) {
					// Check child terms.
					if ( ! $this->maybe_match_child_term( $trigger, $post_id, $selected_taxonomy, $selected_term, $any_taxonomy ) ) {
						return false;
					}
				}
			}
		}

		// Set user ID to the post author.
		$this->set_user_id( $user_id );

		return true;
	}

	/**
	 * Check if a child term matches when "include children" is enabled.
	 *
	 * @param array  $trigger            The trigger settings.
	 * @param int    $post_id            The post ID.
	 * @param string $selected_taxonomy  The selected taxonomy.
	 * @param string $selected_term      The selected term ID.
	 * @param bool   $any_taxonomy       Whether the user selected "Any taxonomy".
	 *
	 * @return bool
	 */
	private function maybe_match_child_term( $trigger, $post_id, $selected_taxonomy, $selected_term, $any_taxonomy = false ) {

		$include_children = $trigger['meta']['WPTAXONOMIES_CHILDREN'] ?? '';
		$include_children = filter_var( strtolower( (string) $include_children ), FILTER_VALIDATE_BOOLEAN );

		if ( ! $include_children ) {
			return false;
		}

		// Determine taxonomy to search -- if "any", search all taxonomies for the post type.
		if ( $any_taxonomy ) {
			$post      = get_post( $post_id );
			$tax_slugs = get_object_taxonomies( $post->post_type );
		} else {
			$tax_slugs = array( $selected_taxonomy );
		}

		foreach ( $tax_slugs as $tax_slug ) {
			$post_terms = wp_get_post_terms( $post_id, $tax_slug );
			if ( empty( $post_terms ) || is_wp_error( $post_terms ) ) {
				continue;
			}

			$child_term = $this->item_helpers->get_term_child_of( $post_terms, $selected_term, $tax_slug, $post_id );
			if ( ! empty( $child_term ) ) {
				$this->set_trigger_log_properties(
					array(
						'type'       => 'string',
						'label'      => esc_html_x( 'Matched Child Term', 'WordPress', 'uncanny-automator' ),
						'value'      => $child_term->term_id . '( ' . $child_term->name . ' )',
						'attributes' => array(),
					)
				);

				return true;
			}
		}

		return false;
	}

	/**
	 * Hydrate token values from hook arguments.
	 *
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $post_id ) = $hook_args;
		$post_id         = (int) $post_id;

		return array_merge(
			Wp_Shared_Tokens::hydrate_post_core_tokens( $post_id ),
			Wp_Shared_Tokens::hydrate_post_featured_image_tokens( $post_id ),
			Wp_Shared_Tokens::hydrate_post_author_tokens( $post_id ),
			Wp_Shared_Tokens::hydrate_post_date_tokens( $post_id ),
			Wp_Shared_Tokens::hydrate_post_taxonomy_tokens( $post_id )
		);
	}
}
