<?php
/**
 * Functions and classes a plugin would declare that already exist.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Infrastructure\Manage;

/**
 * Activating a plugin loads its main file in this request. When it declares
 * a function or class that already exists, as a second copy of an active
 * plugin or the free and paid editions of one plugin side by side do, PHP
 * stops the request with a fatal error no code can catch. This reads, without
 * running anything, the declarations PHP would make unconditionally: those
 * that start a statement at the top level of the main file, and of the
 * plugin's own files that it includes there by a fixed path in a statement of
 * its own, such as require __DIR__ . '/vendor/autoload.php';.
 *
 * Only an unconditional declaration refuses an activation. Skipped are
 * declarations in any block, such as if ( ! function_exists( 'x' ) ) { ... }
 * or if ( ... ) : ... endif;, includes that are part of an expression, such
 * as class_exists( 'Y' ) || require 'y.php'; or defined( 'X' ) || include
 * 'x.php';, or follow a condition, and classes and includes after a return
 * outside any function.
 *
 * It is a guard, not a proof, and it fails open: code that includes files by
 * computed paths, or declares through eval, is not followed, and when the
 * reader loses track of a file's structure, or too little time or memory is
 * left to read the next file, it stops and reports why, without refusing
 * anything. A missed duplicate is acceptable; a wrong refusal is not.
 */
final class Plugin_Declarations {

	/** Most files read for one plugin. */
	private const MAX_FILES = 25;

	/** Largest file read. */
	private const MAX_BYTES = 1048576;

	/** How many includes deep files are followed from the main file. */
	private const MAX_DEPTH = 3;

	/** Functions that may build an include path from __FILE__ or __DIR__. */
	private const PATH_FUNCTIONS = array( 'dirname', 'plugin_dir_path', 'trailingslashit', 'untrailingslashit' );

	/** Least time worth starting to read one more file with. */
	private const MIN_SECONDS = 0.5;

	/**
	 * Memory reading a file may need, per byte of it: PHP's tokens take about
	 * 30 times a file's size for ordinary code and up to 240 times for a file
	 * of two-character tokens.
	 */
	private const MEMORY_PER_BYTE = 256;

	/** Memory always left free below PHP's limit. */
	private const MEMORY_HEADROOM = 16777216;

	/**
	 * The declarations of a plugin that already exist, each with where.
	 *
	 * @param string $file Plugin file, relative to the plugins folder.
	 * @return string[] Such as "the function foo(), already declared by the plugin bar".
	 */
	public static function conflicts( string $file ): array {
		return self::check( $file )[0];
	}

