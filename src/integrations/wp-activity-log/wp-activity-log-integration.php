<?php

namespace Uncanny_Automator\Integrations\Wp_Activity_Log;

/**
 * Class Wp_Activity_Log_Integration
 *
 * @package Uncanny_Automator\Integrations\Wp_Activity_Log
 */
class Wp_Activity_Log_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Wp_Activity_Log_Helpers();
		$this->set_integration( 'WP_ACTIVITY_LOG' );
		$this->set_name( 'WP Activity Log' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/wp-activity-log-icon.svg' );
	}

	/**
	 * Whether WP Activity Log (formerly WP Security Audit Log) is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'WSAL_VERSION' );
	}

	/**
	 * Load triggers.
	 *
	 * @return void
	 */
	public function load() {
		new Wp_Activity_Log_Event_Logged( $this->helpers );
		new Wp_Activity_Log_Failed_Login( $this->helpers );
		new Wp_Activity_Log_Event_Severity( $this->helpers );
		new Wp_Activity_Log_Event_Object( $this->helpers );
		new Wp_Activity_Log_Event_Type( $this->helpers );
	}
}
