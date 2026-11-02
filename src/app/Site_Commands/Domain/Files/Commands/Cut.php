<?php
/**
 * cut: select fields or characters from lines.
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
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Filesystem_Exception;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Invalid_Invocation;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Unexpected_Failure;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Read_Only_Filesystem;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Sandbox\Sandbox;

/**
 * GNU cut: select fields by delimiter or characters by position.
 */
final class Cut extends Abstract_Command {

	/**
	 * The command's contract.
	 *
	 * @return Specification
	 */
	public static function specification(): Specification {
		return new Specification(
			'cut',
			'Select fields or characters from lines.',
			"Extract fields or characters from each line. For fields, give a delimiter and a field list, for example -d ']' -f 2-. For characters, give -c 1-20 instead. In a pipeline, omit file paths to read the previous output.",
			array(
				Option::text( 'delimiter', 'd', 'Field delimiter, one character. Default is a tab.' ),
				Option::text( 'fields', 'f', "Field list, for example '2' or '1,3-5'." ),
				Option::text( 'characters', 'c', "Character position list, for example '1-20'." ),
				Option::flag( 'only_delimited', 's', 'Do not print lines that contain no delimiter.' ),
			),
			array(
				Operand::paths( 'Files to read. Reads the previous stage when omitted in a pipeline.' ),
			),
			true
		);
	}

	/**
	 * Print the selected part of each line.
	 *
	 * @param Invocation           $invocation The validated arguments.
	 * @param Sandbox              $sandbox    Resolves every path.
	 * @param Budget_Ledger        $ledger     The only way to write output.
	 * @param Read_Only_Filesystem $filesystem The only way to read.
	 * @return Exit_Status
	 *
	 * @throws Invalid_Invocation When the lists or the delimiter are unusable.
	 */
	public function run( Invocation $invocation, Sandbox $sandbox, Budget_Ledger $ledger, Read_Only_Filesystem $filesystem ): Exit_Status {
		$fields     = $invocation->option( 'fields' );
		$characters = $invocation->option( 'characters' );
		if ( null === $fields && null === $characters ) {
			throw Invalid_Invocation::custom( 'cut', array( 'cut: you must specify a list of bytes, characters, or fields', "Try 'cut --help' for more information." ), 1 );
		}
		if ( null !== $fields && null !== $characters ) {
			throw Invalid_Invocation::custom( 'cut', array( 'cut: only one type of list may be specified', "Try 'cut --help' for more information." ), 1 );
		}
		$ranges = self::parse_list( (string) ( $fields ?? $characters ) );
		if ( null === $ranges ) {
			throw Invalid_Invocation::custom( 'cut', array( 'cut: invalid field value', "Try 'cut --help' for more information." ), 1 );
		}
		$delimiter = (string) ( $invocation->option( 'delimiter' ) ?? "\t" );
		if ( 1 !== strlen( $delimiter ) ) {
			// GNU's exact words and exit status; an empty delimiter would also make explode() throw.
			throw Invalid_Invocation::custom( 'cut', array( 'cut: the delimiter must be a single character', "Try 'cut --help' for more information." ), 1 );
		}
		$only_delimited = $invocation->flag( 'only_delimited' );

		$paths = $invocation->paths();
		if ( array() === $paths ) {
			$paths = array( '-' );
		}
		$status = Exit_Status::success();

		foreach ( $paths as $given ) {
			$resolved = $this->resolve_for_reading( $given, $sandbox, $ledger, $filesystem );
			if ( null === $resolved ) {
				$status = Exit_Status::failure();
				continue;
			}
			try {
				$ledger->scan_file();
				$this->hold_printed( $resolved, $sandbox, $ledger );
				foreach ( $filesystem->lines( $resolved->real() ) as $line ) {
					$ledger->check_time();
					$ledger->check_memory();
					$terminator = "\n" === substr( $line, -1 ) ? "\n" : '';
					$body       = rtrim( $line, "\n" );
					if ( null !== $fields ) {
						if ( false === strpos( $body, $delimiter ) ) {
							if ( ! $only_delimited ) {
								$ledger->out( $body . $terminator );
							}
							continue;
						}
						$ledger->out( self::select_fields( $body, $delimiter, $ranges, $ledger ) . $terminator );
						continue;
					}
					// Selected spans are already ordered and disjoint. Avoid
					// allocating one PHP array entry for every input byte.
					$length = strlen( $body );
					foreach ( $ranges as $range ) {
						list( $from, $to ) = $range;
						if ( $from > $length ) {
							break;
						}
						$ledger->out( substr( $body, $from - 1, min( $to, $length ) - $from + 1 ) );
					}
					$ledger->out( $terminator );
				}
			} catch ( Filesystem_Exception $failure ) {
				$this->report_filesystem( $failure, $given, $ledger );
				$status = Exit_Status::failure();
			} catch ( Throwable $failure ) {
				// Preserve expected control-flow exceptions; contextualize unexpected failures.
				throw Unexpected_Failure::during( 'selecting text fields', $failure );
			} finally {
				if ( ! $ledger->release() ) {
					$status = Exit_Status::failure();
				}
			}
		}

		return $status;
	}

