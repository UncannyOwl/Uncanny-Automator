<?php
/**
 * sed: print a line range of a file.
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
 * `sed -n 'N,Mp'` and nothing else. One sentence of the sed language: the
 * one people use to read a slice of a file.
 */
final class Sed extends Abstract_Command {

	/**
	 * The command's contract.
	 *
	 * Only printing is supported, so GNU's -n (print only what p selects) is
	 * always in effect. The flag is accepted so the line callers type,
	 * `sed -n '80,120p' file`, reads as it does in a shell.
	 *
	 * @return Specification
	 */
	public static function specification(): Specification {
		return new Specification(
			'sed',
			'Print a line range of a file.',
			"Read a specific line range without changing the file, for example sed -n '80,120p' path/to/file.php. Also accepts '5p' or '10,\$p'. No replacement, editing, or other sed scripts are supported.",
			array(
				Option::flag( 'quiet', 'n', 'Print only the selected lines; always on, since sed here only prints line ranges.' ),
			),
			array(
				new Operand( 'expression', "The range expression, for example '80,120p'.", false, true, null, 20, 64 ),
				Operand::paths( 'The file to read (one).', true, 1 ),
			),
			true
		);
	}

	/**
	 * Print the selected lines.
	 *
	 * @param Invocation           $invocation The validated arguments.
	 * @param Sandbox              $sandbox    Resolves every path.
	 * @param Budget_Ledger        $ledger     The only way to write output.
	 * @param Read_Only_Filesystem $filesystem The only way to read.
	 * @return Exit_Status
	 */
	public function run( Invocation $invocation, Sandbox $sandbox, Budget_Ledger $ledger, Read_Only_Filesystem $filesystem ): Exit_Status {
		$expression = (string) $invocation->operand( 'expression' );
		if ( ! preg_match( '/^\s*(\d+)(?:,(\d+|\$))?p\s*$/', $expression, $range ) ) {
			$ledger->err( "sed: -e expression #1, char 0: only line ranges like 80,120p are supported\n" );

			return Exit_Status::failure();
		}

		$start = max( 1, (int) $range[1] );
		$end   = $start;
		if ( isset( $range[2] ) ) {
			$end = '$' === $range[2] ? PHP_INT_MAX : (int) $range[2];
		}
		if ( $end < $start ) {
			$end = $start;
		}
		$cap = $ledger->limits()->max_sed_lines();
		if ( $end - $start + 1 > $cap && PHP_INT_MAX !== $end ) {
			$ledger->err( sprintf( "sed: range too large (max %d lines); split the range\n", $cap ) );

			return Exit_Status::failure();
		}

		$given    = (string) ( $invocation->paths()[0] ?? '' );
		$resolved = $this->resolve_for_reading( $given, $sandbox, $ledger, $filesystem, "can't read" );
		if ( null === $resolved ) {
			return Exit_Status::trouble();
		}

		$written = true;
		try {
			$info = $filesystem->info( $resolved->real() );
			if ( $info->is_directory() ) {
				$ledger->error_line( 'read error on ' . $given, 'Is a directory' );

				return Exit_Status::of( 4 );
			}
			$ledger->scan_file();
			$this->hold_printed( $resolved, $sandbox, $ledger, "can't read" );
			$number = 0;
			foreach ( $filesystem->lines( $resolved->real() ) as $line ) {
				++$number;
				if ( $number < $start ) {
					$ledger->tick();
					continue;
				}
				if ( $number > $end ) {
					break;
				}
				$ledger->out( $line );
			}
		} catch ( Filesystem_Exception $failure ) {
			$this->report_filesystem( $failure, $given, $ledger, "can't read" );

			return Exit_Status::trouble();
		} catch ( Throwable $failure ) {
			// Preserve expected control-flow exceptions; contextualize unexpected failures.
			throw Unexpected_Failure::during( 'selecting file lines', $failure );
		} finally {
			$written = $ledger->release();
		}

		return $written ? Exit_Status::success() : self::refused_status();
	}

	/**
	 * GNU sed exits 2 for a file it cannot read.
	 *
	 * @return Exit_Status
	 */
	protected static function refused_status(): Exit_Status {
		return Exit_Status::trouble();
	}

	/**
	 * GNU sed calls standard input stdin, as in "read error on stdin".
	 *
	 * @param string $given "-" or empty.
	 * @return string
	 */
	protected function standard_input_name( string $given ): string {
		unset( $given );

		return 'stdin';
	}
}
