<?php

namespace Uncanny_Automator\Integrations\W3_Total_Cache;

/**
 * Class W3_Total_Cache_Helpers
 *
 * @package Uncanny_Automator
 */
class W3_Total_Cache_Helpers {

	/**
	 * Purge all W3 Total Cache caches.
	 *
	 * Flushes page cache, database cache, object cache, minify, browser cache, and CDN.
	 *
	 * @return void
	 */
	public function purge_all_caches() {
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}
	}

	/**
	 * Purge cache for a specific URL.
	 *
	 * @param string $url The URL to purge.
	 *
	 * @return void
	 */
	public function purge_url_cache( $url ) {
		if ( function_exists( 'w3tc_flush_url' ) ) {
			w3tc_flush_url( $url );
		}
	}

	/**
	 * Purge cache for a specific post.
	 *
	 * @param int $post_id The post ID to purge.
	 *
	 * @return void
	 */
	public function purge_post_cache( $post_id ) {
		if ( function_exists( 'w3tc_flush_post' ) ) {
			w3tc_flush_post( $post_id, false );
		}
	}
}
