<?php
/**
 * Check, without calling it, that the object cache API reaches $wp_object_cache.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Infrastructure;

/**
 * A command runs with a private object cache in place of $wp_object_cache.
 * That only keeps the site's cache out of reach if every object cache
 * function WordPress offers works through $wp_object_cache. WordPress's own
 * functions do. A drop-in that replaces them is read as source text, never
 * called. A function is verified when it has one of a few exact forms that
 * can reach nothing but $wp_object_cache:
 *
 * - global $wp_object_cache; [return] $wp_object_cache->method( arguments );
 * - return <literal>;
 * - global $wp_object_cache; $wp_object_cache->method( arguments ); return <literal>;
 * - for wp_cache_supports() only: a switch over its own parameter that
 *   returns literals.
 *
 * Arguments are the function's own parameters, literals or casts of them.
 * There is no assignment, no function call (so no hook) and no other object.
 *
 * Isolating reads is best effort. A function that reads or fills the cache
 * but is in no verified form leaves commands running, with a limitation to
 * report: a read may then refresh the site's cache, which WordPress rebuilds
 * from the database anyway. Drop-ins vary too much to refuse every site whose
 * code takes another shape; Object Cache Pro, which hosts such as GoDaddy
 * use, wraps every group name in trim(), and refusing it took site commands
 * away from every site on those hosts.
 *
 * Flushing is never best effort. A flush empties the cache for every visitor,
 * so a function that flushes must be verified, or reach nothing but
 * $wp_object_cache, call only functions that reach no cache, and fire no hook
 * except the flush filters the isolation answers first (FLUSH_FILTERS).
 * Otherwise commands are refused, and so they are when the cache API cannot
 * be read in full.
 */
final class Cache_Routing {

	/**
	 * Filters through which a drop-in asks whether to flush, as Object Cache
	 * Pro does. While a command runs the isolation answers no first, so a
	 * flush gated by them never happens.
	 */
	public const FLUSH_FILTERS = array( 'pre_objectcache_flush', 'pre_objectcache_flush_group' );

	/** Functions that empty the cache rather than read or fill it. */
	private const FLUSHING = array( 'wp_cache_flush', 'wp_cache_flush_group', 'wp_cache_flush_runtime', 'wp_cache_reset' );

	/**
	 * Functions a flushing function may call: none reaches a cache.
	 * apply_filters() is allowed only for FLUSH_FILTERS, and
	 * _deprecated_function() is what a drop-in's wp_cache_reset() calls.
	 */
	private const FLUSH_CALLS = array( '_deprecated_function', 'apply_filters', 'debug_backtrace', 'defined', 'function_exists', 'trim' );

	/**
	 * The object cache API by the WordPress version that added each part,
	 * from WordPress's own cache files. Every function the installed version
	 * should have must be found in its cache files.
	 */
	private const API_SINCE = array(
		'5.8' => array( 'wp_cache_add', 'wp_cache_add_global_groups', 'wp_cache_add_non_persistent_groups', 'wp_cache_close', 'wp_cache_decr', 'wp_cache_delete', 'wp_cache_flush', 'wp_cache_get', 'wp_cache_get_multiple', 'wp_cache_incr', 'wp_cache_init', 'wp_cache_replace', 'wp_cache_reset', 'wp_cache_set', 'wp_cache_switch_to_blog' ),
		'6.0' => array( 'wp_cache_add_multiple', 'wp_cache_delete_multiple', 'wp_cache_flush_runtime', 'wp_cache_set_multiple' ),
		'6.1' => array( 'wp_cache_flush_group', 'wp_cache_supports' ),
		'6.9' => array( 'wp_cache_get_salted', 'wp_cache_set_salted', 'wp_cache_get_multiple_salted', 'wp_cache_set_multiple_salted' ),
	);

	/**
	 * Finds a function's file and source.
	 *
	 * @var callable
	 */
	private $locate;

	/**
	 * WordPress's cache files.
	 *
	 * @var string[]
	 */
	private array $core_files;

