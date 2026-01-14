<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);

namespace Uncanny_Automator\Api\Components\Field\Value_Objects;

use InvalidArgumentException;
use Uncanny_Automator\Api\Components\Field\Enums\Field_Types;

/**
 * Field Type Value Object.
 *
 * Represents the type of field (e.g., 'text', 'email', 'select').
 * Validates against Field_Types enum values.
 * Empty string is allowed for flexibility.
 *
 * @since 7.0
 */
class Field_Type {

	/**
	 * Field type value.
	 *
	 * @var string
	 */
	private string $value;

	/**
	 * Constructor.
	 *
	 * @param string $value Field type value.
	 * @throws InvalidArgumentException If invalid type.
	 *
	 * @return void
	 */
	public function __construct( string $value = '' ) {
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
	 * Check if type is empty.
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return empty( $this->value );
	}

	/**
	 * Validate field type.
	 *
	 * @param string $value Value to validate.
	 * @throws InvalidArgumentException If invalid.
	 *
	 * @return void
	 */
	private function validate( string $value ): void {
		if ( ! Field_Types::is_valid( $value ) ) {
			throw new InvalidArgumentException(
				sprintf(
					'Invalid field type: %s. Must be one of: %s',
					$value,
					implode( ', ', Field_Types::get_all() )
				)
			);
		}
	}
}
