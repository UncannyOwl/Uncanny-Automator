<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Recipe\Value_Objects;

/**
 * Recipe Trigger Logic.
 *
 * Value object for trigger execution logic in user recipes.
 * Enforces valid logic values: 'all' (AND) or 'any' (OR).
 *
 * @since 7.0.0
 */
class Recipe_Trigger_Logic {

	private string $value;

	const LOGIC_ALL = 'all';
	const LOGIC_ANY = 'any';

	/**
	 * Constructor.
	 *
	 * @param string $value Logic value ('all' or 'any').
	 * @throws \InvalidArgumentException If invalid logic value.
	 */
	public function __construct( ?string $value ) {

		if ( is_null( $value ) ) {
			$this->value = self::LOGIC_ALL;
			return;
		}

		$this->validate( $value );
		$this->value = $value;
	}

	/**
	 * Get value.
	 *
	 * @return string
	 */
	public function get_value(): string {
		return $this->value;
	}

	/**
	 * Check if logic is 'all' (AND).
	 *
	 * @return bool
	 */
	public function is_all(): bool {
		return self::LOGIC_ALL === $this->value;
	}

	/**
	 * Check if logic is 'any' (OR).
	 *
	 * @return bool
	 */
	public function is_any(): bool {
		return self::LOGIC_ANY === $this->value;
	}

	/**
	 * Validate logic value.
	 *
	 * @param string $value Value to validate.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate( string $value ): void {
		$valid_values = array( self::LOGIC_ALL, self::LOGIC_ANY );

		if ( ! in_array( $value, $valid_values, true ) ) {
			throw new \InvalidArgumentException(
				'Recipe trigger logic must be "all" or "any", got: ' . $value . ' Valid values are: ' . implode( ', ', $valid_values )
			);
		}
	}
}