	/**
	 * The installed WordPress version, or null to read it from version.php.
	 *
	 * @var string|null
	 */
	private ?string $version;

	/**
	 * Whether a function exists.
	 *
	 * @var callable
	 */
	private $exists;

	/**
	 * The problem and the limitation, once checked.
	 *
	 * @var array{problem: string|null, limitation: string|null}|null
	 */
	private ?array $checked = null;

	/**
	 * Read the installed WordPress, unless a test supplies another.
	 *
	 * @param callable|null $locate     function( string $name ): ?array{file: string, source: string}.
	 * @param string[]|null $core_files WordPress's cache.php and cache-compat.php.
	 * @param string|null   $version    WordPress version.
	 * @param callable|null $exists     Same signature as function_exists().
	 */
	public function __construct( ?callable $locate = null, ?array $core_files = null, ?string $version = null, ?callable $exists = null ) {
		$this->locate     = $locate ?? array( self::class, 'locate' );
		$this->core_files = $core_files ?? array( ABSPATH . WPINC . '/cache.php', ABSPATH . WPINC . '/cache-compat.php' );
		$this->version    = $version;
		$this->exists     = $exists ?? 'function_exists';
	}

	/**
	 * Why commands cannot run: the cache API cannot be read in full, or a
	 * function could flush the site's cache. Null when they can.
	 *
	 * @return string|null
	 */
	public function problem(): ?string {
		return $this->check()['problem'];
	}

	/**
	 * The functions that read or fill the cache in no verified form, so a
	 * read may refresh the site's cache; null when every one is verified.
	 *
	 * @return string|null
	 */
	public function limitation(): ?string {
		return $this->check()['limitation'];
	}

	/**
	 * Check the cache API once.
	 *
	 * @return array{problem: string|null, limitation: string|null}
	 */
	private function check(): array {
		if ( null === $this->checked ) {
			$this->checked = $this->evaluate();
		}

		return $this->checked;
	}

	/**
	 * Read every cache function: a problem refuses commands, a limitation
	 * only qualifies them.
	 *
	 * @return array{problem: string|null, limitation: string|null}
	 */
	private function evaluate(): array {
		$refuse   = static function ( string $problem ): array {
			return array(
				'problem'    => $problem,
				'limitation' => null,
			);
		};
		$version  = $this->version ?? self::installed_version();
		$expected = null === $version ? null : self::expected_api( $version );
		$api      = self::api_functions( $this->core_files );
		if ( null === $expected || null === $api || array() !== array_diff( $expected, $api ) ) {
			return $refuse( 'WordPress\'s object cache functions could not be read in full, so they cannot be checked.' );
		}

		$core       = array_filter( array_map( 'realpath', $this->core_files ) );
		$flushing   = array();
		$unverified = array();
		foreach ( $api as $name ) {
			// Called only while WordPress starts, or on a site switch without
			// wp_cache_switch_to_blog(), which is itself checked.
			if ( 'wp_cache_init' === $name ) {
				continue;
			}
			// wp_cache_reset() is deprecated since WordPress 3.5 and nothing in
			// WordPress calls it; drop-ins such as Redis Object Cache leave it
			// out. A function that does not exist cannot reach any cache. If
			// it does exist, it is checked like the rest.
			$exists = (bool) call_user_func( $this->exists, $name );
			if ( 'wp_cache_reset' === $name && ! $exists ) {
				continue;
			}
			if ( ! $exists ) {
				return $refuse( sprintf( '%s() is missing.', $name ) );
			}
			$found = call_user_func( $this->locate, $name );
			if ( ! is_array( $found ) || ! isset( $found['file'], $found['source'] ) || ! is_string( $found['file'] ) || ! is_string( $found['source'] ) ) {
				return $refuse( sprintf( '%s() could not be read.', $name ) );
			}
			if ( in_array( realpath( $found['file'] ), $core, true ) || self::accepted( $name, $found['source'] ) ) {
				continue;
			}
			$where = sprintf( '%s() in %s', $name, self::relative( $found['file'] ) );
			if ( ! in_array( $name, self::FLUSHING, true ) ) {
				$unverified[] = $where;
			} elseif ( ! self::flushes_only_the_cache( $name, $found['source'] ) ) {
				$flushing[] = $where;
			}
		}

		if ( array() !== $flushing ) {
			return $refuse( sprintf( '%s could flush the site\'s object cache, and site commands never flush it.', implode( ', ', $flushing ) ) );
		}

		$others = count( $unverified ) - 1;

		return array(
			'problem'    => null,
			'limitation' => array() === $unverified ? null : sprintf(
				'%s %s not in a form site commands can verify',
				$unverified[0] . ( $others > 0 ? sprintf( ' and %d other cache %s', $others, 1 === $others ? 'function' : 'functions' ) : '' ),
				$others > 0 ? 'are' : 'is'
			),
		);
	}

