<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * Class WP_ANON_UPDATES_POST
 *
 * @package Uncanny_Automator
 * @property Wp_Helpers $item_helpers
 */
class WP_ANON_UPDATES_POST extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'WP_ANON_POST_UPDATED', 'WP' )
			->trigger_meta( 'WPPOSTTYPES' )
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
		// translators: %1$s Post type.
		$this->set_sentence(
			sprintf(
				esc_html_x( '{{A type of post:%1$s}} is updated', 'WordPress', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'A post is updated', 'WordPress', 'uncanny-automator' ) );
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
				'option_code'           => $this->get_trigger_meta(),
				'label'                 => esc_html_x( 'Post type', 'WordPress', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => array(),
				'supports_custom_value' => false,
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'post_types' ),
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

		if ( empty( $post_id ) ) {
			return false;
		}

		// Allow external override.
		if ( ! apply_filters( 'automator_wp_post_updates_post_updated', true, $post_id, $post_after, $post_before ) ) {
			return false;
		}

		// Match post type.
		$selected_post_type = $trigger['meta'][ $this->get_trigger_meta() ] ?? '-1';
		if ( intval( '-1' ) !== intval( $selected_post_type ) && (string) $post_after->post_type !== (string) $selected_post_type ) {
			return false;
		}

		// Set user ID to the post author.
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
