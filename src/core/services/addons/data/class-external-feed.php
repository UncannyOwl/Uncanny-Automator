<?php

namespace Uncanny_Automator\Services\Addons\Data;

use Uncanny_Automator\Services\Plugin\Info as Plugin_Info;

/**
 * Class External_Feed
 *
 * - Retrieves the addons data from the external feed.
 * - Caches the data for 12 hours.
 *
 * @package Uncanny_Automator\Services\Addons
 */
class External_Feed {

	/**
	 * External feed base URL
	 *
	 * @var string
	 */
	private $api_base = AUTOMATOR_STORE_URL;

	/**
	 * External feed addons directory.
	 *
	 * @var string
	 */
	private $addons_directory = 'wp-content/uploads/automator-addons/';

	/**
	 * Addons file.
	 *
	 * @var string
	 */
	private $addons_file = 'automator-addons.json';

	/**
	 * Addons feed URL.
	 *
	 * @var string
	 */
	private $addons_feed_url = '';

	/**
	 * Addons feed data.
	 *
	 * @var array
	 */
	private $addons_feed_data = array();

	/**
	 * Cache key.
	 *
	 * @var string
	 */
	private $cache_key = '';

	/**
	 * Cache duration in seconds (12 hours)
	 *
	 * @var int
	 */
	private $cache_duration = 43200;

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {

		// Set the base URL if it's defined.
		if ( defined( 'AUTOMATOR_PLUGIN_ADDONS_URL' ) ) {
			$this->api_base = trailingslashit( AUTOMATOR_PLUGIN_ADDONS_URL );
		}

		// Set the addons directory if it's defined.
		if ( defined( 'AUTOMATOR_PLUGIN_ADDONS_DIRECTORY' ) ) {
			$this->addons_directory = trailingslashit( AUTOMATOR_PLUGIN_ADDONS_DIRECTORY );
		}

		$this->addons_feed_url = $this->api_base . $this->addons_directory . $this->addons_file;
		$this->cache_key       = $this->get_cache_key();
		$this->set_addons_feed_data();
	}

	/**
	 * Set the addons feed data.
	 *
	 * @return void
	 */
	public function set_addons_feed_data() {
		// Check for cached data first
		$cached_data = get_transient( $this->cache_key );
		if ( false !== $cached_data ) {
			$this->addons_feed_data = $cached_data;
			return;
		}

		// Get the feed file if no cache exists
		$response = wp_remote_get( $this->addons_feed_url );
		if ( is_wp_error( $response ) ) {
			return;
		}

		// Decode the feed
		$feed = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $feed ) ) {
			// Cache the feed data with URL-specific key
			$feed = $this->format_feed_data( $feed );
			set_transient( $this->cache_key, $feed, $this->cache_duration );
			$this->addons_feed_data = $feed;
		}
	}

	/**
	 * Get the addons feed data.
	 *
	 * @return array
	 */
	public function get_feed() {
		return $this->addons_feed_data;
	}

	/**
	 * Format the feed data.
	 *
	 * @param array $feed The feed data.
	 *
	 * @return array
	 */
	private function format_feed_data( $feed ) {
		foreach ( $feed as $key => $addon ) {
			// Get the plugin file path using the Info service
			$feed[ $key ]['plugin_file'] = Plugin_Info::get_addon_plugin_file( $addon );
		}

		$filtered_feed = apply_filters( 'automator_addons_feed_data', $feed );
		return empty( $filtered_feed ) || ! is_array( $filtered_feed )
			? $feed
			: $filtered_feed;
	}

	/**
	 * Get the cache key for the current feed URL
	 *
	 * @return string
	 */
	private function get_cache_key() {
		return 'automator_addons_feed_' . md5( $this->addons_feed_url );
	}

	/**
	 * Clear the addons feed cache
	 *
	 * @return void
	 */
	public function clear_cache() {
		delete_transient( $this->get_cache_key() );
	}
}
