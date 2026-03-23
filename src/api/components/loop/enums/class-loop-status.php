<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Loop\Enums;

/**
 * Loop Status Enum.
 *
 * Represents the status of a loop (draft or published).
 * PHP 7.4 compatible class-based enum.
 * Upgrade to PHP 8.1 enum in the future.
 *
 * @since 7.0.0
 */
class Loop_Status {

	/**
	 * Draft status - loop is not active.
	 *
	 * @var string
	 */
	const DRAFT = 'draft';

	/**
	 * Publish status - loop is active and will execute.
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
		return in_array( $value, array( self::DRAFT, self::PUBLISH ), true );
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
