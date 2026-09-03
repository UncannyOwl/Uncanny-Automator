<?php
/**
 * WordPress last-known-good feature-state store.
 *
 * @package Uncanny_Automator
 * @since 7.5.1.1
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Feature_State\Infrastructure;

use Uncanny_Automator\App\Feature_State\Domain\Feature_State;
use Uncanny_Automator\App\Feature_State\Domain\Feature_State_Policy;
use Uncanny_Automator\App\Feature_State\Ports\Last_Known_Feature_State_Store;
use Uncanny_Automator\App\Transient\Domain\License_Transient_Keys;

/**
 * Stores a validated feature-state snapshot in a license-bound transient.
 */
final class WP_Last_Known_Feature_State_Store implements Last_Known_Feature_State_Store {

	public const SCHEMA_VERSION    = 2;
	public const RETENTION_SECONDS = 86400;
	public const RENEWAL_SECONDS   = 43200;

	/**
	 * Whether the transient was read during this request.
	 *
	 * @var bool
	 */
	private bool $loaded = false;

	/**
	 * The validated payload read during this request.
	 *
	 * @var array<string,mixed>|null
	 */
	private ?array $loaded_payload = null;

	/**
	 * Identity fingerprint captured before policy resolution.
	 *
	 * @var string|null
	 */
	private ?string $loaded_identity_hash = null;

	/**
	 * Load the most recent valid feature state.
	 *
	 * Storage errors are treated as an unavailable snapshot. They must not make
	 * feature-state resolution itself fail or manufacture a fallback state.
	 *
	 * @return Feature_State|null
	 */
	public function load(): ?Feature_State {
		$this->loaded               = true;
		$this->loaded_payload       = null;
		$this->loaded_identity_hash = null;

		try {
			// Capture identity before policy resolution even when the transient is absent.
			// A later successful save may create the first snapshot; this fingerprint
			// prevents state resolved for an old identity from being bound to a new one.
			$this->loaded_identity_hash = $this->current_identity_hash();
			$payload                    = get_transient( License_Transient_Keys::FEATURE_STATE_LAST_KNOWN_GOOD );
		} catch ( \Throwable $error ) {
			unset( $error );
			return null;
		}

		if ( ! $this->is_valid_payload( $payload, $this->loaded_identity_hash ) ) {
			return null;
		}

		$this->loaded_payload = $payload;

		$visible_features = array_keys(
			array_filter(
				$payload['visibility'],
				static fn( string $visibility ): bool => Feature_State::VISIBLE === $visibility
			)
		);

		return Feature_State::from_visible_features( $visible_features );
	}

	/**
	 * Persist a successfully evaluated feature state.
	 *
	 * An unchanged snapshot is renewed at most every 12 hours. A storage failure
	 * leaves both the previous transient and the caller's fresh state untouched.
	 *
	 * @param Feature_State $state Successfully evaluated feature state.
	 *
	 * @return void
	 */
	public function save( Feature_State $state ): void {
		if ( ! $this->loaded ) {
			$this->load();
		}

		$now        = time();
		$visibility = $state->to_array();

		if (
			null !== $this->loaded_payload
			&& $visibility === $this->loaded_payload['visibility']
			&& $now - $this->loaded_payload['resolved_at'] < self::RENEWAL_SECONDS
		) {
			return;
		}

		if ( null === $this->loaded_identity_hash ) {
			return;
		}

		try {
			$current_identity_hash = $this->current_identity_hash( true );
		} catch ( \Throwable $error ) {
			unset( $error );
			return;
		}

		// A license/account transition may invalidate the transient while an older
		// request is still resolving. Never let that stale request repopulate a
		// snapshot for the previous identity after the invalidation has completed.
		if ( ! hash_equals( $this->loaded_identity_hash, $current_identity_hash ) ) {
			return;
		}

		// This is a same-request and best-effort shared-cache guard. It catches an
		// invalidation visible through the active transient backend before this request
		// renews or replaces the snapshot. Database-backed transients also rely on
		// set_transient() returning false when another request deleted the option row.
		// WordPress has no portable compare-and-swap across every object-cache drop-in,
		// so cross-request protection for identity changes uses the forced read above.
		if ( null !== $this->loaded_payload ) {
			try {
				$current_payload = get_transient( License_Transient_Keys::FEATURE_STATE_LAST_KNOWN_GOOD );
			} catch ( \Throwable $error ) {
				unset( $error );
				return;
			}

			if ( $this->loaded_payload !== $current_payload ) {
				return;
			}
		}

		$payload = array(
			'schema_version'  => self::SCHEMA_VERSION,
			'policy_revision' => Feature_State_Policy::REVISION,
			'identity_hash'   => $this->loaded_identity_hash,
			'resolved_at'     => $now,
			'visibility'      => $visibility,
		);

		try {
			$stored = set_transient(
				License_Transient_Keys::FEATURE_STATE_LAST_KNOWN_GOOD,
				$payload,
				self::RETENTION_SECONDS
			);
		} catch ( \Throwable $error ) {
			unset( $error );
			return;
		}

		if ( $stored ) {
			$this->loaded_payload = $payload;
		}
	}

