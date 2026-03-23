<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);

namespace Uncanny_Automator\Api\Components\Field\Value_Objects;

/**
 * Field Miscellaneous Value Object.
 *
 * Represents additional field data.
 * Flexible array structure for field-specific metadata.
 * Optional array value.
 *
 * @since 7.0
 */
class Field_Miscellaneous {

	/**
	 * Miscellaneous data array.
	 *
	 * @var array
	 */
	private array $value;

	/**
	 * Constructor.
	 *
	 * @param array $value Miscellaneous data array.
	 */
	public function __construct( array $value = array() ) {
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
	 * Get specific miscellaneous value.
	 *
	 * @param string $key Key.
	 * @param mixed  $default Default value if key doesn't exist.
	 *
	 * @return mixed
	 */
	public function get( string $key, $default_value = null ) {
		return $this->value[ $key ] ?? $default_value;
	}

	/**
	 * Check if key exists.
	 *
	 * @param string $key Key to check.
	 *
	 * @return bool
	 */
	public function has( string $key ): bool {
		return array_key_exists( $key, $this->value );
	}

	/**
	 * Check if miscellaneous data is empty.
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return empty( $this->value );
	}
}
