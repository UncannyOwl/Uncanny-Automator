<?php

namespace Uncanny_Automator\Services\Recipe\Process\Throttle;

/**
 * Trait Time_Unit_Converter
 *
 * Provides time unit conversion functionality
 */
trait Time_Unit_Converter_Trait {

	/**
	 * @var array<string,int>
	 */
	private $unit_to_seconds = array(
		'second'  => 1,
		'seconds' => 1,
		'minute'  => 60,
		'minutes' => 60,
		'hour'    => 3600,
		'hours'   => 3600,
		'day'     => 86400,
		'days'    => 86400,
		'week'    => 604800,
		'weeks'   => 604800,
		'year'    => 31536000,
		'years'   => 31536000,
	);

	/**
	 * Converts a duration and unit to seconds
	 *
	 * @param int $duration
	 * @param string $unit
	 *
	 * @return int|false
	 */
	private function convert_to_seconds( $duration, $unit ) {
		$unit = strtolower( $unit );

		if ( ! isset( $this->unit_to_seconds[ $unit ] ) ) {
			return false;
		}

		return $duration * $this->unit_to_seconds[ $unit ];
	}
}