	/**
	 * The functions a WordPress version's cache files define, or null for a
	 * version older than any this check knows.
	 *
	 * @param string $version WordPress version, such as "6.0.2".
	 * @return string[]|null
	 */
	private static function expected_api( string $version ): ?array {
		if ( 1 !== preg_match( '/^\d+\.\d+/', $version ) || version_compare( $version, '5.8', '<' ) ) {
			return null;
		}
		$expected = array();
		foreach ( self::API_SINCE as $since => $functions ) {
			if ( version_compare( $version, $since, '>=' ) ) {
				$expected = array_merge( $expected, $functions );
			}
		}

		return $expected;
	}

	/**
	 * The installed WordPress version, read from version.php as WordPress
	 * core code, or null when it cannot be read.
	 *
	 * @return string|null
	 */
	private static function installed_version(): ?string {
		$file = ABSPATH . WPINC . '/version.php';
		if ( ! is_readable( $file ) ) {
			return null;
		}
		$wp_version = null;
		require $file;

		return is_string( $wp_version ) ? $wp_version : null;
	}

	/**
	 * A function's file and source text.
	 *
	 * @param string $name Function.
	 * @return array{file: string, source: string}|null
	 */
	public static function locate( string $name ): ?array {
		try {
			$function = new \ReflectionFunction( $name );
		} catch ( \ReflectionException $exception ) {
			return null;
		}
		$file  = $function->getFileName();
		$lines = is_string( $file ) && is_readable( $file ) ? file( $file ) : false;
		if ( ! is_array( $lines ) ) {
			return null;
		}

		return array(
			'file'   => $file,
			'source' => implode( '', array_slice( $lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1 ) ),
		);
	}

