<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Database;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Converts between instants and the site-time strings the log tables hold.
 *
 * Log rows store the site's wall-clock time with no offset ( current_time( 'mysql' ) ). When the site's clocks go back,
 * a stretch of those times happens twice, so one string can name two instants. Both conversions settle that on the
 * later instant: a row sorts before a cutoff only when it is old enough whichever pass wrote it, and the instant read
 * from a string is the latest it can name.
 *
 * @package Uncanny_Automator\App\Infrastructure\Database
 * @since   8.0.4
 */
final class Site_Time {

	/**
	 * The format the log tables' date_time columns read back in.
	 */
	private const FORMAT = 'Y-m-d H:i:s';

	/**
	 * The site's timezone.
	 *
	 * @var DateTimeZone
	 */
	private DateTimeZone $zone;

	/**
	 * @param DateTimeZone $zone The site's timezone, wp_timezone().
	 */
	public function __construct( DateTimeZone $zone ) {
		$this->zone = $zone;
	}

	/**
	 * The site time a row must sort before to have been written more than $seconds before $now.
	 *
	 * The instant $seconds before $now, shifted by the smallest UTC offset the site used between it and $now. A row
	 * written in the first pass of a repeated stretch can therefore wait past $seconds by up to the length of the
	 * change.
	 *
	 * @param int $now     Now, as a Unix timestamp.
	 * @param int $seconds How long a row must have been idle.
	 *
	 * @return string 'Y-m-d H:i:s'.
	 */
	public function cutoff( int $now, int $seconds ): string {

		$from = $now - $seconds;

		return gmdate( self::FORMAT, $from + min( $this->offsets( $from, $now ) ) );
	}

	/**
	 * The latest instant a site time names, or null when it names none: a time the clocks skipped, or not a
	 * 'Y-m-d H:i:s' time.
	 *
	 * @param string $site_time A row's time, 'Y-m-d H:i:s'.
	 *
	 * @return int|null A Unix timestamp.
	 */
	public function latest_instant( string $site_time ): ?int {

		$as_utc = DateTimeImmutable::createFromFormat( '!' . self::FORMAT, $site_time, new DateTimeZone( 'UTC' ) );

		if ( false === $as_utc ) {
			return null;
		}

		// Every reading is the string read as UTC less an offset the site used within a day of it.
		$wall   = $as_utc->getTimestamp();
		$latest = null;

		foreach ( $this->offsets( $wall - DAY_IN_SECONDS, $wall + DAY_IN_SECONDS ) as $offset ) {
			$instant = $wall - $offset;
			if ( $site_time === $this->format( $instant ) && ( null === $latest || $instant > $latest ) ) {
				$latest = $instant;
			}
		}

		return $latest;
	}

	/**
	 * The UTC offsets the site used from $from to $to.
	 *
	 * @param int $from Unix timestamp.
	 * @param int $to   Unix timestamp.
	 *
	 * @return int[] Seconds east of UTC.
	 */
	private function offsets( int $from, int $to ): array {

		// A fixed-offset zone, such as '+05:00', lists no transitions, and PHP before 8.1 leaves out one at $to.
		$transitions = $this->zone->getTransitions( $from, $to );
		$offsets     = false === $transitions ? array() : array_column( $transitions, 'offset' );
		$offsets[]   = $this->zone->getOffset( new DateTimeImmutable( '@' . $from ) );
		$offsets[]   = $this->zone->getOffset( new DateTimeImmutable( '@' . $to ) );

		return array_map( 'intval', $offsets );
	}

	/**
	 * An instant as the site's clock read it.
	 *
	 * @param int $instant Unix timestamp.
	 *
	 * @return string 'Y-m-d H:i:s'.
	 */
	private function format( int $instant ): string {
		return ( new DateTimeImmutable( '@' . $instant ) )->setTimezone( $this->zone )->format( self::FORMAT );
	}
}
