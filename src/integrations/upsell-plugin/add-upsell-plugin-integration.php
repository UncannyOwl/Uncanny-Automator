<?php

namespace Uncanny_Automator;

/**
 * Class Add_Upsell_Plugin_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Upsell_Plugin_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Upsell_Plugin_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'UPSELLPLUGIN' );
		$this->set_name( 'Upsell Plugin' );
		$this->set_icon( 'upsell-plugin.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'upsell/plugin.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( '\Upsell\Plugin' );
	}
}
