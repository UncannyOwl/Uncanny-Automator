<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\User_Selector\Value_Objects;

/**
 * User Selector Prioritized Field Value Object.
 *
 * Immutable value object that validates and encapsulates the prioritized field
 * used when creating new users and a duplicate is found. Determines whether
 * to match by email or username first.
 *
 * @since 7.0.0
 */
class User_Selector_Prioritized_Field {

	const EMAIL    = 'email';
	const USERNAME = 'username';

	/**
	 * Allowed prioritized field values.
	 *
	 * @var array
	 */
	private static $allowed_values = array(
		self::EMAIL,
		self::USERNAME,
	);

	/**
	 * The prioritized field value.
	 *
	 * @var string|null
	 */
	private $value;

	/**
	 * Constructor.
	 *
	 * @param string|null $value Prioritized field type (email or username) or null.
	 * @throws \InvalidArgumentException If prioritized field is invalid.
	 */
	public function __construct( $value ) {
		if ( null !== $value && ! in_array( $value, self::$allowed_values, true ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					'User selector prioritized field must be one of: %s. Given: %s',
					implode( ', ', self::$allowed_values ),
					$value
				)
			);
		}
		$this->value = $value;
	}

	/**
	 * Get value.
	 *
	 * @return string|null
	 */
	public function get_value(): ?string {
		return $this->value;
	}

	/**
	 * Check if email is prioritized.
	 *
	 * @return bool
	 */
	public function is_email_prioritized(): bool {
		return self::EMAIL === $this->value;
	}

	/**
	 * Check if username is prioritized.
	 *
	 * @return bool
	 */
	public function is_username_prioritized(): bool {
		return self::USERNAME === $this->value;
	}

	/**
	 * Get allowed values.
	 *
	 * @return array
	 */
	public static function get_allowed_values(): array {
		return self::$allowed_values;
	}
}
