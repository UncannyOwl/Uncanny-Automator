<?php

namespace Uncanny_Automator\Integrations\Wp_Rocket;

/**
 * Class Wp_Rocket_Helpers
 *
 * @package Uncanny_Automator
 */
class Wp_Rocket_Helpers {

	/**
	 * Purge all WP Rocket caches.
	 *
	 * Clears page cache for the entire domain and minified CSS/JS files.
	 *
	 * @return void
	 */
	public function purge_all_caches() {
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}

		if ( function_exists( 'rocket_clean_minify' ) ) {
			rocket_clean_minify();
		}
	}

	/**
	 * Purge cache for specific URL(s).
	 *
	 * @param string $url The URL to purge.
	 *
	 * @return void
	 */
	public function purge_url_cache( $url ) {
		if ( function_exists( 'rocket_clean_files' ) ) {
			rocket_clean_files( $url );
		}
	}

	/**
	 * Purge cache for a specific post.
	 *
	 * Clears the post's page cache and related archives (homepage, terms, author).
	 *
	 * @param int $post_id The post ID to purge.
	 *
	 * @return void
	 */
	public function purge_post_cache( $post_id ) {
		if ( function_exists( 'rocket_clean_post' ) ) {
			rocket_clean_post( $post_id );
		}
	}
}
