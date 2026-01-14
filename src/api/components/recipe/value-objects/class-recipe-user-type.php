<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Recipe\Value_Objects;

use Uncanny_Automator\Api\Components\Shared\Enums\User_Type;

/**
 * Recipe User Type Value Object.
 *
 * Immutable value object that validates and encapsulates recipe user type.
 * Determines recipe execution context and user authentication requirements.
 * Uses shared User_Type enum from the Shared Kernel.
 *
 * @since 7.0.0
 */
class Recipe_User_Type {

	private string $value;

	/**
	 * Constructor.
	 *
	 * @param string $value Recipe user type (user or anonymous).
	 * @throws \InvalidArgumentException If type is invalid.
	 */
	public function __construct( string $value ) {
		if ( ! User_Type::is_valid( $value ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					'Recipe user type must be one of: %s. Given: %s',
					implode( ', ', User_Type::get_all() ),
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
	 * Check if recipe is for user (logged-in).
	 *
	 * @return bool
	 */
	public function is_user(): bool {
		return User_Type::USER === $this->value;
	}

	/**
	 * Check if recipe is for anonymous (logged-out).
	 *
	 * @return bool
	 */
	public function is_anonymous(): bool {
		return User_Type::ANONYMOUS === $this->value;
	}
}
