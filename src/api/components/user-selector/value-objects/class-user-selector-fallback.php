<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\User_Selector\Value_Objects;

/**
 * User Selector Fallback Value Object.
 *
 * Immutable value object that validates and encapsulates fallback behavior
 * when user matching fails or succeeds unexpectedly.
 *
 * For existing user source:
 * - 'create-new-user': Create a new user if no match found
 * - 'do-nothing': Abort if no match found
 *
 * For new user source:
 * - 'select-existing-user': Use existing user if duplicate found
 * - 'do-nothing': Abort if duplicate found
 *
 * @since 7.0.0
 */
class User_Selector_Fallback {

	const CREATE_NEW_USER       = 'create-new-user';
	const SELECT_EXISTING_USER  = 'select-existing-user';
	const DO_NOTHING            = 'do-nothing';

	/**
	 * Allowed fallback values for existing user source.
	 *
	 * @var array
	 */
	private static $existing_user_fallbacks = array(
		self::CREATE_NEW_USER,
		self::DO_NOTHING,
	);

	/**
	 * Allowed fallback values for new user source.
	 *
	 * @var array
	 */
	private static $new_user_fallbacks = array(
		self::SELECT_EXISTING_USER,
		self::DO_NOTHING,
	);

	/**
	 * The fallback value.
	 *
	 * @var string|null
	 */
	private $value;

	/**
	 * Constructor.
	 *
	 * @param string|null $value Fallback behavior or null.
	 * @throws \InvalidArgumentException If fallback is invalid.
	 */
	public function __construct( $value ) {
		$all_allowed = array_merge( self::$existing_user_fallbacks, self::$new_user_fallbacks );

		if ( null !== $value && ! in_array( $value, $all_allowed, true ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					'User selector fallback must be one of: %s. Given: %s',
					implode( ', ', array_unique( $all_allowed ) ),
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
	 * Check if fallback creates new user.
	 *
	 * @return bool
	 */
	public function creates_new_user(): bool {
		return self::CREATE_NEW_USER === $this->value;
	}

	/**
	 * Check if fallback selects existing user.
	 *
	 * @return bool
	 */
	public function selects_existing_user(): bool {
		return self::SELECT_EXISTING_USER === $this->value;
	}

	/**
	 * Check if fallback does nothing.
	 *
	 * @return bool
	 */
	public function does_nothing(): bool {
		return self::DO_NOTHING === $this->value;
	}

	/**
	 * Get allowed fallbacks for existing user source.
	 *
	 * @return array
	 */
	public static function get_existing_user_fallbacks(): array {
		return self::$existing_user_fallbacks;
	}

	/**
	 * Get allowed fallbacks for new user source.
	 *
	 * @return array
	 */
	public static function get_new_user_fallbacks(): array {
		return self::$new_user_fallbacks;
	}
}
