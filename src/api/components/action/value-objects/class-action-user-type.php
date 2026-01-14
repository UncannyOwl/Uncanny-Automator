<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Action\Value_Objects;

use Uncanny_Automator\Api\Components\Shared\Enums\User_Type;

/**
 * Action User Type Value Object.
 *
 * Represents action user type - must be User_Type::USER or User_Type::ANONYMOUS.
 * Uses shared User_Type enum from the Shared Kernel.
 *
 * @since 7.0.0
 */
class Action_User_Type {

	private string $value;


	/**
	 * Constructor.
	 *
	 * @param string $value Action user type value.
	 * @throws \InvalidArgumentException If invalid type.
	 */
	public function __construct( string $value ) {
		$this->validate( $value );
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
	 * Check if action is for user recipes.
	 *
	 * @return bool
	 */
	public function is_user(): bool {
		return User_Type::USER === $this->value;
	}

	/**
	 * Check if action is for anonymous recipes.
	 *
	 * @return bool
	 */
	public function is_anonymous(): bool {
		return User_Type::ANONYMOUS === $this->value;
	}

	/**
	 * Check if types are compatible.
	 *
	 * @param Action_User_Type $other Other action user type.
	 * @return bool
	 */
	public function is_compatible_with( Action_User_Type $other ): bool {
		return $this->value === $other->get_value();
	}

	/**
	 * Validate action user type.
	 *
	 * @param string $value Value to validate.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate( string $value ): void {
		if ( ! User_Type::is_valid( $value ) ) {
			throw new \InvalidArgumentException(
				'Action user type must be "' . User_Type::USER . '" or "' . User_Type::ANONYMOUS . '", got: ' . $value
			);
		}
	}
}
