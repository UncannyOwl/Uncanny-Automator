<?php
/**
 * pwd: print the working directory.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Commands;

use Uncanny_Automator\App\Site_Commands\Domain\Files\Budget\Budget_Ledger;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Exit_Status;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Invocation;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Specification;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Read_Only_Filesystem;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Sandbox\Sandbox;

/**
 * Prints the directory relative paths resolve against.
 */
final class Pwd extends Abstract_Command {

	/**
	 * The command's contract.
	 *
	 * @return Specification
	 */
	public static function specification(): Specification {
		return new Specification(
			'pwd',
			'Print the working directory.',
			'Show the directory used to resolve relative paths: the site root by default, or --cwd for this call. It does not change later calls.',
			array(),
			array()
		);
	}

	/**
	 * Print the base directory.
	 *
	 * @param Invocation           $invocation The validated arguments.
	 * @param Sandbox              $sandbox    Resolves every path.
	 * @param Budget_Ledger        $ledger     The only way to write output.
	 * @param Read_Only_Filesystem $filesystem The only way to read.
	 * @return Exit_Status
	 */
	public function run( Invocation $invocation, Sandbox $sandbox, Budget_Ledger $ledger, Read_Only_Filesystem $filesystem ): Exit_Status {
		$ledger->out( $sandbox->base() . "\n" );

		return Exit_Status::success();
	}
}
