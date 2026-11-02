<?php

namespace Uncanny_Automator\Integrations\Redirect;

use Uncanny_Automator\Integration;

/**
 * Class Redirect_Integration
 *
 * Built in: no plugin to depend on, so it is always active.
 *
 * @since 8.0.0
 *
 * @package Uncanny_Automator
 */
class Redirect_Integration extends Integration {

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Redirect_Helpers();
		$this->set_integration( 'REDIRECT' );
		$this->set_name( 'Redirect' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/redirect-icon.svg' );
		$this->set_plugin_file_path( 'uncanny-automator/uncanny-automator.php' );
		$this->set_developer_name( 'Uncanny Automator' );
		$this->set_integration_type( 'built-in' );
		$this->set_distribution_type( 'wp_org' );
	}

	/**
	 * Load the action.
	 *
	 * @return void
	 */
	public function load() {
		new Redirect_To_Url( $this->helpers );
	}

	/**
	 * Built in, always active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return true;
	}
}
