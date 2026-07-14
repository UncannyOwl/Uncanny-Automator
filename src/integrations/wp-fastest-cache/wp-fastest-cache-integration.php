<?php

namespace Uncanny_Automator\Integrations\Wp_Fastest_Cache;

/**
 * Class Wp_Fastest_Cache_Integration
 *
 * @package Uncanny_Automator
 */
class Wp_Fastest_Cache_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Set up integration code, name, icon and helpers.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Wp_Fastest_Cache_Helpers();
		$this->set_integration( 'WP_FASTEST_CACHE' );
		$this->set_name( 'WP Fastest Cache' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/wp-fastest-cache-icon.svg' );
	}

	/**
	 * Bootstrap triggers and actions.
	 *
	 * @return void
	 */
	public function load() {
		// Actions.
		new Wpfc_Purge_All_Cache( $this->helpers );
		new Wpfc_Purge_Post_Cache( $this->helpers );

		// Triggers.
		new Wpfc_All_Cache_Cleared( $this->helpers );
	}

	/**
	 * Active when WP Fastest Cache is installed. The plugin defines no version
	 * constant, so detection is by its main class.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'WpFastestCache' );
	}
}
