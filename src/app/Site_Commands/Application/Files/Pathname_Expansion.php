<?php
/**
 * Expands path operands typed with unquoted wildcards, as a shell would.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command output is plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Application\Files;

use Uncanny_Automator\App\Site_Commands\Domain\Files\Budget\Budget_Ledger;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Invocation;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Operand;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Access_Denied;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Budget_Exceeded;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Filesystem_Exception;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Invalid_Invocation;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Read_Only_Filesystem;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Pattern\Glob_Pattern;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Sandbox\Sandbox;

/**
 * Bash's default path-name expansion, inside the sandbox.
 *
 * Each `/`-separated part of a pattern that holds a wildcard is matched
 * against the names a directory listing gives; the other parts are joined
 * as written. A name starting with a dot matches only a part that starts
 * with a dot. The matches are sorted in byte order (the C locale). A
 * pattern that matches nothing stays as written, so the command reports it
 * as missing, as bash does by default. Only directories the sandbox
 * resolves are listed; a part the sandbox refuses matches nothing. Every
 * match is still an ordinary operand the command resolves through the
 * sandbox.
 */
final class Pathname_Expansion {

	/** The most paths the wildcards of one command may become. */
	public const MAX_PATHS = 1000;

	/**
	 * Replace each pattern operand with its matches.
	 *
	 * @param Invocation                        $invocation The validated call, operands as typed.
	 * @param array<string, Operand>            $operands   The command's operands.
	 * @param array<string, array<int, string>> $globs      Patterns by operand name and position.
	 * @param Sandbox                           $sandbox    The readable roots, working directory applied.
	 * @param Read_Only_Filesystem              $filesystem The filesystem.
	 * @param Budget_Ledger                     $ledger     The ledger, for the time budget.
	 * @return Invocation
	 *
	 * @throws Invalid_Invocation When the matches, or the paths one step of a pattern reaches, are too many, or more than the operand takes.
	 * @throws Budget_Exceeded    When the time budget runs out while matching.
	 */
	public function expand( Invocation $invocation, array $operands, array $globs, Sandbox $sandbox, Read_Only_Filesystem $filesystem, Budget_Ledger $ledger ): Invocation {
		$total = 0;
		foreach ( $globs as $name => $patterns ) {
			if ( ! isset( $operands[ $name ] ) ) {
				continue;
			}
			$operand = $operands[ $name ];
			$typed   = $operand->many() ? $invocation->paths( $name ) : array( (string) $invocation->operand( $name ) );
			$values  = array();
			foreach ( $typed as $index => $word ) {
				$matches = isset( $patterns[ $index ] ) ? $this->matches( $patterns[ $index ], $invocation->command(), $word, $sandbox, $filesystem, $ledger ) : array();
				$total  += count( $matches );
				if ( $total > self::MAX_PATHS ) {
					throw self::too_many( $invocation->command(), $word );
				}
				$values = array_merge( $values, array() === $matches ? array( $word ) : $matches );
			}
			if ( ( ! $operand->many() || 1 === $operand->max_items() ) && count( $values ) > 1 ) {
				throw Invalid_Invocation::custom(
					$invocation->command(),
					array(
						sprintf( "%s: extra operand '%s'", $invocation->command(), $values[1] ),
						sprintf( "Try '%s --help' for more information.", $invocation->command() ),
					)
				);
			}
			$invocation = $invocation->with_operand( $name, $operand->many() ? $values : $values[0] );
		}

		return $invocation;
	}

	/**
	 * The sorted paths one pattern matches, or none.
	 *
	 * Every step is bounded: when the paths one part reaches pass
	 * MAX_PATHS the whole pattern is refused, never cut short, since a later
	 * part could have kept any of the dropped ones. A run of `.` parts and
	 * empty parts (from repeated slashes) after a wildcard costs one check
	 * per path, not one per part, and the paths keep the text as written.
	 * The time budget is checked as candidates are tested.
	 *
	 * @param string               $pattern    The pattern; quoted wildcards and backslashes escaped.
	 * @param string               $command    The command name, for the refusal.
	 * @param string               $word       The operand as typed, for the refusal.
	 * @param Sandbox              $sandbox    The readable roots.
	 * @param Read_Only_Filesystem $filesystem The filesystem.
	 * @param Budget_Ledger        $ledger     The ledger, for the time budget.
	 * @return string[]
	 *
	 * @throws Invalid_Invocation When one step reaches more than MAX_PATHS paths.
	 * @throws Budget_Exceeded    When the time budget runs out.
	 */
	private function matches( string $pattern, string $command, string $word, Sandbox $sandbox, Read_Only_Filesystem $filesystem, Budget_Ledger $ledger ): array {
		$absolute    = 0 === strpos( $pattern, '/' );
		$directories = '/' === substr( $pattern, -1 );
		$parts       = explode( '/', trim( $pattern, '/' ) );
		$paths       = array( $absolute ? '/' : '' );
		$wild        = false;
		// Whether every path so far is known to be a directory, after a "." or empty part was checked.
		$checked = false;

		foreach ( $parts as $part ) {
			$next = array();
			if ( ! self::has_wildcard( $part ) ) {
				$literal = self::unescape( $part );
				$same    = '.' === $literal || '' === $literal;
				foreach ( $paths as $path ) {
					$candidate = self::join( $path, $literal );
					// After a wildcard, a written part must exist, as in bash. "x/." and
					// "x//" exist when x is a directory, so a run of them is checked once.
					if ( ! $wild || ( $same && $checked ) ) {
						$next[] = $candidate;
						continue;
					}
					// Each lookup can be slow, so the clock is read around every one.
					$ledger->tick();
					$ledger->check_time();
					if ( $same ? $this->is_directory( $path, $sandbox, $filesystem ) : $this->exists( $candidate, $sandbox, $filesystem ) ) {
						$next[] = $candidate;
					}
				}
				$checked = $wild && $same;
				$paths   = $next;
				if ( array() === $paths ) {
					$ledger->check_time();
					return array();
				}
				continue;
			}

			$wild    = true;
			$checked = false;
			$glob    = new Glob_Pattern( $part );
			$hidden  = '.' === substr( $part, 0, 1 ) || '\\.' === substr( $part, 0, 2 );
			foreach ( $paths as $path ) {
				foreach ( $this->names( $path, $sandbox, $filesystem, $ledger ) as $name ) {
					$ledger->tick();
					if ( ( $hidden || '.' !== $name[0] ) && $glob->matches( $name ) ) {
						$next[] = self::join( $path, $name );
						if ( count( $next ) > self::MAX_PATHS ) {
							throw self::too_many( $command, $word );
						}
					}
				}
			}
			$paths = $next;
			if ( array() === $paths ) {
				$ledger->check_time();
				return array();
			}
		}
		if ( ! $wild ) {
			$ledger->check_time();
			return array();
		}
		if ( $directories ) {
			$kept = array();
			foreach ( $paths as $path ) {
				$ledger->tick();
				$ledger->check_time();
				if ( $this->is_directory( $path, $sandbox, $filesystem ) ) {
					$kept[] = $path . '/';
				}
			}
			$paths = $kept;
		}
		$ledger->check_time();
		sort( $paths, SORT_STRING );

		return $paths;
	}

