<?php

namespace Uncanny_Automator;

/**
 * Class Add_Drip_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Drip_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Drip_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {

		require_once __DIR__ . '/functions/drip-functions.php';

		$functions = new Drip_Functions();

		$this->set_integration( 'DRIP' );
		$this->set_name( 'Drip' );
		$this->set_icon( 'drip-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_connected( $functions->integration_status() );
		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'drip' ) );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return true;
	}

	/**
	 * Set the directories that the auto loader will run in
	 *
	 * @param $directory
	 *
	 * @return array
	 */
	public function add_integration_directory_func( $directory ) {

		$directory[] = dirname( __FILE__ ) . '/helpers';
		$directory[] = dirname( __FILE__ ) . '/actions';

		return $directory;
	}
}
