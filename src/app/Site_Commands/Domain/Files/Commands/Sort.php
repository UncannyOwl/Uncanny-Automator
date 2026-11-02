<?php
/**
 * sort: sort lines.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command output is plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Commands;

use Throwable;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Budget\Budget_Ledger;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Exit_Status;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Invocation;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Operand;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Option;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Specification;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Commands\Support\Sort_Key;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Access_Denied;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Filesystem_Exception;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Invalid_Invocation;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Unexpected_Failure;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Read_Only_Filesystem;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Sandbox\Sandbox;

/**
 * GNU sort in the C locale: whole-line byte order, or leading numeric
 * value with -n. A filter first, a file reader second.
 */
final class Sort extends Abstract_Command {

	/**
	 * GNU sort's words before an input it cannot read: "sort: cannot read: x: Permission denied".
	 */
	private const CANNOT_READ = 'cannot read:';

	/**
	 * The command's contract.
	 *
	 * @return Specification
	 */
	public static function specification(): Specification {
		return new Specification(
			'sort',
			'Sort lines.',
			'Sort text lines. Use -n (--numeric-sort) for numbers, -h (--human-numeric-sort) for sizes such as 4.0K and 12M, and -r (--reverse) for largest first. Use -k POS1[,POS2] for fields, such as -k 2,2n, and -t SEP to split fields at a character, such as -t : -k 3n. In a pipeline, omit file paths to sort the previous output, as in du -sh * | sort -rh. Sort before uniq to group equal lines.',
			array(
				Option::flag( 'reverse', 'r', 'Reverse the result of comparisons.' ),
				Option::flag( 'numeric_sort', 'n', 'Compare according to leading numeric value.' ),
				Option::flag( 'unique', 'u', 'Output only the first of an equal run.' ),
				Option::flag( 'ignore_case', 'f', 'Fold lower case to upper case characters.' ),
				Option::flag( 'human_numeric_sort', 'h', 'Compare human sizes such as 2K and 1G, as du -h and ls -lh print them.' ),
				Option::flag( 'ignore_leading_blanks', 'b', 'Ignore leading blanks in each key.' ),
				Option::list( 'key', 'k', 'Sort by a key, GNU KEYDEF F[.C][OPTS][,F[.C][OPTS]]: field F (from 1), character C within it, letters b f h n r to order this key alone. Without the second position the key runs to the end of the line. Repeat it for tie-breaks; the whole line breaks remaining ties. For example -k 2,2n, -k 3 or -t : -k 3,3nr.' ),
				Option::text( 'field_separator', 't', 'Split fields at this one character instead of at blanks, for example -t : or -t ,.' ),
			),
			array(
				Operand::paths( 'Files to sort. Reads the previous stage when omitted in a pipeline.' ),
			),
			true
		);
	}

