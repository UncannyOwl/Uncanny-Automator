<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Atomic_Option_Lock;

/**
 * Small site-local limiter for public OAuth endpoints.
 *
 * The network subject deliberately comes only from REMOTE_ADDR. Forwarded
 * headers are controlled by upstream vendors and must not become an implicit
 * security boundary inside WordPress. Customers behind the same vendor egress
 * can therefore share the coarse IP bucket; client-specific buckets reduce
 * that effect after identification. This availability tradeoff is preferable
 * to letting callers spoof a forwarding header to bypass a public limiter.
 */
final class OAuth_Rate_Limiter {

	public const STORAGE_FAILURE_MESSAGE = 'This site could not save a security check. Try again. If the problem continues, ask your web host to check WordPress database and object-cache storage.';

	private Atomic_Option_Lock $lock;

	/**
	 * Constructor.
	 */
	public function __construct( ?Atomic_Option_Lock $lock = null ) {
		$this->lock = $lock ?? new Atomic_Option_Lock();
	}

	/**
	 * Consume one request from a bounded, fixed window.
	 *
	 * @return bool|null True if allowed, false if the quota is exhausted, or null if the counter could not be saved.
	 */
	public function allows( string $action, string $client_id, int $limit, int $window ): ?bool {
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] )
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Hashed as an opaque network subject and never output.
			? wp_unslash( $_SERVER['REMOTE_ADDR'] )
			: 'unknown';
		$key   = 'automator_mcp_oauth_rate_' . hash( 'sha256', $action . '|' . $remote . '|' . $client_id );
		$owner = $this->lock->acquire( $key . '_lock', 5, 0.25 );
		if ( false === $owner ) {
			// Lock contention is a slow database or a concurrent request, not an
			// abusive caller. Refusing here would turn a shared-IP burst of two
			// legitimate requests into a 429; the fixed window still bounds volume.
			return true;
		}

		try {
			$now   = time();
			$state = get_transient( $key );
			$state = is_array( $state ) ? $state : array();

			if ( ! isset( $state['started'], $state['count'] ) || (int) $state['started'] + $window <= $now ) {
				$state = array(
					'started' => $now,
					'count'   => 0,
				);
			}

			if ( (int) $state['count'] >= $limit ) {
				return false;
			}

			++$state['count'];

			return set_transient( $key, $state, $window ) ? true : null;
		} finally {
			$this->lock->release( $key . '_lock', $owner );
		}
	}
}
