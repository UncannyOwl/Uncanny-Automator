<?php

namespace Uncanny_Automator\Api\Components\Action\Value_Objects;

/**
 * Action Deprecated Value Object.
 *
 * Represents whether an action is deprecated.
 * Immutable boolean value with explicit semantics.
 *
 * @since 7.0.0
 */
class Action_Deprecated {

	private bool $value;

	/**
	 * Constructor.
	 *
	 * @param bool $value Whether action is deprecated.
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
	 * Check if action is deprecated.
	 *
	 * @return bool
	 */
	public function is_deprecated(): bool {
		return $this->value;
	}

	/**
	 * Check if action is active (not deprecated).
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return ! $this->value;
	}

	/**
	 * Check if values are equal.
	 *
	 * @param Action_Deprecated $other Other deprecated status.
	 * @return bool
	 */
	public function equals( Action_Deprecated $other ): bool {
		return $this->value === $other->get_value();
	}
}
