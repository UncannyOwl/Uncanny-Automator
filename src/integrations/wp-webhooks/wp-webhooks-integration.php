<?php

namespace Uncanny_Automator\Integrations\Wp_Webhooks;

use Uncanny_Automator\Integration;

/**
 * Class Wpwh_Integration
 *
 * @package Uncanny_Automator\Integrations\Wp_Webhooks
 */
class Wpwh_Integration extends Integration {

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Wpwh_Helpers();
		$this->set_integration( 'WPWEBHOOKS' );
		$this->set_name( 'WP Webhooks' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/wp-webhooks-icon.svg' );

		$this->set_plugin_file_path( 'wp-webhooks/wp-webhooks.php' );
		$this->set_developer_name( 'Ironikus' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'wp_org' );
	}

	/**
	 * Load triggers.
	 *
	 * @return void
	 */
	public function load() {
		new WPWH_TRIGGERTRIGGERED( $this->helpers );
	}

	/**
	 * Check if WP Webhooks Pro is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'WP_Webhooks_Pro' );
	}
}
