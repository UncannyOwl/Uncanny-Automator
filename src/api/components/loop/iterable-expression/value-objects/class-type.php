<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Loop\Iterable_Expression\Value_Objects;

use Uncanny_Automator\Api\Components\Loop\Iterable_Expression\Enums\Iteration_Type;

/**
 * Type Value Object.
 *
 * Represents the type of iteration source for a loop (users, posts, or token).
 * Validates against the Iteration_Type enum constants.
 *
 * @since 7.0.0
 */
class Type {

	/**
	 * The iteration type value.
	 *
	 * @var string
	 */
	private string $value;

	/**
	 * Constructor.
	 *
	 * @param string $value Iteration type value ('users', 'posts', or 'token').
	 * @throws \InvalidArgumentException If type is invalid.
	 */
	public function __construct( string $value ) {
		$this->validate( $value );
		$this->value = $value;
	}

	/**
	 * Get value.
	 *
	 * @return string Iteration type value.
	 */
	public function get_value(): string {
		return $this->value;
	}

	/**
	 * Check if this is a users iteration type.
	 *
	 * @return bool True if users type.
	 */
	public function is_users(): bool {
		return Iteration_Type::USERS === $this->value;
	}

	/**
	 * Check if this is a posts iteration type.
	 *
	 * @return bool True if posts type.
	 */
	public function is_posts(): bool {
		return Iteration_Type::POSTS === $this->value;
	}

	/**
	 * Check if this is a token iteration type.
	 *
	 * @return bool True if token type.
	 */
	public function is_token(): bool {
		return Iteration_Type::TOKEN === $this->value;
	}

	/**
	 * Validate iteration type value.
	 *
	 * @param string $value Value to validate.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate( string $value ): void {
		if ( ! Iteration_Type::is_valid( $value ) ) {
			throw new \InvalidArgumentException(
				'Type must be one of: ' . implode( ', ', Iteration_Type::get_all() ) . ', got: ' . $value
			);
		}
	}
}
