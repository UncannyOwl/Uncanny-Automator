<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Trigger\Value_Objects;

/**
 * Trigger Configuration Value Object.
 *
 * Represents trigger-specific configuration settings.
 * Generic container for various trigger configurations.
 *
 * @since 7.0.0
 */
class Trigger_Configuration {

	private array $value;

	/**
	 * Constructor.
	 *
	 * @param array $value Configuration array.
	 */
	public function __construct( array $value = array() ) {
		$this->value = $value;
	}

	/**
	 * Get configuration value.
	 *
	 * @return array
	 */
	public function get_value(): array {
		return $this->value;
	}

	/**
	 * Get specific configuration item.
	 *
	 * @param string $key Configuration key.
	 * @param mixed  $default Default value if key not found.
	 * @return mixed
	 */
	public function get( string $key, $default_value = null ) {
		return $this->value[ $key ] ?? $default_value;
	}

	/**
	 * Check if configuration has specific key.
	 *
	 * @param string $key Configuration key.
	 * @return bool
	 */
	public function has( string $key ): bool {
		return array_key_exists( $key, $this->value );
	}

	/**
	 * Check if configuration is empty.
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return empty( $this->value );
	}

	/**
	 * Get configuration keys.
	 *
	 * @return array
	 */
	public function get_keys(): array {
		return array_keys( $this->value );
	}

	/**
	 * Merge with another configuration.
	 *
	 * @param Trigger_Configuration $other Other configuration.
	 * @return self New instance with merged configuration.
	 */
	public function merge( Trigger_Configuration $other ): self {
		return new self( array_merge( $this->value, $other->get_value() ) );
	}
}
