<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform\Value_Objects;

/**
 * Why the callback receiver could not verify a platform delivery — the
 * `reason` of its `401 {"error":{"type":"SIGNATURE_REJECTED","reason":…}}`
 * answer (callback delivery contract §4).
 *
 * A fixed set of three, and nothing else: the constructor is private, so a
 * value exists only through one of the three factories, and {@see value()} is
 * the wire string the platform reads.
 *
 *  - `timestamp_skew`     — the signature verified, but its timestamp is more
 *                           than the allowance from this site's clock. Fixed by
 *                           the clock, never by re-registering.
 *  - `signature_mismatch` — everything else: no secret this site holds
 *                           (current or previous) matched, the `v1=` prefix is
 *                           missing, or the timestamp or signature header is
 *                           missing or not well formed.
 *  - `stale_fingerprint`  — no secret matched and the delivery claimed a
 *                           signing-secret fingerprint other than the one this
 *                           site holds.
 *
 * @since 8.0.0
 */
final class Signature_Rejection_Reason {

	private const TIMESTAMP_SKEW = 'timestamp_skew';

	private const SIGNATURE_MISMATCH = 'signature_mismatch';

	private const STALE_FINGERPRINT = 'stale_fingerprint';

	/**
	 * @var string One of the three constants above.
	 */
	private $value;

	/**
	 * @param string $value One of the three constants above.
	 */
	private function __construct( string $value ) {
		$this->value = $value;
	}

	/**
	 * @return self
	 */
	public static function timestamp_skew(): self {
		return new self( self::TIMESTAMP_SKEW );
	}

	/**
	 * @return self
	 */
	public static function signature_mismatch(): self {
		return new self( self::SIGNATURE_MISMATCH );
	}

	/**
	 * @return self
	 */
	public static function stale_fingerprint(): self {
		return new self( self::STALE_FINGERPRINT );
	}

	/**
	 * The wire string: `timestamp_skew`, `signature_mismatch` or `stale_fingerprint`.
	 *
	 * @return string
	 */
	public function value(): string {
		return $this->value;
	}

	/**
	 * Whether the clock, not the credentials, is what failed.
	 *
	 * @return bool
	 */
	public function is_timestamp_skew(): bool {
		return self::TIMESTAMP_SKEW === $this->value;
	}
}
