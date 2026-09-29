<?php

namespace Uncanny_Automator\Integrations\Wp_Rocket;

/**
 * Class Wp_Rocket_Integration
 *
 * @package Uncanny_Automator
 */
class Wp_Rocket_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Wp_Rocket_Helpers();
		$this->set_integration( 'WP_ROCKET' );
		$this->set_name( 'WP Rocket' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/wp-rocket-icon.svg' );

		$this->set_plugin_file_path( 'wp-rocket/wp-rocket.php' );
		$this->set_developer_name( 'WP Rocket' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'commercial' );
	}

	/**
	 * Load actions.
	 *
	 * @return void
	 */
	public function load() {
		new Wp_Rocket_Purge_All( $this->helpers );
		new Wp_Rocket_Purge_Url( $this->helpers );
		new Wp_Rocket_Purge_Post( $this->helpers );
	}

	/**
	 * Check if WP Rocket is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'WP_ROCKET_VERSION' );
	}
}
