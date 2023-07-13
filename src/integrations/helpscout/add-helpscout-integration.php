<?php
namespace Uncanny_Automator;

class Add_Helpscout_Integration {

	use Recipe\Integrations;

	public function __construct() {

		$this->setup();

	}

	protected function setup() {

		$this->set_integration( 'HELPSCOUT' );

		$this->set_name( 'Help Scout' );

		$this->set_icon( __DIR__ . '/img/help-scout-icon.svg' );

		$this->set_connected( false !== automator_get_option( 'automator_helpscout_client', false ) ? true : false );

		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'helpscout' ) );

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
