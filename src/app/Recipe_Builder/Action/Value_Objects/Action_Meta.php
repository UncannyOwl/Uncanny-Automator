<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Action\Value_Objects;

/**
 * Action Meta Value Object.
 *
 * Represents action configuration and settings.
 * Flexible array structure for action-specific data.
 * Follows the same pattern as Trigger_Configuration.
 *
 * @since 7.0.0
 */
class Action_Meta {

	private array $value;

	/**
	 * Constructor.
	 *
	 * @param array $value Action meta array.
	 * @throws \InvalidArgumentException If invalid meta.
	 */
	public function __construct( array $value ) {
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
	 * Get specific meta value.
	 *
	 * @param string $key Meta key.
	 * @param mixed  $default Default value if key doesn't exist.
	 * @return mixed
	 */
	public function get( string $key, $default_value = null ) {
		return $this->value[ $key ] ?? $default_value;
	}

	/**
	 * Check if meta key exists.
	 *
	 * @param string $key Meta key to check.
	 * @return bool
	 */
	public function has( string $key ): bool {
		return array_key_exists( $key, $this->value );
	}

	/**
	 * Check if meta is empty.
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return empty( $this->value );
	}

	/**
	 * To array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return $this->value;
	}

	/**
	 * Validate action meta.
	 *
	 * @param array $value Value to validate.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate( array $value ): void {
		// Meta can be empty array - no additional validation needed
		// Individual actions will validate their specific requirements
	}
}
