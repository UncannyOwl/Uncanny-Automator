<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);

namespace Uncanny_Automator\Api\Components\Field\Value_Objects;

/**
 * Field Value Value Object.
 *
 * Represents the actual field value.
 * Can be any type (string, int, array, etc.) - no validation needed.
 * Pure data container.
 *
 * @since 7.0
 */
class Field_Value {

	/**
	 * Field value.
	 *
	 * @var mixed
	 */
	private $value;

	/**
	 * Constructor.
	 *
	 * @param mixed $value Field value.
	 *
	 * @return void
	 */
	public function __construct( $value = '' ) {
		$this->value = $value;
	}

	/**
	 * Get value.
	 *
	 * @return mixed
	 */
	public function get_value() {
		return $this->value;
	}

	/**
	 * Check if value is empty.
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return empty( $this->value );
	}

	/**
	 * Check if value is the custom value sentinel.
	 *
	 * @return bool
	 */
	public function is_custom_value_sentinel(): bool {
		return 'automator_custom_value' === $this->value;
	}

	/**
	 * Check if value contains Automator tokens.
	 *
	 * Automator tokens are wrapped in double curly braces, like:
	 * - {{admin_email}}
	 * - {{UT:ADVANCED:POSTMETA:{{TOKEN_EXTENDED:LOOP_TOKEN:10581:POSTS:POST_ID}}:user_number}}
	 *
	 * @return bool True if value contains tokens, false otherwise.
	 */
	public function contains_automator_token(): bool {
		if ( ! is_string( $this->value ) ) {
			return false;
		}

		return false !== strpos( $this->value, '{{' ) && false !== strpos( $this->value, '}}' );
	}
}
