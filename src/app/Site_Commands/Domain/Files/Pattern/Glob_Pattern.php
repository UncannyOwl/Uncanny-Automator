<?php
/**
 * Shell glob matching for base names.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Pattern;

/**
 * Shell glob matching with the semantics find -name and grep --include use:
 * `*` and `?` match across `/` because they are applied to base names.
 */
final class Glob_Pattern {

	/**
	 * The glob.
	 *
	 * @var string
	 */
	private string $pattern;

	/**
	 * True to match case-insensitively.
	 *
	 * @var bool
	 */
	private bool $case_fold;

	/**
	 * Hold one glob.
	 *
	 * @param string $pattern   The glob.
	 * @param bool   $case_fold True to match case-insensitively.
	 */
	public function __construct( string $pattern, bool $case_fold = false ) {
		$this->pattern   = $pattern;
		$this->case_fold = $case_fold;
	}

	/**
	 * The glob.
	 *
	 * @return string
	 */
	public function pattern(): string {
		return $this->pattern;
	}

	/**
	 * Whether the name matches the glob.
	 *
	 * @param string $name A base name or path.
	 * @return bool
	 */
	public function matches( string $name ): bool {
		if ( function_exists( 'fnmatch' ) && strlen( $this->pattern ) < 1000 && strlen( $name ) < 1024 ) {
			return fnmatch( $this->pattern, $name, $this->case_fold ? FNM_CASEFOLD : 0 );
		}

		// Split at real wildcards, not escaped stars or stars inside brackets.
		// Atomic interior segments prevent repeated stars from causing an
		// exponential search. The final segment must still match the suffix.
		$segments = array( '' );
		$segment  = 0;
		$length   = strlen( $this->pattern );
		for ( $i = 0; $i < $length; ++$i ) {
			$char = $this->pattern[ $i ];
			if ( '*' === $char ) {
				if ( 0 === $segment || '' !== $segments[ $segment ] ) {
					$segments[ ++$segment ] = '';
				}
			} elseif ( '?' === $char ) {
				$segments[ $segment ] .= '.';
			} elseif ( '\\' === $char ) {
				if ( ++$i === $length ) {
					return false; // fnmatch rejects a trailing escape.
				}
				$segments[ $segment ] .= preg_quote( $this->pattern[ $i ], '~' );
			} elseif ( '[' === $char ) {
				$bracket               = $this->bracket( $i );
				$segments[ $segment ] .= $bracket ?? '\[';
			} else {
				$segments[ $segment ] .= preg_quote( $char, '~' );
			}
		}
		$regex = $segments[0];
		for ( $i = 1; $i < $segment; ++$i ) {
			$regex .= '(?>.*?' . $segments[ $i ] . ')';
		}
		if ( $segment > 0 ) {
			$regex .= '.*' . $segments[ $segment ];
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- An invalid translated bracket must read as no match, as fnmatch reports it.
		return 1 === @preg_match( '~\A' . $regex . '\z~s' . ( $this->case_fold ? 'i' : '' ), $name );
	}

	/**
	 * Translate one bracket expression, advancing only when a closing ] exists.
	 *
	 * @param int $offset The position of the opening [; moved to the closing ].
	 * @return string|null
	 */
	private function bracket( int &$offset ): ?string {
		$length = strlen( $this->pattern );
		$i      = $offset + 1;
		$body   = '';
		if ( $i < $length && in_array( $this->pattern[ $i ], array( '!', '^' ), true ) ) {
			$body = '^';
			++$i;
		}
		if ( $i < $length && ']' === $this->pattern[ $i ] ) {
			$body .= '\]';
			++$i;
		}
		for ( ; $i < $length; ++$i ) {
			$char = $this->pattern[ $i ];
			if ( ']' === $char ) {
				$offset = $i;
				return '[' . $body . ']';
			}
			// POSIX classes such as [[:digit:]] contain their own closing ].
			if ( '[' === $char && preg_match( '/\A\[:(alnum|alpha|blank|cntrl|digit|graph|lower|print|punct|space|upper|xdigit):\]/', substr( $this->pattern, $i ), $match ) ) {
				$body .= $match[0];
				$i    += strlen( $match[0] ) - 1;
			} elseif ( '\\' === $char && $i + 1 < $length ) {
				$body .= preg_quote( $this->pattern[ ++$i ], '~' );
			} else {
				// Hyphens retain range semantics; other regex metacharacters
				// are literal inside a shell bracket expression.
				$body .= '-' === $char ? '-' : preg_quote( $char, '~' );
			}
		}
		return null;
	}

	/**
	 * Whether any of the globs matches the name.
	 *
	 * @param string[] $patterns  Globs.
	 * @param string   $name      A base name or path.
	 * @param bool     $case_fold True to match case-insensitively.
	 * @return bool
	 */
	public static function any_matches( array $patterns, string $name, bool $case_fold = false ): bool {
		foreach ( $patterns as $pattern ) {
			if ( ( new self( $pattern, $case_fold ) )->matches( $name ) ) {
				return true;
			}
		}

		return false;
	}
}
