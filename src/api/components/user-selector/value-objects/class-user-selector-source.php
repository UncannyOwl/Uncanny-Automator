<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\User_Selector\Value_Objects;

/**
 * User Selector Source Value Object.
 *
 * Immutable value object that validates and encapsulates user selector source type.
 * Enforces valid source enumeration: 'existingUser' or 'newUser'.
 *
 * @since 7.0.0
 */
class User_Selector_Source {

	const EXISTING_USER = 'existingUser';
	const NEW_USER      = 'newUser';

	/**
	 * Allowed source values.
	 *
	 * @var array
	 */
	private static $allowed_values = array(
		self::EXISTING_USER,
		self::NEW_USER,
	);

	/**
	 * The source value.
	 *
	 * @var string
	 */
	private $value;

	/**
	 * Constructor.
	 *
	 * @param string $value User selector source (existingUser or newUser).
	 * @throws \InvalidArgumentException If source is invalid.
	 */
	public function __construct( $value ) {
		if ( ! in_array( $value, self::$allowed_values, true ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					'User selector source must be one of: %s. Given: %s',
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
	 * @return string
	 */
	public function get_value(): string {
		return $this->value;
	}

	/**
	 * Check if source is existing user.
	 *
	 * @return bool
	 */
	public function is_existing_user(): bool {
		return self::EXISTING_USER === $this->value;
	}

	/**
	 * Check if source is new user.
	 *
	 * @return bool
	 */
	public function is_new_user(): bool {
		return self::NEW_USER === $this->value;
	}
}
