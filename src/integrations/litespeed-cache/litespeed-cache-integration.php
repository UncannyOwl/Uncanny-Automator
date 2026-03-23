<?php

namespace Uncanny_Automator\Integrations\Litespeed_Cache;

/**
 * Class Litespeed_Cache_Integration
 *
 * @package Uncanny_Automator
 */
class Litespeed_Cache_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Litespeed_Cache_Helpers();
		$this->set_integration( 'LITESPEED_CACHE' );
		$this->set_name( 'LiteSpeed Cache' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/litespeed-cache-icon.svg' );
	}

	/**
	 * Load actions.
	 *
	 * @return void
	 */
	public function load() {
		new Litespeed_Cache_Purge_All( $this->helpers );
		new Litespeed_Cache_Purge_Url( $this->helpers );
		new Litespeed_Cache_Purge_Post( $this->helpers );
	}

	/**
	 * Check if LiteSpeed Cache is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'LSCWP_V' );
	}
}
