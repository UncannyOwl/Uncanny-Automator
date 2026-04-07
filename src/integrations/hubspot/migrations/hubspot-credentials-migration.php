<?php

namespace Uncanny_Automator\Integrations\HubSpot;

use Uncanny_Automator\Automator_Helpers_Recipe;
use Uncanny_Automator\Migrations\Migration;
use Exception;

/**
 * Class HubSpot_Credentials_Migration
 *
 * Migrates HubSpot credentials from legacy format to vault-based storage.
 *
 * @package Uncanny_Automator\Integrations\HubSpot
 */
class HubSpot_Credentials_Migration extends Migration {

	/**
	 * The nonce for vault migration (matches API proxy).
	 *
	 * @var string
	 */
	const VAULT_MIGRATION_NONCE = 'h8bsp0t_m1gr@2026';

	/**
	 * Legacy option name.
	 */
	const LEGACY_CREDENTIALS = '_automator_hubspot_settings';

	/**
	 * The helpers instance.
	 *
	 * @var HubSpot_App_Helpers
	 */
	private $helpers;

	/**
	 * The API caller instance.
	 *
	 * @var HubSpot_Api_Caller
	 */
	private $api;

	/**
	 * Constructor.
	 *
	 * @param string   $name         The migration name.
	 * @param stdClass $dependencies The dependencies object.
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
		$new_credentials    = $this->helpers->get_credentials();

		// If new credentials already exist, just clean up legacy.
		if ( ! empty( $new_credentials['hubspot_id'] ) ) {
			$this->cleanup_legacy_options();
			$this->complete();
			return;
		}

		// If no legacy credentials, nothing to migrate.
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
		$this->helpers->store_credentials( $migrated_credentials );

		// Verify credentials were saved.
		$saved = $this->helpers->get_credentials();
		if ( empty( $saved['hubspot_id'] ) ) {
			$this->complete();
			return;
		}

		// Store account info from migration response.
		$this->helpers->store_account_info(
			array(
				'user'       => $migrated_credentials['user'] ?? '',
				'hub_domain' => $migrated_credentials['hub_domain'] ?? '',
			)
		);

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
		automator_delete_option( '_automator_hubspot_token_info' );
		automator_delete_option( '_automator_hubspot_last_refresh_token_call' );
		automator_delete_option( '_automator_hubspot_refresh_token_failed_attempts' );
		delete_transient( '_automator_hubspot_token_info' );
	}

	/**
	 * Migrate legacy credentials to vault.
	 *
	 * @param array $legacy_credentials The legacy credentials to migrate.
	 *
	 * @return array|false The new vault credentials on success, false on failure.
	 */
	private function migrate_credentials_to_vault( $legacy_credentials ) {
		$access_token  = $legacy_credentials['access_token'] ?? '';
		$refresh_token = $legacy_credentials['refresh_token'] ?? '';

		if ( empty( $access_token ) || empty( $refresh_token ) ) {
			return false;
		}

		try {
			$nonce = wp_create_nonce( 'automator_hubspot_migration' );

			$body = array(
				'action'          => 'migrate_to_vault',
				'migration_nonce' => self::VAULT_MIGRATION_NONCE,
				'client'          => wp_json_encode( $legacy_credentials ),
				'nonce'           => $nonce,
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

			if ( empty( $decrypted['vault_signature'] ) || empty( $decrypted['hubspot_id'] ) ) {
				return false;
			}

			return array(
				'vault_signature' => $decrypted['vault_signature'],
				'hubspot_id'      => $decrypted['hubspot_id'],
				'user'            => $decrypted['user'] ?? '',
				'hub_domain'      => $decrypted['hub_domain'] ?? '',
			);

		} catch ( Exception $e ) {
			return false;
		}
	}
}
