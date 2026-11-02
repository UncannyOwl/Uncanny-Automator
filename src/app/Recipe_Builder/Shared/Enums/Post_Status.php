<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Shared\Enums;

/**
 * Post Status Enum.
 *
 * Shared post status constants for store classes that don't have
 * domain-specific status enums.
 *
 * PHP 7.4 compatible class-based enum.
 * Upgrade to PHP 8.1 enum in the future.
 */
class Post_Status {

	/**
	 * Draft status - entity is not active.
	 *
	 * @var string
	 */
	const DRAFT = 'draft';

	/**
	 * Publish status - entity is active.
	 *
	 * @var string
	 */
	const PUBLISH = 'publish';

	/**
	 * Validate status value.
	 *
	 * @param string $value Status value to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public static function is_valid( string $value ): bool {
		return in_array( $value, self::get_all(), true );
	}

	/**
	 * Get all valid status values.
	 *
	 * @return array<string> Array of valid status values.
	 */
	public static function get_all(): array {
		return array( self::DRAFT, self::PUBLISH );
	}
}
