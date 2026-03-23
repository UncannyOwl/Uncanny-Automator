<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Integration\Value_Objects;

use InvalidArgumentException;
/**
 * Integration Collections Value Object.
 *
 * Represents the collections for the integration (e.g., "e-commerce", "membership").
 * Default: empty array
 *
 * @since 7.0.0
 */
class Integration_Collections {

	/**
	 * The collections value.
	 *
	 * @var array<string>
	 */
	private array $value;

	/**
	 * Constructor.
	 *
	 * @param array<string> $value Collections array.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid collections.
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
	 * Check if integration has a specific collection.
	 *
	 * @param string $collection Collection to check.
	 * @return bool
	 */
	public function has_collection( string $collection ): bool {
		return in_array( $collection, $this->value, true );
	}

	/**
	 * Get collections count.
	 *
	 * @return int
	 */
	public function count(): int {
		return count( $this->value );
	}

	/**
	 * Validate collections.
	 *
	 * @param array $value Value to validate.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid.
	 */
	private function validate( array $value ): void {
		// Collections should be an array of strings
		foreach ( $value as $collection ) {
			if ( ! is_string( $collection ) ) {
				throw new InvalidArgumentException( 'All collections must be strings' );
			}
		}
	}
}
