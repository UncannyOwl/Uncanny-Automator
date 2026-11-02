<?php
/**
 * cat: print whole files.
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
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Content_Kind;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Read_Only_Filesystem;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Sandbox\Sandbox;

/**
 * Prints small text files in full, refusing directories, binaries and files
 * over the cat size limit.
 */
final class Cat extends Abstract_Command {

	/**
	 * Cat judges binary files itself, after its size check.
	 *
	 * @return bool
	 */
	protected static function reads_binary(): bool {
		return true;
	}

	/**
	 * The command's contract.
	 *
	 * @return Specification
	 */
	public static function specification(): Specification {
		return new Specification(
			'cat',
			'Print whole files.',
			'Read small text files in full. Use -n (--number) for line numbers. For large files, use head, tail, or sed. Binary files and protected content are refused.',
			array(
				Option::flag( 'number', 'n', 'Number all output lines.' ),
			),
			array(
				Operand::paths( 'Files to print, relative to the site root or absolute.', true ),
			),
			true
		);
	}

	/**
	 * Print each file.
	 *
	 * @param Invocation           $invocation The validated arguments.
	 * @param Sandbox              $sandbox    Resolves every path.
	 * @param Budget_Ledger        $ledger     The only way to write output.
	 * @param Read_Only_Filesystem $filesystem The only way to read.
	 * @return Exit_Status
	 */
	public function run( Invocation $invocation, Sandbox $sandbox, Budget_Ledger $ledger, Read_Only_Filesystem $filesystem ): Exit_Status {
		$status      = Exit_Status::success();
		$number      = $invocation->flag( 'number' );
		$line_number = 0;

		foreach ( $invocation->paths() as $given ) {
			$resolved = $this->resolve_for_reading( $given, $sandbox, $ledger, $filesystem );
			if ( null === $resolved ) {
				$status = Exit_Status::failure();
				continue;
			}

			$numbered = $line_number;
			try {
				$info = $filesystem->info( $resolved->real() );
				if ( $info->is_directory() ) {
					throw Filesystem_Exception::is_a_directory( $given );
				}
				if ( $info->size() > $ledger->limits()->max_cat_file_bytes() ) {
					$ledger->error_line( $given, sprintf( 'file too large for cat (%d bytes); use head, tail, or sed -n', $info->size() ) );
					$status = Exit_Status::failure();
					continue;
				}
				if ( Content_Kind::BINARY === Content_Kind::detect( $filesystem->sample( $resolved->real(), Content_Kind::SAMPLE_BYTES ) ) ) {
					$ledger->error_line( $given, 'binary file; use file, stat, or md5sum' );
					$status = Exit_Status::failure();
					continue;
				}

				$ledger->scan_file();
				$this->hold_printed( $resolved, $sandbox, $ledger );
				foreach ( $filesystem->lines( $resolved->real() ) as $line ) {
					$ledger->tick();
					$ledger->out( $line, $number ? sprintf( "%6d\t", ++$line_number ) : '' );
				}
			} catch ( Filesystem_Exception $failure ) {
				$this->report_filesystem( $failure, $given, $ledger );
				$status = Exit_Status::failure();
			} catch ( Throwable $failure ) {
				// Preserve expected control-flow exceptions; contextualize unexpected failures.
				throw Unexpected_Failure::during( 'reading file content', $failure );
			} finally {
				if ( ! $ledger->release() ) {
					// As for a file never opened: its lines are not numbered.
					$line_number = $numbered;
					$status      = Exit_Status::failure();
				}
			}
		}

		return $status;
	}
}
