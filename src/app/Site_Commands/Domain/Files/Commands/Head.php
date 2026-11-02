<?php
/**
 * head: print the first lines of files.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command output is plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Commands;

use SplQueue;
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
 * Prints the first lines, or bytes, of each file, with GNU headers when
 * there are several.
 */
final class Head extends Abstract_Command {

	/**
	 * The command's contract.
	 *
	 * @return Specification
	 */
	public static function specification(): Specification {
		return new Specification(
			'head',
			'Print the first lines of files.',
			'Read the beginning of a text file. Use -n N (--lines=N) for the number of lines wanted (default 10), or -c N (--bytes=N) for a byte limit. Use -n -N to print all but the last N lines, or -c -N all but the last N bytes. In a pipeline, omit the file operands to read the previous output.',
			array(
				Option::integer( 'lines', 'n', 'Number of lines to print; -N prints all but the last N.', 10, -5000, 5000 ),
				Option::integer( 'bytes', 'c', 'Print this many bytes instead of lines; -N prints all but the last N.', null, -65536, 65536 ),
			),
			array(
				Operand::paths( 'Files to read.', true ),
			),
			true
		);
	}

	/**
	 * Print the start of each file.
	 *
	 * @param Invocation           $invocation The validated arguments.
	 * @param Sandbox              $sandbox    Resolves every path.
	 * @param Budget_Ledger        $ledger     The only way to write output.
	 * @param Read_Only_Filesystem $filesystem The only way to read.
	 * @return Exit_Status
	 */
	public function run( Invocation $invocation, Sandbox $sandbox, Budget_Ledger $ledger, Read_Only_Filesystem $filesystem ): Exit_Status {
		$status  = Exit_Status::success();
		$paths   = $invocation->paths();
		$headers = count( $paths ) > 1;
		$bytes   = $invocation->integer( 'bytes' );
		$lines   = min( abs( $invocation->integer( 'lines' ) ?? 10 ), $ledger->limits()->max_tail_lines() );
		// GNU: -N leaves out the last N lines or bytes.
		$but_bytes = self::all_but( $invocation->option( 'bytes' ) );
		$but_lines = self::all_but( $invocation->option( 'lines' ) );
		$first     = true;

		foreach ( $paths as $given ) {
			$resolved = $this->resolve_for_reading( $given, $sandbox, $ledger, $filesystem, 'cannot open' );
			if ( null === $resolved ) {
				$status = Exit_Status::failure();
				continue;
			}

			$first_before = $first;
			try {
				$info = $filesystem->info( $resolved->real() );
				if ( $info->is_directory() ) {
					$ledger->error_line( 'error reading ' . "'" . $given . "'", 'Is a directory' );
					$status = Exit_Status::failure();
					continue;
				}
				$this->hold_printed( $resolved, $sandbox, $ledger, 'cannot open' );
				if ( $headers ) {
					$ledger->out_label( ( $first ? '' : "\n" ) . '==> ' . ( $resolved->is_standard_input() ? 'standard input' : $given ) . " <==\n" );
				}
				$first = false;
				$ledger->scan_file();

				if ( null !== $bytes && null !== $but_bytes ) {
					// Never more than the output budget plus one byte, so the ledger says when it stops.
					$keep = min( $info->size() - $but_bytes, $ledger->limits()->max_output_bytes() + 1 );
					if ( $keep > 0 ) {
						$ledger->out( $filesystem->sample( $resolved->real(), $keep ) );
					}
					continue;
				}
				if ( null !== $bytes ) {
					// Byte reads do not need a complete logical line. This
					// remains useful when a minified file exceeds the line cap.
					if ( $bytes > 0 ) {
						$ledger->out( $filesystem->sample( $resolved->real(), $bytes ) );
					}
					continue;
				}
				if ( null !== $but_lines ) {
					$this->print_all_but( $filesystem->lines( $resolved->real() ), $but_lines, $ledger );
					continue;
				}
				if ( 0 === $lines ) {
					continue;
				}

				$printed = 0;
				foreach ( $filesystem->lines( $resolved->real() ) as $line ) {
					++$printed;
					$ledger->out( $line );
					if ( $printed >= $lines ) {
						break; // Do not read an unrequested oversized next line.
					}
				}
			} catch ( Filesystem_Exception $failure ) {
				$this->report_filesystem( $failure, $given, $ledger, 'cannot open' );
				$status = Exit_Status::failure();
			} catch ( Throwable $failure ) {
				// Preserve expected control-flow exceptions; contextualize unexpected failures.
				throw Unexpected_Failure::during( 'reading the start of a file', $failure );
			} finally {
				if ( ! $ledger->release() ) {
					// As for a file never opened: no header, and the next one is the first.
					$first  = $first_before;
					$status = Exit_Status::failure();
				}
			}
		}

		return $status;
	}

	/**
	 * Print every line but the last $count, holding at most $count lines.
	 *
	 * @param iterable<string> $lines  The file's lines.
	 * @param int              $count  Lines to leave out at the end.
	 * @param Budget_Ledger    $ledger The only way to write output.
	 * @return void
	 */
	private function print_all_but( iterable $lines, int $count, Budget_Ledger $ledger ): void {
		$held = new SplQueue();
		foreach ( $lines as $line ) {
			$ledger->tick();
			$held->enqueue( $line );
			if ( $held->count() > $count ) {
				$ledger->out( $held->dequeue() );
			}
		}
	}

	/**
	 * How many lines or bytes a count written -N leaves out, or null for a
	 * count from the start.
	 *
	 * @param mixed $count The option value; the specification keeps a written sign.
	 * @return int|null
	 */
	private static function all_but( $count ): ?int {
		return is_string( $count ) && '-' === substr( $count, 0, 1 ) ? (int) substr( $count, 1 ) : null;
	}

	/**
	 * GNU head quotes operands in messages.
	 *
	 * @return bool
	 */
	protected function quotes_operands(): bool {
		return true;
	}

	/**
	 * GNU head calls standard input 'standard input', as in "error reading
	 * 'standard input'".
	 *
	 * @param string $given "-" or empty.
	 * @return string
	 */
	protected function standard_input_name( string $given ): string {
		unset( $given );

		return 'standard input';
	}

	/**
	 * GNU head says "cannot open 'x' for reading".
	 *
	 * @return string
	 */
	protected function open_failure_suffix(): string {
		return ' for reading';
	}
}
