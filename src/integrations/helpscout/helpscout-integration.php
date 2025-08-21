<?php

namespace Uncanny_Automator\Integrations\Helpscout;

/**
 * Class Helpscout_Integration
 *
 * @package Uncanny_Automator
 */
class Helpscout_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->helpers = new Helpscout_Helpers();
		$this->set_integration( 'HELPSCOUT' );
		$this->set_name( 'Help Scout' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/help-scout-icon.svg' );
		$this->set_connected( false !== automator_get_option( 'automator_helpscout_client', false ) ? true : false );
		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'helpscout' ) );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {

		// Actions
		new Helpscout_Conversation_Create( $this->helpers );
		new Helpscout_Customer_Properties_Update( $this->helpers );
		new Helpscout_Conversation_Tag_Add( $this->helpers );

		// Triggers
		new Hs_Note_Added( $this->helpers );
		new Hs_Conversation_Tag_Updated( $this->helpers );
		new Hs_Rating_Received( $this->helpers );
		new Hs_Conversation_Customer_Reply_Received( $this->helpers );
		new Hs_Conversation_Created( $this->helpers );
	}

	/**
	 * Check if plugin is active
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return true;
	}
}
