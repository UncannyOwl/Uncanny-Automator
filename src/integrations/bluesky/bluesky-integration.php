<?php

namespace Uncanny_Automator\Integrations\Bluesky;

/**
 * Class Bluesky_Integration
 *
 * @package Uncanny_Automator
 */
class Bluesky_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->helpers = new Bluesky_Helpers();

		$this->set_integration( 'BLUESKY' );
		$this->set_name( 'Bluesky' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/bluesky-icon.svg' );
		$this->set_connected( $this->helpers->integration_status() );
		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'bluesky' ) );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {

		new Bluesky_Settings( $this->helpers );
		new BLUESKY_CREATE_POST( $this->helpers );
	}
}
