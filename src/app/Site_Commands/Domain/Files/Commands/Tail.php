<?php
/**
 * tail: print the last lines of files.
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
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Tail_Read_Limit_Exceeded;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Unexpected_Failure;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Read_Only_Filesystem;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Sandbox\Sandbox;

/**
 * Prints the last lines, or bytes, of each file: a snapshot, not a watcher.
 */
final class Tail extends Abstract_Command {

	/**
	 * The command's contract.
	 *
	 * @return Specification
	 */
	public static function specification(): Specification {
		return new Specification(
			'tail',
			'Print the last lines of files.',
			'Read the end of a text file, useful for recent log entries. Use -n N (--lines=N, default 10) or -c N (--bytes=N). Use -n +N to print from line N on, or -c +N from byte N on. This is a snapshot, not a live log watcher. In a pipeline, omit the file operands to read the previous output.',
			array(
				Option::integer( 'lines', 'n', 'Number of lines to print; +N prints from line N on.', 10, -5000, 5000 ),
				Option::integer( 'bytes', 'c', 'Print this many bytes from the end instead of lines; +N prints from byte N on.', null, -65536, 65536 ),
			),
			array(
				Operand::paths( 'Files to read.', true ),
			),
			true
		);
	}

	/**
	 * Print the end of each file.
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
		// GNU: +N counts from the start of the file, N and -N from the end.
		$from_byte = self::from_start( $invocation->option( 'bytes' ) );
		$from_line = self::from_start( $invocation->option( 'lines' ) );
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

				if ( null !== $bytes && null !== $from_byte ) {
					// Never more than the output budget plus one byte, so the ledger says when it stops.
					$skip = max( 0, $from_byte - 1 );
					$read = $filesystem->sample( $resolved->real(), $skip + $ledger->limits()->max_output_bytes() + 1 );
					$ledger->out( (string) substr( $read, $skip ) );
					continue;
				}
				if ( null !== $bytes ) {
					$ledger->out( $filesystem->tail_bytes( $resolved->real(), abs( $bytes ) ) );
					continue;
				}
				if ( null !== $from_line ) {
					$skip = max( 0, $from_line - 1 );
					foreach ( $filesystem->lines( $resolved->real() ) as $line ) {
						$ledger->tick();
						if ( $skip > 0 ) {
							--$skip;
							continue;
						}
						$ledger->out( $line );
					}
					continue;
				}

				$checkpoint = static function () use ( $ledger ): void {
					$ledger->check_time();
					$ledger->check_memory();
				};
				foreach ( $filesystem->tail_lines( $resolved->real(), $lines, $checkpoint ) as $line ) {
					$ledger->out( $line );
				}
			} catch ( Tail_Read_Limit_Exceeded $stopped ) {
				unset( $stopped );
				$ledger->truncate( 'stopped after reading 1 MB from the end; use tail --bytes or request fewer lines; results are partial' );
			} catch ( Filesystem_Exception $failure ) {
				$this->report_filesystem( $failure, $given, $ledger, 'cannot open' );
				$status = Exit_Status::failure();
			} catch ( Throwable $failure ) {
				// Preserve expected control-flow exceptions; contextualize unexpected failures.
				throw Unexpected_Failure::during( 'reading the end of a file', $failure );
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
	 * The line or byte a count written +N starts at, or null for a count
	 * from the end.
	 *
	 * @param mixed $count The option value; the specification keeps a written sign.
	 * @return int|null
	 */
	private static function from_start( $count ): ?int {
		return is_string( $count ) && '+' === substr( $count, 0, 1 ) ? (int) substr( $count, 1 ) : null;
	}

	/**
	 * GNU tail quotes operands in messages.
	 *
	 * @return bool
	 */
	protected function quotes_operands(): bool {
		return true;
	}

	/**
	 * GNU tail calls standard input 'standard input', as in "error reading
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
	 * GNU tail says "cannot open 'x' for reading".
	 *
	 * @return string
	 */
	protected function open_failure_suffix(): string {
		return ' for reading';
	}
}
