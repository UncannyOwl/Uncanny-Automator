<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\User_Selector\Value_Objects;

/**
 * User Selector Unique Field Value Object.
 *
 * Immutable value object that validates and encapsulates the unique field
 * used to identify existing users (email, id, or username).
 *
 * @since 7.0.0
 */
class User_Selector_Unique_Field {

	const EMAIL    = 'email';
	const ID       = 'id';
	const USERNAME = 'username';

	/**
	 * Allowed unique field values.
	 *
	 * @var array
	 */
	private static $allowed_values = array(
		self::EMAIL,
		self::ID,
		self::USERNAME,
	);

	/**
	 * The unique field value.
	 *
	 * @var string|null
	 */
	private $value;

	/**
	 * Constructor.
	 *
	 * @param string|null $value Unique field type (email, id, username) or null.
	 * @throws \InvalidArgumentException If unique field is invalid.
	 */
	public function __construct( $value ) {
		if ( null !== $value && ! in_array( $value, self::$allowed_values, true ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					'User selector unique field must be one of: %s. Given: %s',
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
	 * Get allowed values.
	 *
	 * @return array
	 */
	public static function get_allowed_values(): array {
		return self::$allowed_values;
	}
}
