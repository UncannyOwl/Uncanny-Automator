<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Connections;

/**
 * What one connection sync did ({@see Connection_Sync}).
 *
 *  - synced     the list arrived and every row agrees with it.
 *  - incomplete the list arrived, but a row could not be written or a
 *               trigger subscription is still owed; the sync stays owed.
 *  - failed     the list did not arrive; nothing changed and the sync is
 *               owed.
 *  - deferred   nothing was asked: the site's registration is not settled,
 *               or the same connection was repaired moments ago.
 *
 * @since 8.0.0
 */
final class Connection_Sync_Result {

	public const STATUS_SYNCED     = 'synced';
	public const STATUS_INCOMPLETE = 'incomplete';
	public const STATUS_FAILED     = 'failed';
	public const STATUS_DEFERRED   = 'deferred';

	/**
	 * @var string
	 */
	private $status;

	/**
	 * @var int
	 */
	private $restored;

	/**
	 * @var int
	 */
	private $updated;

	/**
	 * @var int
	 */
	private $created;

	/**
	 * @var int
	 */
	private $http_status;

	/**
	 * @param string $status      One of the STATUS_* constants.
	 * @param int    $restored    Rows switched back on.
	 * @param int    $updated     Rows given another connection, signature or status.
	 * @param int    $created     Rows added.
	 * @param int    $http_status The list's status when it failed, 0 otherwise.
	 */
	private function __construct( string $status, int $restored, int $updated, int $created, int $http_status ) {

		$this->status      = $status;
		$this->restored    = $restored;
		$this->updated     = $updated;
		$this->created     = $created;
		$this->http_status = $http_status;
	}

	/**
	 * @param string $status   STATUS_SYNCED or STATUS_INCOMPLETE.
	 * @param int    $restored Rows switched back on.
	 * @param int    $updated  Rows updated.
	 * @param int    $created  Rows added.
	 *
	 * @return self
	 */
	public static function applied( string $status, int $restored, int $updated, int $created ): self {

		return new self( $status, $restored, $updated, $created, 0 );
	}

	/**
	 * @param int $http_status The list's status, 0 when there was no answer.
	 *
	 * @return self
	 */
	public static function failed( int $http_status ): self {

		return new self( self::STATUS_FAILED, 0, 0, 0, $http_status );
	}

	/**
	 * @return self
	 */
	public static function deferred(): self {

		return new self( self::STATUS_DEFERRED, 0, 0, 0, 0 );
	}

	/**
	 * The same tallies, reported incomplete: the rows were applied but
	 * something that follows (a trigger subscription) is still owed.
	 *
	 * @return self
	 */
	public function as_incomplete(): self {

		return new self( self::STATUS_INCOMPLETE, $this->restored, $this->updated, $this->created, 0 );
	}

	/**
	 * @return string One of the STATUS_* constants.
	 */
	public function status(): string {
		return $this->status;
	}

	/**
	 * @return int Rows that were not active and are active again (switched off, needing re-authorising, revoked or expired).
	 */
	public function restored(): int {
		return $this->restored;
	}

	/**
	 * @return int Rows given another connection, signature or status.
	 */
	public function updated(): int {
		return $this->updated;
	}

	/**
	 * @return int Rows added for connections the site had no row for.
	 */
	public function created(): int {
		return $this->created;
	}

	/**
	 * @return int
	 */
	public function http_status(): int {
		return $this->http_status;
	}

	/**
	 * @return bool
	 */
	public function is_synced(): bool {
		return self::STATUS_SYNCED === $this->status;
	}
}
