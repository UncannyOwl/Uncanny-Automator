<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Services\Availability;

/**
 * Interface for checking feature availability.
 *
 * @since 7.0.0
 * @package Uncanny_Automator\Api\Services\Availability
 */
interface Availability_Checker_Interface {

	/**
	 * Check if a feature is available and return a human-readable message.
	 *
	 * @param Availability_Data $data The feature availability data.
	 *
	 * @return string Human-readable availability message.
	 */
	public function check( Availability_Data $data );

	/**
	 * Get an array of blocking issues preventing feature availability.
	 *
	 * @param Availability_Data $data The feature availability data.
	 *
	 * @return array Array of blocking issues (empty if available).
	 */
	public function get_blockers( Availability_Data $data );

	/**
	 * Check if a feature is available (simple boolean check).
	 *
	 * @param Availability_Data $data The feature availability data.
	 *
	 * @return bool True if available, false otherwise.
	 */
	public function is_available( Availability_Data $data );
}
