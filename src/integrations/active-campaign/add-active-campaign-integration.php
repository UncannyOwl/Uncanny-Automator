<?php

namespace Uncanny_Automator;

/**
 * Class Add_Active_Campaign_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Active_Campaign_Integration {
	use Recipe\Integrations;

	/**
	 * Add_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 * @return bool
	 */
	public function is_connected() {

		$api_url = get_option( 'uap_active_campaign_api_url', '' );
		$api_key = get_option( 'uap_active_campaign_api_key', '' );
		$user    = get_option( 'uap_active_campaign_connected_user' );

		return ! empty( $user ) && ! empty( $api_url ) && ! empty( $api_key );

	}
	
	/**
	 * Integration Set-up.
	 */
	protected function setup() {
		$this->set_integration( 'ACTIVE_CAMPAIGN' );
		$this->set_name( 'ActiveCampaign' );
		$this->set_icon( __DIR__ . '/img/activecampaign-icon.svg' );
		$this->set_connected( $this->is_connected() );
		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'active-campaign' ) );
	}

	/**
	 * Explicitly return true because its a 3rd-party plugin.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return true;
	}
}
