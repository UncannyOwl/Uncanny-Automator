<?php

namespace Uncanny_Automator\Integrations\Wp;

use Uncanny_Automator\Recipe\Log_Properties;

/**
 * Class WP_USERCREATESPOST
 *
 * @package Uncanny_Automator
 * @property Wp_Helpers $item_helpers
 */
class WP_USERCREATESPOST extends \Uncanny_Automator\Recipe\Trigger {

	use Log_Properties;

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'USERSPOST', 'WP' )
			->trigger_meta( 'WPPOSTTYPES' )
			->hook( 'wp_after_insert_post', 90, 4 );
	}

	/**
	 * Setup trigger configuration.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type are auto-applied from definition().
		$this->set_is_login_required( true );
		// translators: %1$s is a post type, %2$s is a taxonomy term, %3$s is a taxonomy.
		$this->set_sentence(
			sprintf(
				esc_html_x( 'A user publishes a {{type of:%1$s}} post with {{a taxonomy term:%2$s}} in {{a taxonomy:%3$s}}', 'WordPress', 'uncanny-automator' ),
				$this->get_trigger_meta(),
				'WPTAXONOMYTERM:' . $this->get_trigger_meta(),
				'WPTAXONOMIES:' . $this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'A user publishes a post in a taxonomy', 'WordPress', 'uncanny-automator' ) );
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
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'post_types' ),
			),
			array(
				'option_code'           => 'WPTAXONOMIES',
				'label'                 => esc_html_x( 'Taxonomy', 'WordPress', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => false,
				'options'               => array(),
				'supports_custom_value' => false,
				'remote_data'           => $this->item_helpers->remote_data_parent_config( 'taxonomies_by_type', array( 'WPPOSTTYPES' ) ),
			),
			array(
				'option_code'           => 'WPTAXONOMYTERM',
				'label'                 => esc_html_x( 'Term', 'WordPress', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => false,
				'options'               => array(),
				'supports_custom_value' => false,
				'remote_data'           => $this->item_helpers->remote_data_parent_config( 'terms_by_taxonomy', array( 'WPTAXONOMIES' ) ),
			),
			$this->item_helpers->conditional_child_taxonomy_checkbox(),
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
			Wp_Shared_Tokens::post_author_tokens(),
			Wp_Shared_Tokens::post_featured_image_tokens(),
			Wp_Shared_Tokens::post_date_tokens(),
			Wp_Shared_Tokens::post_taxonomy_tokens()
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

		// Only fire when a post is being published for the first time.
		if ( ! \Automator()->utilities->is_wp_post_being_published( $post, $post_before ) ) {
			return false;
		}

		// Deprecated since 7.0 - cron-based processing replaced by wp_after_insert_post.
		apply_filters_deprecated( 'automator_wp_user_creates_post_cron_enabled', array( true, $post_id, $post, $update, $post_before, $this ), '7.1' );

		// Deduplication -- prevent multiple firings from other plugins.
		$transient_key = 'automator_trigger_' . $this->get_trigger_code() . '_' . $post_id;
		if ( false !== get_transient( $transient_key ) ) {
			return false;
		}
		set_transient( $transient_key, true, 10 );

		// Author must exist.
		$user_obj = get_user_by( 'ID', (int) $post->post_author );
		if ( ! $user_obj instanceof \WP_User ) {
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
		if ( ! $any_taxonomy ) {
			$post_terms = wp_get_post_terms( $post_id, $selected_taxonomy );
			if ( empty( $post_terms ) || is_wp_error( $post_terms ) ) {
				return false;
			}
		}

		// Match term. UI stores `-1` for "Any term".
		$selected_term = (string) ( $trigger['meta']['WPTAXONOMYTERM'] ?? '-1' );
		if ( -1 !== intval( $selected_term ) ) {
			$taxonomy_for_terms = $selected_taxonomy;
			if ( $any_taxonomy ) {
				// Any taxonomy -- check all taxonomies for the term.
				$term_obj = get_term( absint( $selected_term ) );
				if ( ! $term_obj || is_wp_error( $term_obj ) ) {
					return false;
				}
				$taxonomy_for_terms = $term_obj->taxonomy;
			}

			$post_terms    = wp_get_post_terms( $post_id, $taxonomy_for_terms );
			$post_term_ids = array_map( 'absint', array_column( $post_terms, 'term_id' ) );

			if ( ! in_array( absint( $selected_term ), $post_term_ids, true ) ) {
				// Check child terms if the checkbox is enabled.
				$include_children = $trigger['meta']['WPTAXONOMIES_CHILDREN'] ?? '';
				$include_children = filter_var( strtolower( (string) $include_children ), FILTER_VALIDATE_BOOLEAN );

				if ( $include_children ) {
					$child_term = $this->item_helpers->get_term_child_of(
						$post_terms,
						$selected_term,
						$taxonomy_for_terms,
						$post_id
					);
					if ( empty( $child_term ) ) {
						return false;
					}
					$this->set_trigger_log_properties(
						array(
							'type'       => 'string',
							'label'      => esc_html_x( 'Matched Child Term', 'WordPress', 'uncanny-automator' ),
							'value'      => $child_term->term_id . '( ' . $child_term->name . ' )',
							'attributes' => array(),
						)
					);
				} else {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Hydrate token values from hook arguments.
	 *
	 * @param array $trigger   The completed trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $post_id ) = $hook_args;
		$post_id         = (int) $post_id;

		return array_merge(
			Wp_Shared_Tokens::hydrate_post_core_tokens( $post_id ),
			Wp_Shared_Tokens::hydrate_post_author_tokens( $post_id ),
			Wp_Shared_Tokens::hydrate_post_featured_image_tokens( $post_id ),
			Wp_Shared_Tokens::hydrate_post_date_tokens( $post_id ),
			Wp_Shared_Tokens::hydrate_post_taxonomy_tokens( $post_id )
		);
	}
}
