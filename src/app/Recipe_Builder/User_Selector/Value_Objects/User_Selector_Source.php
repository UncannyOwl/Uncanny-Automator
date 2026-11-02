<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\User_Selector\Value_Objects;

/**
 * User Selector Source Value Object.
 *
 * Immutable value object that validates and encapsulates user selector source type.
 * Enforces valid source enumeration: 'existing-user' or 'new-user' (legacy camelCase normalised on input).
 *
 * @since 7.0.0
 */
class User_Selector_Source {

	const EXISTING_USER = 'existing-user';
	const NEW_USER      = 'new-user';

	/**
	 * Allowed canonical source values.
	 *
	 * @var array
	 */
	private static $allowed_values = array(
		self::EXISTING_USER,
		self::NEW_USER,
	);

	/**
	 * Legacy camelCase → canonical kebab map (read-side compat shim).
	 *
	 * Stored `source` meta predates the kebab wire vocabulary; normalise on
	 * the way in so persisted recipes keep resolving without a migration.
	 *
	 * @var array
	 */
	private static $legacy_aliases = array(
		'existingUser' => self::EXISTING_USER,
		'newUser'      => self::NEW_USER,
	);

	/**
	 * The source value (canonical kebab).
	 *
	 * @var string
	 */
	private $value;

	/**
	 * Constructor.
	 *
	 * @param string $value User selector source (kebab canonical or legacy camelCase).
	 * @throws \InvalidArgumentException If source is invalid.
	 */
	public function __construct( $value ) {
		$normalised = self::normalize( $value );
		if ( ! in_array( $normalised, self::$allowed_values, true ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					'User selector source must be one of: %s. Given: %s',
					esc_html( implode( ', ', self::$allowed_values ) ),
					esc_html( $value )
				)
			);
		}
		$this->value = $normalised;
	}

	/**
	 * Map a legacy camelCase source onto the canonical kebab spelling.
	 *
	 * Does not validate — an unrecognised value is returned unchanged, so
	 * callers that must not throw (the wire mapper) can normalise without
	 * constructing the VO, while the constructor still rejects the result.
	 * Keeps the alias pair owned by exactly one class.
	 *
	 * Deliberately untyped: the constructor accepts anything and relies on the
	 * allowed-values check to reject it, so a `string` hint here would turn a
	 * documented InvalidArgumentException into a TypeError.
	 *
	 * @param mixed $value Kebab canonical or legacy camelCase source.
	 *
	 * @return mixed Canonical spelling where known, else the input unchanged.
	 */
	public static function normalize( $value ) {
		return self::$legacy_aliases[ $value ] ?? $value;
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
