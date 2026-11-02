<?php
/**
 * uniq: collapse adjacent repeated lines.
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
 * GNU uniq: collapse adjacent repeated lines. With -c, prefix the count
 * in a seven-wide right-aligned column, exactly as GNU prints it.
 */
final class Uniq extends Abstract_Command {

	/**
	 * The command's contract.
	 *
	 * @return Specification
	 */
	public static function specification(): Specification {
		return new Specification(
			'uniq',
			'Collapse adjacent repeated lines.',
			'Collapse neighboring equal lines. Sort first to group all duplicates. Use -c (--count) to count each group. To rank counts, follow with sort -rn.',
			array(
				Option::flag( 'count', 'c', 'Prefix lines by the number of occurrences.' ),
				Option::flag( 'repeated', 'd', 'Only print duplicate lines, one for each group.' ),
				Option::flag( 'ignore_case', 'i', 'Ignore differences in case when comparing.' ),
			),
			array(
				Operand::paths( 'File to read (one). Reads the previous stage when omitted in a pipeline.', false, 1 ),
			),
			true
		);
	}

	/**
	 * Print each run of equal lines once.
	 *
	 * @param Invocation           $invocation The validated arguments.
	 * @param Sandbox              $sandbox    Resolves every path.
	 * @param Budget_Ledger        $ledger     The only way to write output.
	 * @param Read_Only_Filesystem $filesystem The only way to read.
	 * @return Exit_Status
	 */
	public function run( Invocation $invocation, Sandbox $sandbox, Budget_Ledger $ledger, Read_Only_Filesystem $filesystem ): Exit_Status {
		$paths    = $invocation->paths();
		$given    = (string) ( $paths[0] ?? '-' );
		$resolved = $this->resolve_for_reading( $given, $sandbox, $ledger, $filesystem );
		if ( null === $resolved ) {
			return Exit_Status::failure();
		}

		$count         = $invocation->flag( 'count' );
		$repeated_only = $invocation->flag( 'repeated' );
		$fold          = $invocation->flag( 'ignore_case' );
		$current       = null;
		$occurrences   = 0;

		$emit = static function ( ?string $line, int $seen ) use ( $count, $repeated_only, $ledger ): void {
			if ( null === $line || ( $repeated_only && $seen < 2 ) ) {
				return;
			}
			$ledger->out( $line, $count ? sprintf( '%7d ', $seen ) : '' );
		};

		$written = true;
		try {
			$ledger->scan_file();
			$this->hold_printed( $resolved, $sandbox, $ledger );
			foreach ( $filesystem->lines( $resolved->real() ) as $line ) {
				$ledger->tick();
				$line = "\n" === substr( $line, -1 ) ? $line : $line . "\n";
				$same = null !== $current && 0 === ( $fold ? strcasecmp( $current, $line ) : strcmp( $current, $line ) );
				if ( $same ) {
					++$occurrences;
					continue;
				}
				$emit( $current, $occurrences );
				$current     = $line;
				$occurrences = 1;
			}
			$emit( $current, $occurrences );
		} catch ( Filesystem_Exception $failure ) {
			$this->report_filesystem( $failure, $given, $ledger );

			return Exit_Status::failure();
		} catch ( Throwable $failure ) {
			// Preserve expected control-flow exceptions; contextualize unexpected failures.
			throw Unexpected_Failure::during( 'grouping adjacent lines', $failure );
		} finally {
			$written = $ledger->release();
		}

		return $written ? Exit_Status::success() : Exit_Status::failure();
	}
}
