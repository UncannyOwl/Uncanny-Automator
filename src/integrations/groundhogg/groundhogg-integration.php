<?php

namespace Uncanny_Automator\Integrations\Groundhogg;

/**
 * Class Groundhogg_Integration
 *
 * @package Uncanny_Automator\Integrations\Groundhogg
 */
class Groundhogg_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup the integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Groundhogg_Helpers();
		$this->set_integration( 'GH' );
		$this->set_name( 'Groundhogg' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/groundhogg-icon.svg' );
	}

	/**
	 * Load triggers and actions.
	 *
	 * @return void
	 */
	public function load() {
		new GH_ADDTAG( $this->helpers );
		new GH_REMOVETAG( $this->helpers );
		new GH_ADD_TAG_TO_CONTACT( $this->helpers );
		new GH_REMOVE_TAG_FROM_CONTACT( $this->helpers );
		new GH_CREATE_UPDATE_CONTACT( $this->helpers );
		new GH_ADD_NOTE_TO_CONTACT( $this->helpers );
	}

	/**
	 * Check if Groundhogg is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'GROUNDHOGG_VERSION' );
	}
}
