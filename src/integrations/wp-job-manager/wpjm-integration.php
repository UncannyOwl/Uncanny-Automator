<?php

namespace Uncanny_Automator\Integrations\Wpjm;

/**
 * Class Wpjm_Integration
 *
 * @package Uncanny_Automator\Integrations\Wpjm
 */
class Wpjm_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup integration
	 */
	protected function setup() {
		$this->helpers = new Wpjm_Helpers();
		$this->set_integration( 'WPJM' );
		$this->set_name( 'WP Job Manager' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/wp-job-manager-icon.svg' );
	}

	/**
	 * Load triggers and actions
	 */
	public function load() {
		// Load triggers
		new Wpjm_Submitjob( $this->helpers );
		new Wpjm_Jobapplication( $this->helpers );
		new Wpjm_Submitresume( $this->helpers );
	}

	/**
	 * Check if plugin is active
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'WP_Job_Manager' );
	}
}
