<?php

namespace Uncanny_Automator\Integrations\Zoho_Campaigns;

use Uncanny_Automator\Automator_Helpers_Recipe;
use Uncanny_Automator\Migrations\Migration;
use Exception;

/**
 * Class Zoho_Campaigns_Credentials_Migration
 *
 * Migrates Zoho Campaigns credentials from legacy format to vault-based storage.
 *
 * @package Uncanny_Automator\Integrations\Zoho_Campaigns
 */
class Zoho_Campaigns_Credentials_Migration extends Migration {

	/**
	 * Legacy option name.
	 *
	 * @var string
	 */
	const LEGACY_CREDENTIALS = 'zoho_campaigns_credentials';

	/**
	 * Nonce action for migration requests.
	 *
	 * @var string
	 */
	const MIGRATION_NONCE_ACTION = 'automator_zoho_campaigns_migration';

	/**
	 * The helpers instance.
	 *
	 * @var Zoho_Campaigns_App_Helpers
	 */
	private $helpers;

	/**
	 * The API caller instance.
	 *
	 * @var Zoho_Campaigns_Api_Caller
	 */
	private $api;

	/**
	 * Constructor.
	 *
	 * @param string $name         The migration name.
	 * @param object $dependencies The dependencies object with properties:
	 *                             - Zoho_Campaigns_App_Helpers $helpers
	 *                             - Zoho_Campaigns_Api_Caller $api
	 *
	 * @return void
	 */
	public function __construct( $name, $dependencies ) {
		$this->name    = $name;
		$this->helpers = $dependencies->helpers ?? null;
		$this->api     = $dependencies->api ?? null;
		add_action( 'shutdown', array( $this, 'maybe_run_migration' ) );
	}

	/**
	 * Check if migration conditions are met.
	 *
	 * @return bool
	 */
	public function conditions_met() {
		return true;
	}

	/**
	 * Perform the migration.
	 *
	 * @return void
	 */
	public function migrate() {
		// Ensure we have dependencies.
		if ( empty( $this->helpers ) || empty( $this->api ) ) {
			$this->complete();
			return;
		}

		$legacy_credentials = automator_get_option( self::LEGACY_CREDENTIALS );

		try {
			$new_credentials = $this->helpers->get_credentials();
		} catch ( Exception $e ) {
			$new_credentials = array();
		}

		// If new credentials already exist (has vault_signature), migration already done.
		if ( ! empty( $new_credentials['vault_signature'] ?? null ) ) {
			$this->cleanup_legacy_options();
			$this->complete();
			return;
		}

		// If no legacy credentials or no access_token, nothing to migrate.
		if ( empty( $legacy_credentials['access_token'] ) ) {
			$this->complete();
			return;
		}

		// Attempt vault migration.
		$migrated_credentials = $this->migrate_credentials_to_vault( $legacy_credentials );
		if ( false === $migrated_credentials ) {
			$this->complete();
			return;
		}

		// Save new credentials using framework method.
		$this->helpers->store_credentials(
			array(
				'vault_signature' => $migrated_credentials['vault_signature'],
				'user_id'         => $migrated_credentials['user_id'],
			)
		);

		// Verify credentials were saved.
		$saved = $this->helpers->get_credentials();
		if ( empty( $saved['vault_signature'] ) ) {
			$this->complete();
			return;
		}

		// Clean up legacy options only after successful migration.
		$this->cleanup_legacy_options();

		$this->complete();
	}

	/**
	 * Clean up legacy options.
	 *
	 * @return void
	 */
	private function cleanup_legacy_options() {
		automator_delete_option( self::LEGACY_CREDENTIALS );
		automator_delete_option( 'zoho_campaigns_credentials_last_refreshed' );
	}

	/**
	 * Migrate legacy credentials to vault.
	 *
	 * Sends legacy access/refresh tokens to API proxy which stores them in vault
	 * and returns a vault_signature for future authenticated requests.
	 *
	 * @param array $legacy_credentials The legacy credentials to migrate.
	 *
	 * @return array|false Vault credentials on success, false on failure.
	 */
	private function migrate_credentials_to_vault( $legacy_credentials ) {
		$access_token  = $legacy_credentials['access_token'] ?? '';
		$refresh_token = $legacy_credentials['refresh_token'] ?? '';

		if ( empty( $access_token ) || empty( $refresh_token ) ) {
			return false;
		}

		try {
			$nonce = wp_create_nonce( self::MIGRATION_NONCE_ACTION );

			$body = array(
				'action' => 'migrate_to_vault',
				'client' => wp_json_encode( $legacy_credentials ),
				'nonce'  => $nonce,
			);

			$response = $this->api->api_request(
				$body,
				null,
				array(
					'exclude_credentials' => true,
					'exclude_error_check' => true,
				)
			);

			if ( empty( $response['data']['automator_api_message'] ) ) {
				return false;
			}

			// API encrypt() returns urlencode(), so we must urldecode() first.
			$encrypted_message = urldecode( $response['data']['automator_api_message'] );
			$decrypted         = Automator_Helpers_Recipe::automator_api_decode_message( $encrypted_message, $nonce );

			if ( ! is_array( $decrypted ) ) {
				$decrypted = is_object( $decrypted ) ? (array) $decrypted : array();
			}

			if ( empty( $decrypted['vault_signature'] ) || empty( $decrypted['user_id'] ) ) {
				return false;
			}

			return array(
				'vault_signature' => $decrypted['vault_signature'],
				'user_id'         => $decrypted['user_id'],
			);

		} catch ( Exception $e ) {
			return false;
		}
	}
}
