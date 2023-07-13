<?php
namespace Uncanny_Automator;

/**
 * Class Add_LinkedIn_Integration.
 *
 * @package Uncanny_Automator\Add_LinkedIn_Integration
 */
class Add_LinkedIn_Integration {

	use Recipe\Integrations;

	public function __construct() {

		$this->setup();

	}

	protected function setup() {

		$this->set_integration( 'LINKEDIN' );

		$this->set_name( 'LinkedIn Pages' );

		$this->set_connected( false !== automator_get_option( 'automator_linkedin_client', false ) ? true : false );

		$this->set_icon( 'linkedin-icon.svg' );

		$this->set_icon_path( __DIR__ . '/img/' );

		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'linkedin' ) );

	}

	/**
	 * 3rd-party Integration.
	 *
	 * @return bool True. Always.
	 */
	public function plugin_active() {

		return true;

	}
}
