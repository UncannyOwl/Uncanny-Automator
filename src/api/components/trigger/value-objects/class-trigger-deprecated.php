<?php

namespace Uncanny_Automator\Api\Components\Trigger\Value_Objects;

/**
 * Trigger Deprecated Value Object.
 *
 * Represents whether a trigger is deprecated.
 * Immutable boolean value with explicit semantics.
 *
 * @since 7.0.0
 */
class Trigger_Deprecated {

	private bool $value;

	/**
	 * Constructor.
	 *
	 * @param bool $value Whether trigger is deprecated.
	 */
	public function __construct( bool $value ) {
		$this->value = $value;
	}

	/**
	 * Get value.
	 *
	 * @return bool
	 */
	public function get_value(): bool {
		return $this->value;
	}

	/**
	 * Check if trigger is deprecated.
	 *
	 * @return bool
	 */
	public function is_deprecated(): bool {
		return $this->value;
	}

	/**
	 * Check if trigger is active (not deprecated).
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return ! $this->value;
	}

	/**
	 * Check if values are equal.
	 *
	 * @param Trigger_Deprecated $other Other deprecated status.
	 * @return bool
	 */
	public function equals( Trigger_Deprecated $other ): bool {
		return $this->value === $other->get_value();
	}
}
