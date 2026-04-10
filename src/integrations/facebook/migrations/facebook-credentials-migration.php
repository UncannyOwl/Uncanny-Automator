<?php

namespace Uncanny_Automator\Integrations\Facebook;

use Uncanny_Automator\Automator_Helpers_Recipe;
use Uncanny_Automator\Migrations\Migration;
use Exception;

/**
 * Class Facebook_Credentials_Migration
 *
 * Migrates Facebook credentials from legacy format to vault-based storage.
 * Also migrates option names from legacy to framework defaults and cleans page data.
 *
 * @package Uncanny_Automator\Integrations\Facebook
 */
class Facebook_Credentials_Migration extends Migration {

	/**
	 * The nonce for vault migration (matches API proxy).
	 *
	 * @var string
	 */
	const VAULT_MIGRATION_NONCE = 'm1gr4t5_fB@2026';

	/**
	 * Legacy option names.
	 */
	const LEGACY_CREDENTIALS = '_uncannyowl_facebook_settings';
	const LEGACY_PAGES       = '_uncannyowl_facebook_pages_settings';

	/**
	 * New framework-standard option names.
	 */
	const NEW_CREDENTIALS = 'automator_facebook_pages_credentials';
	const NEW_PAGES       = 'automator_facebook_pages_account';

	/**
	 * The API caller instance.
	 *
	 * @var Facebook_Api_Caller
	 */
	private $api;

	/**
	 * Constructor.
	 *
	 * @param string              $name The migration name.
	 * @param Facebook_Api_Caller $api  The API caller instance.
	 */
	public function __construct( $name, $api = null ) {
		$this->name = $name;
		$this->api  = $api;
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
		$legacy_credentials = automator_get_option( self::LEGACY_CREDENTIALS );
		$new_credentials    = automator_get_option( self::NEW_CREDENTIALS );

		// If new credentials already exist, just clean up legacy.
		if ( ! empty( $new_credentials ) ) {
			$this->cleanup_legacy_options();
			$this->complete();
			return;
		}

		// If no legacy credentials, nothing to migrate.
		if ( empty( $legacy_credentials ) ) {
			$this->complete();
			return;
		}

		// Attempt vault migration.
		$migrated_credentials = $this->migrate_credentials_to_vault( $legacy_credentials );

		if ( false === $migrated_credentials ) {
			// Migration failed - preserve legacy options.
			$this->complete();
			return;
		}

		// Save new credentials.
		automator_update_option( self::NEW_CREDENTIALS, $migrated_credentials );

		// Verify credentials were saved.
		if ( empty( automator_get_option( self::NEW_CREDENTIALS ) ) ) {
			// Save failed - preserve legacy options.
			$this->complete();
			return;
		}

		// Migrate pages if we have them.
		$legacy_pages = automator_get_option( self::LEGACY_PAGES );
		if ( ! empty( $legacy_pages ) ) {
			$cleaned_pages = array_map( array( $this, 'clean_page_data' ), (array) $legacy_pages );
			automator_update_option( self::NEW_PAGES, $cleaned_pages );
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
		automator_delete_option( self::LEGACY_PAGES );
	}

	/**
	 * Migrate legacy credentials to vault.
	 *
	 * @param array $legacy_credentials The legacy credentials to migrate.
	 *
	 * @return array|false The new vault credentials on success, false on failure.
	 */
	private function migrate_credentials_to_vault( $legacy_credentials ) {
		$token   = $legacy_credentials['user']['token'] ?? '';
		$user_id = $legacy_credentials['user']['id'] ?? '';

		if ( empty( $token ) || empty( $user_id ) || empty( $this->api ) ) {
			return false;
		}

		try {
			$nonce = wp_create_nonce( 'automator_facebook_migration_' . $user_id );

			$body = array(
				'action'          => 'migrate_to_vault',
				'migration_nonce' => self::VAULT_MIGRATION_NONCE,
				'access_token'    => $token,
				'fb_user_id'      => $user_id,
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

			if ( empty( $response['data']['uncannny_api_message'] ) ) {
				return false;
			}

			// API encrypt() returns urlencode(), so we must urldecode() first.
			$encrypted_message = urldecode( $response['data']['uncannny_api_message'] );
			$decrypted         = Automator_Helpers_Recipe::automator_api_decode_message( $encrypted_message, $nonce );

			if ( ! is_array( $decrypted ) ) {
				$decrypted = is_object( $decrypted ) ? (array) $decrypted : array();
			}

			if ( empty( $decrypted['vault_signature'] ) || empty( $decrypted['fb_user_id'] ) ) {
				return false;
			}

			return array(
				'vault_signature' => $decrypted['vault_signature'],
				'fb_user_id'      => $decrypted['fb_user_id'],
				'user-info'       => $legacy_credentials['user-info'] ?? array(),
			);

		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Clean page data - remove tokens and migrate IG account format.
	 *
	 * @param array $page The page data.
	 *
	 * @return array The cleaned page data.
	 */
	private function clean_page_data( $page ) {
		if ( is_object( $page ) ) {
			$page = (array) $page;
		}

		unset( $page['page_access_token'] );
		unset( $page['tasks'] );

		if ( ! empty( $page['ig_account'] ) ) {
			$page['ig_account'] = $this->migrate_instagram_account( $page['ig_account'] );
		}

		return $page;
	}

	/**
	 * Migrate Instagram account from legacy nested format to flat format.
	 *
	 * Legacy: { data: [{ instagram_business_account: 'id', username: '...' }] }
	 * New:    { id: 'id', username: '...', connection_status: 'connected' }
	 *
	 * @param array|object $ig_account The Instagram account data.
	 *
	 * @return array The migrated account data.
	 */
	private function migrate_instagram_account( $ig_account ) {
		if ( is_object( $ig_account ) ) {
			$ig_account = (array) $ig_account;
		}

		// Already in new format.
		if ( ! isset( $ig_account['data'] ) ) {
			return $ig_account;
		}

		$data = is_object( $ig_account['data'] ) ? (array) $ig_account['data'] : $ig_account['data'];

		$account = end( $data );
		if ( empty( $account ) ) {
			return array( 'connection_status' => 'not_connected' );
		}

		if ( is_object( $account ) ) {
			$account = (array) $account;
		}

		$business_id = $account['instagram_business_account'] ?? '';
		if ( empty( $business_id ) ) {
			return array( 'connection_status' => 'not_connected' );
		}

		return array(
			'id'                  => $business_id,
			'username'            => $account['username'] ?? '',
			'profile_picture_url' => $account['profile_picture_url'] ?? $account['profile_pic'] ?? '',
			'connection_status'   => 'connected',
		);
	}
}
