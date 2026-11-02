<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects;

/**
 * Recipe Total Times Value Object — recipe-wide execution limit.
 *
 * null = no limit (unset). A non-null value is a non-negative integer; a
 * limit of 0 is a real (blocking) limit.
 */
class Recipe_Total_Times {

	/** Integer-boundary sentinel: the int that represents "no limit" (unset). */
	const UNLIMITED = -1;

	private $value;

	/**
	 * Wrap a recipe-wide total-times limit.
	 *
	 * @param int|null $value Total allowed runs for the recipe (>= 0), or null for no limit.
	 * @throws \InvalidArgumentException If value is a negative or non-numeric scalar.
	 */
	public function __construct( $value = null ) {

		if ( is_null( $value ) ) {
			$this->value = null;
			return;
		}

		$this->validate( $value );

		$this->value = (int) $value;
	}

	/**
	 * Get the limit value.
	 *
	 * @return int|null Positive limit, or null when there is no limit.
	 */
	public function get_value() {
		return $this->value;
	}

	/**
	 * Integer representation for the service/wire boundary: the positive limit,
	 * or UNLIMITED (-1) when there is no limit.
	 *
	 * @return int
	 */
	public function to_int(): int {
		return $this->value ?? self::UNLIMITED;
	}

	/**
	 * Wire/panel presentation rule (single authoritative source for the
	 * settings projection): does a projected limit value render as "no limit"
	 * in the publish panel? A non-positive value — the `-1` unset sentinel, or
	 * a degenerate stored `0` — renders blank. Presentation only; enforcement
	 * treats a stored `0` as a real, blocking limit.
	 *
	 * @param int $value Projected limit (positive = a limit; <= 0 = no limit shown).
	 * @return bool
	 */
	public static function renders_as_no_limit( int $value ): bool {
		return $value <= 0;
	}

	/**
	 * Validate the total-times value (null, or a non-negative integer).
	 *
	 * @param mixed $value Value to validate.
	 * @throws \InvalidArgumentException When the value is negative or non-numeric.
	 */
	private function validate( $value ) {

		if ( is_null( $value ) ) {
			return;
		}

		if ( ! is_numeric( $value ) || $value < 0 ) {
			throw new \InvalidArgumentException( 'Total times must be a non-negative integer' );
		}
	}
}
