<?php

namespace Uncanny_Automator\Integrations\M4IS;

/**
 * Class Memberium_Integration
 *
 * @package Uncanny_Automator
 */
class M4IS_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->set_integration( 'M4IS' );
		$this->set_name( 'Memberium for Keap' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/memberium-icon.svg' );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {

		$helper = new M4IS_HELPERS();

		// Load actions.
		new M4IS_UPDATE_CONTACT_FIELD( $helper );

	}

	/**
	 * Check if Plugin is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'MEMBERIUM_SKU' ) && strtolower( MEMBERIUM_SKU ) === 'm4is';
	}

}
