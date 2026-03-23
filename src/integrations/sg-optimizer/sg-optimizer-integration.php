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
		$this->set_name( 'SG Optimizer' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/sg-optimizer-icon.svg' );
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
	 * Check if SG Optimizer is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( '\SiteGround_Optimizer\VERSION' );
	}
}
