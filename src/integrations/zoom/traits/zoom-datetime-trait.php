<?php

namespace Uncanny_Automator\Integrations\Zoom;

use Exception;

/**
 * Trait Zoom_Datetime_Trait
 *
 * Parses the tokenizable start date, start time, timezone and recurrence end date
 * fields shared by the Zoom Meetings and Zoom Webinar create actions. The host class
 * must provide get_parsed_meta_value().
 *
 * @package Uncanny_Automator\Integrations\Zoom
 */
trait Zoom_Datetime_Trait {

	/**
	 * Parse timezone with fallback to site timezone.
	 *
	 * @return string
	 */
	protected function parse_timezone() {
		$timezone = trim( (string) $this->get_parsed_meta_value( 'TIMEZONE' ) );

		if ( empty( $timezone ) ) {
			return wp_timezone_string();
		}

		// WordPress-style manual offsets, e.g. "UTC+5", "UTC-3:30" or "UTC+5.5".
		if ( 1 === preg_match( '/^(?:UTC|GMT)\s*([+-])(\d{1,2})(?:([.:])(\d{1,2}))?$/i', $timezone, $matches ) ) {
			return $this->format_utc_offset( $matches );
		}

		return $timezone;
	}

	/**
	 * Convert a matched "UTC+5.5" / "UTC-3:30" offset into "+05:30" form.
	 *
	 * @param array $matches Regex matches: sign, hours, separator, fraction.
	 *
	 * @return string
	 */
	private function format_utc_offset( $matches ) {
		$minutes = 0;

		if ( ! empty( $matches[4] ) ) {
			$minutes = '.' === $matches[3] ? (int) round( (float) ( '0.' . $matches[4] ) * 60 ) : (int) $matches[4];
		}

		return sprintf( '%s%02d:%02d', $matches[1], (int) $matches[2], $minutes );
	}

	/**
	 * Parse datetime with timezone conversion.
	 *
	 * Date and time are parsed separately so token values that carry a full
	 * date-time (e.g. "2026-09-20 14:30:00") or a Unix timestamp work in either field.
	 *
	 * @param string $start_date
	 * @param string $start_time
	 * @param string $timezone
	 * @param bool $as_utc Return Zoom's GMT format (yyyy-MM-ddTHH:mm:ssZ). Zoom ignores
	 *                     offsets like "+05:00" and reads the wall-clock time in the
	 *                     account's timezone, so the API request must use UTC.
	 *
	 * @return string
	 * @throws Exception
	 */
	protected function parse_datetime( $start_date, $start_time, $timezone, $as_utc = false ) {
		try {
			$zone      = new \DateTimeZone( $timezone );
			$date      = $this->to_zoned_datetime( $start_date, $zone )->format( 'Y-m-d' );
			$time      = $this->to_zoned_datetime( $start_time, $zone )->format( 'H:i:s' );
			$date_time = new \DateTime( $date . ' ' . $time, $zone );
		} catch ( Exception $e ) {
			throw new Exception(
				sprintf(
					// translators: %1$s Start date value, %2$s Start time value, %3$s Timezone value
					esc_html_x( 'Invalid start date "%1$s", start time "%2$s", or timezone "%3$s". Use a date such as YYYY-MM-DD, a time such as HH:MM or h:mm AM/PM, and a timezone such as America/New_York.', 'Zoom', 'uncanny-automator' ),
					esc_html( $start_date ),
					esc_html( $start_time ),
					esc_html( $timezone )
				)
			);
		}

		if ( ! $as_utc ) {
			return $date_time->format( 'Y-m-d\TH:i:s' );
		}

		return $date_time->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' );
	}

	/**
	 * Parse the recurrence end date into Zoom's GMT format (yyyy-MM-ddTHH:mm:ssZ).
	 *
	 * Accepts the same token values as the start date so the two fields behave alike.
	 *
	 * @param string $end_date
	 * @param string $timezone
	 *
	 * @return string
	 * @throws Exception
	 */
	protected function parse_end_datetime( $end_date, $timezone ) {
		try {
			$zone = new \DateTimeZone( $timezone );

			return $this->to_zoned_datetime( $end_date, $zone )
				->setTimezone( new \DateTimeZone( 'UTC' ) )
				->format( 'Y-m-d\TH:i:s\Z' );
		} catch ( Exception $e ) {
			throw new Exception(
				sprintf(
					// translators: %1$s End date value, %2$s Timezone value
					esc_html_x( 'Invalid end date "%1$s" or timezone "%2$s". Use a date such as YYYY-MM-DD and a timezone such as America/New_York.', 'Zoom', 'uncanny-automator' ),
					esc_html( $end_date ),
					esc_html( $timezone )
				)
			);
		}
	}

	/**
	 * Build a DateTime in the given zone from a date, time, date-time or Unix timestamp string.
	 *
	 * @param string        $value
	 * @param \DateTimeZone $zone
	 *
	 * @return \DateTime
	 * @throws Exception When the value can't be parsed.
	 */
	private function to_zoned_datetime( $value, \DateTimeZone $zone ) {
		$value = trim( (string) $value );

		// Nine or more digits is a Unix timestamp, not a compact date like 20260920.
		if ( 1 === preg_match( '/^\d{9,}$/', $value ) ) {
			$value = '@' . $value;
		}

		$date_time = new \DateTime( $value, $zone );

		// Values carrying their own offset (timestamps, ISO 8601) are converted into the event timezone.
		return $date_time->setTimezone( $zone );
	}
}
