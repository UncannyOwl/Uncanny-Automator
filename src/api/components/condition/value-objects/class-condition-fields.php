<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Condition\Value_Objects;

/**
 * Condition Fields Value Object.
 *
 * Represents the field configuration for an individual condition.
 * Fields are stored as key-value pairs where keys are field codes
 * and values are the configured field values.
 *
 * Example:
 * {
 *   "TOKEN": "45",
 *   "CRITERIA": "is",
 *   "CRITERIA_readable": "is",
 *   "VALUE": "67"
 * }
 *
 * @since 7.0.0
 */
class Condition_Fields {

	private array $fields;

	/**
	 * Constructor.
	 *
	 * @param array $fields Field configuration array.
	 * @throws \InvalidArgumentException If fields are invalid.
	 */
	public function __construct( array $fields ) {
		$this->validate( $fields );
		$this->fields = $fields;
	}

	/**
	 * Create empty condition fields.
	 *
	 * @return self Empty condition fields.
	 */
	public static function empty(): self {
		return new self( array() );
	}

	/**
	 * Get all fields.
	 *
	 * @return array All field configurations.
	 */
	public function get_all(): array {
		return $this->fields;
	}

	/**
	 * Get a specific field value.
	 *
	 * @param string $field_code Field code to get.
	 * @return mixed Field value or null if not found.
	 */
	public function get_field( string $field_code ) {
		return $this->fields[ $field_code ] ?? null;
	}

	/**
	 * Check if a field exists.
	 *
	 * @param string $field_code Field code to check.
	 * @return bool True if field exists.
	 */
	public function has_field( string $field_code ): bool {
		return isset( $this->fields[ $field_code ] );
	}

	/**
	 * Get field with readable suffix.
	 *
	 * @param string $field_code Base field code.
	 * @return mixed Readable field value or null if not found.
	 */
	public function get_readable_field( string $field_code ) {
		return $this->get_field( $field_code . '_readable' );
	}

	/**
	 * Get field with label suffix.
	 *
	 * @param string $field_code Base field code.
	 * @return mixed Label field value or null if not found.
	 */
	public function get_label_field( string $field_code ) {
		return $this->get_field( $field_code . '_label' );
	}

	/**
	 * Add or update a field.
	 *
	 * @param string $field_code Field code.
	 * @param mixed  $value Field value.
	 * @return self New instance with updated field.
	 */
	public function with_field( string $field_code, $value ): self {
		$fields                = $this->fields;
		$fields[ $field_code ] = $value;

		return new self( $fields );
	}

	/**
	 * Remove a field.
	 *
	 * @param string $field_code Field code to remove.
	 * @return self New instance without the field.
	 */
	public function without_field( string $field_code ): self {
		$fields = $this->fields;
		unset( $fields[ $field_code ] );

		return new self( $fields );
	}

	/**
	 * Convert to array representation.
	 *
	 * @return array Fields as associative array.
	 */
	public function to_array(): array {
		return $this->fields;
	}

	/**
	 * Check if fields are empty.
	 *
	 * @return bool True if no fields are configured.
	 */
	public function is_empty(): bool {
		return empty( $this->fields );
	}

	/**
	 * Count the number of fields.
	 *
	 * @return int Number of fields.
	 */
	public function count(): int {
		return count( $this->fields );
	}

	/**
	 * Check equality with another Condition_Fields.
	 *
	 * @param Condition_Fields $other Other fields to compare.
	 * @return bool True if fields are equal.
	 */
	public function equals( Condition_Fields $other ): bool {
		return $this->fields === $other->get_all();
	}

	/**
	 * Validate field configuration.
	 *
	 * @param array $fields Fields to validate.
	 * @throws \InvalidArgumentException If fields are invalid.
	 */
	private function validate( array $fields ): void {
		foreach ( $fields as $key => $value ) {
			if ( ! is_string( $key ) || empty( $key ) ) {
				throw new \InvalidArgumentException( 'Field codes must be non-empty strings' );
			}

			// Allow various field value types (string, int, bool, array)
			if ( is_object( $value ) && ! method_exists( $value, '__toString' ) ) {
				throw new \InvalidArgumentException(
					sprintf( 'Field value for "%s" must be a scalar, array, or stringable object', $key )
				);
			}
		}
	}
}
