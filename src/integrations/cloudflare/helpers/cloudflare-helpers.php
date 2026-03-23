<?php

namespace Uncanny_Automator\Integrations\Cloudflare;

/**
 * Class Cloudflare_Helpers
 *
 * @package Uncanny_Automator
 */
class Cloudflare_Helpers {

	/**
	 * Get the Cloudflare Hooks instance.
	 *
	 * @return \Cloudflare\APO\WordPress\Hooks|false
	 */
	public function get_hooks_instance() {

		if ( ! class_exists( '\Cloudflare\APO\WordPress\Hooks' ) ) {
			return false;
		}

		return new \Cloudflare\APO\WordPress\Hooks();
	}

	/**
	 * Purge all Cloudflare cache.
	 *
	 * @return bool
	 */
	public function purge_all_cache() {

		$hooks = $this->get_hooks_instance();

		if ( false === $hooks ) {
			return false;
		}

		$hooks->purgeCacheEverything();

		return true;
	}

	/**
	 * Purge Cloudflare cache for a specific post and its related URLs.
	 *
	 * @param int $post_id The post ID to purge.
	 *
	 * @return bool
	 */
	public function purge_post_cache( $post_id ) {

		$hooks = $this->get_hooks_instance();

		if ( false === $hooks ) {
			return false;
		}

		$hooks->purgeCacheByRelevantURLs( $post_id );

		return true;
	}

	/**
	 * Purge Cloudflare cache for a specific URL.
	 *
	 * Wraps the URL in a temporary post context to use the plugin's URL purge mechanism.
	 *
	 * @param string $url The URL to purge.
	 *
	 * @return bool
	 */
	public function purge_url_cache( $url ) {

		$hooks = $this->get_hooks_instance();

		if ( false === $hooks ) {
			return false;
		}

		// Attempt to resolve the URL to a post ID for targeted purge.
		$post_id = url_to_postid( $url );

		if ( 0 !== $post_id ) {
			$hooks->purgeCacheByRelevantURLs( $post_id );
			return true;
		}

		// Cannot purge a non-post URL via the Cloudflare plugin API.
		return false;
	}
}
