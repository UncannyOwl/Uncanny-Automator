<?php
/**
 * date: print the current date and time.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.DateTime.RestrictedFunctions.date_date -- GNU date prints the runtime's local time, which is what date() formats.

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Commands;

use Uncanny_Automator\App\Site_Commands\Domain\Files\Budget\Budget_Ledger;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Exit_Status;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Invocation;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Option;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Specification;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Read_Only_Filesystem;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Sandbox\Sandbox;

/**
 * GNU date: the current time in the default format, UTC, or ISO 8601. The
 * agent reads timestamps all day and needs to know what "now" is.
 */
final class Date extends Abstract_Command {

	/**
	 * The command's contract.
	 *
	 * @return Specification
	 */
	public static function specification(): Specification {
		return new Specification(
			'date',
			'Print the current date and time.',
			'Show the current PHP runtime date and time. Use -u for UTC or -I for a timestamp with timezone offset. The WordPress timezone is reported by option get timezone_string and may differ.',
			array(
				Option::flag( 'utc', 'u', 'Print Coordinated Universal Time.' ),
				Option::flag( 'iso_8601', 'I', 'Print in ISO 8601 format with seconds and offset.' ),
			),
			array()
		);
	}

	/**
	 * Print the time.
	 *
	 * @param Invocation           $invocation The validated arguments.
	 * @param Sandbox              $sandbox    Resolves every path.
	 * @param Budget_Ledger        $ledger     The only way to write output.
	 * @param Read_Only_Filesystem $filesystem The only way to read.
	 * @return Exit_Status
	 */
	public function run( Invocation $invocation, Sandbox $sandbox, Budget_Ledger $ledger, Read_Only_Filesystem $filesystem ): Exit_Status {
		$now = $ledger->clock()->wall_time();
		$utc = $invocation->flag( 'utc' );

		if ( $invocation->flag( 'iso_8601' ) ) {
			$ledger->out( ( $utc ? gmdate( 'Y-m-d\TH:i:sP', $now ) : date( 'Y-m-d\TH:i:sP', $now ) ) . "\n" );

			return Exit_Status::success();
		}

		$format = static fn ( string $pattern ): string => $utc ? gmdate( $pattern, $now ) : date( $pattern, $now );
		// GNU pads the day to two characters with a space: "Sat Sep  6 12:34:56 UTC 2026".
		$ledger->out( sprintf( "%s %s %2d %s %s %s\n", $format( 'D' ), $format( 'M' ), (int) $format( 'j' ), $format( 'H:i:s' ), $utc ? 'UTC' : $format( 'T' ), $format( 'Y' ) ) );

		return Exit_Status::success();
	}
}
