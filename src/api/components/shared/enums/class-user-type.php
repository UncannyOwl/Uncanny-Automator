<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Shared\Enums;

/**
 * User Type Enum.
 *
 * Cross-cutting concern representing user authentication context.
 * Shared across Recipe, Action, and Trigger aggregates.
 *
 * PHP 7.4 compatible class-based enum.
 * Upgrade to PHP 8.1 enum in the future.
 *
 * @since 7.0.0
 */
class User_Type {

	/**
	 * User type - logged-in users.
	 *
	 * @var string
	 */
	const USER = 'user';

	/**
	 * Anonymous type - non-logged-in users.
	 *
	 * @var string
	 */
	const ANONYMOUS = 'anonymous';

	/**
	 * Validate user type value.
	 *
	 * @param string $value User type value to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public static function is_valid( string $value ): bool {
		return in_array( $value, self::get_all(), true );
	}

	/**
	 * Get all valid user type values.
	 *
	 * @return array<string> Array of valid user type values.
	 */
	public static function get_all(): array {
		return array( self::USER, self::ANONYMOUS );
	}
}
