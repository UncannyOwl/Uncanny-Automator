<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\License;

/**
 * Class License_Snapshot
 *
 * The license document from the last successful get_credits fetch, and
 * when it was fetched. Served when a later fetch fails for a reason that is
 * not a verdict on the license (see License_Manager::is_verdict()).
 *
 * @since 8.0.1
 * @package Uncanny_Automator\App\Infrastructure\License
 */
final class License_Snapshot {

	/**
	 * How long a snapshot may stand in for a failed refresh (7 days).
	 *
	 * Bounds how long a license the platform can no longer confirm keeps
	 * working: an outage longer than this degrades to "no license", as it
	 * did before snapshots existed.
	 *
	 * @var int
	 */
	const MAX_AGE = 7 * DAY_IN_SECONDS;

	/**
	 * The license document.
	 *
	 * @var array
	 */
	private $license;

	/**
	 * When it was fetched (Unix time).
	 *
	 * @var int
	 */
	private $fetched_at;

	/**
	 * Constructor.
	 *
	 * @param array $license    The license document.
	 * @param int   $fetched_at When it was fetched (Unix time).
	 */
	public function __construct( array $license, int $fetched_at ) {
		$this->license    = $license;
		$this->fetched_at = $fetched_at;
	}

	/**
	 * Get the license document.
	 *
	 * @return array
	 */
	public function get_license(): array {
		return $this->license;
	}

	/**
	 * Get when it was fetched (Unix time).
	 *
	 * @return int
	 */
	public function get_fetched_at(): int {
		return $this->fetched_at;
	}

	/**
	 * Get when it stops being usable (Unix time).
	 *
	 * @return int
	 */
	public function get_expires_at(): int {
		return $this->fetched_at + self::MAX_AGE;
	}

	/**
	 * Whether it may be served at the given time.
	 *
	 * A snapshot dated in the future is not trusted: no clock-skew tolerance,
	 * so MAX_AGE stays a strict maximum.
	 *
	 * @param int $now The current Unix time.
	 *
	 * @return bool
	 */
	public function is_usable_at( int $now ): bool {
		return $this->fetched_at <= $now && $now < $this->get_expires_at();
	}
}