	/**
	 * Read every input, sort, and print.
	 *
	 * @param Invocation           $invocation The validated arguments.
	 * @param Sandbox              $sandbox    Resolves every path.
	 * @param Budget_Ledger        $ledger     The only way to write output.
	 * @param Read_Only_Filesystem $filesystem The only way to read.
	 * @return Exit_Status
	 */
	public function run( Invocation $invocation, Sandbox $sandbox, Budget_Ledger $ledger, Read_Only_Filesystem $filesystem ): Exit_Status {
		$lines            = array();
		$origins          = array();
		$inputs           = array();
		$status           = Exit_Status::success();
		$input_truncated  = false;
		$input_bytes      = 0;
		$byte_limit       = 4 * 1048576;
		$truncated_reason = '';

		foreach ( self::inputs( $invocation ) as $given ) {
			$resolved = $this->resolve_for_reading( $given, $sandbox, $ledger, $filesystem, self::CANNOT_READ );
			if ( null === $resolved ) {
				return Exit_Status::trouble();
			}
			$inputs[] = $resolved;
			$origin   = count( $inputs ) - 1;
			try {
				$ledger->scan_file();
				foreach ( $filesystem->lines( $resolved->real() ) as $line ) {
					$ledger->check_time();
					$ledger->check_memory();
					$terminated   = "\n" === substr( $line, -1 );
					$input_bytes += strlen( $line ) + ( $terminated ? 0 : 1 );
					if ( $input_bytes > $byte_limit ) {
						$input_truncated  = true;
						$truncated_reason = sprintf( 'input truncated at %d bytes', $byte_limit );
						break 2;
					}
					$lines[]   = $terminated ? $line : $line . "\n";
					$origins[] = $origin;
					if ( count( $lines ) > $ledger->limits()->max_output_lines() * 10 ) {
						$input_truncated  = true;
						$truncated_reason = sprintf( 'input truncated at %d lines', $ledger->limits()->max_output_lines() * 10 );
						break 2;
					}
				}
			} catch ( Filesystem_Exception $failure ) {
				$this->report_filesystem( $failure, $given, $ledger, self::CANNOT_READ );

				return Exit_Status::trouble();
			} catch ( Throwable $failure ) {
				// Preserve expected control-flow exceptions; contextualize unexpected failures.
				throw Unexpected_Failure::during( 'sorting input', $failure );
			}
		}

		if ( $input_truncated ) {
			$ledger->transcript()->mark_truncated();
		}
		$separator = $invocation->option( 'field_separator' );
		if ( null !== $separator && 1 !== strlen( (string) $separator ) ) {
			throw Invalid_Invocation::custom( 'sort', array( '' === $separator ? 'sort: empty tab' : sprintf( "sort: multi-character tab '%s'", $separator ), "Try 'sort --help' for more information." ) );
		}
		$keys    = self::keys( $invocation );
		$unique  = $invocation->flag( 'unique' );
		$reverse = $invocation->flag( 'reverse' );
		// GNU: keys only, then (unless -u) the whole line byte by byte, reversed by -r.
		$compare_keys = static function ( string $a, string $b ) use ( $keys, $separator ): int {
			$a = rtrim( $a, "\n" );
			$b = rtrim( $b, "\n" );
			if ( array() === $keys ) {
				return strcmp( $a, $b ) <=> 0;
			}
			foreach ( $keys as $key ) {
				$result = $key->compare( $a, $b, $separator );
				if ( 0 !== $result ) {
					return $result;
				}
			}

			return 0;
		};
		// Positions keep equal lines in input order, as GNU's stable merge does under -u.
		$order = array_keys( $lines );
		$ledger->check_time();
		$ledger->check_memory();
		usort(
			$order,
			static function ( int $x, int $y ) use ( $lines, $compare_keys, $unique, $reverse, $ledger ): int {
				// Throw before emitting any rows if sorting exhausts the budget.
				$ledger->tick();
				$result = $compare_keys( $lines[ $x ], $lines[ $y ] );
				if ( 0 === $result && ! $unique ) {
					$result = strcmp( rtrim( $lines[ $x ], "\n" ), rtrim( $lines[ $y ], "\n" ) ) <=> 0;
					$result = $reverse ? -$result : $result;
				}

				return 0 !== $result ? $result : $x <=> $y;
			}
		);
		$ledger->check_time();

		$printed  = array();
		$previous = null;
		foreach ( $order as $index ) {
			$line = $lines[ $index ];
			if ( $unique && null !== $previous && 0 === $compare_keys( $previous, $line ) ) {
				continue;
			}
			$previous  = $line;
			$printed[] = $index;
		}
		$refused = self::refused_input( self::within_budget( $printed, $lines, $ledger ), $lines, $origins, $inputs, $sandbox );
		if ( null !== $refused ) {
			$this->report_denied( Access_Denied::policy( $refused->given() ), $ledger, self::CANNOT_READ, $refused->is_standard_input() );

			return Exit_Status::trouble();
		}
		foreach ( $printed as $index ) {
			$ledger->out( $lines[ $index ] );
		}
		if ( $input_truncated ) {
			$ledger->err( sprintf( "sort: %s; narrow the input or previous stage; results are partial\n", $truncated_reason ) );
		}

		return $status;
	}

