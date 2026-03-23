<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Loop\Iterable_Expression\Enums;

/**
 * Iteration Type Enum.
 *
 * Represents the type of iteration source for a loop.
 * PHP 7.4 compatible class-based enum.
 * Upgrade to PHP 8.1 enum in the future.
 *
 * @since 7.0.0
 */
class Iteration_Type {

	/**
	 * Users iteration type - loop iterates over WordPress users.
	 *
	 * @var string
	 */
	const USERS = 'users';

	/**
	 * Posts iteration type - loop iterates over WordPress posts.
	 *
	 * @var string
	 */
	const POSTS = 'posts';

	/**
	 * Token iteration type - loop iterates over a token value (e.g., order items).
	 *
	 * @var string
	 */
	const TOKEN = 'token';

	/**
	 * Validate iteration type value.
	 *
	 * @param string $value Iteration type value to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public static function is_valid( string $value ): bool {
		return in_array( $value, array( self::USERS, self::POSTS, self::TOKEN ), true );
	}

	/**
	 * Get all valid iteration type values.
	 *
	 * @return array<string> Array of valid iteration type values.
	 */
	public static function get_all(): array {
		return array( self::USERS, self::POSTS, self::TOKEN );
	}
}
