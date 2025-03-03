<?php
namespace Uncanny_Automator\Integrations\ClickUp\Utilities;

use DateTime;
use DateTimeZone;

/**
 * Class Time_Utility
 *
 * Handles date and time parsing, formatting, and validation.
 * Handles compatiblity with Automator specific date and time formats as well.
 *
 * @package Uncanny_Automator\Integrations\ClickUp\Utilities
 */
class Time_Utility {

	/**
	 * The date format to use for parsing.
	 *
	 * @var string
	 */
	private $date_format;

	/**
	 * The time format to use for parsing.
	 *
	 * @var string
	 */
	private $time_format;

	/**
	 * The timezone to use for parsing.
	 *
	 * @var string
	 */
	private $timezone;

	/**
	 * Constructor.
	 *
	 * @param string $date_format The date format to use.
	 * @param string $time_format The time format to use.
	 * @param string $timezone The timezone to use.
	 */
	public function __construct( $date_format = 'Y-m-d', $time_format = 'H:i:s', $timezone = 'UTC' ) {
		$this->date_format = $date_format;
		$this->time_format = $time_format;
		$this->timezone    = $timezone;
	}

	/**
	 * Converts a date and time into a Unix timestamp in milliseconds.
	 *
	 * If the date is empty, returns null as per business policy.
	 * If the time is empty, defaults to '00:00:00'.
	 *
	 * @param string $date The date string (e.g., '2024-12-06').
	 * @param string $time The time string (e.g., '14:00:00').
	 *
	 * @return int|null The timestamp in milliseconds, or null if parsing fails.
	 *
	 * @throws \Exception If the date and time cannot be parsed into a valid timestamp.
	 */
	public function to_timestamp( $date = '', $time = '' ) {

		if ( empty( $date ) ) {
			return null; // No valid input to process.
		}

		// Normalize inputs.
		$date = trim( $date );
		$time = empty( trim( $time ) ) ? '00:00:00' : trim( $time );

		// Check for ISO 8601 format.
		if ( $this->is_iso_8601( $date ) ) {
			$timestamp = strtotime( $date );
			if ( false !== $timestamp ) {
				return $timestamp * 1000; // Convert to milliseconds.
			}
		}

		// Handle Unix timestamp inputs (10 digits).
		if ( is_numeric( $date ) && strlen( $date ) === 10 ) {
			return (int) $date * 1000; // Convert to milliseconds.
		}

		// Combine date and time for parsing.
		$date_time_string = "{$date} {$time}";
		$date_time_format = $this->handle_date_format( $date ) . ' ' . $this->handle_time_format( $time );

		// Parse using the configured timezone, date format, and time format.
		$date_time_object = DateTime::createFromFormat(
			$date_time_format,
			$date_time_string,
			new DateTimeZone( $this->timezone )
		);

		if ( false === $date_time_object ) {
			throw new \Exception(
				sprintf(
				/* translators: %1$s: Date/time string, %2$s: Expected date format, %3$s: Expected time format */
					esc_html__( 'Failed to parse date/time: "%1$s". Ensure inputs match "%2$s %3$s".', 'uncanny-automator' ),
					esc_html( $date_time_string ),
					esc_html( $this->date_format ),
					esc_html( $this->time_format )
				)
			);
		}

		// Return the Unix timestamp in milliseconds.
		return (int) $date_time_object->format( 'U' ) * 1000;
	}

	/**
	 * Determines the correct date format to use based on the input.
	 *
	 * If the date is in 'Y-m-d' format, it explicitly returns that format.
	 *
	 * @param string $date The date string.
	 * @return string The determined date format.
	 */
	public function handle_date_format( $date ) {

		if ( $this->is_date_in_ymd_format( $date ) ) {
			return 'Y-m-d'; // Adjust for compatibility with standard 'Y-m-d'.
		}

		return $this->date_format;
	}

	/**
	 * Determines the correct time format to use based on the input.
	 *
	 * If the time is in a valid 12-hour format, it adjusts accordingly.
	 *
	 * @param string $time_string The time string.
	 * @return string The determined time format.
	 */
	public function handle_time_format( $time_string ) {

		if ( $this->is_valid_12_hour_time( $time_string ) ) {
			return 'g:i A'; // Adjust for compatibility with 12-hour format.
		}

		return $this->time_format;
	}

	/**
	 * Validates if a string is in ISO 8601 format.
	 *
	 * @param string $date The date string.
	 * @return bool True if the string is in ISO 8601 format, false otherwise.
	 */
	private function is_iso_8601( $date ) {

		$pattern = '/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})/';
		return (bool) preg_match( $pattern, $date );
	}

	/**
	 * Checks if a date is in the 'Y-m-d' format.
	 *
	 * Validates against the strict 'Y-m-d' format.
	 *
	 * @param string $date The date string to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public function is_date_in_ymd_format( $date ) {

		$format   = 'Y-m-d';
		$dateTime = DateTime::createFromFormat( $format, $date );

		return $dateTime && $dateTime->format( $format ) === $date;
	}

	/**
	 * Validates if a time is in a valid 12-hour format.
	 *
	 * Ensures the time matches 'g:i A' format.
	 *
	 * @param string $time_string The time string.
	 * @return bool True if valid, false otherwise.
	 */
	public function is_valid_12_hour_time( $time_string ) {

		$format = 'g:i A';
		$date   = DateTime::createFromFormat( $format, $time_string );

		return $date && empty( DateTime::getLastErrors()['warning_count'] ) && empty( DateTime::getLastErrors()['error_count'] );
	}
}
