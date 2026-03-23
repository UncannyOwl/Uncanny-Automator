<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Integration\Enums;

/**
 * Distribution Type Enum.
 *
 * Represents the distribution type of a plugin integration.
 *
 * @since 7.0
 */
class Distribution_Type {

	/**
	 * WordPress.org plugin directory.
	 *
	 * @var string
	 */
	const WP_ORG = 'wp_org';

	/**
	 * WordPress.org slug (hyphenated).
	 *
	 * @var string
	 */
	const SLUG_WP_ORG = 'wp-org';

	/**
	 * Open source (non-WP.org).
	 *
	 * @var string
	 */
	const OPEN_SOURCE = 'open_source';

	/**
	 * Open source slug (hyphenated).
	 *
	 * @var string
	 */
	const SLUG_OPEN_SOURCE = 'open-source';

	/**
	 * Commercial plugin.
	 *
	 * @var string
	 */
	const COMMERCIAL = 'commercial';

	/**
	 * Commercial slug.
	 *
	 * @var string
	 */
	const SLUG_COMMERCIAL = 'commercial';

	/**
	 * Validate distribution type value.
	 *
	 * @param string $value Distribution type value to validate.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public static function is_valid( string $value ): bool {
		return in_array( $value, self::get_all(), true );
	}

	/**
	 * Get all valid distribution type values.
	 *
	 * @return array<string> Array of valid distribution type values.
	 */
	public static function get_all(): array {
		return array(
			self::WP_ORG,
			self::OPEN_SOURCE,
			self::COMMERCIAL,
		);
	}

	/**
	 * Get slug for a distribution type.
	 *
	 * @param string $type Distribution type constant.
	 *
	 * @return string Slug (hyphenated version).
	 */
	public static function get_slug( string $type ): string {
		$map = array(
			self::WP_ORG      => self::SLUG_WP_ORG,
			self::OPEN_SOURCE => self::SLUG_OPEN_SOURCE,
			self::COMMERCIAL  => self::SLUG_COMMERCIAL,
		);

		return $map[ $type ] ?? self::SLUG_COMMERCIAL;
	}
}
