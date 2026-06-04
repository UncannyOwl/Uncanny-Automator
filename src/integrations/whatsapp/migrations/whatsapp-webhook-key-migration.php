<?php

namespace Uncanny_Automator\Integrations\WhatsApp;

use Uncanny_Automator\Migrations\Migration;

/**
 * Class WhatsApp_Webhook_Key_Migration
 *
 * Migrates WhatsApp webhook verification key option name to resolve a legacy bug and conflict with ActiveCampaign.
 *
 * @package Uncanny_Automator\Integrations\WhatsApp
 */
class WhatsApp_Webhook_Key_Migration extends Migration {

	/**
	 * Legacy option name (shared with ActiveCampaign due to copy/paste bug).
	 *
	 * @var string
	 */
	const LEGACY_KEY = 'uap_active_campaign_webhook_key';

	/**
	 * The webhooks instance.
	 *
	 * @var WhatsApp_Webhooks
	 */
	private $webhooks;

	/**
	 * Whether the integration is connected.
	 *
	 * @var bool
	 */
	private $is_connected;

	/**
	 * Constructor.
	 *
	 * @param string $name The migration name.
	 * @param WhatsApp_Webhooks $webhooks The webhooks instance.
	 * @param bool $is_connected Whether the integration is connected.
	 *
	 * @return void
	 */
	public function __construct( $name, $webhooks, $is_connected ) {
		$this->name         = $name;
		$this->webhooks     = $webhooks;
		$this->is_connected = $is_connected;
		add_action( 'shutdown', array( $this, 'maybe_run_migration' ) );
	}

	/**
	 * Perform the migration.
	 *
	 * @return void
	 */
	public function migrate() {

		// If the integration is not connected, skip the migration.
		if ( ! $this->is_connected ) {
			$this->complete();
			return;
		}

		// Get current webhook key value from legacy option.
		$webhook_key = automator_get_option( self::LEGACY_KEY );
		$cleanup     = true;

		// If the webhook key is not set, regenerate via framework.
		if ( empty( $webhook_key ) ) {
			$this->webhooks->regenerate_webhook_key();
			automator_delete_option( self::LEGACY_KEY );
			$cleanup = false;
		} else {
			// Migrate existing key to the framework's option name.
			$new_key = $this->webhooks->get_webhook_key_option_name();
			automator_update_option( $new_key, $webhook_key );
		}

		// Maybe remove legacy option if ActiveCampaign is not using it.
		if ( $cleanup ) {
			$this->maybe_cleanup_legacy_option();
		}

		$this->complete();
	}

	/**
	 * Clean up legacy option if ActiveCampaign is not connected.
	 *
	 * @return void
	 */
	private function maybe_cleanup_legacy_option() {

		$active_campaign = Automator()->get_integration( 'ACTIVE_CAMPAIGN' );

		// If ActiveCampaign integration doesn't exist, safe to delete.
		if ( is_null( $active_campaign ) ) {
			automator_delete_option( self::LEGACY_KEY );
			return;
		}

		// Check if ActiveCampaign is connected.
		$is_connected = $active_campaign['connected'] ?? false;
		if ( $is_connected ) {
			// ActiveCampaign is using it, leave the legacy key alone.
			return;
		}

		// ActiveCampaign exists but is not connected - safe to delete.
		automator_delete_option( self::LEGACY_KEY );
	}
}
