<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);

namespace Uncanny_Automator\Api\Components\Field\Value_Objects;

/**
 * Field Custom Value Object.
 *
 * Represents the custom value when field value is "automator_custom_value".
 * Typically contains Automator tokens or custom input.
 * Optional string value.
 *
 * @since 7.0
 */
class Field_Custom {

	/**
	 * Custom value.
	 *
	 * @var string
	 */
	private string $value;

	/**
	 * Constructor.
	 *
	 * @param string $value Custom value.
	 */
	public function __construct( string $value = '' ) {
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
	 * Check if has custom value.
	 *
	 * @return bool
	 */
	public function has_value(): bool {
		return ! empty( $this->value );
	}
}
