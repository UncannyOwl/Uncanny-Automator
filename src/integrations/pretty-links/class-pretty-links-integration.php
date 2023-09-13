<?php

namespace Uncanny_Automator\Integrations\Pretty_Links;

/**
 * Class Pretty_Links_Integration
 *
 * @package Uncanny_Automator
 */
class Pretty_Links_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Pretty_Links_Helpers();
		$this->set_integration( 'PRETTY_LINKS' );
		$this->set_name( 'Pretty Links' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/pretty-links-icon.svg' );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		// Load triggers and actions
		new PRLI_ANON_CREATE_A_LINK( $this->helpers );
		new PRLI_ANON_LINK_CLICKED( $this->helpers );
		new PRLI_LINK_CLICKED( $this->helpers );

		new PRLI_CREATE_LINK( $this->helpers );
	}

	/**
	 * Check if Plugin is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'PrliLink' );
	}

}
