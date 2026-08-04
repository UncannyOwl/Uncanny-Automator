<?php
/**
 * MCP client page URL sanitizer.
 *
 * @since 7.5.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Application\Mcp;

/**
 * Builds a trusted admin URL from approved page-context fields.
 */
final class Client_Page_Url_Sanitizer {

	/**
	 * Query parameters that identify an admin page context.
	 *
	 * @var string[]
	 */
	private const CONTEXT_QUERY_ARGS = array(
		'action',
		'canvas_id',
		'entry_id',
		'id',
		'page',
		'path',
		'post',
		'post_type',
		'route',
		'tab',
		'tag_ID',
		'taxonomy',
		'user_id',
		'view',
	);

	/**
	 * Numeric page-context parameters.
	 *
	 * @var string[]
	 */
	private const NUMERIC_CONTEXT_ARGS = array(
		'canvas_id',
		'entry_id',
		'id',
		'post',
		'tag_ID',
		'user_id',
	);

	/**
	 * Slug page-context parameters.
	 *
	 * @var string[]
	 */
	private const SLUG_CONTEXT_ARGS = array(
		'page',
		'post_type',
		'taxonomy',
	);

	/**
	 * Closed values used by current MCP consumers.
	 *
	 * @var array<string,string[]>
	 */
	private const CONTEXT_ARG_VALUES = array(
		'action' => array( 'edit' ),
		'path'   => array( '/', '/analytics', '/customers' ),
		'route'  => array( 'editor', 'entries' ),
		'tab'    => array( 'premium-integrations' ),
		'view'   => array( 'details', 'entry', 'list' ),
	);

	/**
	 * Admin entry points used by MCP context and conversation starters.
	 *
	 * @var string[]
	 */
	private const CONTEXT_ENTRY_FILES = array(
		'admin.php',
		'edit.php',
		'edit-comments.php',
		'edit-tags.php',
		'index.php',
		'post.php',
		'post-new.php',
		'profile.php',
		'term.php',
		'upload.php',
		'user-edit.php',
		'users.php',
	);

	/**
	 * Build an absolute trusted URL from an admin page URL.
	 *
	 * Fragments and query parameters outside the context whitelist are removed.
	 *
	 * @param string $page_url  Candidate page URL.
	 * @param string $admin_url Trusted WordPress admin URL.
	 * @return string
	 */
	public static function sanitize( string $page_url, string $admin_url ): string {
		$parts       = wp_parse_url( $page_url );
		$admin_parts = wp_parse_url( $admin_url );

		if ( false === $parts || false === $admin_parts ) {
			return $admin_url;
		}

		$trusted_origin = self::trusted_origin( $admin_parts );
		$admin_path     = self::absolute_path( $admin_parts['path'] ?? '' );
		$path           = self::absolute_path( $parts['path'] ?? '' );
		$decoded_path   = self::decode_path( $path );

		if ( '' === $trusted_origin || ! self::is_admin_entry_path( $decoded_path, $admin_path ) ) {
			return $admin_url;
		}

		$query = isset( $parts['query'] ) ? self::filter_context_query( $parts['query'] ) : '';
		$url   = $trusted_origin . $path;

		return '' === $query ? $url : $url . '?' . $query;
	}

	/**
	 * Build the configured admin origin.
	 *
	 * @param array<string,mixed> $admin_parts Parsed admin URL.
	 * @return string
	 */
	private static function trusted_origin( array $admin_parts ): string {
		$scheme = strtolower( $admin_parts['scheme'] ?? '' );
		$host   = $admin_parts['host'] ?? '';

		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || '' === $host ) {
			return '';
		}

		$origin = $scheme . '://' . $host;

		return isset( $admin_parts['port'] ) ? $origin . ':' . (int) $admin_parts['port'] : $origin;
	}

	/**
	 * Normalize a URL path to one leading slash.
	 *
	 * @param string $path URL path.
	 * @return string
	 */
	private static function absolute_path( string $path ): string {
		return '/' . ltrim( $path, '/' );
	}

	/**
	 * Decode path separators and encoded traversal segments.
	 *
	 * @param string $path URL path.
	 * @return string
	 */
	private static function decode_path( string $path ): string {
		$decoded = $path;

		for ( $pass = 0; $pass < 3; $pass++ ) {
			$next = rawurldecode( $decoded );

			if ( $next === $decoded ) {
				break;
			}

			$decoded = $next;
		}

		return str_replace( '\\', '/', $decoded );
	}

	/**
	 * Test whether MCP uses the supplied WordPress admin entry point.
	 *
	 * @param string $path       Decoded candidate path.
	 * @param string $admin_path Configured admin directory path.
	 * @return bool
	 */
	private static function is_admin_entry_path( string $path, string $admin_path ): bool {
		$admin_directory = trailingslashit( $admin_path );

		if ( rtrim( $path, '/' ) === rtrim( $admin_path, '/' ) ) {
			return true;
		}

		if ( 0 !== strpos( trailingslashit( $path ), $admin_directory ) ) {
			return false;
		}

		$relative_path = ltrim( substr( $path, strlen( $admin_directory ) ), '/' );

		if ( '' === $relative_path ) {
			return true;
		}

		if ( 0 === strpos( $relative_path, 'network/' ) ) {
			$relative_path = substr( $relative_path, strlen( 'network/' ) );
		}

		return in_array( strtolower( $relative_path ), self::CONTEXT_ENTRY_FILES, true );
	}

	/**
	 * Retain raw query parameters that identify the current admin context.
	 *
	 * @param string $query Raw query string.
	 * @return string
	 */
	private static function filter_context_query( string $query ): string {
		$retained = array();

		foreach ( explode( '&', $query ) as $parameter ) {
			$parts = explode( '=', $parameter, 2 );
			$key   = rawurldecode( $parts[0] );

			if ( ! in_array( $key, self::CONTEXT_QUERY_ARGS, true ) || ! isset( $parts[1] ) ) {
				continue;
			}

			$value = self::normalize_context_value( $key, urldecode( $parts[1] ) );

			if ( null !== $value ) {
				$retained[] = $key . '=' . rawurlencode( $value );
			}
		}

		return implode( '&', $retained );
	}

	/**
	 * Validate and normalize one whitelisted page-context value.
	 *
	 * @param string $key   Query parameter name.
	 * @param string $value Decoded query parameter value.
	 * @return string|null
	 */
	private static function normalize_context_value( string $key, string $value ): ?string {
		if ( in_array( $key, self::NUMERIC_CONTEXT_ARGS, true ) ) {
			return '' !== $value && ctype_digit( $value ) ? $value : null;
		}

		if ( in_array( $key, self::SLUG_CONTEXT_ARGS, true ) ) {
			return 1 === preg_match( '/^[a-z0-9_-]+$/i', $value ) ? strtolower( $value ) : null;
		}

		$allowed_values = self::CONTEXT_ARG_VALUES[ $key ] ?? array();

		return in_array( $value, $allowed_values, true ) ? $value : null;
	}
}
