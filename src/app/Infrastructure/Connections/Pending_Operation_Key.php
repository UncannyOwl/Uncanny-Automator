<?php
declare( strict_types=1 );

// phpcs:disable WordPress.DB.PreparedSQL -- The one query runs through $wpdb->prepare(); the only interpolated token is the table name.
namespace Uncanny_Automator\App\Infrastructure\Connections;

/**
 * The Idempotency-Key of a platform write that is under way.
 *
 * The platform's IdempotencyKey middleware records the answer to a key for
 * 24 hours, and replays it to every later request with that key and the
 * same body (the same key with another body is refused, 422
 * VALIDATION_ERROR). So a key must name one logical operation, never a
 * subject: a key derived from the connection or the token answers the next
 * registration or adoption of the same subject with the first one's answer,
 * however much changed in between (an unregister, a disconnect).
 *
 * A key is minted when the operation starts, `{operation}_` + 32 random hex
 * characters, and reused for its retries while the outcome is unknown, so a
 * retry after a timeout gets the answer of the attempt that ran instead of
 * running it (and charging for it) twice. Once the platform answered for
 * good the key is settled ({@see conclude()}): the next operation on the
 * subject gets a new one.
 *
 * A pending key belongs to one body. The caller fingerprints the request
 * fields that can change while the operation is pending; a different
 * fingerprint is a different operation and gets a new key.
 *
 * One option per pending operation, never autoloaded, holding the key, its
 * subject, its fingerprint and when it was minted. A key older than the
 * platform's 24 hours is never reused, and minting prunes every such key.
 *
 * An operation is ended one subject at a time ({@see settle()}), or every one
 * of a kind at once ({@see settle_every()}) when what ends it covers more than
 * one subject — Dropbox's "Enable triggers" switch is the integration's, so
 * turning it off ends every Dropbox registration under way, whichever
 * connection it was for and whether or not that connection can be resolved
 * right now.
 *
 * @since 8.0.0
 */
final class Pending_Operation_Key {

	/**
	 * Prefix of every pending key's option.
	 */
	private const PREFIX = 'automator_platform_operation_key_';

	/**
	 * How long the platform remembers a key.
	 */
	public const TTL_SECONDS = DAY_IN_SECONDS;

	/**
	 * Statuses under 500 that do not say how the operation ended: a timeout
	 * (408), the key still in progress or the request in conflict with one
	 * (409), locked (423), the vendor not answering (424), too early (425),
	 * throttled (429). The platform records none of them as the key's answer.
	 */
	private const UNKNOWN_OUTCOME_STATUSES = array( 408, 409, 423, 424, 425, 429 );

	/**
	 * The key for an operation on a subject: the pending one when its body
	 * is the same, otherwise a new one.
	 *
	 * @param string $operation        What is done, e.g. `adopt`, `register_webhooks`. Also the key's prefix.
	 * @param string $subject          What it is done to, e.g. `dropbox:{connection_id}`.
	 * @param string $body_fingerprint A hash of the request fields that can change while the operation is pending.
	 *
	 * @return string
	 */
	public function key_for( string $operation, string $subject, string $body_fingerprint ): string {

		$option  = $this->option( $operation, $subject );
		$pending = automator_get_option( $option, array(), true );

		if ( is_array( $pending )
			&& isset( $pending['key'], $pending['fingerprint'], $pending['minted_at'] )
			&& $body_fingerprint === $pending['fingerprint']
			&& time() - (int) $pending['minted_at'] < self::TTL_SECONDS
		) {
			return (string) $pending['key'];
		}

		$this->prune();

		$key = sanitize_key( $operation ) . '_' . bin2hex( random_bytes( 16 ) );

		automator_update_option(
			$option,
			array(
				'key'         => $key,
				'subject'     => $subject,
				'fingerprint' => $body_fingerprint,
				'minted_at'   => time(),
			),
			false
		);

		return $key;
	}

