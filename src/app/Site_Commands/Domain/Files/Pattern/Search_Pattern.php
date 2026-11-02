<?php
/**
 * A grep pattern compiled to PCRE.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command output is plain terminal text for AI clients, never rendered as HTML.
// phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged -- PCRE failures are read from the return value and preg_last_error(), then reported in grep's words.

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Pattern;

use Closure;
use InvalidArgumentException;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Pattern_Exception;

/**
 * A grep pattern compiled to PCRE.
 *
 * GNU basic regular expressions and PCRE differ. This compiler handles the
 * forms that appear in practice: in basic mode `\(`, `\)`, `\{`, `\}`,
 * `\|`, `\+`, `\?` are operators and the bare characters are literals; in
 * extended mode the bare characters are operators; fixed mode quotes
 * everything. `\<` and `\>` become word boundaries. Back-references are
 * passed through. Anything PCRE rejects is reported with grep's wording.
 * Several patterns, one per line as grep -e gives them, are alternatives.
 */
final class Search_Pattern {

	/**
	 * The compiled PCRE, delimiters and flags included.
	 *
	 * @var string
	 */
	private string $pcre;

	/**
	 * The pattern as given.
	 *
	 * @var string
	 */
	private string $pattern;

	/**
	 * One of the Pattern_Mode constants.
	 *
	 * @var string
	 */
	private string $mode;

	/**
	 * True for grep -i.
	 *
	 * @var bool
	 */
	private bool $ignore_case;

	/**
	 * True for grep -w.
	 *
	 * @var bool
	 */
	private bool $word_regexp;

	/**
	 * Compile the pattern.
	 *
	 * @param string $pattern     The pattern as given.
	 * @param string $mode        One of the Pattern_Mode constants.
	 * @param bool   $ignore_case True for grep -i.
	 * @param bool   $word_regexp True for grep -w.
	 *
	 * @throws Pattern_Exception When PCRE rejects the translated pattern.
	 */
	public function __construct( string $pattern, string $mode, bool $ignore_case = false, bool $word_regexp = false ) {
		$this->pattern     = $pattern;
		$this->mode        = $mode;
		$this->ignore_case = $ignore_case;
		$this->word_regexp = $word_regexp;
		$this->pcre        = $this->compile();
	}

	/**
	 * The pattern as given.
	 *
	 * @return string
	 */
	public function pattern(): string {
		return $this->pattern;
	}

	/**
	 * One of the Pattern_Mode constants.
	 *
	 * @return string
	 */
	public function mode(): string {
		return $this->mode;
	}

	/**
	 * Whether the line matches.
	 *
	 * @param string $line One line.
	 * @return bool
	 *
	 * @throws Pattern_Exception When PCRE gives up on the line (backtrack,
	 *                           recursion, or JIT stack limit) so the caller
	 *                           reports it instead of printing "no match".
	 */
	public function matches( string $line ): bool {
		$result = @preg_match( $this->pcre, $line );
		if ( false === $result ) {
			throw new Pattern_Exception( sprintf( 'regular expression too expensive to match (%s); simplify it, anchor it, or use --fixed-strings', self::pcre_error_name( preg_last_error() ) ) );
		}

		return 1 === $result;
	}

	/**
	 * Yield one nonempty match at a time. preg_match_all would allocate every
	 * capture before the output budget gets a chance to stop a dense match.
	 *
	 * @param string       $line       One line.
	 * @param Closure|null $checkpoint Runs before each attempt; may throw to stop.
	 * @return iterable<string>
	 *
	 * @throws Pattern_Exception When PCRE gives up on the line.
	 */
	public function pieces( string $line, ?Closure $checkpoint = null ): iterable {
		$offset    = 0;
		$length    = strlen( $line );
		$delimiter = strrpos( $this->pcre, '/' );
		$body      = substr( $this->pcre, 1, $delimiter - 1 );
		$flags     = substr( $this->pcre, $delimiter + 1 );
		// After an empty match, try a nonempty alternative at that exact
		// position before advancing. This mirrors preg_match_all's progress
		// rule without keeping all captures in memory. \G names the offset.
		$nonempty_at_offset = '/\G(?:' . $body . ')(?!\G)/' . $flags;
		while ( $offset < $length ) {
			if ( null !== $checkpoint ) {
				$checkpoint();
			}
			$result = @preg_match( $this->pcre, $line, $matches, PREG_OFFSET_CAPTURE, $offset );
			if ( false === $result ) {
				throw new Pattern_Exception( sprintf( 'regular expression too expensive to match (%s); simplify it, anchor it, or use --fixed-strings', self::pcre_error_name( preg_last_error() ) ) );
			}
			if ( 0 === $result ) {
				return;
			}
			list( $piece, $position ) = $matches[0];
			if ( '' === $piece ) {
				$result = @preg_match( $nonempty_at_offset, $line, $matches, PREG_OFFSET_CAPTURE, $position );
				if ( false === $result ) {
					throw new Pattern_Exception( sprintf( 'regular expression too expensive to match (%s); simplify it, anchor it, or use --fixed-strings', self::pcre_error_name( preg_last_error() ) ) );
				}
				if ( 0 === $result ) {
					$offset = $position + 1;
					continue;
				}
				list( $piece, $position ) = $matches[0];
				if ( '' === $piece ) {
					// PCRE's \K can reset the reported match after consuming
					// text. Even then, never retry the same empty position.
					$offset = $position + 1;
					continue;
				}
			}
			yield $piece;
			$offset = $position + strlen( $piece );
		}
	}

