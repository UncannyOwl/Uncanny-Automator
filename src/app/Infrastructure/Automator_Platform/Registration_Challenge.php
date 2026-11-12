<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Challenge_Progress;

/**
 * C1 requester-bound registration proof (site identity spec, ClickUp
 * 868jw791t).
 *
 * A first registration cannot prove possession of a signing secret (there is
 * none yet), and a site whose secret was lost or no longer matches the
 * platform's cannot either. Instead the plugin generates a random attempt
 * secret, sends it once over TLS in every register request (wire contract W1),
 * and — when the platform answers 202 for a first registration or a recovery —
 * proves it controls the callback host by signing a challenge the platform
 * issues to this site's dedicated pre-registration endpoint. Two
 * domain-separated messages are signed so a challenge-endpoint signature can
 * never be replayed as a completion proof:
 *
 *   - challenge:  `uap-register-challenge-v1:{attempt_id}:{challenge}`
 *   - completion: `uap-register-complete-v1:{attempt_id}:{completion_nonce}`
 *
 * Both are HMAC-SHA256 over the base64-DECODED attempt secret producing lowercase
 * hex — the SAME encoding the platform's RegistrationChallengeService uses (and
 * the same base64-secret / raw-key convention as Hmac_Validator), so the two
 * sides compute byte-for-byte identical output.
 *
 * The attempt (id + secret) is held in the durable options table with its OWN
 * expiry: the challenge endpoint reads it to sign, and the completion poll reads
 * it to redeem the winning signing secret. NOT a transient — on a host with a
 * persistent object cache a transient lives only in that cache, and a flush
 * (routine during a plugin update) would destroy the sole credential that can
 * re-poll /complete, orphaning a site the platform has already committed.
 *
 * The record also paces the completion poll ({@see poll_due()} /
 * {@see record_poll()}): the poll runs on `init`, so without a ladder every
 * request would pay a blocking platform round trip while an attempt is pending.
 *
 * And it says whether the attempt was begun by a move ({@see is_move()}), and
 * whether that move carries no secret ({@see is_move_without_secret()}, move
 * contract §10): the platform keeps the first intention it stored for an
 * attempt id, so what the attempt is for has to live as long as the attempt,
 * and no longer.
 *
 * @since 8.0.0
 */
final class Registration_Challenge {

	const CHALLENGE_PREFIX  = 'uap-register-challenge-v1';
	const COMPLETION_PREFIX = 'uap-register-complete-v1';

	/**
	 * Option holding the in-flight attempt. Short-lived — the whole handshake
	 * completes in seconds; the TTL (matching the platform's attempt lifetime) is
	 * only a backstop so an abandoned attempt cannot linger and shadow a later
	 * fresh registration.
	 */
	const ATTEMPT_KEY = 'automator_platform_reg_attempt';
	const ATTEMPT_TTL = 600;

	/**
	 * Seconds to wait before the next completion poll, indexed by polls already
	 * made; the last rung repeats. The first poll is immediate (polls = 0 has no
	 * wait), so a fast challenge is still redeemed on the very next request.
	 */
	const POLL_LADDER_SECONDS = array( 5, 10, 20, 40, 60 );

	/**
	 * Sign the challenge with the attempt secret.
	 *
	 * @param string $attempt_secret_b64 Base64 attempt secret.
	 * @param string $attempt_id         Attempt id.
	 * @param string $challenge          Platform-issued challenge value.
	 * @return string Lowercase hex HMAC.
	 */
	public function sign_challenge( string $attempt_secret_b64, string $attempt_id, string $challenge ): string {

		return $this->hmac( $attempt_secret_b64, self::CHALLENGE_PREFIX . ':' . $attempt_id . ':' . $challenge );
	}

	/**
	 * Sign the completion with the attempt secret.
	 *
	 * @param string $attempt_secret_b64 Base64 attempt secret.
	 * @param string $attempt_id         Attempt id.
	 * @param string $completion_nonce   Per-completion nonce.
	 * @return string Lowercase hex HMAC.
	 */
	public function sign_completion( string $attempt_secret_b64, string $attempt_id, string $completion_nonce ): string {

		return $this->hmac( $attempt_secret_b64, self::COMPLETION_PREFIX . ':' . $attempt_id . ':' . $completion_nonce );
	}

