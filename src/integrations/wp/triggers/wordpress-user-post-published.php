<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * Class WP_USERS_POST_PUBLISHED
 *
 * @package Uncanny_Automator
 * @property Wp_Helpers $item_helpers
 */
class WP_USERS_POST_PUBLISHED extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'WP_USER_POST_PUBLISHED', 'WP' )
			->trigger_meta( 'WPPOSTTYPES' )
			->hook( 'wp_after_insert_post', 10, 4 );
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
				esc_html_x( 'A user publishes a {{type of post:%1$s}}', 'WordPress', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'A user publishes a post', 'WordPress', 'uncanny-automator' ) );
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
	 * Override login requirement for scheduled posts.
	 *
	 * Scheduled posts transition from 'future' to 'publish' without a logged-in
	 * user. Detect this and set the user_id from the post author.
	 *
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool Whether login is required.
	 */
	protected function is_user_logged_in_required( ...$args ) {

		$hook_args = $args[0] ?? array();

		// Already logged in -- proceed normally.
		if ( is_user_logged_in() ) {
			return true;
		}

		list( $post_id, $wp_post, $update, $wp_post_before ) = $hook_args;

		// Ensure we have a post before object.
		if ( ! is_object( $wp_post_before ) ) {
			return true;
		}

		// This is a scheduled post transitioning future -> publish.
		if ( 'future' === $wp_post_before->post_status && 'publish' === $wp_post->post_status ) {
			$this->set_user_id( $wp_post->post_author );
			return false;
		}

		return true;
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

		list( $post_id, $wp_post, $update, $wp_post_before ) = $hook_args;

		// Only fire when a post is being published (first time).
		if ( ! \Automator()->utilities->is_wp_post_being_published( $wp_post, $wp_post_before ) ) {
			return false;
		}

		// Match post type.
		$selected_post_type = $trigger['meta'][ $this->get_trigger_meta() ] ?? '';
		if ( intval( '-1' ) !== intval( $selected_post_type ) && $wp_post->post_type !== $selected_post_type ) {
			return false;
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
			Wp_Shared_Tokens::hydrate_post_featured_image_tokens( $post_id ),
			Wp_Shared_Tokens::hydrate_post_author_tokens( $post_id ),
			Wp_Shared_Tokens::hydrate_post_date_tokens( $post_id ),
			Wp_Shared_Tokens::hydrate_post_taxonomy_tokens( $post_id )
		);
	}
}
