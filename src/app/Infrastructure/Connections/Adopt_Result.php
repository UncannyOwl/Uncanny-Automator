<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Connections;

/**
 * The answer to `POST /app/v1/connections/adopt` — the site hands the
 * platform a vendor credential it already holds, and the platform proves it
 * and binds this site to the account it names (Facebook Lead Ads signed
 * delivery design, §6.1 and §8). Classified by status and `error.type`:
 *
 *  | Answer                              | Outcome       | A refusal of the credential? |
 *  |-------------------------------------|---------------|------------------------------|
 *  | 200 with an id and a signature      | adopted       | no                           |
 *  | 404 RESOURCE_NOT_FOUND              | not_supported | no                           |
 *  | 422 VALIDATION_ERROR                | invalid       | yes                          |
 *  | 403 INSUFFICIENT_PRIVILEGES         | not_permitted | yes                          |
 *  | everything else                     | unavailable   | no                           |
 *
 * `not_supported` is a platform without an adopter for the integration (one
 * older than the adopter). `invalid` is the credential itself: the token is
 * not for the account it names, or it no longer works. `not_permitted` is a
 * credential that works but lacks a permission the integration needs. Those
 * two are about the credential the site sent, so sending it again cannot
 * succeed: only a new credential (the user connecting again) can.
 *
 * "Everything else" is a transport failure, a 5xx, the vendor being down
 * (424 FAILED_DEPENDENCY), a throttle, the license or site-signature layer
 * (401: it says nothing about the credential), a 200 that is not usable, and
 * any status without the platform's typed envelope — a firewall's 403 page is
 * not the platform saying "no permission".
 *
 * @since 8.0.0
 */
final class Adopt_Result {

	public const OUTCOME_ADOPTED       = 'adopted';
	public const OUTCOME_NOT_SUPPORTED = 'not_supported';
	public const OUTCOME_INVALID       = 'invalid';
	public const OUTCOME_NOT_PERMITTED = 'not_permitted';
	public const OUTCOME_UNAVAILABLE   = 'unavailable';

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
	 * @param Platform_Connection|null $connection  The adopted connection, when adopted.
	 * @param int                      $http_status The answer's status, 0 when none.
	 */
	private function __construct( string $outcome, ?Platform_Connection $connection, int $http_status ) {

		$this->outcome     = $outcome;
		$this->connection  = $connection;
		$this->http_status = $http_status;
	}

	/**
	 * @param Platform_Connection $connection This site's connection to the adopted account.
	 *
	 * @return self
	 */
	public static function adopted( Platform_Connection $connection ): self {

		return new self( self::OUTCOME_ADOPTED, $connection, 200 );
	}

	/**
	 * @param string $outcome     Any OUTCOME_* but adopted.
	 * @param int    $http_status The answer's status, 0 when none.
	 *
	 * @return self
	 */
	public static function not_adopted( string $outcome, int $http_status ): self {

		return new self( $outcome, null, $http_status );
	}

	/**
	 * @return string One of the OUTCOME_* constants.
	 */
	public function outcome(): string {
		return $this->outcome;
	}

	/**
	 * @return Platform_Connection|null The adopted connection, only when adopted.
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
	 * Whether the platform refused the credential itself — invalid or not
	 * permitted. Sending the same credential again cannot change the answer.
	 *
	 * @return bool
	 */
	public function is_refusal(): bool {
		return self::OUTCOME_INVALID === $this->outcome || self::OUTCOME_NOT_PERMITTED === $this->outcome;
	}
}
