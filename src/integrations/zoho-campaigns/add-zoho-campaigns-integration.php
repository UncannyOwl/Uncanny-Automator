<?php
namespace Uncanny_Automator;

/**
 * Class Add_Zoho_Campaigns_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Zoho_Campaigns_Integration {

	use Recipe\Integrations;

	/**
	 * Method __construct.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 * Setup integrations.
	 */
	protected function setup() {

		$this->set_integration( 'ZOHO_CAMPAIGNS' );

		$this->set_name( 'Zoho Campaigns' );

		$this->set_icon( 'zoho-campaigns-icon.svg' );

		$this->set_icon_path( __DIR__ . '/img/' );

		$this->set_connected( automator_get_option( 'zoho_campaigns_credentials', false ) );

		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'zoho_campaigns' ) );

	}

	/**
	 * 3rd party integrations always return true.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return true;
	}
}
