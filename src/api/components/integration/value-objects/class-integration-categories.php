<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Integration\Value_Objects;

use InvalidArgumentException;

/**
 * Integration Categories Value Object.
 *
 * Represents the categories for the integration (e.g., "featured", "e-learning").
 * Default: empty array
 *
 * @since 7.0.0
 */
class Integration_Categories {

	/**
	 * The categories value.
	 *
	 * @var array<string>
	 */
	private array $value;

	/**
	 * Constructor.
	 *
	 * @param array<string> $value Categories array.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid categories.
	 */
	public function __construct( array $value = array() ) {
		$this->validate( $value );
		$this->value = $value;
	}

	/**
	 * Get value.
	 *
	 * @return array
	 */
	public function get_value(): array {
		return $this->value;
	}

	/**
	 * Check if integration has a specific category.
	 *
	 * @param string $category Category to check.
	 *
	 * @return bool
	 */
	public function has_category( string $category ): bool {
		return in_array( $category, $this->value, true );
	}

	/**
	 * Check if integration is featured.
	 *
	 * @return bool
	 */
	public function is_featured(): bool {
		return $this->has_category( 'featured' );
	}

	/**
	 * Get categories count.
	 *
	 * @return int
	 */
	public function count(): int {
		return count( $this->value );
	}

	/**
	 * Validate categories.
	 *
	 * @param array $value Value to validate.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid.
	 */
	private function validate( array $value ): void {
		// Categories should be an array of strings
		foreach ( $value as $category ) {
			if ( ! is_string( $category ) ) {
				throw new InvalidArgumentException( 'All categories must be strings' );
			}
		}
	}
}
