<?php

namespace Uncanny_Automator\Integrations\W3_Total_Cache;

/**
 * Class W3_Total_Cache_Integration
 *
 * @package Uncanny_Automator
 */
class W3_Total_Cache_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new W3_Total_Cache_Helpers();
		$this->set_integration( 'W3_TOTAL_CACHE' );
		$this->set_name( 'W3 Total Cache' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/w3-total-cache-icon.svg' );
	}

	/**
	 * Load actions.
	 *
	 * @return void
	 */
	public function load() {
		new W3_Total_Cache_Purge_All( $this->helpers );
		new W3_Total_Cache_Purge_Url( $this->helpers );
		new W3_Total_Cache_Purge_Post( $this->helpers );
	}

	/**
	 * Check if W3 Total Cache is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'W3TC' );
	}
}
