<?php
namespace Uncanny_Automator;

class Add_ClickUp_Integration {

	use Recipe\Integrations;

	public function __construct() {

		$this->setup();

	}

	protected function setup() {

		$this->set_integration( 'CLICKUP' );

		$this->set_name( 'ClickUp' );

		$this->set_icon( __DIR__ . '/img/clickup-icon.svg' );

		$this->set_connected( false !== automator_get_option( 'automator_clickup_client', false ) );

		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'clickup' ) );

	}

	/**
	 * Method plugin_active
	 *
	 * @return bool True, always.
	 */
	public function plugin_active() {

		return true;

	}
}
