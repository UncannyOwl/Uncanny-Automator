<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Loop\Filter\Value_Objects;

/**
 * Backup Value Object.
 *
 * Represents backup data for a filter.
 * Stores a JSON array of backup configuration for filter state restoration.
 *
 * @since 7.0.0
 */
class Backup {

	/**
	 * The backup array.
	 *
	 * @var array
	 */
	private array $value;

	/**
	 * Constructor.
	 *
	 * @param array $value Backup array.
	 */
	public function __construct( array $value ) {
		$this->value = $value;
	}

	/**
	 * Get value.
	 *
	 * @return array Backup array.
	 */
	public function get_value(): array {
		return $this->value;
	}

	/**
	 * Get a specific backup value.
	 *
	 * @param string $key           Backup key.
	 * @param mixed  $default_value Default value if key not found.
	 * @return mixed
	 */
	public function get( string $key, $default_value = null ) {
		return $this->value[ $key ] ?? $default_value;
	}

	/**
	 * Check if a backup key exists.
	 *
	 * @param string $key Backup key.
	 * @return bool
	 */
	public function has( string $key ): bool {
		return isset( $this->value[ $key ] );
	}

	/**
	 * Check if backup is empty.
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
