<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\License;

/**
 * Class License_Snapshot_Store
 *
 * Persists the last-known-good license snapshot in a non-autoloaded option.
 *
 * An option, not a transient: the snapshot must survive an object cache that
 * evicts transients, and it is separate from the 12-hour license cache so it
 * still exists when that cache expires — the moment a refresh is needed.
 *
 * The snapshot is bound to the hash of the license key it was fetched for
 * and is never returned for another key. The license document is stored as
 * the platform sent it, as the 12-hour cache stores it, so it carries the
 * license_key field that get_resolved_plan() checks.
 *
 * @since 8.0.1
 * @package Uncanny_Automator\App\Infrastructure\License
 */
final class License_Snapshot_Store {

	/**
	 * Option holding array( 'key_hash' => string, 'license' => array, 'fetched_at' => int ).
	 *
	 * @var string
	 */
	const OPTION = 'automator_api_license_last_good';

	/**
	 * Save the snapshot for a license key.
	 *
	 * @param string           $key_hash The sha256 hash of the license key.
	 * @param License_Snapshot $snapshot The snapshot.
	 *
	 * @return void
	 */
	public function save( string $key_hash, License_Snapshot $snapshot ): void {
		automator_update_option(
			self::OPTION,
			array(
				'key_hash'   => $key_hash,
				'license'    => $snapshot->get_license(),
				'fetched_at' => $snapshot->get_fetched_at(),
			),
			false
		);
	}

	/**
	 * Find the usable snapshot for a license key.
	 *
	 * @param string $key_hash The sha256 hash of the license key.
	 * @param int    $now      The current Unix time.
	 *
	 * @return License_Snapshot|null The snapshot, or null when there is none for this key, it is malformed, or it is too old.
	 */
	public function find( string $key_hash, int $now ): ?License_Snapshot {
		$stored = automator_get_option( self::OPTION, false );

		if ( ! $this->is_well_formed( $stored ) || ! hash_equals( $stored['key_hash'], $key_hash ) ) {
			return null;
		}

		$snapshot = new License_Snapshot( $stored['license'], $stored['fetched_at'] );

		return $snapshot->is_usable_at( $now ) ? $snapshot : null;
	}

	/**
	 * Delete the snapshot.
	 *
	 * @return void
	 */
	public function delete(): void {
		automator_delete_option( self::OPTION );
	}

	/**
	 * Whether a stored value has the shape save() writes, with a license document in it.
	 *
	 * @param mixed $stored The stored option value.
	 *
	 * @return bool
	 */
	private function is_well_formed( $stored ): bool {
		return is_array( $stored )
			&& isset( $stored['key_hash'], $stored['license'], $stored['fetched_at'] )
			&& is_string( $stored['key_hash'] )
			&& is_int( $stored['fetched_at'] )
			&& License_Manager::is_license_document( $stored['license'] );
	}
}