	/**
	 * The lines the output budgets would reach. A credential the cap would
	 * never print refuses nothing, and the line that crosses the cap counts
	 * whole, so the cap cannot print part of a credential.
	 *
	 * @param int[]         $printed The indexes of the lines to print, in order.
	 * @param string[]      $lines   Every line read.
	 * @param Budget_Ledger $ledger  The budgets.
	 * @return int[]
	 */
	private static function within_budget( array $printed, array $lines, Budget_Ledger $ledger ): array {
		$texts = array();
		foreach ( $printed as $index ) {
			$texts[] = $lines[ $index ];
		}

		return array_slice( $printed, 0, $ledger->texts_within_budget( $texts ) );
	}

	/**
	 * The first input, in the order given, whose lines sort would print
	 * hold a credential signature, or null. Sort prints nothing until every
	 * input is read, so it judges what it would print from each input before
	 * printing any of it, and refuses as GNU sort refuses an input it cannot
	 * read: no output, the input named, exit 2. Each input's lines are
	 * judged in the order they would be printed.
	 *
	 * @param int[]           $printed The indexes of the lines that would print, in order.
	 * @param string[]        $lines   Every line read.
	 * @param int[]           $origins The input each line was read from.
	 * @param Resolved_Path[] $inputs  The inputs, in the order given.
	 * @param Sandbox         $sandbox The sandbox, whose content policy reads the lines.
	 * @return Resolved_Path|null
	 */
	private static function refused_input( array $printed, array $lines, array $origins, array $inputs, Sandbox $sandbox ): ?Resolved_Path {
		$texts = array_fill( 0, count( $inputs ), '' );
		foreach ( $printed as $index ) {
			$texts[ $origins[ $index ] ] .= $lines[ $index ];
		}
		foreach ( $texts as $origin => $text ) {
			if ( ! $sandbox->allows_printed( $text ) ) {
				return $inputs[ $origin ];
			}
		}

		return null;
	}

	/**
	 * The operands, or standard input when there are none.
	 *
	 * @param Invocation $invocation The validated arguments.
	 * @return string[]
	 */
	private static function inputs( Invocation $invocation ): array {
		$paths = $invocation->paths();

		return array() === $paths ? array( '-' ) : $paths;
	}

	/**
	 * The keys to compare by, as GNU builds them: each -k key, with the
	 * global ordering options when it names none of its own; without -k,
	 * the whole line when any ordering option is given.
	 *
	 * @param Invocation $invocation The validated arguments.
	 * @return Sort_Key[]
	 *
	 * @throws Invalid_Invocation When a key definition is malformed.
	 */
	private static function keys( Invocation $invocation ): array {
		$global = '';
		foreach ( array(
			'b' => 'ignore_leading_blanks',
			'f' => 'ignore_case',
			'h' => 'human_numeric_sort',
			'n' => 'numeric_sort',
			'r' => 'reverse',
		) as $letter => $option ) {
			if ( $invocation->flag( $option ) ) {
				$global .= $letter;
			}
		}
		$keys = array();
		foreach ( $invocation->list( 'key' ) as $definition ) {
			$key = Sort_Key::parse( $definition );
			if ( null === $key ) {
				throw Invalid_Invocation::custom( 'sort', array( sprintf( "sort: invalid field specification '%s'", $definition ), "Try 'sort --help' for more information." ) );
			}
			// A global -b skips blanks at both ends of an inheriting key.
			$keys[] = $key->has_ordering() ? $key : $key->with_flags( $global . ( false !== strpos( $global, 'b' ) ? 'B' : '' ) );
		}
		if ( array() === $keys && '' !== $global ) {
			$keys[] = Sort_Key::whole_line( $global );
		}

		return $keys;
	}
}
