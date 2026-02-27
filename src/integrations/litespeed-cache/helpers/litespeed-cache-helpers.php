<?php

namespace Uncanny_Automator\Integrations\Litespeed_Cache;

/**
 * Class Litespeed_Cache_Helpers
 *
 * @package Uncanny_Automator
 */
class Litespeed_Cache_Helpers {

	/**
	 * Purge all LiteSpeed caches.
	 *
	 * Uses the official litespeed_purge_all action hook which clears
	 * page cache, CSS/JS, object cache, OPcache, and image caches.
	 *
	 * @return void
	 */
	public function purge_all_caches() {
		do_action( 'litespeed_purge_all' );
	}

	/**
	 * Purge LiteSpeed cache for a specific URL.
	 *
	 * @param string $url The URL to purge.
	 *
	 * @return void
	 */
	public function purge_url_cache( $url ) {
		do_action( 'litespeed_purge_url', $url );
	}

	/**
	 * Purge LiteSpeed cache for a specific post.
	 *
	 * @param int $post_id The post ID to purge.
	 *
	 * @return void
	 */
	public function purge_post_cache( $post_id ) {
		do_action( 'litespeed_purge_post', $post_id );
	}
}
