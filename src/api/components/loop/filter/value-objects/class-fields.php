<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Loop\Filter\Value_Objects;

/**
 * Fields Value Object.
 *
 * Represents the field configuration data for a filter.
 * Wraps a JSON array of field values that configure the filter behavior.
 *
 * @since 7.0.0
 */
class Fields {

	/**
	 * The fields array.
	 *
	 * @var array
	 */
	private array $value;

	/**
	 * Constructor.
	 *
	 * @param array $value Fields array.
	 */
	public function __construct( array $value ) {
		$this->value = $value;
	}

	/**
	 * Get value.
	 *
	 * @return array Fields array.
	 */
	public function get_value(): array {
		return $this->value;
	}

	/**
	 * Get a specific field value.
	 *
	 * @param string $key           Field key.
	 * @param mixed  $default_value Default value if key not found.
	 * @return mixed
	 */
	public function get( string $key, $default_value = null ) {
		return $this->value[ $key ] ?? $default_value;
	}

	/**
	 * Check if a field exists.
	 *
	 * @param string $key Field key.
	 * @return bool
	 */
	public function has( string $key ): bool {
		return isset( $this->value[ $key ] );
	}

	/**
	 * Check if fields are empty.
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return empty( $this->value );
	}

	/**
	 * Convert to array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return $this->value;
	}

	/**
	 * Create from JSON string.
	 *
	 * @param string $json JSON string.
	 * @return self
	 */
	public static function from_json( string $json ): self {
		$decoded = json_decode( $json, true );
		return new self( is_array( $decoded ) ? $decoded : array() );
	}
}
