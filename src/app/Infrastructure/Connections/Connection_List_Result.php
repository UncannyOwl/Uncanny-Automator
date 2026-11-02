<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Connections;

/**
 * The answer to `GET /app/v1/connections`: this site's connections, or a
 * failed fetch. A failed fetch knows nothing about any connection, so a
 * caller changes nothing on one.
 *
 * @since 8.0.0
 */
final class Connection_List_Result {

	/**
	 * @var bool
	 */
	private $ok;

	/**
	 * @var array<int, Platform_Connection>
	 */
	private $connections;

	/**
	 * @var int
	 */
	private $http_status;

	/**
	 * @var string
	 */
	private $error_type;

	/**
	 * @param bool                            $ok          Whether the list arrived.
	 * @param array<int, Platform_Connection> $connections The connections.
	 * @param int                             $http_status The answer's status, 0 when none.
	 * @param string                          $error_type  The answer's error.type, '' when none.
	 */
	private function __construct( bool $ok, array $connections, int $http_status, string $error_type ) {

		$this->ok          = $ok;
		$this->connections = $connections;
		$this->http_status = $http_status;
		$this->error_type  = $error_type;
	}

	/**
	 * @param array<int, Platform_Connection> $connections This site's connections.
	 *
	 * @return self
	 */
	public static function ok( array $connections ): self {

		return new self( true, array_values( $connections ), 200, '' );
	}

	/**
	 * @param int    $http_status The answer's status, 0 when there was none.
	 * @param string $error_type  The answer's error.type, '' when none.
	 *
	 * @return self
	 */
	public static function failed( int $http_status, string $error_type ): self {

		return new self( false, array(), $http_status, $error_type );
	}

	/**
	 * @return bool
	 */
	public function is_ok(): bool {
		return $this->ok;
	}

	/**
	 * @return array<int, Platform_Connection>
	 */
	public function connections(): array {
		return $this->connections;
	}

	/**
	 * The connections with those that concern a row put ahead of the rest,
	 * so a repair of that row is applied first.
	 *
	 * @param Connection|null $first The row being repaired, or null.
	 *
	 * @return array<int, Platform_Connection>
	 */
	public function ordered_for( ?Connection $first ): array {

		if ( null === $first ) {
			return $this->connections;
		}

		$ahead = array();
		$rest  = array();

		foreach ( $this->connections as $connection ) {
			if ( $connection->concerns( $first ) ) {
				$ahead[] = $connection;
			} else {
				$rest[] = $connection;
			}
		}

		return array_merge( $ahead, $rest );
	}

	/**
	 * @return int
	 */
	public function http_status(): int {
		return $this->http_status;
	}

	/**
	 * @return string
	 */
	public function error_type(): string {
		return $this->error_type;
	}
}
