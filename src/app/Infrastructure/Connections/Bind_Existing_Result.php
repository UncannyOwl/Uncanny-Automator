<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Connections;

/**
 * The answer to `POST /app/v1/connections/bind-existing` (staging-copies
 * contract §3.3), classified by status and `error.type`:
 *
 *  | Answer                              | Outcome      | An answer? |
 *  |-------------------------------------|--------------|------------|
 *  | 200 with an id and a signature      | attached     | yes        |
 *  | 403 INSUFFICIENT_PRIVILEGES         | not_offered  | yes        |
 *  | 404 RESOURCE_NOT_FOUND              | account_gone | yes        |
 *  | 400 VALIDATION_ERROR                | invalid      | yes        |
 *  | any other typed 4xx but 401 and 429 | refused      | yes        |
 *  | everything else                     | unavailable  | no         |
 *
 * "Everything else" is a transport failure, a 5xx, a throttle, the license
 * or site-signature layer (401: it says nothing about the account), a 200
 * that is not usable, and any status without the platform's typed envelope —
 * a firewall's 403 page is not the platform saying "not on offer". Only an
 * answer settles a row; an unavailable outcome is tried again later.
 *
 * `not_offered` does not say why (contract §3.6): the owning site has not
 * shared the account, the account belongs to another customer, or it no
 * longer exists or was revoked before the call. `account_gone` is narrow — the
 * account went away between the platform's offer check and its write (§9) —
 * so it is rarely seen.
 *
 * @since 8.0.0
 */
final class Bind_Existing_Result {

	public const OUTCOME_ATTACHED     = 'attached';
	public const OUTCOME_NOT_OFFERED  = 'not_offered';
	public const OUTCOME_ACCOUNT_GONE = 'account_gone';
	public const OUTCOME_INVALID      = 'invalid';
	public const OUTCOME_REFUSED      = 'refused';
	public const OUTCOME_UNAVAILABLE  = 'unavailable';

	/**
	 * @var string
	 */
	private $outcome;

	/**
	 * @var Platform_Connection|null
	 */
	private $connection;

	/**
	 * @var int
	 */
	private $http_status;

	/**
	 * @param string                   $outcome     One of the OUTCOME_* constants.
	 * @param Platform_Connection|null $connection  The attachment, when attached.
	 * @param int                      $http_status The answer's status, 0 when none.
	 */
	private function __construct( string $outcome, ?Platform_Connection $connection, int $http_status ) {

		$this->outcome     = $outcome;
		$this->connection  = $connection;
		$this->http_status = $http_status;
	}

	/**
	 * @param Platform_Connection $connection This site's new connection to the account.
	 *
	 * @return self
	 */
	public static function attached( Platform_Connection $connection ): self {

		return new self( self::OUTCOME_ATTACHED, $connection, 200 );
	}

	/**
	 * @param string $outcome     Any OUTCOME_* but attached.
	 * @param int    $http_status The answer's status, 0 when none.
	 *
	 * @return self
	 */
	public static function not_attached( string $outcome, int $http_status ): self {

		return new self( $outcome, null, $http_status );
	}

	/**
	 * @return string One of the OUTCOME_* constants.
	 */
	public function outcome(): string {
		return $this->outcome;
	}

	/**
	 * @return Platform_Connection|null The attachment, only when attached.
	 */
	public function connection(): ?Platform_Connection {
		return $this->connection;
	}

	/**
	 * @return int
	 */
	public function http_status(): int {
		return $this->http_status;
	}

	/**
	 * Whether the platform decided — anything but unavailable.
	 *
	 * @return bool
	 */
	public function is_answer(): bool {
		return self::OUTCOME_UNAVAILABLE !== $this->outcome;
	}
}
