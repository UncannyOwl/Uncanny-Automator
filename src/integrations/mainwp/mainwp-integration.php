<?php

namespace Uncanny_Automator\Integrations\Mainwp;

use Uncanny_Automator\Integration;

/**
 * Class Mainwp_Integration
 *
 * Targets the MainWP Dashboard, which runs on the control site and fires every
 * event here. MainWP Child runs on the managed sites and fires none of them.
 *
 * @package Uncanny_Automator
 */
class Mainwp_Integration extends Integration {

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Mainwp_Helpers();

		$this->set_integration( 'MAINWP' );
		$this->set_name( 'MainWP' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/mainwp-icon.svg' );

		$this->set_plugin_file_path( 'mainwp/mainwp.php' );
		$this->set_developer_name( 'MainWP' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'wp_org' );
	}

	/**
	 * Load triggers.
	 *
	 * @return void
	 */
	public function load() {
		new Mainwp_Child_Site_Added( $this->helpers );
		new Mainwp_Software_Updated( $this->helpers );
		new Mainwp_Core_Updated( $this->helpers );
		new Mainwp_Child_Site_Removed( $this->helpers );
	}

	/**
	 * Check if the MainWP Dashboard is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return $this->helpers->dashboard_active();
	}
}