	/**
	 * Parse a GNU list such as "1,3-5,7-".
	 *
	 * @param string $ranges_text The list.
	 * @return array<int, array{0: int, 1: int}>|null 1-based inclusive ranges; PHP_INT_MAX means open-ended.
	 */
	private static function parse_list( string $ranges_text ): ?array {
		$ranges = array();
		foreach ( explode( ',', $ranges_text ) as $item ) {
			$item = trim( $item );
			if ( preg_match( '/^(\d+)$/', $item, $m ) ) {
				$ranges[] = array( (int) $m[1], (int) $m[1] );
			} elseif ( preg_match( '/^(\d+)-(\d+)$/', $item, $m ) ) {
				$ranges[] = array( (int) $m[1], (int) $m[2] );
			} elseif ( preg_match( '/^(\d+)-$/', $item, $m ) ) {
				$ranges[] = array( (int) $m[1], PHP_INT_MAX );
			} elseif ( preg_match( '/^-(\d+)$/', $item, $m ) ) {
				$ranges[] = array( 1, (int) $m[1] );
			} else {
				return null;
			}
			if ( 0 === $ranges[ count( $ranges ) - 1 ][0] ) {
				return null;
			}
		}

		// Coalesce overlap once, rather than comparing every field with every
		// user-supplied range. The option itself is limited to 4096 bytes.
		usort(
			$ranges,
			static fn( array $a, array $b ): int => $a[0] <=> $b[0]
		);
		$merged = array();
		foreach ( $ranges as $range ) {
			list( $from, $to ) = $range;
			if ( $to < $from ) {
				return null;
			}
			$previous = array_pop( $merged );
			if ( null !== $previous && ( $from <= $previous[1] || $from - 1 === $previous[1] ) ) {
				$merged[] = array( $previous[0], max( $previous[1], $to ) );
			} else {
				if ( null !== $previous ) {
					$merged[] = $previous;
				}
				$merged[] = array( $from, $to );
			}
		}

		return $merged;
	}

	/**
	 * The selected fields of one line, joined by the delimiter.
	 *
	 * @param string                            $body      The line without its newline.
	 * @param string                            $delimiter One character.
	 * @param array<int, array{0: int, 1: int}> $ranges    Ordered, disjoint field ranges.
	 * @param Budget_Ledger                     $ledger    The ledger.
	 * @return string
	 */
	private static function select_fields( string $body, string $delimiter, array $ranges, Budget_Ledger $ledger ): string {
		$selected     = '';
		$has_selected = false;
		$offset       = 0;
		$position     = 1;
		$range        = 0;
		$length       = strlen( $body );
		while ( $offset <= $length ) {
			$ledger->tick();
			while ( isset( $ranges[ $range ] ) && $position > $ranges[ $range ][1] ) {
				++$range;
			}
			if ( ! isset( $ranges[ $range ] ) ) {
				break;
			}
			$end = strpos( $body, $delimiter, $offset );
			if ( $position >= $ranges[ $range ][0] ) {
				$selected    .= ( $has_selected ? $delimiter : '' ) . substr( $body, $offset, ( false === $end ? $length : $end ) - $offset );
				$has_selected = true;
			}
			if ( false === $end ) {
				break;
			}
			$offset = $end + 1;
			++$position;
		}

		return $selected;
	}
}