	/**
	 * The refusal for a pattern that reaches too many paths.
	 *
	 * @param string $command The command name.
	 * @param string $word    The operand as typed.
	 * @return Invalid_Invocation
	 */
	private static function too_many( string $command, string $word ): Invalid_Invocation {
		return Invalid_Invocation::custom(
			$command,
			array( sprintf( '%s: %s: the wildcards match more than %d paths; narrow the pattern, or use find with -name and pass its output on with "| xargs %s"', $command, $word, self::MAX_PATHS, $command ) )
		);
	}

	/**
	 * The entry names of a directory, or none when it cannot be listed.
	 *
	 * @param string               $path       The directory as written so far; empty for the working directory.
	 * @param Sandbox              $sandbox    The readable roots.
	 * @param Read_Only_Filesystem $filesystem The filesystem.
	 * @param Budget_Ledger        $ledger     The ledger, for the time budget.
	 * @return string[]
	 */
	private function names( string $path, Sandbox $sandbox, Read_Only_Filesystem $filesystem, Budget_Ledger $ledger ): array {
		$names = array();
		$ledger->check_time();
		try {
			foreach ( $filesystem->list( $sandbox->resolve( '' === $path ? '.' : $path, $filesystem ) ) as $entry ) {
				$ledger->tick();
				$ledger->check_time();
				$names[] = $entry->name();
			}
		} catch ( Access_Denied | Filesystem_Exception $unreadable ) {
			// Outside the roots, missing, or not a directory: no matches, as in bash.
			unset( $unreadable );
		}

		return $names;
	}

	/**
	 * Whether a path exists inside the sandbox.
	 *
	 * @param string               $path       The path as written.
	 * @param Sandbox              $sandbox    The readable roots.
	 * @param Read_Only_Filesystem $filesystem The filesystem.
	 * @return bool
	 */
	private function exists( string $path, Sandbox $sandbox, Read_Only_Filesystem $filesystem ): bool {
		try {
			return $filesystem->exists( $sandbox->resolve( $path, $filesystem ) );
		} catch ( Access_Denied | Filesystem_Exception $refused ) {
			unset( $refused );

			return false;
		}
	}

	/**
	 * Whether a path is a directory inside the sandbox.
	 *
	 * @param string               $path       The path as written.
	 * @param Sandbox              $sandbox    The readable roots.
	 * @param Read_Only_Filesystem $filesystem The filesystem.
	 * @return bool
	 */
	private function is_directory( string $path, Sandbox $sandbox, Read_Only_Filesystem $filesystem ): bool {
		try {
			return $filesystem->info( $sandbox->resolve( $path, $filesystem ), true )->is_directory();
		} catch ( Access_Denied | Filesystem_Exception $refused ) {
			unset( $refused );

			return false;
		}
	}

	/**
	 * Whether a part holds a wildcard that is not escaped.
	 *
	 * @param string $part One part of a pattern.
	 * @return bool
	 */
	private static function has_wildcard( string $part ): bool {
		$length = strlen( $part );
		for ( $i = 0; $i < $length; ++$i ) {
			if ( '\\' === $part[ $i ] ) {
				++$i;
				continue;
			}
			if ( false !== strpos( '*?[', $part[ $i ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * A part without its escapes.
	 *
	 * @param string $part One part of a pattern.
	 * @return string
	 */
	private static function unescape( string $part ): string {
		return (string) preg_replace( '/\\\\(.)/s', '$1', $part );
	}

	/**
	 * Join a path so far and a name.
	 *
	 * @param string $path The path so far: empty, "/", or a path.
	 * @param string $name The next name.
	 * @return string
	 */
	private static function join( string $path, string $name ): string {
		if ( '' === $path ) {
			return $name;
		}

		return '/' === $path ? '/' . $name : $path . '/' . $name;
	}
}
