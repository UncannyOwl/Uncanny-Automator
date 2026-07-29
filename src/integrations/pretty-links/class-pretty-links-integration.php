<?php

namespace Uncanny_Automator\Integrations\Pretty_Links;

/**
 * Class Pretty_Links_Integration
 *
 * @package Uncanny_Automator
 */
class Pretty_Links_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Set up the integration's basic properties like code, name, and helpers.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Pretty_Links_Helpers();
		$this->set_integration( 'PRETTY_LINKS' );
		$this->set_name( 'PrettyLinks' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/pretty-links-icon.svg' );
	}

	/**
	 * Load the integration's triggers and actions.
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
	 * Check if the Pretty Links plugin is active.
	 *
	 * PRLI_PATH is defined in the plugin's main file on every release line
	 * (verified 1.0.0 through 4.x), unlike the PrliLink class the v4 rewrite removed.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'PRLI_PATH' );
	}
}
