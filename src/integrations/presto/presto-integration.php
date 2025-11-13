<?php

namespace Uncanny_Automator\Integrations\Presto;

/**
 * Class Presto_Integration
 *
 * @package Uncanny_Automator
 */
class Presto_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Integration Set-up.
	 */
	protected function setup() {
		$this->helpers = new Presto_Helpers();
		$this->set_integration( 'PRESTO' );
		$this->set_name( 'Presto' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/presto-player-icon.svg' );
	}
	/**
	 * Load.
	 */
	public function load() {
		//triggers
		new PRESTO_VIDEOCOMPLETE( $this->helpers );
	}

	/**
	 * Determines whether the integration should be loaded or not.
	 *
	 * Checks whether an existing dependency condition is satisfied.
	 *
	 * @return bool Returns true if PRESTO_PLAYER_PLUGIN_FILE constant is defined. Returns false, otherwise.
	 */
	public function plugin_active() {
		return defined( 'PRESTO_PLAYER_PLUGIN_FILE' );
	}
}
