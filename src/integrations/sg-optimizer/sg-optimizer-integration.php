<?php

namespace Uncanny_Automator\Integrations\Sg_Optimizer;

/**
 * Class Sg_Optimizer_Integration
 *
 * @package Uncanny_Automator
 */
class Sg_Optimizer_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Sg_Optimizer_Helpers();
		$this->set_integration( 'SG_OPTIMIZER' );
		$this->set_name( 'Speed Optimizer' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/sg-optimizer-icon.svg' );

		$this->set_plugin_file_path( 'sg-optimizer/sg-optimizer.php' );
		$this->set_developer_name( 'SiteGround' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'wp_org' );
	}

	/**
	 * Load actions.
	 *
	 * @return void
	 */
	public function load() {
		new Sg_Optimizer_Purge_All_Cache( $this->helpers );
		new Sg_Optimizer_Purge_Url_Cache( $this->helpers );
	}

	/**
	 * Check if Speed Optimizer is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( '\SiteGround_Optimizer\VERSION' );
	}
}
