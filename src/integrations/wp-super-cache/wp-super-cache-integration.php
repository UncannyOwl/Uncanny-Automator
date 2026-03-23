<?php

namespace Uncanny_Automator\Integrations\Wp_Super_Cache;

/**
 * Class Wp_Super_Cache_Integration
 *
 * @package Uncanny_Automator
 */
class Wp_Super_Cache_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Wp_Super_Cache_Helpers();
		$this->set_integration( 'WP_SUPER_CACHE' );
		$this->set_name( 'WP Super Cache' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/wp-super-cache-icon.svg' );
	}

	/**
	 * Load actions.
	 *
	 * @return void
	 */
	public function load() {
		new Wp_Super_Cache_Purge_All( $this->helpers );
		new Wp_Super_Cache_Purge_Url( $this->helpers );
		new Wp_Super_Cache_Purge_Post( $this->helpers );
	}

	/**
	 * Check if WP Super Cache is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return function_exists( 'wp_cache_clear_cache' );
	}
}
