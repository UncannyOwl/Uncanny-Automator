<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Hosted_Bridge;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server\Hosted_Agent_Access;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server\OAuth_Artifact_Store;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server\OAuth_Client_Registry;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server\OAuth_Server_Configuration;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server\OAuth_User_Revocation_Marker;

/**
 * Whether a hosted sign-in still stands in WordPress.
 *
 * The hosted service's sign-in is one OAuth refresh family. It stands while
 * the family is live and belongs to the user, its client is the trusted hosted
 * callback and not revoked, OAuth credentials are still accepted, the user's
 * revocation marker does not cover it, and the user can still manage options.
 * The routes check this on every call. A stored read credential may be held
 * for a retry when its liveness or remote revocation is unconfirmed.
 */
final class Hosted_Sign_In_Liveness {

	private OAuth_Artifact_Store $artifacts;

	private OAuth_Client_Registry $clients;

	/**
	 * Constructor.
	 */
	public function __construct( ?OAuth_Artifact_Store $artifacts = null, ?OAuth_Client_Registry $clients = null ) {
		$this->artifacts = $artifacts ?? new OAuth_Artifact_Store();
		$this->clients   = $clients ?? new OAuth_Client_Registry();
	}

	/**
	 * Whether the sign-in stands.
	 *
	 * @param string|null $client_id When given, the family must belong to exactly this client.
	 */
	public function is_live( int $user_id, string $family_id, ?string $client_id = null ): bool {
		return true === $this->status( $user_id, $family_id, $client_id );
	}

	/**
	 * Check whether the sign-in is live, dead, or could not be verified.
	 *
	 * @since 8.0.0
	 *
	 * @return bool|null Null means the sign-in's status is unproven.
	 */
	public function status( int $user_id, string $family_id, ?string $client_id = null ): ?bool {
		if ( $user_id <= 0 || '' === $family_id ) {
			return null;
		}

		$state = $this->artifacts->get_confirmed( 'family', $family_id );
		if ( null === $state ) {
			return null;
		}
		if ( ! $state['exists'] ) {
			return false;
		}
		$family = $state['record'];
		if (
			! is_numeric( $family['expires_at'] ?? null )
			|| ! is_numeric( $family['user_id'] ?? null )
			|| ( isset( $family['revoked_at'] ) && ! is_numeric( $family['revoked_at'] ) )
			|| ( isset( $family['issued_at'] ) && ! is_numeric( $family['issued_at'] ) )
			|| ( isset( $family['family_id'] ) && ( ! is_string( $family['family_id'] ) || $family_id !== $family['family_id'] ) )
		) {
			return null;
		}
		if ( (int) $family['user_id'] !== $user_id ) {
			return null;
		}
		if ( ! empty( $family['revoked_at'] ) || (int) $family['expires_at'] <= time() ) {
			return false;
		}

		$family_client = $family['client_id'] ?? null;
		if ( ! is_string( $family_client ) || '' === $family_client ) {
			return null;
		}
		if ( null !== $client_id && $family_client !== $client_id ) {
			return null;
		}

		$revoked = $this->clients->is_revoked_confirmed( $family_client );
		if ( null === $revoked ) {
			return null;
		}
		if ( $revoked ) {
			return false;
		}
		$client = $this->clients->resolve( $family_client );
		if ( ! is_array( $client ) ) {
			return null;
		}

		$covered = ( new OAuth_User_Revocation_Marker( $this->artifacts ) )->covers_confirmed(
			$user_id,
			is_numeric( $family['issued_at'] ?? null ) ? (float) $family['issued_at'] : null
		);
		if ( null === $covered ) {
			return null;
		}
		if ( $covered ) {
			return false;
		}
		if (
			! Hosted_Agent_Access::allows_client( $client )
			|| ! ( new OAuth_Server_Configuration() )->can_use_existing_credentials()
			|| ! user_can( $user_id, 'manage_options' )
		) {
			return null;
		}

		return true;
	}

	/**
	 * When the admin approved the sign-in: its family's issue time, which
	 * renewals keep. Unknown sign-ins sort last.
	 */
	public function connected_at( string $family_id ): float {
		$family = '' === $family_id ? null : $this->artifacts->get( 'family', $family_id );

		return is_array( $family ) && is_numeric( $family['issued_at'] ?? null ) ? (float) $family['issued_at'] : PHP_FLOAT_MAX;
	}
}
