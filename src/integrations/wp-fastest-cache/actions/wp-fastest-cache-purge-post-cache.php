<?php

namespace Uncanny_Automator\Integrations\Wp_Fastest_Cache;

use Uncanny_Automator\Recipe\Action;

/**
 * Class Wpfc_Purge_Post_Cache
 *
 * Purges the WP Fastest Cache for a single post via the plugin's global
 * `wpfc_clear_post_cache_by_id()` API. A post-type selector scopes the post
 * picker; the post field also accepts a token for a dynamic post ID.
 *
 * @package Uncanny_Automator\Integrations\Wp_Fastest_Cache
 *
 * @property Wp_Fastest_Cache_Helpers $item_helpers
 */
class Wpfc_Purge_Post_Cache extends Action {

	/**
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'WP_FASTEST_CACHE' );
		$this->set_action_code( 'WPFC_PURGE_POST_CACHE' );
		$this->set_action_meta( 'WPFC_POST' );
		$this->set_is_pro( false );
		// Cache clearing needs no recipe user (works in anonymous/scheduled
		// recipes) and can be slow, so run it out of the web request.
		$this->set_requires_user( false );
		$this->set_background_processing( true );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s: Post selector token placeholder */
				esc_html_x( 'Purge the cache for {{a post:%1$s}}', 'WP Fastest Cache', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Purge the cache for {{a post}}', 'WP Fastest Cache', 'uncanny-automator' ) );
	}

	/**
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => Wp_Fastest_Cache_Helpers::POST_TYPE,
				'label'           => esc_html_x( 'Post type', 'WP Fastest Cache', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'post_types_strict' ),
			),
			array(
				'option_code'     => $this->get_action_meta(),
				'label'           => esc_html_x( 'Post', 'WP Fastest Cache', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_parent_config( 'posts_strict', array( Wp_Fastest_Cache_Helpers::POST_TYPE ) ),
			),
		);
	}

	/**
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'WPFC_POST_ID'    => array(
				'name' => esc_html_x( 'Post ID', 'WP Fastest Cache', 'uncanny-automator' ),
				'type' => 'int',
			),
			'WPFC_POST_TITLE' => array(
				'name' => esc_html_x( 'Post title', 'WP Fastest Cache', 'uncanny-automator' ),
				'type' => 'text',
			),
			'WPFC_POST_URL'   => array(
				'name' => esc_html_x( 'Post URL', 'WP Fastest Cache', 'uncanny-automator' ),
				'type' => 'url',
			),
			'WPFC_POST_TYPE'  => array(
				'name' => esc_html_x( 'Post type', 'WP Fastest Cache', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		if ( ! function_exists( 'wpfc_clear_post_cache_by_id' ) ) {
			$this->add_log_error( esc_html_x( 'WP Fastest Cache is not available.', 'WP Fastest Cache', 'uncanny-automator' ) );
			return false;
		}

		$post_id = isset( $parsed[ $this->get_action_meta() ] ) ? absint( $parsed[ $this->get_action_meta() ] ) : 0;
		$post    = $post_id > 0 ? get_post( $post_id ) : null;

		if ( ! $post instanceof \WP_Post ) {
			$this->add_log_error( esc_html_x( 'The selected post could not be found.', 'WP Fastest Cache', 'uncanny-automator' ) );
			return false;
		}

		// Guard against this purge firing the integration's own "post cache
		// cleared" trigger — wpfc_clear_post_cache_by_id() fires that exact hook.
		Wp_Fastest_Cache_Helpers::$is_clearing_via_action = true;
		try {
			wpfc_clear_post_cache_by_id( $post_id );
		} finally {
			Wp_Fastest_Cache_Helpers::$is_clearing_via_action = false;
		}

		$this->hydrate_tokens( $this->item_helpers->hydrate_post_tokens( $post_id ) );

		return true;
	}
}