	/**
	 * A short name for a PCRE error code.
	 *
	 * @param int $code A preg_last_error() code.
	 * @return string
	 */
	private static function pcre_error_name( int $code ): string {
		switch ( $code ) {
			case PREG_BACKTRACK_LIMIT_ERROR:
				return 'backtrack limit';
			case PREG_RECURSION_LIMIT_ERROR:
				return 'recursion limit';
			case PREG_JIT_STACKLIMIT_ERROR:
				return 'JIT stack limit';
			case PREG_BAD_UTF8_ERROR:
			case PREG_BAD_UTF8_OFFSET_ERROR:
				return 'bad UTF-8';
			default:
				return 'PCRE error ' . $code;
		}
	}

	/**
	 * Translate and verify the pattern.
	 *
	 * @return string
	 *
	 * @throws Pattern_Exception        When PCRE rejects the translated pattern.
	 * @throws InvalidArgumentException When the mode is not a Pattern_Mode constant.
	 */
	private function compile(): string {
		// As in GNU grep, a newline separates patterns; a line matching any one is selected.
		$bodies = array();
		foreach ( explode( "\n", $this->pattern ) as $pattern ) {
			switch ( $this->mode ) {
				case Pattern_Mode::FIXED:
					$bodies[] = preg_quote( $pattern, '/' );
					break;
				case Pattern_Mode::EXTENDED:
					$bodies[] = $this->translate_extended( $pattern );
					break;
				case Pattern_Mode::BASIC:
					$bodies[] = $this->translate_basic( $pattern );
					break;
				default:
					throw new InvalidArgumentException( 'Unknown pattern mode: ' . $this->mode );
			}
		}
		$body = 1 === count( $bodies ) ? $bodies[0] : '(?:' . implode( ')|(?:', $bodies ) . ')';

		if ( $this->word_regexp ) {
			$body = '(?<![[:alnum:]_])(?:' . $body . ')(?![[:alnum:]_])';
		}

		$flags = 'S' . ( $this->ignore_case ? 'i' : '' );
		$pcre  = '/' . $body . '/' . $flags;

		if ( false === @preg_match( $pcre, '' ) ) {
			throw new Pattern_Exception( $this->describe_error( $this->pattern ) );
		}

		return $pcre;
	}

	/**
	 * GNU extended syntax to PCRE.
	 *
	 * @param string $pattern The pattern as given.
	 * @return string
	 */
	private function translate_extended( string $pattern ): string {
		$out    = '';
		$length = strlen( $pattern );
		for ( $i = 0; $i < $length; ++$i ) {
			$char = $pattern[ $i ];
			if ( '\\' === $char && $i + 1 < $length ) {
				$next = $pattern[ ++$i ];
				$out .= ( '<' === $next || '>' === $next ) ? '\\b' : '\\' . $next;
				continue;
			}
			$out .= '/' === $char ? '\\/' : $char;
		}

		return $out;
	}

	/**
	 * GNU basic syntax to PCRE.
	 *
	 * @param string $pattern The pattern as given.
	 * @return string
	 */
	private function translate_basic( string $pattern ): string {
		$out      = '';
		$length   = strlen( $pattern );
		$at_start = true; // a * at the start of a BRE, or right after ^, \( or \|, is a literal star
		$bre_ops  = array( '(', ')', '{', '}', '|', '+', '?' );
		for ( $i = 0; $i < $length; ++$i ) {
			$char = $pattern[ $i ];
			if ( '\\' === $char && $i + 1 < $length ) {
				$next = $pattern[ ++$i ];
				if ( in_array( $next, $bre_ops, true ) ) {
					$out .= $next;
				} elseif ( '<' === $next || '>' === $next ) {
					$out .= '\\b';
				} else {
					$out .= '\\' . $next;
				}
				$at_start = '(' === $next || '|' === $next;
				continue;
			}
			if ( '*' === $char && $at_start ) {
				$out     .= '\\*';
				$at_start = false;
				continue;
			}
			if ( in_array( $char, $bre_ops, true ) ) {
				$out .= '\\' . $char;
			} elseif ( '/' === $char ) {
				$out .= '\\/';
			} else {
				$out .= $char;
			}
			$at_start = '^' === $char && '' === substr( $out, 0, -1 );
		}

		return $out;
	}

	/**
	 * The message grep prints for a pattern it cannot compile.
	 *
	 * @param string $pattern The pattern as given.
	 * @return string
	 */
	private function describe_error( string $pattern ): string {
		$opens  = substr_count( $pattern, '[' );
		$closes = substr_count( $pattern, ']' );
		if ( $opens > $closes ) {
			return 'grep: Unmatched [, [^, [:, [., or [=';
		}
		if ( substr_count( $pattern, '(' ) > substr_count( $pattern, ')' ) ) {
			return 'grep: Unmatched ( or \\(';
		}
		if ( substr_count( $pattern, ')' ) > substr_count( $pattern, '(' ) ) {
			return 'grep: Unmatched ) or \\)';
		}
		if ( false !== strpos( $pattern, '{' ) ) {
			return 'grep: Invalid content of \\{\\}';
		}

		return 'grep: Invalid regular expression';
	}
}
