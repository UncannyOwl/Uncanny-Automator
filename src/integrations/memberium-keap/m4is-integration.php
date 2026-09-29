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
		$this->helpers = new M4IS_HELPERS();
		$this->set_integration( 'M4IS' );
		$this->set_name( 'Memberium for Keap' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/memberium-icon.svg' );

		$this->set_plugin_file_path( 'memberium-for-keap/memberium-for-keap.php' );
		$this->set_developer_name( 'Memberium' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'commercial' );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		new M4IS_UPDATE_CONTACT_FIELD( $this->helpers );
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
