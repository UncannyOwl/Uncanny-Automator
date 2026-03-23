<?php
declare(strict_types=1);

namespace Uncanny_Automator\Api\Components\Field\Value_Objects;

/**
 * Flattened Field Config Value Object.
 *
 * Represents flattened field configuration array for CRUD services.
 *
 * Example structure:
 * [
 *   "FIELD_CODE" => "automator_custom_value",
 *   "FIELD_CODE_readable" => "Use a token/custom value",
 *   "FIELD_CODE_custom" => "{{recipe_id}}"
 * ]
 *
 * @since 7.0
 */
class Flattened_Field_Config {

	/**
	 * Config data.
	 *
	 * @var array<string, mixed>
	 */
	private array $config;

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $config Config array.
	 */
	public function __construct( array $config = array() ) {
		$this->config = $config;
	}

	/**
	 * Create empty config.
	 *
	 * @return self
	 */
	public static function empty(): self {
		return new self( array() );
	}

	/**
	 * Add field value.
	 *
	 * @param string $field_code Field code.
	 * @param mixed  $value      Field value.
	 * @return self
	 */
	public function add_field( string $field_code, $value ): self {
		$this->config[ $field_code ] = $value;
		return $this;
	}

	/**
	 * Add readable field value.
	 *
	 * @param string $field_code Field code.
	 * @param string $readable    Readable value.
	 * @return self
	 */
	public function add_readable( string $field_code, string $readable ): self {
		if ( ! empty( $readable ) ) {
			$this->config[ $field_code . '_readable' ] = $readable;
		}
		return $this;
	}

	/**
	 * Add custom field value.
	 *
	 * @param string $field_code Field code.
	 * @param string $custom     Custom value.
	 * @return self
	 */
	public function add_custom( string $field_code, string $custom ): self {
		if ( ! empty( $custom ) ) {
			$this->config[ $field_code . '_custom' ] = $custom;
		}
		return $this;
	}

	/**
	 * Get config as array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return $this->config;
	}

	/**
	 * Get field value.
	 *
	 * @param string $field_code Field code.
	 * @param mixed  $default    Default value.
	 * @return mixed
	 */
	public function get_field( string $field_code, $default = null ) {
		return $this->config[ $field_code ] ?? $default;
	}

	/**
	 * Check if field exists.
	 *
	 * @param string $field_code Field code.
	 * @return bool
	 */
	public function has_field( string $field_code ): bool {
		return isset( $this->config[ $field_code ] );
	}

	/**
	 * Merge with another config.
	 *
	 * @param Flattened_Field_Config $other Other config to merge.
	 * @return self
	 */
	public function merge( Flattened_Field_Config $other ): self {
		$this->config = array_merge( $this->config, $other->to_array() );
		return $this;
	}
}
