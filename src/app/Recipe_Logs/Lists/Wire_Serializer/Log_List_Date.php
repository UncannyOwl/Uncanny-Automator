<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Lists\Wire_Serializer;

/**
 * The forms of an entry's date.
 *
 * Log rows store the site's local time (`current_time( 'mysql' )`), so the
 * stored value is read in the site's time zone.
 *
 * @since 8.1.0
 */
final class Log_List_Date {

	/**
	 * The moment, as a Unix timestamp.
	 *
	 * @var int
	 */
	private $timestamp;

	/**
	 * The current moment, as a Unix timestamp.
	 *
	 * @var int
	 */
	private $now;

	/**
	 * Today in the site's time zone, `Y-m-d`.
	 *
	 * @var string
	 */
	private $today;

	/**
	 * @param string   $stored The stored local date and time, `Y-m-d H:i:s`.
	 * @param int|null $now    The current moment; the real one when null.
	 *
	 * @throws \InvalidArgumentException For a value that is not a date.
	 */
	public function __construct( string $stored, ?int $now = null ) {
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $stored, wp_timezone() );

		// PHP reads MySQL's zero date as a moment in year -1; no log row ran before 1970.
		if ( false === $date || (int) $date->format( 'Y' ) < 1970 ) {
			throw new \InvalidArgumentException( sprintf( 'Not a stored log date: "%s".', esc_html( $stored ) ) );
		}

		$this->timestamp = $date->getTimestamp();
		$this->now       = $now ?? time();
		$this->today     = wp_date( 'Y-m-d', $this->now );
	}

	/**
	 * The whole date and time, in the site's date and time formats.
	 *
	 * @return string
	 */
	public function full(): string {
		return (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $this->timestamp );
	}

	/**
	 * The date abbreviated like a mail inbox: the time for today, the month
	 * and day for this year, a short numeric date before that.
	 *
	 * @return string
	 */
	public function short(): string {
		if ( wp_date( 'Y-m-d', $this->timestamp ) === $this->today ) {
			return (string) wp_date( (string) get_option( 'time_format' ), $this->timestamp );
		}

		if ( wp_date( 'Y', $this->timestamp ) === substr( $this->today, 0, 4 ) ) {
			/* translators: PHP date format of a log entry's date earlier this year: the month and day, e.g. Nov 5. */
			return (string) wp_date( esc_html_x( 'M j', 'Logs list short date this year', 'uncanny-automator' ), $this->timestamp );
		}

		/* translators: PHP date format of a log entry's date before this year, e.g. 11/5/25. */
		return (string) wp_date( esc_html_x( 'n/j/y', 'Logs list short date before this year', 'uncanny-automator' ), $this->timestamp );
	}

	/**
	 * How long ago the moment was, for a moment of today that has passed.
	 *
	 * @return string|null Null for a moment of any other day, and for one still ahead.
	 */
	public function relative(): ?string {
		if ( wp_date( 'Y-m-d', $this->timestamp ) !== $this->today || $this->timestamp > $this->now ) {
			return null;
		}

		/* translators: %s: A length of time, e.g. 17 minutes. */
		return sprintf( esc_html_x( '%s ago', 'Recipe log relative time', 'uncanny-automator' ), human_time_diff( $this->timestamp, $this->now ) );
	}

	/**
	 * The moment in ISO 8601, in UTC.
	 *
	 * @return string
	 */
	public function gmt(): string {
		return gmdate( 'Y-m-d\TH:i:s\Z', $this->timestamp );
	}
}
