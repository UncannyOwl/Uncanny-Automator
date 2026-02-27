<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Loop\Filter\Value_Objects;

use Uncanny_Automator\Api\Components\Shared\Enums\User_Type;

/**
 * User Type Value Object.
 *
 * Represents the user authentication context for a filter.
 * Wraps the User_Type enum with domain-specific behavior.
 *
 * @since 7.0.0
 */
class User_Type_Value {

	/**
	 * The user type value.
	 *
	 * @var string
	 */
	private string $value;

	/**
	 * Constructor.
	 *
	 * @param string $value User type ('user' or 'anonymous').
	 * @throws \InvalidArgumentException If value is invalid.
	 */
	public function __construct( string $value ) {
		if ( ! User_Type::is_valid( $value ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					'Invalid user type: %s. Must be "user" or "anonymous".',
					$value
				)
			);
		}

		$this->value = $value;
	}

	/**
	 * Create a user type for logged-in users.
	 *
	 * @return self
	 */
	public static function user(): self {
		return new self( User_Type::USER );
	}

	/**
	 * Create a user type for anonymous visitors.
	 *
	 * @return self
	 */
	public static function anonymous(): self {
		return new self( User_Type::ANONYMOUS );
	}

	/**
	 * Create from iteration types.
	 *
	 * Maps iteration types to user authentication context:
	 * - User iteration: requires logged-in users
	 * - Post/Token iteration: works for any visitor
	 *
	 * @param array $iteration_types Iteration types (e.g., ['users'], ['posts'], ['token']).
	 * @return self
	 */
	public static function from_iteration_types( array $iteration_types ): self {
		return in_array( 'users', $iteration_types, true )
			? self::user()
			: self::anonymous();
	}

	/**
	 * Get the raw value.
	 *
	 * @return string
	 */
	public function get_value(): string {
		return $this->value;
	}

	/**
	 * Check if this requires logged-in users.
	 *
	 * @return bool
	 */
	public function requires_logged_in_user(): bool {
		return User_Type::USER === $this->value;
	}

	/**
	 * Check if this allows anonymous visitors.
	 *
	 * @return bool
	 */
	public function allows_anonymous(): bool {
		return User_Type::ANONYMOUS === $this->value;
	}

	/**
	 * Equality check.
	 *
	 * @param User_Type_Value $other Other user type to compare.
	 * @return bool
	 */
	public function equals( User_Type_Value $other ): bool {
		return $this->value === $other->value;
	}

	/**
	 * String representation.
	 *
	 * @return string
	 */
	public function __toString(): string {
		return $this->value;
	}
}
