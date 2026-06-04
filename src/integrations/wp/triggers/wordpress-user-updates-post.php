<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * Class WP_USER_UPDATES_POST
 *
 * @package Uncanny_Automator
 * @property Wp_Helpers $item_helpers
 */
class WP_USER_UPDATES_POST extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'WP_USER_POST_UPDATED', 'WP' )
			->trigger_meta( 'WPPOSTTYPES' )
			->hook( 'post_updated', 10, 3 );
	}

	/**
	 * Setup trigger configuration.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type are auto-applied from definition().
		$this->set_is_login_required( true );
		// translators: %1$s is a post type.
		$this->set_sentence(
			sprintf(
				esc_html_x( 'A user updates {{a type of post:%1$s}}', 'WordPress', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'A user updates {{a type of post}}', 'WordPress', 'uncanny-automator' ) );
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

		list( $post_id, $wp_post_after, $wp_post_before ) = $hook_args;

		// Skip autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		if ( empty( $post_id ) ) {
			return false;
		}

		// Prevent if publishing a post (new publish, not an update).
		if ( 'publish' === $wp_post_after->post_status && 'publish' !== $wp_post_before->post_status ) {
			return false;
		}

		// Prevent if the status is excluded.
		$ignore_statuses = apply_filters(
			'automator_wp_post_updates_ignore_statuses',
			array( 'trash', 'draft', 'future' ),
			$post_id,
			$wp_post_after,
			$wp_post_before
		);

		if ( in_array( $wp_post_after->post_status, $ignore_statuses, true ) ) {
			return false;
		}

		// Exclude non-public post types unless filtered.
		$include_non_public = apply_filters( 'automator_wp_post_updates_include_non_public_posts', false, $post_id );
		if ( false === $include_non_public ) {
			$post_type_obj = get_post_type_object( $wp_post_after->post_type );
			if ( false === $post_type_obj->public ) {
				return false;
			}
		}

		// Match post type.
		$selected_post_type = $trigger['meta'][ $this->get_trigger_meta() ] ?? '';
		if ( intval( '-1' ) !== intval( $selected_post_type ) && $wp_post_after->post_type !== $selected_post_type ) {
			return false;
		}

		return apply_filters( 'automator_wp_post_updates_post_updated', true, $post_id, $wp_post_after, $wp_post_before );
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
			Wp_Shared_Tokens::hydrate_post_featured_image_tokens( $post_id ),
			Wp_Shared_Tokens::hydrate_post_author_tokens( $post_id ),
			Wp_Shared_Tokens::hydrate_post_date_tokens( $post_id ),
			Wp_Shared_Tokens::hydrate_post_taxonomy_tokens( $post_id )
		);
	}
}
