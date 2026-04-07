<?php
/**
 * String polyfills for PHP 7.4 compatibility.
 *
 * PHP 8.0 introduced str_contains, str_starts_with, and str_ends_with.
 * WordPress polyfills these from WP 5.9+, but Automator supports WP 5.8.
 *
 * This is a static utility class so it can be used anywhere without
 * instantiation, and later injected into services as a dependency.
 *
 * @package Uncanny_Automator
 * @since 7.1.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Components\Shared\Polyfill;

/**
 * Str — PHP 8.0 string function polyfills.
 *
 * @since 7.1.0
 */
final class Str {

	/**
	 * Check if a string contains a substring.
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle   The substring to search for.
	 *
	 * @return bool
	 */
	public static function contains( string $haystack, string $needle ): bool {
		return '' === $needle || false !== strpos( $haystack, $needle );
	}

	/**
	 * Check if a string starts with a prefix.
	 *
	 * @param string $haystack The string to search in.
	 * @param string $prefix   The prefix to check for.
	 *
	 * @return bool
	 */
	public static function starts_with( string $haystack, string $prefix ): bool {
		return '' === $prefix || 0 === strncmp( $haystack, $prefix, strlen( $prefix ) );
	}

	/**
	 * Check if a string ends with a suffix.
	 *
	 * @param string $haystack The string to search in.
	 * @param string $suffix   The suffix to check for.
	 *
	 * @return bool
	 */
	public static function ends_with( string $haystack, string $suffix ): bool {
		return '' === $suffix || substr( $haystack, -strlen( $suffix ) ) === $suffix;
	}
}