	/**
	 * The declarations of a plugin that already exist, and why the check
	 * stopped before it read every file it follows, if it did. Before each
	 * file it makes sure the time and memory to read it are left.
	 *
	 * @param string             $file         Plugin file, relative to the plugins folder.
	 * @param callable|null      $seconds_left Returns the seconds the check may still take; null for no limit.
	 * @param callable|null      $memory_left  Returns the bytes PHP may still allocate; null to ask PHP.
	 * @return array{0: string[], 1: string|null} Conflicts found, and why the check stopped early, as a clause.
	 */
	public static function check( string $file, ?callable $seconds_left = null, ?callable $memory_left = null ): array {
		$main = realpath( WP_PLUGIN_DIR . '/' . $file );
		if ( false === $main ) {
			return array( array(), null );
		}
		// A single-file plugin owns only its file.
		$root        = '.' === dirname( $file ) ? $main : (string) realpath( WP_PLUGIN_DIR . '/' . dirname( $file ) );
		$memory_left = $memory_left ?? array( self::class, 'memory_left' );
		$queue       = array( array( $main, 0 ) );
		$read        = array();
		$conflicts   = array();
		$stopped     = null;
		$files       = 0;
		while ( array() !== $queue && $files < self::MAX_FILES ) {
			list( $path, $depth ) = array_shift( $queue );
			if ( isset( $read[ $path ] ) || in_array( $path, get_included_files(), true ) ) {
				// Already loaded in this request: include_once would not load it again.
				continue;
			}
			$read[ $path ] = true;
			++$files;
			$size = is_file( $path ) && is_readable( $path ) ? filesize( $path ) : false;
			if ( false === $size || $size > self::MAX_BYTES ) {
				continue;
			}
			if ( null !== $seconds_left && (float) call_user_func( $seconds_left ) < self::MIN_SECONDS ) {
				$stopped = 'too little time was left to read all of its files';
				break;
			}
			if ( $size * self::MEMORY_PER_BYTE > (int) call_user_func( $memory_left ) ) {
				$stopped = sprintf( 'reading %s would need more memory than PHP\'s memory limit leaves', self::named( $path ) );
				break;
			}
			$code = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local plugin file, read as text, never run.
			if ( ! is_string( $code ) ) {
				continue;
			}
			$found = self::read( $code, $path );
			unset( $code );
			if ( null === $found ) {
				// Unsure where a declaration stands, the reader refuses nothing from this file.
				$stopped = sprintf( 'the structure of %s could not be followed', self::named( $path ) );
				break;
			}
			list( $declared, $includes ) = $found;
			unset( $found );
			foreach ( $declared as list( $kind, $name ) ) {
				$conflict = self::existing( $kind, $name );
				if ( null !== $conflict ) {
					$conflicts[] = $conflict;
				}
			}
			if ( $depth >= self::MAX_DEPTH ) {
				continue;
			}
			foreach ( $includes as $include ) {
				$real = realpath( $include );
				if ( false !== $real && ( $real === $root || 0 === strpos( $real, $root . DIRECTORY_SEPARATOR ) ) ) {
					$queue[] = array( $real, $depth + 1 );
				}
			}
		}

		return array( array_values( array_unique( $conflicts ) ), $stopped );
	}

	/**
	 * Bytes PHP may still allocate, keeping MEMORY_HEADROOM free.
	 *
	 * @return int
	 */
	private static function memory_left(): int {
		$limit = wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );

