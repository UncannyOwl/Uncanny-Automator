<?php
namespace Uncanny_Automator;

/**
 * Class Add_WhatsApp_Integration
 *
 * @package Uncanny_Automator
 */
class Add_WhatsApp_Integration {

	use Recipe\Integrations;

	const CLIENT = 'automator_whatsapp_client';

	public function __construct() {

		$this->setup();

	}


	protected function setup() {

		$client = $this->get_client();

		$is_connected = ! empty( $client ) && ! $this->has_missing_scopes( $client['scopes'] );

		$this->set_integration( 'WHATSAPP' );

		$this->set_name( 'WhatsApp' );

		$this->set_connected( $is_connected );

		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'whatsapp' ) );

		$this->set_icon( __DIR__ . '/img/whatsapp-icon.svg' );

	}

	/**
	 * 3rd party integrations always return true.
	 *
	 * @return bool True. Always.
	 */
	public function plugin_active() {

		return true;

	}

	public function get_client() {

		$option = automator_get_option( self::CLIENT, array() );

		return ! empty( $option['data']['data'] ) ? $option['data']['data'] : array();

	}

	public function has_missing_scopes( $client = array() ) {

		if ( empty( $client ) || empty( $client['scopes'] ) ) {
			return;
		}

		$required_scopes = array(
			'whatsapp_business_management',
			'whatsapp_business_messaging',
		);

		// Would return false if either one of the required scopes is missing.
		return count( array_intersect( $client['scopes'], $required_scopes ) ) < 2;

	}
}
