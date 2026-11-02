<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Connections;

/**
 * Immutable result of a v3 disconnect attempt. Six observable outcomes:
 *
 *  - DELETED: platform 2xx — the platform disconnected the connection;
 *    local row removed, consumers notified.
 *  - RECONCILED: platform 404 / 410 — the platform says the connection is
 *    gone; the local row is stale drift, removed the same way. Distinct
 *    from DELETED for observability (ops cares whether a disconnect
 *    actually released a live connection or just cleaned up a stale row).
 *  - RETRY: no platform verdict on the connection, and trying again can
 *    help — a 401 (license, site signature or throttle), 408, 425, 429,
 *    5xx or a transport error. Local row preserved: the platform may still
 *    hold the connection live, and a site that cannot see a connection
 *    cannot disconnect it either.
 *  - FAILED: no platform verdict on the connection, and the same request
 *    will be refused again (400, 403, 409, 422 …). Local row preserved for
 *    the same reason.
 *  - NOT_FOUND_LOCAL: caller passed a connection_id the plugin has no
 *    row for — no HTTP call made, nothing to clean up.
 *  - REMOVED_LOCALLY: the row was switched off because this site is a copy
 *    and it was inherited from the source site; its connection is the source
 *    site's, never this site's, so the row is removed with no HTTP call and
 *    the source site is untouched.
 *
 * @since 8.0.0
 */
final class Disconnect_Result {

	public const STATUS_DELETED         = 'deleted';
	public const STATUS_RECONCILED      = 'reconciled';
	public const STATUS_RETRY           = 'retry';
	public const STATUS_FAILED          = 'failed';
	public const STATUS_NOT_FOUND_LOCAL = 'not_found_local';
	public const STATUS_REMOVED_LOCALLY = 'removed_locally';

	/**
	 * @var string
	 */
	private $status;

	/**
	 * @var string
	 */
	private $message;

	/**
	 * HTTP status code from the platform, or 0 if no HTTP call was made.
	 *
	 * @var int
	 */
	private $http_status;

	/**
	 * @param string $status      One of the STATUS_* constants.
	 * @param string $message     Human-readable detail (empty on the happy path).
	 * @param int    $http_status Platform HTTP status, or 0 when no call was made.
	 */
	private function __construct( string $status, string $message, int $http_status ) {

		$this->status      = $status;
		$this->message     = $message;
		$this->http_status = $http_status;
	}
	/**
	 * Deleted.
	 *
	 * @param int $http_status The status.
	 * @return self
	 */
	public static function deleted( int $http_status = 204 ): self {

		return new self( self::STATUS_DELETED, '', $http_status );
	}
	/**
	 * Reconciled.
	 *
	 * @param int $http_status The status.
	 * @param string $message The message.
	 * @return self
	 */
	public static function reconciled( int $http_status, string $message = '' ): self {

		return new self( self::STATUS_RECONCILED, $message, $http_status );
	}
	/**
	 * Retry.
	 *
	 * @param string $message The message.
	 * @param int $http_status The status.
	 * @return self
	 */
	public static function retry( string $message, int $http_status = 0 ): self {

		return new self( self::STATUS_RETRY, $message, $http_status );
	}

	/**
	 * The platform refused the disconnect in a way the same request will
	 * meet again. The local row is preserved.
	 *
	 * @since 8.0.0
	 *
	 * @param string $message     The platform's description.
	 * @param int    $http_status The status.
	 *
	 * @return self
	 */
	public static function failed( string $message, int $http_status ): self {

		return new self( self::STATUS_FAILED, $message, $http_status );
	}

	/**
	 * A switched-off inherited row removed with no platform call.
	 *
	 * @since 8.0.0
	 *
	 * @return self
	 */
	public static function removed_locally(): self {

		return new self( self::STATUS_REMOVED_LOCALLY, '', 0 );
	}

	/**
	 * Not found local.
	 *
	 * @param string $connection_id The connection ID.
	 *
	 * @return self
	 */
	public static function not_found_local( string $connection_id ): self {

		return new self(
			self::STATUS_NOT_FOUND_LOCAL,
			sprintf( 'No local connection row for connection_id=%s', $connection_id ),
			0
		);
	}
	/**
	 * Status.
	 *
	 * @return string
	 */
	public function status(): string {

		return $this->status;
	}
	/**
	 * Message.
	 *
	 * @return string
	 */
	public function message(): string {

		return $this->message;
	}
	/**
	 * Http status.
	 *
	 * @return int
	 */
	public function http_status(): int {

		return $this->http_status;
	}

	/**
	 * True when the local `uap_connections` row was removed (the platform
	 * disconnected the connection, or said it was already gone).
	 */
	public function local_row_removed(): bool {

		return in_array( $this->status, array( self::STATUS_DELETED, self::STATUS_RECONCILED, self::STATUS_REMOVED_LOCALLY ), true );
	}

	/**
	 * True when the disconnect did not happen: the platform may still hold
	 * the connection, and the local row was kept. The caller must surface the
	 * failure rather than treat the connection as gone.
	 *
	 * @since 8.0.0
	 */
	public function is_failure(): bool {

		return self::STATUS_RETRY === $this->status || self::STATUS_FAILED === $this->status;
	}

	/**
	 * True when the disconnect failed and trying again later can succeed.
	 */
	public function should_retry(): bool {

		return self::STATUS_RETRY === $this->status;
	}
}