		return $limit <= 0 ? PHP_INT_MAX : max( 0, $limit - memory_get_usage() - self::MEMORY_HEADROOM );
	}

	/**
	 * A file as the plugins folder names it, for messages.
	 *
	 * @param string $path File.
	 * @return string
	 */
	private static function named( string $path ): string {
		$folder = trailingslashit( wp_normalize_path( (string) realpath( WP_PLUGIN_DIR ) ) );
		$path   = wp_normalize_path( $path );

		return 0 === strpos( $path, $folder ) ? 'plugins/' . substr( $path, strlen( $folder ) ) : basename( $path );
	}

	/**
	 * A declaration that already exists, described, or null.
	 *
	 * @param string $kind "function" or "class".
	 * @param string $name Fully qualified name.
	 * @return string|null
	 */
	private static function existing( string $kind, string $name ): ?string {
		if ( 'function' === $kind ) {
			if ( ! function_exists( $name ) ) {
				return null;
			}
			$where = ( new \ReflectionFunction( $name ) )->getFileName();

			return sprintf( 'the function %s(), already declared by %s', $name, self::owner( $where ) );
		}
		$exists = class_exists( $name, false ) || interface_exists( $name, false ) || trait_exists( $name, false ) || ( function_exists( 'enum_exists' ) && enum_exists( $name, false ) );
		if ( ! $exists ) {
			return null;
		}
		$where = ( new \ReflectionClass( $name ) )->getFileName();

		return sprintf( 'the class %s, already declared by %s', $name, self::owner( $where ) );
	}

	/**
	 * Who declared something, by the file it is in.
	 *
	 * @param mixed $path File, or false for PHP itself.
	 * @return string
	 */
	private static function owner( $path ): string {
		if ( ! is_string( $path ) || '' === $path ) {
			return 'PHP itself';
		}
		$path  = wp_normalize_path( $path );
		$roots = array(
			'the plugin %s'          => WP_PLUGIN_DIR,
			'the must-use plugin %s' => WPMU_PLUGIN_DIR,
			'the theme %s'           => get_theme_root(),
		);
		foreach ( $roots as $label => $root ) {
			foreach ( array_unique( array( (string) $root, (string) realpath( (string) $root ) ) ) as $form ) {
				$form = trailingslashit( wp_normalize_path( $form ) );
				if ( '/' !== $form && 0 === strpos( $path, $form ) ) {
					return sprintf( $label, strtok( substr( $path, strlen( $form ) ), '/' ) );
				}
			}
		}
		$wordpress = wp_normalize_path( ABSPATH );

		return 0 === strpos( $path, $wordpress . WPINC . '/' ) || 0 === strpos( $path, $wordpress . 'wp-admin/' ) ? 'WordPress' : 'other code on this site';
	}

	/**
	 * The unconditional declarations of one file, and the files it includes
	 * by a fixed path.
	 *
	 * Every token is looked at once, in order, and every brace and
	 * alternative-syntax block is counted: nothing is skipped over. When the
	 * count does not add up, so the reader cannot tell where it is, it gives
	 * up on the file.
	 *
	 * @param string $code PHP source.
	 * @param string $path Its file, for __FILE__ and __DIR__.
	 * @return array{0: array<int, array{0: string, 1: string}>, 1: string[]}|null Kind and name of each declaration, and included files; null when the structure could not be followed.
	 */
	private static function read( string $code, string $path ): ?array {
		$tokens    = array_values( array_filter( token_get_all( $code ), array( self::class, 'significant' ) ) );
		$count     = count( $tokens );
		$depth     = 0;
		$top       = 0;
		$namespace = '';
		$bodies    = array();
		$body_next = false;
		$returned  = false;
		$open      = 0;
		$declared  = array();
		$includes  = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$token = $tokens[ $i ];
			if ( '{' === $token || ( is_array( $token ) && in_array( $token[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) ) {
				++$depth;
				if ( $body_next && '{' === $token ) {
					// The body of a function or class: a return in it does not end the file.
					$bodies[]  = $depth;
					$body_next = false;
				}
				continue;
			}
			if ( '}' === $token ) {
				if ( 0 === $depth ) {
					return null;
				}
				if ( end( $bodies ) === $depth ) {
					array_pop( $bodies );
				}
				--$depth;
				if ( $depth < $top ) {
					// The end of a braced namespace.
					$top       = 0;
					$namespace = '';
				}
				continue;
			}
			if ( ';' === $token ) {
				$body_next = false;
				continue;
			}
			if ( ! is_array( $token ) ) {
				continue;
			}
			if ( self::is( $token, array( 'T_FUNCTION', 'T_CLASS', 'T_INTERFACE', 'T_TRAIT', 'T_ENUM' ) ) && ! self::is( $tokens[ $i - 1 ] ?? null, array( 'T_DOUBLE_COLON' ) ) && ':' !== ( $tokens[ $i + 1 ] ?? null ) ) {
				// Not X::class, nor a named argument such as f( function: ... ).
				$body_next = true;
			}
			if ( T_RETURN === $token[0] && array() === $bodies ) {
				// What follows may never run; only functions are declared before it does.
				$returned = true;
				continue;
			}
			if ( $depth !== $top ) {
				continue;
			}
			// if ( ... ) : ... endif; and its kin keep the depth but make what is inside conditional.
			if ( self::is( $token, array( 'T_ENDIF', 'T_ENDWHILE', 'T_ENDFOR', 'T_ENDFOREACH', 'T_ENDSWITCH', 'T_ENDDECLARE' ) ) ) {
				if ( 0 === $open ) {
					return null;
				}
				--$open;
				continue;
			}
			if ( self::is( $token, array( 'T_IF', 'T_WHILE', 'T_FOR', 'T_FOREACH', 'T_SWITCH', 'T_DECLARE' ) ) ) {
				$after = self::after_parentheses( $tokens, $i + 1 );
				if ( null === $after ) {
					// A condition that never closes: invalid code, and searching
					// for its end again at every later condition would take long.
					return null;
				}
				if ( ':' === ( $tokens[ $after ] ?? null ) ) {
					++$open;
					continue;
				}
			}
			if ( $open > 0 ) {
				continue;
			}
			$id   = $token[0];
			$next = $tokens[ $i + 1 ] ?? null;
			if ( T_NAMESPACE === $id && ( '{' === $next || self::is( $next, array( 'T_STRING', 'T_NAME_QUALIFIED' ) ) ) ) {
				list( $namespace, $i ) = self::name( $tokens, $i + 1 );
				if ( '{' === ( $tokens[ $i ] ?? null ) ) {
					++$depth;
					$top = $depth;
				}
				continue;
			}
			if ( T_FUNCTION === $id ) {
				// function &name(): PHP 8.1 and later give the & a token of its own.
				$by_reference = '&' === $next || self::is( $next, array( 'T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG', 'T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG' ) );
				$next         = $by_reference ? ( $tokens[ $i + 2 ] ?? null ) : $next;
				if ( self::is( $next, array( 'T_STRING' ) ) && self::starts_statement( $tokens, $i ) ) {
					$declared[] = array( 'function', self::qualify( $namespace, $next[1] ) );
				}
				continue;
			}
			if ( ! $returned && self::is( $token, array( 'T_CLASS', 'T_INTERFACE', 'T_TRAIT', 'T_ENUM' ) ) ) {
				if ( self::is( $next, array( 'T_STRING' ) ) && self::starts_statement( $tokens, $i ) ) {
					$declared[] = array( 'class', self::qualify( $namespace, $next[1] ) );
				}
				continue;
			}
			if ( ! $returned && in_array( $id, array( T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE ), true ) && self::starts_statement( $tokens, $i ) ) {
				// Only the path is read here; the main loop still looks at every token after it.
				$include = self::path( self::statement_rest( $tokens, $i + 1 ), $path );
				if ( null !== $include ) {
					$includes[] = 0 === strpos( $include, '/' ) || 1 === preg_match( '#^[A-Za-z]:[\\\\/]#', $include ) ? $include : dirname( $path ) . '/' . $include;
				}
			}
		}
		if ( 0 !== $depth || 0 !== $open || array() !== $bodies ) {
			return null;
		}

		return array( $declared, $includes );
	}

	/**
	 * The tokens from $i up to the end of the statement, for reading an
	 * include's path, or none when a brace or another statement comes first,
	 * so the path is never read past a structure.
	 *
	 * @param array<int, mixed> $tokens Significant tokens.
	 * @param int               $i      Where the expression starts.
	 * @return array<int, mixed>
	 */
	private static function statement_rest( array $tokens, int $i ): array {
		$count = count( $tokens );
		$rest  = array();
		for ( $j = $i; $j < $count; $j++ ) {
			$token = $tokens[ $j ];
			if ( ';' === $token || self::is( $token, array( 'T_CLOSE_TAG' ) ) ) {
				return $rest;
			}
			if ( in_array( $token, array( '{', '}', ':' ), true ) || ( is_array( $token ) && in_array( $token[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) ) {
				return array();
			}
			$rest[] = $token;
		}

		return array();
	}

	/**
	 * Whether the token at $i begins a statement of its own, after any class
	 * modifiers, attributes and the @ operator: so it runs whenever the code
	 * around it runs, not as part of an expression or after a condition.
	 *
	 * @param array<int, mixed> $tokens Significant tokens.
	 * @param int               $i      Where the declaration or include is.
	 * @return bool
	 */
	private static function starts_statement( array $tokens, int $i ): bool {
		$before = $i - 1;
		while ( $before >= 0 ) {
			$token = $tokens[ $before ];
			if ( '@' === $token || self::is( $token, array( 'T_ABSTRACT', 'T_FINAL', 'T_READONLY' ) ) ) {
				--$before;
				continue;
			}
			if ( ']' === $token ) {
				// An attribute group, #[ ... ], belongs to the declaration after it.
				$level = 0;
				for ( $j = $before; $j >= 0; $j-- ) {
					if ( ']' === $tokens[ $j ] ) {
						++$level;
					} elseif ( '[' === $tokens[ $j ] || self::is( $tokens[ $j ], array( 'T_ATTRIBUTE' ) ) ) {
						--$level;
					}
					if ( 0 === $level ) {
						break;
					}
				}
				if ( $j < 0 || ! self::is( $tokens[ $j ], array( 'T_ATTRIBUTE' ) ) ) {
					return false;
				}
				$before = $j - 1;
				continue;
			}
			break;
		}
		if ( $before < 0 ) {
			return true;
		}
		$token = $tokens[ $before ];

		return in_array( $token, array( ';', '{', '}' ), true ) || self::is( $token, array( 'T_CLOSE_TAG', 'T_INLINE_HTML' ) );
	}

	/**
	 * The index after the parenthesised condition that starts at $i, such
	 * as an if's, $i when there is none, or null when it never closes.
	 *
	 * @param array<int, mixed> $tokens Significant tokens.
	 * @param int               $i      Where the "(" should be.
	 * @return int|null
	 */
	private static function after_parentheses( array $tokens, int $i ): ?int {
		if ( '(' !== ( $tokens[ $i ] ?? null ) ) {
			return $i;
		}
		$count = count( $tokens );
		$level = 0;
		for ( $j = $i; $j < $count; $j++ ) {
			if ( '(' === $tokens[ $j ] ) {
				++$level;
			} elseif ( ')' === $tokens[ $j ] && 0 === --$level ) {
				return $j + 1;
			}
		}

		return null;
	}

	/**
	 * Whether a token counts: whitespace, comments and doc blocks do not.
	 *
	 * @param mixed $token A token.
	 * @return bool
	 */
	private static function significant( $token ): bool {
		return ! is_array( $token ) || ! in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG ), true );
	}

	/**
	 * Whether a token is one of these kinds, by name, so kinds that older
	 * PHP versions lack can be named.
	 *
	 * @param mixed    $token A token.
	 * @param string[] $names Token names, such as T_STRING.
	 * @return bool
	 */
	private static function is( $token, array $names ): bool {
		return is_array( $token ) && in_array( token_name( $token[0] ), $names, true );
	}

	/**
	 * A namespace name, from PHP 8's single token or older PHP's parts.
	 *
	 * @param array<int, mixed> $tokens Significant tokens.
	 * @param int               $i      Where the name starts.
	 * @return array{0: string, 1: int} The name, and the index after it.
	 */
	private static function name( array $tokens, int $i ): array {
		$name = '';
		while ( self::is( $tokens[ $i ] ?? null, array( 'T_STRING', 'T_NAME_QUALIFIED', 'T_NS_SEPARATOR' ) ) ) {
			$name .= $tokens[ $i ][1];
			++$i;
		}

		return array( trim( $name, '\\' ), $i );
	}

	/**
	 * A name in a namespace.
	 *
	 * @param string $namespace Namespace, or "".
	 * @param string $name      Name.
	 * @return string
	 */
	private static function qualify( string $namespace, string $name ): string {
		return '' === $namespace ? $name : $namespace . '\\' . $name;
	}

	/**
	 * The path an include builds, when it is built only from strings,
	 * __FILE__, __DIR__, constants this request already defines, and
	 * dirname(), plugin_dir_path() or trailingslashit() of those.
	 *
	 * @param array<int, mixed> $tokens The include's expression.
	 * @param string            $file   The including file.
	 * @return string|null
	 */
	private static function path( array $tokens, string $file ): ?string {
		$position = 0;
		$path     = self::concatenation( $tokens, $position, $file );

		return null !== $path && count( $tokens ) === $position && '' !== $path ? $path : null;
	}

	/**
	 * Terms joined by dots.
	 *
	 * @param array<int, mixed> $tokens   Tokens.
	 * @param int               $position Where to read, moved past what was read.
	 * @param string            $file     The including file.
	 * @return string|null
	 */
	private static function concatenation( array $tokens, int &$position, string $file ): ?string {
		$value = self::term( $tokens, $position, $file );
		while ( null !== $value && '.' === ( $tokens[ $position ] ?? null ) ) {
			++$position;
			$next  = self::term( $tokens, $position, $file );
			$value = null === $next ? null : $value . $next;
		}

		return $value;
	}

	/**
	 * One term: a string, a magic constant, a defined constant, a path
	 * function's result, or an expression in parentheses.
	 *
	 * @param array<int, mixed> $tokens   Tokens.
	 * @param int               $position Where to read, moved past what was read.
	 * @param string            $file     The including file.
	 * @return string|null
	 */
	private static function term( array $tokens, int &$position, string $file ): ?string {
		$token = $tokens[ $position ] ?? null;
		if ( '(' === $token ) {
			++$position;
			$value = self::concatenation( $tokens, $position, $file );
			if ( ')' !== ( $tokens[ $position ] ?? null ) ) {
				return null;
			}
			++$position;
			return $value;
		}
		if ( self::is( $token, array( 'T_NS_SEPARATOR' ) ) ) {
			// A global name written with its leading backslash.
			++$position;
			$token = $tokens[ $position ] ?? null;
		}
		if ( ! is_array( $token ) ) {
			return null;
		}
		++$position;
		switch ( token_name( $token[0] ) ) {
			case 'T_CONSTANT_ENCAPSED_STRING':
				return self::literal( $token[1] );
			case 'T_DIR':
				return dirname( $file );
			case 'T_FILE':
				return $file;
			case 'T_STRING':
			case 'T_NAME_FULLY_QUALIFIED':
				$name = ltrim( $token[1], '\\' );
				if ( '(' !== ( $tokens[ $position ] ?? null ) ) {
					return defined( $name ) && is_string( constant( $name ) ) ? constant( $name ) : null;
				}
				return self::call( strtolower( $name ), $tokens, $position, $file );
		}

		return null;
	}

	/**
	 * A path function applied to one argument.
	 *
	 * @param string            $function Function name, in lower case.
	 * @param array<int, mixed> $tokens   Tokens; $position is at its "(".
	 * @param int               $position Where to read, moved past the call.
	 * @param string            $file     The including file.
	 * @return string|null
	 */
	private static function call( string $function, array $tokens, int &$position, string $file ): ?string {
		if ( ! in_array( $function, self::PATH_FUNCTIONS, true ) ) {
			return null;
		}
		++$position;
		$argument = self::concatenation( $tokens, $position, $file );
		if ( null === $argument || ')' !== ( $tokens[ $position ] ?? null ) ) {
			return null;
		}
		++$position;
		switch ( $function ) {
			case 'dirname':
				return dirname( $argument );
			case 'plugin_dir_path':
				return trailingslashit( dirname( $argument ) );
			case 'trailingslashit':
				return trailingslashit( $argument );
		}

		return untrailingslashit( $argument );
	}

	/**
	 * A quoted string's text, unless it has escapes or variables.
	 *
	 * @param string $quoted The token, quotes included.
	 * @return string|null
	 */
	private static function literal( string $quoted ): ?string {
		$text = substr( $quoted, 1, -1 );
		if ( false !== strpos( $text, '\\' ) || ( '"' === $quoted[0] && false !== strpos( $text, '$' ) ) ) {
			return null;
		}

		return $text;
	}
}
