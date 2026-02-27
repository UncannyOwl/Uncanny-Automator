<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Loop\Filter\Value_Objects;

/**
 * Id Value Object.
 *
 * Immutable value object that validates and encapsulates filter identifier.
 * Maps to WordPress post ID for uo-loop-filter post type.
 *
 * @since 7.0.0
 */
class Id {

	/**
	 * The filter ID value.
	 *
	 * @var int|null
	 */
	private $value;

	/**
	 * Constructor.
	 *
	 * @param int|null $value Filter ID or null for new filters.
	 * @throws \InvalidArgumentException If ID is invalid.
	 */
	public function __construct( $value ) {
		$this->validate( $value );
		$this->value = null !== $value ? (int) $value : null;
	}

	/**
	 * Get value.
	 *
	 * @return int|null Filter ID or null.
	 */
	public function get_value(): ?int {
		return $this->value;
	}

	/**
	 * Check if filter ID is null (new filter).
	 *
	 * @return bool
	 */
	public function is_null(): bool {
		return null === $this->value;
	}

	/**
	 * Check if filter is persisted.
	 *
	 * @return bool
	 */
	public function is_persisted(): bool {
		return null !== $this->value && $this->value > 0;
	}

	/**
	 * Validate filter ID.
	 *
	 * @param mixed $value Value to validate.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate( $value ): void {
		if ( null !== $value ) {
			$int_value = (int) $value;
			if ( $int_value <= 0 ) {
				throw new \InvalidArgumentException( 'Id must be a positive integer or null' );
			}
		}
	}
}
