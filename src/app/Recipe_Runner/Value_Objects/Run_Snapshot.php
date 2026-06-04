<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Value_Objects;

/**
 * Immutable value object representing a captured recipe-run snapshot.
 *
 * Carries the trigger context that was active when a recipe run started,
 * so a failed run can be replayed later. Returned by the run-snapshot
 * store; consumers iterate or read named accessors instead of poking at
 * raw associative arrays.
 *
 * @since 7.4.0
 * @package Uncanny_Automator\App\Recipe_Runner\Value_Objects
 */
final class Run_Snapshot {

	/**
	 * Decoded snapshot payload (trigger args + meta).
	 *
	 * @var array
	 */
	private array $data;

	/**
	 * @param array $data Decoded snapshot payload.
	 */
	public function __construct( array $data ) {
		$this->data = $data;
	}

	/**
	 * Get the full payload as an associative array.
	 *
	 * Provided for legacy callers that still expect the array shape; new
	 * consumers should prefer the named accessors below.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return $this->data;
	}

	/**
	 * Look up a named field in the payload.
	 *
	 * @param string $key     Top-level key.
	 * @param mixed  $default Fallback returned when the key is missing.
	 * @return mixed
	 */
	public function get( string $key, $default = null ) {
		return $this->data[ $key ] ?? $default;
	}
}
