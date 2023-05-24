<?php
namespace Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Utils;

use Uncanny_Automator\Automator_Status;

class Formatters_Utils {

	/**
	 * Helper method to handle a falsy variable.
	 *
	 * @param mixed $mixed The variable to handle.
	 * @param string $fallback The fallback value when $mixed is falsy.
	 *
	 * @return mixed
	 */
	public static function handle_var( $mixed = null, $fallback = '' ) {

		return empty( $mixed ) ? $fallback : $mixed;

	}

	/**
	 * Flattens the array from post meta.
	 *
	 * @param mixed[] $meta The result from get_post_meta with only key given as parameter.
	 *
	 * @return array<string>
	 */
	public static function flatten_post_meta( $meta = array() ) {
		return array_map(
			function( $item ) {
				if ( is_array( $item ) && isset( $item[0] ) ) {
					return (string) $item[0];
				}
				return '';
			},
			$meta
		);
	}

	/**
	 * Convert a specific datetime string into its timestamp format, with respect to time zone.
	 *
	 * @param  string $date_string
	 * @return int|false The timestamp. False if date object is not a valid object.
	 */
	public static function strtotime( $date_string = '' ) {
		try {
			$date = new \DateTime( $date_string, new \DateTimeZone( Automator()->get_timezone_string() ) );
			if ( false !== $date ) {
				return intval( $date->format( 'U' ) );
			}
			/** @phpstan-ignore-next-line */
			return false;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Formats the given date string into a valid format.
	 *
	 * @param string $date_string Any valid date.
	 * @param string $format Any valid date format.
	 *
	 * @return false|null|string
	 */
	public static function date_time_format( $date_string = '', $format = 'Y-m-d @ H:i:s' ) {

		// Handle default mysql date.
		if ( strtotime( '0000-00-00 00:00:00' ) === strtotime( $date_string ) ) {
			return null;
		}

		$wp_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		$dt = \DateTime::createFromFormat( 'Y-m-d H:i:s', trim( $date_string ), new \DateTimeZone( Automator()->get_timezone_string() ) );

		if ( false === $dt ) {
			return false;
		}

		return $dt->format( $wp_format );

	}

	/**
	 * Accepts status code and return its corresponding class name.
	 *
	 * @param Automator_Status $automator_status The class for handling common logs status.
	 * @param int $status_code
	 *
	 * @return string The status class name.
	 */
	public static function status_class_name( Automator_Status $automator_status, $status_code = 0 ) {

		$class_name = $automator_status::get_class_name( $status_code );

		// Not completed status in Triggers are shown as in-progress.
		if ( $automator_status::get_class_name( $automator_status::NOT_COMPLETED ) === $class_name ) {
			$class_name = $automator_status::get_class_name( $automator_status::IN_PROGRESS );
		}

		return (string) $class_name;

	}

	/**
	 * Given the integer time. Return its time units value.
	 *
	 * @param int $time
	 *
	 * @return string[]
	 */
	public static function time_units( $time = 0 ) {
		return array(
			/* translators: Units of time (seconds) */
			'seconds' => _n( '%1$s second', '%1$s seconds', $time, 'uncanny-automator' ),
			/* translators: Units of time (minutes)*/
			'minutes' => _n( '%1$s minute', '%1$s minutes', $time, 'uncanny-automator' ),
			/* translators: Units of time (hours)*/
			'hours'   => _n( '%1$s hour', '%1$s hours', $time, 'uncanny-automator' ),
			/* translators: Units of time (days)*/
			'days'    => _n( '%1$s day', '%1$s days', $time, 'uncanny-automator' ),
			/* translators: Units of time (years)*/
			'years'   => _n( '%1$s year', '%1$s years', $time, 'uncanny-automator' ),
		);
	}

	/**
	 * @param mixed[] $fields
	 *
	 * @return bool
	 */
	public static function fields_has_combination_of_options_and_options_group( $fields = array() ) {

		$fields = (object) $fields;

		/** @phpstan-ignore-next-line */
		foreach ( $fields as $field ) {
			foreach ( $field as $key => $value ) {
				if ( is_numeric( $key ) ) {
					return true;
				}
			}
		};

		return false;

	}

}
