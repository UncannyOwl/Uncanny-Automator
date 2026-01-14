<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Trigger\Value_Objects;

use Uncanny_Automator\Api\Components\Shared\Enums\User_Type;

/**
 * Trigger User Type Value Object.
 *
 * Immutable value object that validates and encapsulates trigger user type.
 * Uses shared User_Type enum from the Shared Kernel.
 *
 * @since 7.0.0
 */
class Trigger_User_Type {

	private string $value;

	/**
	 * Constructor.
	 *
	 * @param string $value Trigger user type (user or anonymous).
	 * @throws \InvalidArgumentException If type is invalid.
	 */
	public function __construct( string $value ) {
		if ( ! User_Type::is_valid( $value ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					'Trigger user type must be one of: %s. Given: %s',
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
	 * Check if trigger is for user (logged-in).
	 *
	 * @return bool
	 */
	public function is_user(): bool {
		return User_Type::USER === $this->value;
	}

	/**
	 * Check if trigger is for anonymous (logged-out).
	 *
	 * @return bool
	 */
	public function is_anonymous(): bool {
		return User_Type::ANONYMOUS === $this->value;
	}

	/**
	 * Check if types are compatible.
	 *
	 * @param Trigger_User_Type $other Other trigger user type.
	 * @return bool
	 */
	public function is_compatible_with( Trigger_User_Type $other ): bool {
		return $this->value === $other->get_value();
	}
}
