<?php

namespace Uncanny_Automator\Integrations\Sg_Optimizer;

/**
 * Class Sg_Optimizer_Helpers
 *
 * @package Uncanny_Automator
 */
class Sg_Optimizer_Helpers {

	/**
	 * Purge all SG Optimizer caches.
	 *
	 * Clears dynamic cache, file-based cache, memcached, and minified assets.
	 *
	 * @return bool
	 */
	public function purge_all_caches() {

		// Purge dynamic cache (also handles file cache purge internally).
		\SiteGround_Optimizer\Supercacher\Supercacher::purge_cache();

		// Purge file-based cache.
		\SiteGround_Optimizer\File_Cacher\File_Cacher::get_instance()->purge_everything();

		// Flush memcached / object cache.
		\SiteGround_Optimizer\Supercacher\Supercacher::flush_memcache();

		// Delete minified CSS/JS assets.
		\SiteGround_Optimizer\Supercacher\Supercacher::delete_assets();

		return true;
	}

	/**
	 * Purge cache for a specific URL.
	 *
	 * Clears both dynamic and file-based cache for the given URL.
	 *
	 * @param string $url The URL to purge.
	 *
	 * @return bool
	 */
	public function purge_url_cache( $url ) {

		// Purge dynamic cache for the URL.
		\SiteGround_Optimizer\Supercacher\Supercacher::purge_cache_request( $url, true );

		// Purge file-based cache for the URL.
		\SiteGround_Optimizer\File_Cacher\File_Cacher::purge_cache_request( $url, true );

		return true;
	}
}
