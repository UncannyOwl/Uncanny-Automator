<?php

namespace Uncanny_Automator\Integrations\Wp_Super_Cache;

/**
 * Class Wp_Super_Cache_Helpers
 *
 * @package Uncanny_Automator
 */
class Wp_Super_Cache_Helpers {

	/**
	 * Purge all WP Super Cache caches.
	 *
	 * @return void
	 */
	public function purge_all_caches(): bool {
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
			return true;
		}
		return false;
	}

	/**
	 * Purge cache for a specific URL.
	 *
	 * @param string $url The URL to purge.
	 *
	 * @return bool
	 */
	public function purge_url_cache( $url ): bool {
		if ( function_exists( 'wpsc_delete_url_cache' ) ) {
			wpsc_delete_url_cache( $url );
			return true;
		}
		return false;
	}

	/**
	 * Purge cache for a specific post.
	 *
	 * @param int $post_id The post ID to purge.
	 *
	 * @return bool
	 */
	public function purge_post_cache( $post_id ): bool {
		if ( function_exists( 'wpsc_delete_post_cache' ) ) {
			wpsc_delete_post_cache( $post_id );
			return true;
		}
		return false;
	}
}
