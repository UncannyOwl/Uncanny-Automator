<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Loop\Value_Objects;

/**
 * Loop UI Order Value Object.
 *
 * Represents the UI ordering position for loops.
 * Loops always have a UI order of 2 (after normal actions at 0 and closures at 1).
 *
 * UI Order values:
 * - 0 = Normal actions
 * - 1 = Closures
 * - 2 = Loops
 * - 5 = Delays
 *
 * @since 7.0.0
 */
class Loop_Ui_Order {

	/**
	 * Default UI order for loops.
	 *
	 * @var int
	 */
	const DEFAULT_ORDER = 2;

	/**
	 * The UI order value.
	 *
	 * @var int
	 */
	private int $value;

	/**
	 * Constructor.
	 *
	 * @param int|null $value UI order value (defaults to 2).
	 * @throws \InvalidArgumentException If value is negative.
	 */
	public function __construct( $value = null ) {
		if ( null !== $value ) {
			$int_value = (int) $value;
			if ( $int_value < 0 ) {
				throw new \InvalidArgumentException( 'UI order must be a non-negative integer' );
			}
			$this->value = $int_value;
		} else {
			$this->value = self::DEFAULT_ORDER;
		}
	}

	/**
	 * Get value.
	 *
	 * @return int UI order value.
	 */
	public function get_value(): int {
		return $this->value;
	}

	/**
	 * Check if this is the default loop order.
	 *
	 * @return bool True if default order.
	 */
	public function is_default(): bool {
		return self::DEFAULT_ORDER === $this->value;
	}
}
