<?php

namespace Uncanny_Automator\Integrations\Wp;

use Uncanny_Automator\Recipe\Log_Properties;

/**
 * Class ANON_WP_UPDATES_POST_IN_TAXONOMY
 *
 * @package Uncanny_Automator
 * @property Wp_Helpers $item_helpers
 */
class ANON_WP_UPDATES_POST_IN_TAXONOMY extends \Uncanny_Automator\Recipe\Trigger {

	use Log_Properties;

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'ANON_POST_UPDATED_IN_TAXONOMY', 'WP' )
			->trigger_meta( 'WPTAXONOMIES' )
			->trigger_type( 'anonymous' )
			->hook( 'post_updated', 10, 3 );
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
				esc_html_x( '{{A type of post:%1$s}} with {{a taxonomy term:%2$s}} in {{a taxonomy:%3$s}} is updated', 'WordPress', 'uncanny-automator' ),
				'WPPOSTTYPES:' . $this->get_trigger_meta(),
				'WPTAXONOMYTERM:' . $this->get_trigger_meta(),
				'WPTAXONOMIES:' . $this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'A post in a taxonomy is updated', 'WordPress', 'uncanny-automator' ) );
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

		list( $post_id, $post_after, $post_before ) = $hook_args;

		// Bail on autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		// Maybe bail on REST requests. Default is to ALLOW — Gutenberg saves
		// every post via REST, so blocking REST_REQUEST silently kills the
		// trigger for any block-editor edit. Sites that want to opt back into
		// the old block-on-REST behaviour can return true from the filter.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			if ( apply_filters( 'automator_wp_post_updates_prevent_trigger_on_rest_requests', false, $post_id ) ) {
				return false;
			}
		}

		// Prevent if publishing a new post.
		if ( 'publish' === $post_after->post_status && 'publish' !== $post_before->post_status ) {
			return false;
		}

		// Exclude ignored statuses.
		$ignore_statuses = apply_filters(
			'automator_wp_post_updates_ignore_statuses',
			array( 'trash', 'draft', 'future' ),
			$post_id,
			$post_after,
			$post_before
		);

		if ( in_array( $post_after->post_status, $ignore_statuses, true ) ) {
			return false;
		}

		// Maybe bail for non-public posts (filterable).
		$include_non_public = apply_filters( 'automator_wp_post_updates_include_non_public_posts', false, $post_id );
		if ( false === $include_non_public ) {
			$post_type_obj = get_post_type_object( $post_after->post_type );
			if ( false === $post_type_obj->public ) {
				return false;
			}
		}

		// Match post type.
		$selected_post_type = $trigger['meta']['WPPOSTTYPES'] ?? '-1';
		if (
			intval( '-1' ) !== intval( $selected_post_type )
			&& (string) $post_after->post_type !== (string) $selected_post_type
			&& ! empty( $selected_post_type )
		) {
			return false;
		}

		// Match taxonomy. UI stores `-1` for "Any taxonomy".
		$selected_taxonomy = (string) ( $trigger['meta'][ $this->get_trigger_meta() ] ?? '-1' );
		$any_taxonomy      = ( -1 === intval( $selected_taxonomy ) );
		if ( $any_taxonomy ) {
			// Any taxonomy -- post must have at least one term in any taxonomy.
			$has_terms  = false;
			$taxonomies = get_object_taxonomies( $post_after->post_type, 'objects' );
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
			$post_terms = wp_get_post_terms( $post_id, $selected_taxonomy );
			if ( empty( $post_terms ) || is_wp_error( $post_terms ) ) {
				return false;
			}
		}

		// Match term. UI stores `-1` for "Any term".
		$selected_term = (string) ( $trigger['meta']['WPTAXONOMYTERM'] ?? '-1' );
		if ( -1 !== intval( $selected_term ) ) {
			$matched = $this->match_term( $post_id, $post_after, $selected_taxonomy, $selected_term, $trigger, $any_taxonomy );
			if ( ! $matched ) {
				return false;
			}
		}

		// Set user ID to post author or current user.
		$user_id = 0 !== (int) $post_after->post_author ? (int) $post_after->post_author : get_current_user_id();
		$this->set_user_id( $user_id );

		return $this->dedupe_recent_fire( $post_id );
	}

	/**
	 * Suppress near-duplicate fires of this trigger. Gutenberg's REST save
	 * can invoke post_updated twice within milliseconds (autosave path
	 * fires once, then the actual save fires again), and without this
	 * guard a recipe would run twice for one user-visible edit.
	 *
	 * Keys on trigger class + post + user, so concurrent edits from
	 * different users on the same post stay independent.
	 *
	 * @param int $post_id Updated post ID.
	 *
	 * @return bool True if the trigger should fire (slot now claimed for
	 *              5 seconds); false if a fire already claimed the slot.
	 */
	private function dedupe_recent_fire( $post_id ) {
		$key = 'uap_post_update_dedup_' . md5( static::class . '_' . absint( $post_id ) . '_' . get_current_user_id() );
		if ( false !== get_transient( $key ) ) {
			return false;
		}
		set_transient( $key, 1, 5 );
		return true;
	}

	/**
	 * Match a specific term (with optional child-term support).
	 *
	 * @param int      $post_id            The post ID.
	 * @param \WP_Post $post_after         The post after update.
	 * @param string   $selected_taxonomy  The selected taxonomy slug.
	 * @param string   $selected_term      The selected term ID.
	 * @param array    $trigger            The trigger settings.
	 * @param bool     $any_taxonomy       Whether the user selected "Any taxonomy".
	 *
	 * @return bool
	 */
	private function match_term( $post_id, $post_after, $selected_taxonomy, $selected_term, $trigger, $any_taxonomy = false ) {

		if ( $any_taxonomy ) {
			// Any taxonomy -- search across all taxonomies.
			$taxonomies = get_object_taxonomies( $post_after->post_type, 'objects' );
			foreach ( $taxonomies as $tax_obj ) {
				$terms    = wp_get_post_terms( $post_id, $tax_obj->name );
				$term_ids = array_map( 'absint', array_column( $terms, 'term_id' ) );
				if ( in_array( absint( $selected_term ), $term_ids, true ) ) {
					return true;
				}
			}
		} else {
			$post_terms = wp_get_post_terms( $post_id, $selected_taxonomy );
			$term_ids   = array_map( 'absint', array_column( $post_terms, 'term_id' ) );
			if ( in_array( absint( $selected_term ), $term_ids, true ) ) {
				return true;
			}
		}

		// Check child terms.
		return $this->maybe_match_child_term( $trigger, $post_id, $selected_taxonomy, $selected_term, $post_after, $any_taxonomy );
	}

	/**
	 * Check if a child term matches when "include children" is enabled.
	 *
	 * @param array    $trigger            The trigger settings.
	 * @param int      $post_id            The post ID.
	 * @param string   $selected_taxonomy  The selected taxonomy.
	 * @param string   $selected_term      The selected term ID.
	 * @param \WP_Post $post_after         The post after update.
	 * @param bool     $any_taxonomy       Whether the user selected "Any taxonomy".
	 *
	 * @return bool
	 */
	private function maybe_match_child_term( $trigger, $post_id, $selected_taxonomy, $selected_term, $post_after, $any_taxonomy = false ) {

		$include_children = $trigger['meta']['WPTAXONOMIES_CHILDREN'] ?? '';
		$include_children = filter_var( strtolower( (string) $include_children ), FILTER_VALIDATE_BOOLEAN );

		if ( ! $include_children ) {
			return false;
		}

		if ( $any_taxonomy ) {
			$tax_slugs = get_object_taxonomies( $post_after->post_type );
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
