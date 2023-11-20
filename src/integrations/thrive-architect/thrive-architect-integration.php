<?php

namespace Uncanny_Automator\Integrations\Thrive_Architect;

use Uncanny_Automator\Integrations\Thrive_Architect\FORM_SUBMITTED;

/**
 * Class Thrive_Architect_Integration
 *
 * @package Uncanny_Automator\Integrations\Thrive_Architect
 */
class Thrive_Architect_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setups the integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->set_integration( 'THRIVE_ARCHITECT' );
		$this->set_name( 'Thrive Architect' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/thrive-architect-icon.svg' );
	}

	/**
	 * Selectively instantiates the components.
	 *
	 * @return void
	 */
	protected function load() {
		new FORM_SUBMITTED();
		new USER_FORM_SUBMITTED();
	}

	/**
	 * Determinines whether the integration should show up or not.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'TVE_IN_ARCHITECT' );
	}

}
