<?php

namespace Uncanny_Automator\Integrations\SliceWP;

use Uncanny_Automator\Integration;

/**
 * Class Slicewp_Integration
 *
 * @pacakge Uncanny_Automator
 */
class Slicewp_Integration extends Integration {

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Slicewp_Helpers();
		$this->set_integration( 'SLICEWP' );
		$this->set_name( 'SliceWP' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/slicewp-icon.svg' );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		// Load triggers and action.
		new SLICEWP_BECOMES_AFFILIATE( $this->helpers );
		new SLICEWP_AFFILIATE_IS_APPROVED( $this->helpers );
		new SLICEWP_AFFILAITE_AWAITING_APPROVAL( $this->helpers );
		new SLICEWP_CREATE_AFFILIATE( $this->helpers );
	}

	/**
	 * Check if Plugin is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'SliceWP' );
	}
}
