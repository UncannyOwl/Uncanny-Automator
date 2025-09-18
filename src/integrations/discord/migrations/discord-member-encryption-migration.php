<?php

namespace Uncanny_Automator\Integrations\Discord;

use Uncanny_Automator\Migrations\Migration;

/**
 * Class Discord_Member_Encryption_Migration.
 *
 * Migrates Discord member cache data to encrypted format.
 *
 * @package Uncanny_Automator
 */
class Discord_Member_Encryption_Migration extends Migration {

	/**
	 * Discord helpers instance
	 *
	 * @var Discord_App_Helpers
	 */
	private $helpers;

	/**
	 * __construct
	 *
	 * @param string $name
	 * @return void
	 */
	public function __construct( $name, $helpers ) {

		$this->name    = $name;
		$this->helpers = $helpers;

		add_action( 'shutdown', array( $this, 'maybe_run_migration' ) );
	}

	/**
	 * conditions_met
	 *
	 * Check if migration should run - only if Discord integration is connected
	 *
	 * @return bool
	 */
	public function conditions_met() {
		// This class will only be loaded if the Discord integration is connected.
		return true;
	}

	/**
	 * migrate
	 *
	 * Remove unencrypted Discord member cache data to force regeneration
	 *
	 * @return void
	 */
	public function migrate() {

		$processed = 0;
		$total     = 0;

		// Get all Discord server configurations
		$servers = automator_get_option( $this->helpers->get_const( 'SERVERS' ), array() );

		// Loop through servers.
		foreach ( $servers as $server_id => $server ) {
			++$total;

			// Check for cached member data
			$key     = 'DISCORD_MEMBERS_' . $server_id;
			$members = automator_get_option( $key, array() );

			if ( ! empty( $members ) ) {
				// Delete the option we no longer store this.
				automator_delete_option( $key );
			}

			++$processed;
		}

		automator_log(
			"Discord member encryption migration complete: Processed {$processed} / {$total} servers",
			$this->name
		);

		// Encrypt user data stored in credentials.
		$credentials = $this->helpers->get_credentials();
		if ( ! is_string( $credentials['user'] ) ) {
			$encrypted_user      = $this->helpers->encrypt_data( $credentials['user'], $credentials['discord_id'], 'user' );
			$credentials['user'] = $encrypted_user;
			automator_update_option( $this->helpers->get_const( 'CREDENTIALS' ), $credentials );
			automator_log(
				"Discord connected account user data encryption complete",
				$this->name
			);
		}

		// Clean up Discord username user meta for compliance
		$this->delete_discord_username_meta();

		// Mark migration as complete
		$this->complete();
	}

	/**
	 * Clean up Discord username user meta for compliance.
	 *
	 * @return void
	 */
	private function delete_discord_username_meta() {
		global $wpdb;

		// Delete all Discord username user meta entries
		$deleted = $wpdb->delete(
			$wpdb->usermeta,
			array( 'meta_key' => 'automator_discord_member_username' ),
			array( '%s' )
		);

		automator_log(
			"Discord username meta cleanup: Removed {$deleted} entries for compliance",
			$this->name
		);
	}
}