	/**
	 * Determine whether a stored payload is complete, current, and trustworthy.
	 *
	 * @param mixed  $payload       Stored transient value.
	 * @param string $identity_hash Current license/account fingerprint.
	 *
	 * @return bool
	 */
	private function is_valid_payload( $payload, string $identity_hash ): bool {
		if (
			! is_array( $payload )
			|| array(
				'schema_version',
				'policy_revision',
				'identity_hash',
				'resolved_at',
				'visibility',
			) !== array_keys( $payload )
			|| self::SCHEMA_VERSION !== $payload['schema_version']
			|| Feature_State_Policy::REVISION !== $payload['policy_revision']
			|| ! is_string( $payload['identity_hash'] )
			|| 1 !== preg_match( '/\A[a-f0-9]{64}\z/D', $payload['identity_hash'] )
			|| ! hash_equals( $identity_hash, $payload['identity_hash'] )
			|| ! is_int( $payload['resolved_at'] )
			|| ! is_array( $payload['visibility'] )
			|| Feature_State::features() !== array_keys( $payload['visibility'] )
		) {
			return false;
		}

		$now = time();

		// Reject all future timestamps without clock-skew tolerance. This keeps the
		// documented 24-hour stale-visibility window as a strict maximum.
		if (
			$payload['resolved_at'] > $now
			|| $payload['resolved_at'] <= $now - self::RETENTION_SECONDS
		) {
			return false;
		}

		foreach ( $payload['visibility'] as $visibility ) {
			if ( ! in_array( $visibility, array( Feature_State::VISIBLE, Feature_State::HIDDEN ), true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Hash the local facts that select the active license/account identity.
	 *
	 * Raw license keys never leave this method. A forced read is used only before
	 * a write, so a concurrent identity transition cannot be hidden by this
	 * request's in-memory option cache.
	 *
	 * @param bool $force Whether to bypass shared and request option caches.
	 *
	 * @return string
	 */
	private function current_identity_hash( bool $force = false ): string {
		$identity = array(
			'pro_plugin_active' => defined( 'AUTOMATOR_PRO_FILE' ),
			'pro_license_key'   => $this->identity_option( 'uap_automator_pro_license_key', $force ),
			'pro_status'        => $this->identity_option( 'uap_automator_pro_license_status', $force ),
			'free_license_key'  => $this->identity_option( 'uap_automator_free_license_key', $force ),
			'free_status'       => $this->identity_option( 'uap_automator_free_license_status', $force ),
		);

		$encoded = wp_json_encode( $identity, JSON_INVALID_UTF8_SUBSTITUTE );

		if ( ! is_string( $encoded ) ) {
			throw new \UnexpectedValueException( 'The feature-state identity could not be encoded.' );
		}

		return hash( 'sha256', $encoded );
	}

	/**
	 * Normalize one identity option without accepting an arbitrary stored shape.
	 *
	 * @param string $option Option name.
	 * @param bool   $force  Whether to bypass option caches.
	 *
	 * @return string
	 */
	private function identity_option( string $option, bool $force ): string {
		$value = automator_get_option( $option, '', $force );

		if ( null === $value ) {
			return '';
		}

		if ( ! is_string( $value ) ) {
			return '__invalid_' . gettype( $value );
		}

		return trim( $value );
	}
}
