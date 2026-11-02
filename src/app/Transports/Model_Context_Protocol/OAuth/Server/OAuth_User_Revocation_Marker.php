<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server;

/**
 * Lasting record that a user's earlier MCP credentials are revoked on this site.
 *
 * Revocation cleanup works family by family and skips a family whose lock is
 * busy. The marker is written first, so every grant, code and access token whose
 * issuance began at or before it is refused wherever it is checked, whether or
 * not cleanup finished.
 */
final class OAuth_User_Revocation_Marker {

	private const KIND = 'user_revocation';

	/**
	 * The marker must outlive every credential issued before it. Refresh families
	 * are the longest-lived, so keep it for their lifetime plus a margin.
	 */
	private const RETENTION = OAuth_Refresh_Token_Service::LIFETIME + 2 * DAY_IN_SECONDS;

	private OAuth_Artifact_Store $store;

	/**
	 * Constructor.
	 */
	public function __construct( ?OAuth_Artifact_Store $store = null ) {
		$this->store = $store ?? new OAuth_Artifact_Store();
	}

	/**
	 * Revoke everything issued for the user up to now. Never moves the marker back.
	 */
	public function mark( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}

		$now      = microtime( true );
		$previous = $this->revoked_after( $user_id ) ?? 0.0;

		return $this->store->put(
			self::KIND,
			(string) $user_id,
			array(
				'user_id'       => $user_id,
				'revoked_after' => max( $previous, $now ),
				'expires_at'    => (int) $now + self::RETENTION,
			)
		);
	}

	/**
	 * Whether a credential whose issuance began at $issued_at is revoked.
	 *
	 * A record saved before issue times were stored has none, so it counts as
	 * issued before any marker.
	 *
	 * @param int        $user_id   Credential owner.
	 * @param float|null $issued_at When issuance began, or null when unknown.
	 */
	public function covers( int $user_id, ?float $issued_at ): bool {
		$revoked_after = $this->revoked_after( $user_id );

		return null !== $revoked_after && ( $issued_at ?? 0.0 ) <= $revoked_after;
	}

	/**
	 * Check coverage without treating a failed database read as an absent marker.
	 *
	 * @since 8.0.0
	 *
	 * @return bool|null Null means the marker could not be checked.
	 */
	public function covers_confirmed( int $user_id, ?float $issued_at ): ?bool {
		if ( $user_id <= 0 ) {
			return null;
		}

		$state = $this->store->get_confirmed( self::KIND, (string) $user_id );
		if ( null === $state ) {
			return null;
		}
		if ( ! $state['exists'] ) {
			return false;
		}
		$record = $state['record'];
		if ( ! is_numeric( $record['revoked_after'] ?? null ) ) {
			return null;
		}

		return ( $issued_at ?? 0.0 ) <= (float) $record['revoked_after'];
	}

	/**
	 * The current marker, or null when the user's credentials were never revoked here.
	 */
	private function revoked_after( int $user_id ): ?float {
		if ( $user_id <= 0 ) {
			return null;
		}

		$record = $this->store->get( self::KIND, (string) $user_id );

		return is_array( $record ) && is_numeric( $record['revoked_after'] ?? null )
			? (float) $record['revoked_after']
			: null;
	}
}
