<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Value_Objects\Hmac_Verification_Result;

/**
 * HMAC signing + verification for platform-originated requests.
 *
 * Scheme: `v1:{ts}:{body}` hashed with HMAC-SHA256 using the per-site
 * signing secret; signature transmitted as `v1=<hex>`. A delivery is accepted
 * when its timestamp is within {@see self::allowance_seconds()} of this site's
 * clock — {@see self::SKEW_SECONDS}, the same allowance the platform gives this
 * site's own signed requests (callback delivery contract §4), unless the site
 * tightened it. During rotation, the previous secret
 * remains valid for a 24h grace (owned by {@see Site_Credentials_Store}).
 *
 * What protects against a replayed delivery inside that allowance:
 *  - the platform re-signs on EVERY delivery attempt (CallbackSigner stamps
 *    `time()` inside the attempt), so a signature only ever has to survive
 *    transit and clock drift, never queue time — a captured delivery replays
 *    for at most the allowance, and outside it is refused;
 *  - the receiver is idempotent: per-event dedup in
 *    Integrations_Webhook_Controller (the durable inbox's UNIQUE key, fronted
 *    by 24h transients) and the resume handler's Layer-2 guard make a
 *    replayed batch accepted again without effect.
 *
 * Idempotency is deliberately NOT enforced here. The platform's callback
 * delivery is at-least-once: its first retry has zero backoff
 * (DeliverCallbackBatchJob::BACKOFF = [0, …]) and re-signs in the same
 * wall-clock second → a BYTE-IDENTICAL signature. A single-use signature
 * nonce would refuse that legitimate retry. Duplicate suppression is owned by
 * the event-id dedup above, which correctly answers success on a repeat.
 *
 * Every refusal carries one {@see Value_Objects\Signature_Rejection_Reason}.
 * The signature is checked BEFORE the timestamp's distance: a timestamp means
 * something about this site's clock only once a secret this site holds binds
 * it to the platform. So `timestamp_skew` is a delivery that verified but is
 * stamped too far from this site's clock; a far-off timestamp on a signature
 * nothing verifies is `signature_mismatch`, like any other unverifiable
 * request — anyone can send one, and it must not reach the clock record.
 *
 * Mirrors the platform-side implementation documented in
 * `automator-platform-alignment-plan.md` Task 3 and the platform's
 * Plan 02a Task 9 `ConnectionSignature`.
 *
 * @since 8.0.0
 */
final class Hmac_Validator {

	/**
	 * How far a signature's timestamp may be from the verifier's clock, in
	 * either direction: 300 seconds. One quantity on both sides of the v1
	 * scheme — the platform refuses this site's requests past it (`CLOCK_SKEW`)
	 * and this site refuses the platform's deliveries past it
	 * (`timestamp_skew`). The one owner of the number; {@see Clock_Skew_Monitor}
	 * and {@see \Uncanny_Automator\App\Presentation\Admin\Clock_Skew_Notice}
	 * read it, and the allowance this site actually gives deliveries
	 * ({@see allowance_seconds()}).
	 *
	 * It is also the ceiling of the `automator_platform_hmac_skew_seconds`
	 * filter: a site may tighten its allowance, never widen it.
	 */
	public const SKEW_SECONDS = 300;

	/**
	 * The floor of the `automator_platform_hmac_skew_seconds` filter: no site
	 * narrows the allowance below transit time plus a little drift.
	 */
	private const MIN_SKEW_SECONDS = 30;

	/**
	 * @var Site_Credentials_Store
	 */
	private $store;

	/**
	 * Returns this site's clock as a unix timestamp.
	 *
	 * @var callable(): int
	 */
	private $clock;

	/**
	 * @param Site_Credentials_Store $store Per-site signing-secret store.
	 * @param callable|null          $clock Returns this site's clock as a unix
	 *                                      timestamp. Defaults to `time()`;
	 *                                      tests pin it so a boundary second is exact.
	 */
	public function __construct( Site_Credentials_Store $store, ?callable $clock = null ) {

		$this->store = $store;
		$this->clock = $clock ?? 'time';
	}

	/**
	 * Sign a body with the active signing secret.
	 *
	 * @param string      $body   Raw request body (exactly the bytes to hash).
	 * @param string      $ts     Unix timestamp as a string.
	 * @param string|null $secret Override secret. Falls back to the store's current.
	 *
	 * @return string Signature in `v1=<hex>` form.
	 */
	public function sign( string $body, string $ts, ?string $secret = null ): string {

		$use = null !== $secret ? $secret : (string) $this->store->signing_secret();
		return 'v1=' . hash_hmac( 'sha256', "v1:{$ts}:{$body}", self::decode_secret( $use ) );
	}

	/**
	 * The allowance this site gives the platform's deliveries:
	 * {@see self::SKEW_SECONDS} by default, which is also the ceiling. The
	 * `automator_platform_hmac_skew_seconds` filter may tighten it, never widen
	 * it: the result is clamped to [{@see self::MIN_SKEW_SECONDS},
	 * {@see self::SKEW_SECONDS}].
	 *
	 * The one reader of that filter. A tightened allowance applies to
	 * deliveries only — the platform keeps giving this site's requests
	 * {@see self::SKEW_SECONDS} — so it is the stricter of the two, and what
	 * {@see Clock_Skew_Monitor} clears its record against and
	 * {@see \Uncanny_Automator\App\Presentation\Admin\Clock_Skew_Notice} names.
	 *
	 * @since 8.0.0
	 *
	 * @return int Seconds.
	 */
	public static function allowance_seconds(): int {

		$filtered = (int) Dispatcher::filter( 'automator_platform_hmac_skew_seconds', self::SKEW_SECONDS );

		return min( self::SKEW_SECONDS, max( self::MIN_SKEW_SECONDS, $filtered ) );
	}

