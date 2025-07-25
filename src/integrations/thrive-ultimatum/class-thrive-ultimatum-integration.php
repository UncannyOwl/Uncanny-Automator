<?php

namespace Uncanny_Automator\Integrations\Thrive_Ultimatum;

/**
 * Class Thrive_Ultimatum_Integration
 *
 * @package Uncanny_Automator
 */
class Thrive_Ultimatum_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Thrive_Ultimatum_Helpers();
		$this->set_integration( 'THRIVE_ULTIMATUM' );
		$this->set_name( 'Thrive Ultimatum' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/thrive-ultimatum-icon.svg' );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		// Load triggers.
		new THRIVE_ULTIMATUM_EVERGREEN_CAMPAIGN_TRIGGERED( $this->helpers );
		new THRIVE_ULTIMATUM_USER_TRIGGERS_EVERGREEN_CAMPAIGN( $this->helpers );
		new THRIVE_ULTIMATUM_EVERGREEN_START_CAMPAIGN( $this->helpers );
	}

	/**
	 * Check if Plugin is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'TVE_Ult_Const', false );
	}
}
