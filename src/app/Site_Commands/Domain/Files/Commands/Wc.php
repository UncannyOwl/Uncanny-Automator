<?php
/**
 * wc: count lines, words, and bytes.
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
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Unexpected_Failure;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Read_Only_Filesystem;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Sandbox\Sandbox;

/**
 * Counts lines, words and bytes per file, with a total for several, in
 * GNU's column widths.
 */
final class Wc extends Abstract_Command {

	/**
	 * Counting or hashing a binary file prints none of it.
	 *
	 * @return bool
	 */
	protected static function reads_binary(): bool {
		return true;
	}

	/**
	 * GNU wc calls standard input 'standard input' when no file was named,
	 * as in "wc: 'standard input': Is a directory", and - when "-" was.
	 *
	 * @param string $given "-" or empty.
	 * @return string
	 */
	protected function standard_input_name( string $given ): string {
		return '-' === $given ? '-' : "'standard input'";
	}

	/**
	 * The command's contract.
	 *
	 * @return Specification
	 */
	public static function specification(): Specification {
		return new Specification(
			'wc',
			'Count lines, words, and bytes.',
			'Count lines, words, and bytes of each file, with a total when there are several. Use it to size a file before reading it.',
			array(
				Option::flag( 'lines', 'l', 'Print the newline counts.' ),
				Option::flag( 'words', 'w', 'Print the word counts.' ),
				Option::flag( 'bytes', 'c', 'Print the byte counts.' ),
			),
			array(
				Operand::paths( 'Files to count.', true ),
			),
			true
		);
	}

	/**
	 * Count each file and print the rows.
	 *
	 * @param Invocation           $invocation The validated arguments.
	 * @param Sandbox              $sandbox    Resolves every path.
	 * @param Budget_Ledger        $ledger     The only way to write output.
	 * @param Read_Only_Filesystem $filesystem The only way to read.
	 * @return Exit_Status
	 */
	public function run( Invocation $invocation, Sandbox $sandbox, Budget_Ledger $ledger, Read_Only_Filesystem $filesystem ): Exit_Status {
		$status     = Exit_Status::success();
		$show_lines = $invocation->flag( 'lines' );
		$show_words = $invocation->flag( 'words' );
		$show_bytes = $invocation->flag( 'bytes' );
		if ( ! $show_lines && ! $show_words && ! $show_bytes ) {
			$show_lines = true;
			$show_words = true;
			$show_bytes = true;
		}

		$rows     = array();
		$totals   = array( 0, 0, 0 );
		$size_sum = 0;
		$paths    = $invocation->paths();

		$has_non_regular = false;
		foreach ( $paths as $given ) {
			$resolved = $this->resolve_for_reading( $given, $sandbox, $ledger, $filesystem );
			if ( null === $resolved ) {
				$status = Exit_Status::failure();
				continue;
			}
			if ( $resolved->is_standard_input() ) {
				$has_non_regular = true;
			}
			try {
				$info = $filesystem->info( $resolved->real() );
				if ( $info->is_directory() ) {
					$has_non_regular = true;
					$ledger->error_line( $given, 'Is a directory' );
					$rows[] = array( 0, 0, 0, $given );
					$status = Exit_Status::failure();
					continue;
				}
				if ( $info->size() > $ledger->limits()->max_hash_file_bytes() ) {
					$ledger->error_line( $given, sprintf( 'file too large (limit %d bytes)', $ledger->limits()->max_hash_file_bytes() ) );
					$status = Exit_Status::failure();
					continue;
				}
				$size_sum += $info->size();
				$ledger->scan_file();
				$lines = 0;
				$words = 0;
				$bytes = 0;
				foreach ( $filesystem->lines( $resolved->real() ) as $line ) {
					$ledger->tick();
					$bytes += strlen( $line );
					$lines += substr_count( $line, "\n" );
					if ( $show_words ) {
						// With no matches argument, PCRE returns only the count;
						// a dense line must not allocate an array per word.
						$words += (int) preg_match_all( '/\S+/', $line );
					}
				}
				$rows[]     = array( $lines, $words, $bytes, $given );
				$totals[0] += $lines;
				$totals[1] += $words;
				$totals[2] += $bytes;
			} catch ( Filesystem_Exception $failure ) {
				$this->report_filesystem( $failure, $given, $ledger );
				$status = Exit_Status::failure();
			} catch ( Throwable $failure ) {
				// Preserve expected control-flow exceptions; contextualize unexpected failures.
				throw Unexpected_Failure::during( 'counting file content', $failure );
			}
		}

		if ( count( $paths ) > 1 ) {
			$rows[] = array( $totals[0], $totals[1], $totals[2], 'total' );
		}

		$width = max( 1, strlen( (string) $size_sum ) );
		if ( $has_non_regular ) {
			$width = max( 7, $width );
		}
		if ( 1 === count( $paths ) && 1 === (int) $show_lines + (int) $show_words + (int) $show_bytes ) {
			$width = 1;
		}

		foreach ( $rows as list( $lines, $words, $bytes, $name ) ) {
			$columns = array();
			if ( $show_lines ) {
				$columns[] = str_pad( (string) $lines, $width, ' ', STR_PAD_LEFT );
			}
			if ( $show_words ) {
				$columns[] = str_pad( (string) $words, $width, ' ', STR_PAD_LEFT );
			}
			if ( $show_bytes ) {
				$columns[] = str_pad( (string) $bytes, $width, ' ', STR_PAD_LEFT );
			}
			$ledger->out( implode( ' ', $columns ) . ( '' === $name ? '' : ' ' . $name ) . "\n" );
		}

		return $status;
	}
}