	/**
	 * Decode the stored signing secret to the raw bytes the platform
	 * signs with.
	 *
	 * Platform's `SiteSigningSecretService::registerSite` mints
	 * `random_bytes(32)` and returns the base64-encoded form to the
	 * plugin. The platform's `CallbackSigner` signs with the RAW
	 * bytes (after `Cipher::decrypt` rehydrates the original 32
	 * bytes), so the plugin must base64-decode the stored value
	 * before using it as the HMAC key.
	 *
	 * Soft-decode: an unparseable input falls back to the original
	 * string so legacy fixtures (or any future caller that supplies
	 * an already-raw secret via the override path) still work.
	 * Caveat: an override-path secret whose bytes happen to be valid
	 * base64 alphabet (e.g. a hex string) WILL be decoded — the
	 * override path must supply base64, not raw bytes that look like it.
	 *
	 * @param string $stored Whatever Site_Credentials_Store hands us.
	 *
	 * @return string Raw secret bytes for HMAC.
	 */
	private static function decode_secret( string $stored ): string {

		if ( '' === $stored ) {
			return '';
		}

		$decoded = base64_decode( $stored, true );
		return false === $decoded ? $stored : $decoded;
	}

	/**
	 * Verify an incoming signed request.
	 *
	 * In order:
	 *  1. A signature without the `v1=` prefix, or a timestamp that is not a
	 *     run of digits (missing, signed, fractional, padded), is
	 *     `signature_mismatch`.
	 *  2. The signature is checked against the current secret, then the
	 *     previous one. When neither matches: `stale_fingerprint` if a claimed
	 *     fingerprint is present and differs from the stored one (absent
	 *     included), `signature_mismatch` otherwise.
	 *  3. A signature that matched is `timestamp_skew` when its timestamp is
	 *     more than the allowance from this site's clock, and verified
	 *     (CURRENT / PREVIOUS) otherwise.
	 *
	 * @param string      $body                Raw body bytes (must match signed form).
	 * @param string      $ts                  Claimed unix timestamp.
	 * @param string      $signature           Signature header value (`v1=<hex>`).
	 * @param string|null $claimed_fingerprint The signing-secret fingerprint the
	 *                                         sender claims to have signed with,
	 *                                         when it sent one.
	 *
	 * @return Hmac_Verification_Result
	 */
	public function verify(
		string $body,
		string $ts,
		string $signature,
		?string $claimed_fingerprint = null
	): Hmac_Verification_Result {

		if ( 0 !== strpos( $signature, 'v1=' ) || 1 !== preg_match( '/\A[0-9]+\z/', $ts ) ) {
			return Hmac_Verification_Result::signature_mismatch();
		}

		$secret = $this->matching_secret( "v1:{$ts}:{$body}", $signature );

		if ( '' === $secret ) {
			return $this->is_stale_fingerprint( $claimed_fingerprint )
				? Hmac_Verification_Result::stale_fingerprint()
				: Hmac_Verification_Result::signature_mismatch();
		}

		$clock_difference = (int) call_user_func( $this->clock ) - (int) $ts;

		if ( abs( $clock_difference ) > self::allowance_seconds() ) {
			return Hmac_Verification_Result::timestamp_skew( $clock_difference );
		}

		return 'CURRENT' === $secret
			? Hmac_Verification_Result::current( $clock_difference )
			: Hmac_Verification_Result::previous( $clock_difference );
	}

	/**
	 * Which secret this site holds signed the payload.
	 *
	 * Platform mints `random_bytes(32)` raw and returns the base64-encoded form
	 * to the plugin in the /sites/register response. Platform's CallbackSigner
	 * signs with the RAW bytes (Cipher::decrypt returns the original 32 bytes).
	 * The plugin's stored value is base64 — it MUST be decoded before use as
	 * the HMAC key, otherwise every callback verifies against a different
	 * secret than the platform signed with and is refused forever.
	 *
	 * @param string $payload   The signed string, `v1:{ts}:{body}`.
	 * @param string $signature The claimed signature, `v1=<hex>`.
	 *
	 * @return string 'CURRENT', 'PREVIOUS', or '' when neither matched.
	 */
	private function matching_secret( string $payload, string $signature ): string {

		$candidates = array(
			'CURRENT'  => self::decode_secret( (string) $this->store->signing_secret() ),
			'PREVIOUS' => self::decode_secret( (string) ( $this->store->previous_signing_secret() ?? '' ) ),
		);

		foreach ( $candidates as $which => $raw ) {
			if ( '' !== $raw && hash_equals( 'v1=' . hash_hmac( 'sha256', $payload, $raw ), $signature ) ) {
				return $which;
			}
		}

		return '';
	}

	/**
	 * Whether a claimed fingerprint is present and is not the one this site
	 * holds. The platform sends no fingerprint today, so this is true only for
	 * a sender that does.
	 *
	 * @param string|null $claimed_fingerprint The sender's claim, or null.
	 *
	 * @return bool
	 */
	private function is_stale_fingerprint( ?string $claimed_fingerprint ): bool {

		return null !== $claimed_fingerprint
			&& '' !== $claimed_fingerprint
			&& $this->store->fingerprint() !== $claimed_fingerprint;
	}
}