	/**
	 * Every wp_cache_* function WordPress's cache files define, or null when
	 * a file cannot be read.
	 *
	 * @param string[] $files Files.
	 * @return string[]|null
	 */
	private static function api_functions( array $files ): ?array {
		$names = array();
		foreach ( $files as $file ) {
			$source = is_readable( $file ) ? file_get_contents( $file ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local core file, read as text.
			if ( ! is_string( $source ) || '' === $source ) {
				return null;
			}
			$tokens = self::code( $source, false );
			foreach ( $tokens as $index => $token ) {
				if ( T_FUNCTION === $token[0] && T_STRING === ( $tokens[ $index + 1 ][0] ?? null ) && 0 === strpos( $tokens[ $index + 1 ][1], 'wp_cache_' ) ) {
					$names[ $tokens[ $index + 1 ][1] ] = true;
				}
			}
		}

		return array_keys( $names );
	}

	/**
	 * Whether a function has one of the accepted forms.
	 *
	 * @param string $name   Function name.
	 * @param string $source Function source.
	 * @return bool
	 */
	private static function accepted( string $name, string $source ): bool {
		$parts = self::parts( $name, $source );
		if ( null === $parts ) {
			return false;
		}
		list( $parameters, $body ) = $parts;

		if ( 'wp_cache_supports' === $name && self::literal_switch( $body, $parameters ) ) {
			return true;
		}

		// return <literal>;
		if ( 3 === count( $body ) && 'return' === $body[0][1] && self::literal( $body[1] ) && ';' === $body[2][1] ) {
			return true;
		}

		// global $wp_object_cache; then [return] <call>; or <call>; return <literal>;
		if ( 'global' !== ( $body[0][1] ?? '' ) || '$wp_object_cache' !== ( $body[1][1] ?? '' ) || ';' !== ( $body[2][1] ?? '' ) ) {
			return false;
		}
		$rest      = array_slice( $body, 3 );
		$returning = 'return' === ( $rest[0][1] ?? '' );
		if ( $returning ) {
			$rest = array_slice( $rest, 1 );
		}
		$end = self::call_end( $rest, $parameters );
		if ( null === $end || ';' !== ( $rest[ $end + 1 ][1] ?? '' ) ) {
			return false;
		}
		$after = array_slice( $rest, $end + 2 );

		return array() === $after
			|| ( ! $returning && 3 === count( $after ) && 'return' === $after[0][1] && self::literal( $after[1] ) && ';' === $after[2][1] );
	}

	/**
	 * Whether a function that flushes can reach nothing but $wp_object_cache.
	 * It may hold its own values and the globals it declares, call
	 * $wp_object_cache's methods and FLUSH_CALLS, and fire FLUSH_FILTERS.
	 * Anything that could reach another object or run other code is refused:
	 * another object's methods, a call through a call's result, static calls,
	 * new objects, closures, variable functions, $GLOBALS, interpolated
	 * strings, includes and eval, and replacing $wp_object_cache.
	 *
	 * @param string $name   Function name.
	 * @param string $source Function source.
	 * @return bool
	 */
	private static function flushes_only_the_cache( string $name, string $source ): bool {
		$parts = self::parts( $name, $source );
		if ( null === $parts ) {
			return false;
		}
		$body    = $parts[1];
		$refused = array( T_NEW, T_DOUBLE_COLON, T_EVAL, T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE, T_FUNCTION, T_FN, T_STATIC, T_CLASS, T_UNSET, T_ECHO, T_PRINT, T_EXIT, T_GOTO, T_YIELD, T_YIELD_FROM, T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES, T_INLINE_HTML, T_HALT_COMPILER );
		foreach ( array( 'T_NAME_QUALIFIED', 'T_NAME_RELATIVE' ) as $constant ) {
			if ( defined( $constant ) ) {
				$refused[] = constant( $constant );
			}
		}
		$arrows = defined( 'T_NULLSAFE_OBJECT_OPERATOR' ) ? array( T_OBJECT_OPERATOR, constant( 'T_NULLSAFE_OBJECT_OPERATOR' ) ) : array( T_OBJECT_OPERATOR );

		for ( $index = 0, $count = count( $body ); $index < $count; $index++ ) {
			list( $type, $text ) = $body[ $index ];
			$previous            = $body[ $index - 1 ] ?? array( null, '' );
			$next                = $body[ $index + 1 ][1] ?? '';
			if ( in_array( $type, $refused, true ) || in_array( $text, array( '`', '$', '"' ), true ) ) {
				return false;
			}
			if ( in_array( $type, $arrows, true ) ) {
				// Only $wp_object_cache's own members: skip the member's name.
				if ( '$wp_object_cache' !== $previous[1] ) {
					return false;
				}
				++$index;
				continue;
			}
			if ( T_VARIABLE === $type ) {
				// No variable function, no other global's store, and $wp_object_cache only used, never replaced.
				if ( '(' === $next || in_array( $text, array( '$GLOBALS', '$this' ), true ) || ( '$wp_object_cache' === $text && ! in_array( $next, array( '->', '?->', ',', ';' ), true ) ) ) {
					return false;
				}
				continue;
			}
			if ( '(' === $text && ( in_array( $previous[1], array( ')', ']' ), true ) || T_CONSTANT_ENCAPSED_STRING === $previous[0] ) ) {
				// A call through a value: the result of a call, an array element or a string.
				return false;
			}
			if ( '(' === $next && ! self::flush_call( $body, $index ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether the name at a position, followed by "(", calls one of
	 * FLUSH_CALLS: a global function, with apply_filters() only for
	 * FLUSH_FILTERS. Language constructs such as isset() and array() are not
	 * names and need no check.
	 *
	 * @param array $body  Tokens.
	 * @param int   $index Position of the name.
	 * @return bool
	 */
	private static function flush_call( array $body, int $index ): bool {
		list( $type, $text ) = $body[ $index ];
		$qualified           = defined( 'T_NAME_FULLY_QUALIFIED' ) ? constant( 'T_NAME_FULLY_QUALIFIED' ) : null;
		if ( T_STRING !== $type && $qualified !== $type ) {
			return ! in_array( $type, array( T_STRING, T_NS_SEPARATOR ), true );
		}
		// Before PHP 8, "\trim" is a separator and a name, and "Vendor\trim" has a name before the separator.
		if ( T_NS_SEPARATOR === ( $body[ $index - 1 ][0] ?? null ) && in_array( $body[ $index - 2 ][0] ?? null, array( T_STRING, T_NAMESPACE ), true ) ) {
			return false;
		}
		$function = strtolower( ltrim( $text, '\\' ) );
		if ( ! in_array( $function, self::FLUSH_CALLS, true ) ) {
			return false;
		}
		$hook = $body[ $index + 2 ] ?? array( null, '' );

		return 'apply_filters' !== $function
			|| ( T_CONSTANT_ENCAPSED_STRING === $hook[0] && in_array( substr( $hook[1], 1, -1 ), self::FLUSH_FILTERS, true ) );
	}

	/**
	 * A function's parameter names and body tokens, when its source is one
	 * function of that name whose signature holds no code.
	 *
	 * @param string $name   Function name.
	 * @param string $source Function source.
	 * @return array{0: string[], 1: array}|null
	 */
	private static function parts( string $name, string $source ): ?array {
		$code = self::code( $source, true );
		if ( count( $code ) < 5 || T_FUNCTION !== $code[0][0] || strtolower( $code[1][1] ) !== $name || '(' !== $code[2][1] ) {
			return null;
		}

		// The signature: parameter names, and no code in default values.
		$close = self::matching( $code, 2 );
		if ( null === $close ) {
			return null;
		}
		$parameters = array();
		foreach ( array_slice( $code, 3, $close - 3 ) as $token ) {
			if ( T_VARIABLE === $token[0] ) {
				$parameters[] = $token[1];
			} elseif ( ! in_array( $token[1], array( ',', '=', '&', '...', '?', '|' ), true ) && ! self::literal( $token ) && T_STRING !== $token[0] && T_ARRAY !== $token[0] ) {
				return null;
			}
		}
		$open = $close + 1;
		while ( isset( $code[ $open ] ) && '{' !== $code[ $open ][1] ) {
			// A return type, such as ": bool".
			if ( ! in_array( $code[ $open ][1], array( ':', '?', '|' ), true ) && T_STRING !== $code[ $open ][0] && T_ARRAY !== $code[ $open ][0] ) {
				return null;
			}
			++$open;
		}
		if ( ! isset( $code[ $open ] ) || self::matching( $code, $open ) !== count( $code ) - 1 ) {
			return null;
		}

		return array( $parameters, array_slice( $code, $open + 1, -1 ) );
	}

	/**
	 * Where "$wp_object_cache->method( arguments )" ends at the start of the
	 * tokens, or null when they do not start with one.
	 *
	 * @param array    $tokens     Tokens.
	 * @param string[] $parameters The function's parameter names.
	 * @return int|null The position of its closing parenthesis.
	 */
	private static function call_end( array $tokens, array $parameters ): ?int {
		if ( '$wp_object_cache' !== ( $tokens[0][1] ?? '' ) || T_OBJECT_OPERATOR !== ( $tokens[1][0] ?? null ) || T_STRING !== ( $tokens[2][0] ?? null ) || '(' !== ( $tokens[3][1] ?? '' ) ) {
			return null;
		}
		$expect_argument = true;
		for ( $position = 4, $count = count( $tokens ); $position < $count; $position++ ) {
			$token = $tokens[ $position ];
			if ( ')' === $token[1] ) {
				return $position;
			}
			if ( $expect_argument ) {
				if ( in_array( $token[0], array( T_INT_CAST, T_STRING_CAST, T_BOOL_CAST, T_ARRAY_CAST ), true ) ) {
					continue;
				}
				if ( ! in_array( $token[1], $parameters, true ) && ! self::literal( $token ) ) {
					return null;
				}
				$expect_argument = false;
			} elseif ( ',' === $token[1] ) {
				$expect_argument = true;
			} else {
				return null;
			}
		}

		return null;
	}

	/**
	 * Whether a body is a switch over a parameter whose cases return literals.
	 *
	 * @param array    $body       Tokens.
	 * @param string[] $parameters The function's parameter names.
	 * @return bool
	 */
	private static function literal_switch( array $body, array $parameters ): bool {
		if ( 'switch' !== ( $body[0][1] ?? '' ) || '(' !== ( $body[1][1] ?? '' ) || ! in_array( $body[2][1] ?? '', $parameters, true ) || ')' !== ( $body[3][1] ?? '' ) || '{' !== ( $body[4][1] ?? '' ) || self::matching( $body, 4 ) !== count( $body ) - 1 ) {
			return false;
		}
		foreach ( array_slice( $body, 5, -1 ) as $token ) {
			if ( ! in_array( $token[1], array( 'case', 'default', 'return', ':', ';' ), true ) && ! self::literal( $token ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether a token is a literal: a quoted string without variables, a
	 * number, true, false or null.
	 *
	 * @param array $token Token.
	 * @return bool
	 */
	private static function literal( array $token ): bool {
		return in_array( $token[0], array( T_CONSTANT_ENCAPSED_STRING, T_LNUMBER, T_DNUMBER ), true )
			|| ( T_STRING === $token[0] && in_array( strtolower( $token[1] ), array( 'true', 'false', 'null' ), true ) );
	}

	/**
	 * The position of the bracket that closes the one at a position.
	 *
	 * @param array $tokens   Tokens.
	 * @param int   $position Opening bracket.
	 * @return int|null
	 */
	private static function matching( array $tokens, int $position ): ?int {
		$pairs = array(
			'(' => ')',
			'{' => '}',
		);
		$open  = $tokens[ $position ][1] ?? '';
		if ( ! isset( $pairs[ $open ] ) ) {
			return null;
		}
		$depth = 0;
		for ( $index = $position, $count = count( $tokens ); $index < $count; $index++ ) {
			$text = $tokens[ $index ][1];
			if ( $open === $text || ( '{' === $open && in_array( $tokens[ $index ][0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) ) {
				++$depth;
			} elseif ( $pairs[ $open ] === $text && 0 === --$depth ) {
				return $index;
			}
		}

		return null;
	}

	/**
	 * Code tokens as array( type, text ), without whitespace or comments;
	 * single characters have a null type.
	 *
	 * @param string $source  Source.
	 * @param bool   $snippet Whether the source lacks its opening tag.
	 * @return array<int, array{0: int|null, 1: string}>
	 */
	private static function code( string $source, bool $snippet ): array {
		$code = array();
		foreach ( token_get_all( ( $snippet ? '<?php ' : '' ) . $source ) as $token ) {
			$token = is_array( $token ) ? array( $token[0], $token[1] ) : array( null, $token );
			if ( ! in_array( $token[0], array( T_OPEN_TAG, T_CLOSE_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				$code[] = $token;
			}
		}

		return $code;
	}

	/**
	 * A path relative to the WordPress folder, for messages.
	 *
	 * @param string $file Path.
	 * @return string
	 */
	private static function relative( string $file ): string {
		return 0 === strpos( $file, ABSPATH ) ? substr( $file, strlen( ABSPATH ) ) : basename( $file );
	}
}
