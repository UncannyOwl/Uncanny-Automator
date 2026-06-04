<?php
namespace Uncanny_Automator\Integrations\WhatsApp;

use Uncanny_Automator\App_Integrations\App_Integration;

/**
 * Class WhatsApp_Integration
 *
 * @package Uncanny_Automator
 */
class WhatsApp_Integration extends App_Integration {

	/**
	 * Get the integration config.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'WHATSAPP',     // Integration code.
			'name'         => 'WhatsApp',     // Integration name.
			'api_endpoint' => 'v2/whatsapp',  // Automator API server endpoint.
			'settings_id'  => 'whatsapp',     // Settings ID ( Settings url / tab id ).
		);
	}

	/**
	 * Spins up new integration
	 *
	 * @return void
	 */
	protected function setup() {
		// Define helpers with common config values.
		$this->helpers = new WhatsApp_Helpers( self::get_config() );

		// Set the icon URL.
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/whatsapp-icon.svg' );

		// Finalize setup via the parent class with the common config.
		$this->setup_app_integration( self::get_config() );
	}

	/**
	 * Bootstrap actions, triggers, settings page, etc.
	 *
	 * @return void
	 */
	public function load() {
		// Migrate webhook key from legacy ActiveCampaign option to framework standard.
		new WhatsApp_Webhook_Key_Migration(
			'whatsapp_webhook_key_migration',
			$this->dependencies->webhooks,
			$this->is_app_connected()
		);

		// Load settings page.
		new WhatsApp_Settings( $this->dependencies, $this->get_settings_config() );

		// Load triggers.
		new WA_MESSAGE_NOT_DELIVERED_NO_OPTIN( $this->dependencies );
		new WA_MESSAGE_NOT_DELIVERED( $this->dependencies );
		new WA_MESSAGE_RECEIVED( $this->dependencies );
		new WA_MESSAGE_STATUS_UPDATED( $this->dependencies );

		// Load actions.
		new WHATSAPP_SEND_MESSAGE_TEMPLATE( $this->dependencies );
		new WHATSAPP_SEND_MESSAGE( $this->dependencies );
	}

	/**
	 * Check if the app is connected
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		$account = $this->helpers->get_account_info();
		return ! empty( $account ) && ! $this->helpers->has_missing_scopes( $account );
	}

	/**
	 * Register hooks
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Flush expired transients.
		add_action( 'automator_whatsapp_flush_transient', array( $this->helpers, 'flush_transient' ) );

		// Register common WhatsApp action filters
		add_filter( 'automator_get_action_completed_status', array( $this->helpers, 'filter_whatsapp_completed_status' ), 10, 7 );
		add_filter( 'automator_get_action_error_message', array( $this->helpers, 'filter_whatsapp_error_message' ), 10, 7 );
		add_filter( 'automator_pro_get_action_completed_labels', array( $this->helpers, 'filter_whatsapp_action_completed_label' ), 10, 1 );
		add_action( 'automator_whatsapp_webhook_noresponse_closure', array( $this->helpers, 'handle_whatsapp_noresponse_closure' ), 10, 3 );
		add_action( 'automator_action_created', array( $this->helpers, 'persist_whatsapp_action_meta' ), 10, 1 );
	}
}
