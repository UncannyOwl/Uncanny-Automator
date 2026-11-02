<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform\Value_Objects;

/**
 * Immutable outcome of an HMAC verification attempt.
 *
 * Three possible outcomes:
 *  - CURRENT  — signature matched the active signing secret
 *  - PREVIOUS — signature matched the prior secret within the rotation grace
 *  - FAIL     — refused, for one {@see Signature_Rejection_Reason}
 *
 * The clock difference (this site's clock − the delivery's timestamp, in
 * seconds, positive when this site is ahead) is carried only when the
 * signature verified — CURRENT, PREVIOUS, or a FAIL for `timestamp_skew`.
 * A timestamp no secret of this site's binds to the platform measures
 * nothing, so it never reaches a clock record.
 *
 * Built only through the named factories, so a refusal always has a reason
 * and an accepted result never has one.
 *
 * @since 8.0.0
 */
final class Hmac_Verification_Result {

	/**
	 * @var string One of CURRENT | PREVIOUS | FAIL.
	 */
	private $outcome;

	/**
	 * @var Signature_Rejection_Reason|null
	 */
	private $rejection;

	/**
	 * @var int|null
	 */
	private $clock_difference;

	/**
	 * @param string                          $outcome          CURRENT | PREVIOUS | FAIL.
	 * @param Signature_Rejection_Reason|null $rejection        Null unless FAIL.
	 * @param int|null                        $clock_difference Null unless the signature verified.
	 */
	private function __construct( string $outcome, ?Signature_Rejection_Reason $rejection, ?int $clock_difference ) {
		$this->outcome          = $outcome;
		$this->rejection        = $rejection;
		$this->clock_difference = $clock_difference;
	}

	/**
	 * Verified with the active signing secret.
	 *
	 * @param int $clock_difference This site's clock − the delivery's timestamp.
	 *
	 * @return self
	 */
	public static function current( int $clock_difference ): self {
		return new self( 'CURRENT', null, $clock_difference );
	}

	/**
	 * Verified with the prior signing secret, inside the rotation grace.
	 *
	 * @param int $clock_difference This site's clock − the delivery's timestamp.
	 *
	 * @return self
	 */
	public static function previous( int $clock_difference ): self {
		return new self( 'PREVIOUS', null, $clock_difference );
	}

	/**
	 * The signature verified, but its timestamp is outside the allowance.
	 *
	 * @param int $clock_difference This site's clock − the delivery's timestamp.
	 *
	 * @return self
	 */
	public static function timestamp_skew( int $clock_difference ): self {
		return new self( 'FAIL', Signature_Rejection_Reason::timestamp_skew(), $clock_difference );
	}

	/**
	 * No secret this site holds verified the delivery.
	 *
	 * @return self
	 */
	public static function signature_mismatch(): self {
		return new self( 'FAIL', Signature_Rejection_Reason::signature_mismatch(), null );
	}

	/**
	 * No secret matched, and the delivery claimed another fingerprint.
	 *
	 * @return self
	 */
	public static function stale_fingerprint(): self {
		return new self( 'FAIL', Signature_Rejection_Reason::stale_fingerprint(), null );
	}

	/**
	 * @return bool
	 */
	public function ok(): bool {

		return 'FAIL' !== $this->outcome;
	}

	/**
	 * @return string CURRENT | PREVIOUS | FAIL.
	 */
	public function outcome(): string {

		return $this->outcome;
	}

	/**
	 * Why the delivery was refused; null when it verified.
	 *
	 * @return Signature_Rejection_Reason|null
	 */
	public function rejection(): ?Signature_Rejection_Reason {

		return $this->rejection;
	}

	/**
	 * This site's clock − the delivery's timestamp, in seconds; null unless
	 * the signature verified.
	 *
	 * @return int|null
	 */
	public function clock_difference(): ?int {

		return $this->clock_difference;
	}
}
