<?php

namespace Uncanny_Automator\Integrations\Cloudflare;

/**
 * Class Cloudflare_Integration
 *
 * @package Uncanny_Automator
 */
class Cloudflare_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Cloudflare_Helpers();
		$this->set_integration( 'CLOUDFLARE' );
		$this->set_name( 'Cloudflare' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/cloudflare-icon.svg' );
	}

	/**
	 * Load actions.
	 *
	 * @return void
	 */
	public function load() {
		new Cloudflare_Purge_All( $this->helpers );
		new Cloudflare_Purge_Url( $this->helpers );
		new Cloudflare_Purge_Post( $this->helpers );
	}

	/**
	 * Check if Cloudflare plugin is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'CLOUDFLARE_PLUGIN_DIR' );
	}
}