	/**
	 * The operation is over: forget its key.
	 *
	 * @param string $operation What was done.
	 * @param string $subject   What it was done to.
	 *
	 * @return void
	 */
	public function settle( string $operation, string $subject ): void {

		automator_delete_option( $this->option( $operation, $subject ) );
	}

	/**
	 * Every operation of a kind is over: forget the key of each one whose
	 * subject starts with `$subject_prefix`.
	 *
	 * The option name holds only a hash of the subject, so the subject is
	 * read from the stored value. A key stored before subjects were recorded
	 * carries none and is forgotten too: forgetting a key only makes the
	 * operation's next request a new one, never answered from another's
	 * record, so ending one too many is safe where ending one too few is not.
	 *
	 * @param string $operation      What was done, e.g. `register_webhooks`.
	 * @param string $subject_prefix Whose operations end, e.g. `dropbox:`.
	 *
	 * @return void
	 */
	public function settle_every( string $operation, string $subject_prefix ): void {

		$name_prefix = self::PREFIX . sanitize_key( $operation ) . '_';

		foreach ( $this->stored( $name_prefix ) as $name => $pending ) {
			// The name is exactly {prefix}{operation}_{md5}: an operation whose
			// name extends this one (`register_webhooks_v2`) is another kind.
			if ( 1 !== preg_match( '/\A[0-9a-f]{32}\z/', substr( $name, strlen( $name_prefix ) ) ) ) {
				continue;
			}

			$subject = is_array( $pending ) ? ( $pending['subject'] ?? null ) : null;
			if ( null === $subject || 0 === strpos( (string) $subject, $subject_prefix ) ) {
				automator_delete_option( $name );
			}
		}
	}

	/**
	 * Settle the operation when the platform's answer is definitive; keep its
	 * key for the retry otherwise.
	 *
	 * @param string $operation What was done.
	 * @param string $subject   What it was done to.
	 * @param int    $status    The answer's HTTP status, 0 when none arrived.
	 *
	 * @return void
	 */
	public function conclude( string $operation, string $subject, int $status ): void {

		if ( self::is_definitive( $status ) ) {
			$this->settle( $operation, $subject );
		}
	}

	/**
	 * Whether an answer says how the operation ended: a 2xx, or a 4xx the
	 * platform gives for the request itself. Not a missing answer, a 5xx, or
	 * a 4xx that only says "not now".
	 *
	 * @param int $status The answer's HTTP status, 0 when none arrived.
	 *
	 * @return bool
	 */
	public static function is_definitive( int $status ): bool {

		if ( 200 <= $status && $status < 300 ) {
			return true;
		}

		return 400 <= $status && $status < 500 && ! in_array( $status, self::UNKNOWN_OUTCOME_STATUSES, true );
	}

	/**
	 * Remove every key the platform has forgotten.
	 *
	 * @return void
	 */
	private function prune(): void {

		foreach ( $this->stored( self::PREFIX ) as $name => $pending ) {
			if ( ! is_array( $pending ) || time() - (int) ( $pending['minted_at'] ?? 0 ) >= self::TTL_SECONDS ) {
				automator_delete_option( $name );
			}
		}
	}

	/**
	 * Every pending key whose option name starts with `$name_prefix`.
	 *
	 * @param string $name_prefix An option-name prefix, at least {@see self::PREFIX}.
	 *
	 * @return array<string, mixed> Option name => stored value.
	 */
	private function stored( string $name_prefix ): array {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
		$names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->prefix}uap_options WHERE option_name LIKE %s",
				$wpdb->esc_like( $name_prefix ) . '%'
			)
		);

		$stored = array();
		foreach ( $names as $name ) {
			$stored[ (string) $name ] = automator_get_option( (string) $name, array(), true );
		}

		return $stored;
	}

	/**
	 * @param string $operation What is done.
	 * @param string $subject   What it is done to.
	 *
	 * @return string The pending operation's option.
	 */
	private function option( string $operation, string $subject ): string {

		return self::PREFIX . sanitize_key( $operation ) . '_' . md5( $subject );
	}
}