	/**
	 * Generate + persist a fresh attempt (id + 32-byte base64 secret).
	 *
	 * @param bool $is_move        Whether the register carrying it asks the
	 *                             platform to move this site's row
	 *                             (`migrate_from`).
	 * @param bool $without_secret Whether that move carries no proof of the
	 *                             old secret, so the platform confirms it at
	 *                             the old address instead. Only a move can.
	 *
	 * @return array{attempt_id: string, attempt_secret: string}
	 */
	public function begin_attempt( bool $is_move = false, bool $without_secret = false ): array {

		$attempt = array(
			'attempt_id'     => 'att_' . bin2hex( random_bytes( 16 ) ),
			'attempt_secret' => base64_encode( random_bytes( 32 ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encoding a random secret for the wire, not obfuscation.
		);

		automator_update_option(
			self::ATTEMPT_KEY,
			$attempt + array(
				'expires_at'          => time() + self::ATTEMPT_TTL,
				'polls'               => 0,
				'next_poll_at'        => 0,
				'move'                => $is_move,
				'move_without_secret' => $is_move && $without_secret,
			),
			false
		);

		return $attempt;
	}

	/**
	 * Whether the attempt on file, expired or not, was begun by a move.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function is_move(): bool {

		$attempt = $this->record();

		return null !== $attempt && true === ( $attempt['move'] ?? false );
	}

	/**
	 * Whether the attempt on file, expired or not, was begun by a move that
	 * carries no proof of the old secret (move contract §10).
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function is_move_without_secret(): bool {

		$attempt = $this->record();

		return null !== $attempt && true === ( $attempt['move_without_secret'] ?? false );
	}

	/**
	 * When the attempt on file began, as a unix timestamp; 0 when none is on
	 * file. Read back from its own expiry, which is set {@see ATTEMPT_TTL}
	 * after it begins.
	 *
	 * @since 8.0.0
	 *
	 * @return int
	 */
	public function begun_at(): int {

		$attempt = $this->record();

		return null === $attempt ? 0 : (int) ( $attempt['expires_at'] ?? 0 ) - self::ATTEMPT_TTL;
	}

	/**
	 * The in-flight attempt, or null when none is pending.
	 *
	 * @return array{attempt_id: string, attempt_secret: string}|null
	 */
	public function pending_attempt(): ?array {

		$attempt = $this->record();
		if ( null === $attempt || $this->attempt_expired() ) {
			return null;
		}

		return array(
			'attempt_id'     => (string) $attempt['attempt_id'],
			'attempt_secret' => (string) $attempt['attempt_secret'],
		);
	}

	/**
	 * Whether an attempt is on file but past its own expiry — it aged out
	 * unproven. Distinct from "no attempt": the caller backs off rather than
	 * re-registering on the very next request.
	 *
	 * @return bool
	 */
	public function attempt_expired(): bool {

		$attempt = $this->record();

		return null !== $attempt && time() >= (int) ( $attempt['expires_at'] ?? 0 );
	}

	/**
	 * Whether the completion poll may run on this request.
	 *
	 * @return bool
	 */
	public function poll_due(): bool {

		$attempt = $this->record();

		return null !== $attempt && time() >= (int) ( $attempt['next_poll_at'] ?? 0 );
	}

	/**
	 * When the next completion poll is allowed, as a unix timestamp. 0 while
	 * the first poll is still immediate, and when no attempt is on file.
	 *
	 * Read by {@see Registration\Completion_Schedule::sync()} to
	 * put the self-driving WP-Cron event on the same rung as the ladder below.
	 *
	 * @return int
	 */
	public function next_poll_at(): int {

		$attempt = $this->record();

		return null === $attempt ? 0 : (int) ( $attempt['next_poll_at'] ?? 0 );
	}

	/**
	 * Record a completion poll that left the attempt pending, pushing the next
	 * one up the ladder — or further, when the platform asked for longer, but
	 * never past the attempt's last answerable moment ({@see answerable()}).
	 *
	 * @param int                     $min_wait Platform-directed minimum wait in seconds (Retry-After).
	 * @param bool                    $answered Whether the platform answered that it did NOT
	 *                                          complete the attempt ({@see mark_completion_sent()}).
	 * @param Challenge_Progress|null $progress How the host challenge is going, when the answer
	 *                                          said; kept for {@see progress()}.
	 *
	 * @return void
	 */
	public function record_poll( int $min_wait = 0, bool $answered = false, ?Challenge_Progress $progress = null ): void {

		$attempt = $this->record();
		if ( null === $attempt ) {
			return;
		}

		$polls = (int) ( $attempt['polls'] ?? 0 );
		$rung  = min( $polls, count( self::POLL_LADDER_SECONDS ) - 1 );

		$attempt['polls']        = $polls + 1;
		$attempt['next_poll_at'] = $this->answerable( $attempt, time() + max( self::POLL_LADDER_SECONDS[ $rung ], $min_wait ) );

		if ( $answered ) {
			$attempt['completion_unanswered'] = false;
		}

		if ( null !== $progress ) {
			$attempt['progress'] = $progress->to_array();
		}

		automator_update_option( self::ATTEMPT_KEY, $attempt, false );
	}

	/**
	 * How the host challenge of the pending attempt was going at the last
	 * poll that said; null when no pending attempt, or no answer, said.
	 *
	 * @since 8.0.0
	 *
	 * @return Challenge_Progress|null
	 */
	public function progress(): ?Challenge_Progress {

		$attempt = $this->record();
		if ( null === $attempt || $this->attempt_expired() ) {
			return null;
		}

		return Challenge_Progress::from_block( $attempt['progress'] ?? null );
	}

	/**
	 * How the host challenge of the attempt on file was going at the last poll
	 * that said, whether or not the attempt has expired since; null when no
	 * attempt is on file, or no answer said. What an attempt that ends
	 * unproven leaves as evidence about the address it gave the platform
	 * ({@see Registration\Registration_Door::after_unproven()}).
	 *
	 * @since 8.0.4
	 *
	 * @return Challenge_Progress|null
	 */
	public function last_progress(): ?Challenge_Progress {

		$attempt = $this->record();

		return null === $attempt ? null : Challenge_Progress::from_block( $attempt['progress'] ?? null );
	}

	/**
	 * Record that a completion request is going out. Until an answer saying
	 * the platform did not complete the attempt is read ({@see record_poll()}),
	 * it may have: the request may still be in flight in another process, or
	 * its answer may have been lost. Written BEFORE the request, so a process
	 * killed mid-request leaves it standing.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	public function mark_completion_sent(): void {

		$attempt = $this->record();
		if ( null === $attempt ) {
			return;
		}

		$attempt['completion_unanswered'] = true;

		automator_update_option( self::ATTEMPT_KEY, $attempt, false );
	}

	/**
	 * Whether a completion request was sent for the attempt on file and no
	 * answer has said the platform did not complete it.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function is_completion_unanswered(): bool {

		$attempt = $this->record();

		return null !== $attempt && true === ( $attempt['completion_unanswered'] ?? false );
	}

	/**
	 * Hold the completion poll until the given time without counting a poll.
	 * The register that carries the attempt holds it for its own round trip —
	 * until the platform has answered, it cannot redeem the attempt, and a
	 * concurrent tick's poll would read a 404 as "unknown attempt" and abandon
	 * it. The 202 that answers it holds them for the platform's Retry-After,
	 * the wait before its first try while its challenge workers are backed
	 * up. 0 makes the next poll due at once.
	 *
	 * @since 8.0.0
	 *
	 * @param int $timestamp Unix time the next poll is allowed at.
	 *
	 * @return void
	 */
	public function hold_polls_until( int $timestamp ): void {

		$attempt = $this->record();
		if ( null === $attempt ) {
			return;
		}

		$attempt['next_poll_at'] = $this->answerable( $attempt, $timestamp );

		automator_update_option( self::ATTEMPT_KEY, $attempt, false );
	}

	/**
	 * Drop the pending attempt (on completion, or to abandon a stale handshake).
	 *
	 * @return void
	 */
	public function clear_attempt(): void {

		automator_delete_option( self::ATTEMPT_KEY );
	}

	/**
	 * The next poll, brought back to the attempt's last answerable moment when
	 * a wait would carry it past: one request timeout before the attempt's own
	 * expiry, so the poll reaches the platform while the attempt still lives
	 * there (it began there after it began here). The platform's Retry-After
	 * runs up to the attempt's whole life and assumes every try times out, so
	 * a challenge proven sooner is still redeemed. Once that moment has passed
	 * the wait stands: brought back, the poll would be due on every request
	 * until the attempt ages out.
	 *
	 * @since 8.0.0
	 *
	 * @param array<string, mixed> $attempt The stored attempt.
	 * @param int                  $next    Unix time the wait ends.
	 *
	 * @return int
	 */
	private function answerable( array $attempt, int $next ): int {

		$last = (int) ( $attempt['expires_at'] ?? 0 ) - Platform_Http::TIMEOUT_SECONDS;

		return ( $next > $last && time() < $last ) ? $last : $next;
	}

	/**
	 * The stored attempt record, or null when none is on file (expired or not).
	 *
	 * @return array<string, mixed>|null
	 */
	private function record(): ?array {

		$attempt = automator_get_option( self::ATTEMPT_KEY, null );
		if ( ! is_array( $attempt ) || empty( $attempt['attempt_id'] ) || empty( $attempt['attempt_secret'] ) ) {
			return null;
		}

		return $attempt;
	}

	/**
	 * HMAC-SHA256 over the RAW attempt-secret bytes (the secret travels base64;
	 * the key is its decoded bytes). A non-base64 value decodes to '' under
	 * strict mode and can never match a real proof.
	 *
	 * @param string $secret_b64 Base64 secret.
	 * @param string $message    Canonical message.
	 * @return string Lowercase hex.
	 */
	private function hmac( string $secret_b64, string $message ): string {

		$raw = base64_decode( $secret_b64, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding the base64 wire secret to raw HMAC key bytes.

		return hash_hmac( 'sha256', $message, false === $raw ? '' : $raw );
	}
}
