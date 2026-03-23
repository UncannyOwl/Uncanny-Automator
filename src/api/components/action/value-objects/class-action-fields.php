<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
/**
 * Action Fields Value Object
 *
 * Wraps action field parameters to replace magic string array access
 * with type-safe getter methods.
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Components\Action\Value_Objects;

/**
 * Class Action_Fields
 *
 * @since 7.0.0
 */
class Action_Fields {

	/**
	 * Field data.
	 *
	 * @since 7.0.0
	 * @var array
	 */
	private $fields;

	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 * @param array $fields Field data array.
	 */
	public function __construct( array $fields ) {
		$this->fields = $fields;
	}

	/**
	 * Get field value by key.
	 *
	 * @since 7.0.0
	 * @param string $key     Field key.
	 * @param mixed  $default Default value if key doesn't exist.
	 * @return mixed Field value or default.
	 */
	public function get( string $key, $default_value = null ) {
		return $this->fields[ $key ] ?? $default_value;
	}

	/**
	 * Check if field exists.
	 *
	 * @since 7.0.0
	 * @param string $key Field key.
	 * @return bool True if field exists.
	 */
	public function has( string $key ): bool {
		return isset( $this->fields[ $key ] );
	}

	/**
	 * Get all fields as array.
	 *
	 * @since 7.0.0
	 * @return array All field data.
	 */
	public function to_array(): array {
		return $this->fields;
	}
}
