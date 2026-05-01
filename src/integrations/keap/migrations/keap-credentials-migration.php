<?php

namespace Uncanny_Automator\Integrations\Keap;

use Uncanny_Automator\Automator_Helpers_Recipe;
use Uncanny_Automator\Migrations\Migration;
use Exception;

/**
 * Class Keap_Credentials_Migration
 *
 * Migrates Keap credentials from legacy format to vault-based storage.
 *
 * @package Uncanny_Automator\Integrations\Keap
 */
class Keap_Credentials_Migration extends Migration {

	/**
	 * The nonce for vault migration (matches API proxy).
	 *
	 * @var string
	 */
	const VAULT_MIGRATION_NONCE = 'k3ap_m1gr@2026';

	/**
	 * Legacy option name.
	 *
	 * @var string
	 */
	const LEGACY_CREDENTIALS = 'automator_keap_credentials';

	/**
	 * The helpers instance.
	 *
	 * @var Keap_App_Helpers
	 */
	private $helpers;

	/**
	 * The API caller instance.
	 *
	 * @var Keap_Api_Caller
	 */
	private $api;

	/**
	 * Constructor.
	 *
	 * @param string   $name         The migration name.
	 * @param stdClass $dependencies The dependencies object.
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
		$new_credentials    = $this->helpers->get_credentials();

		// If new credentials already exist (has vault_signature), migration already done.
		if ( ! empty( $new_credentials['vault_signature'] ) ) {
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
				'keap_id'         => $migrated_credentials['keap_id'],
			)
		);

		// Verify credentials were saved.
		$saved = $this->helpers->get_credentials();
		if ( empty( $saved['vault_signature'] ) ) {
			$this->complete();
			return;
		}

		$this->complete();
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
			$nonce = wp_create_nonce( 'automator_keap_migration' );

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

			if ( empty( $decrypted['vault_signature'] ) || empty( $decrypted['keap_id'] ) ) {
				return false;
			}

			return array(
				'vault_signature' => $decrypted['vault_signature'],
				'keap_id'         => $decrypted['keap_id'],
			);

		} catch ( Exception $e ) {
			return false;
		}
	}
}
