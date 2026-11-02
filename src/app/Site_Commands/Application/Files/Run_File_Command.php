<?php
/**
 * Runs one file command.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Application\Files;

use Uncanny_Automator\App\Site_Commands\Domain\Files\Budget\Limits;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Exit_Status;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Unexpected_Failure;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Sandbox\Denylist;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Sandbox\Sandbox;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Transcript\Stream;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Transcript\Transcript;
use Uncanny_Automator\App\Site_Commands\Ports\Files\Access_Audit;
use Uncanny_Automator\App\Site_Commands\Ports\Files\Failure_Reporter;
use Uncanny_Automator\App\Site_Commands\Ports\Files\Sandbox_Roots;

/**
 * Run one command and return its transcript. Never throws for a path or
 * content condition; those are exit codes in the transcript.
 */
final class Run_File_Command {

	public const NO_ROOTS = "files: no readable roots on this host; WordPress could not name a directory to read, or the host uses Windows paths, which are not supported yet\n";

	/**
	 * The commands.
	 *
	 * @var File_Command_Registry
	 */
	private File_Command_Registry $registry;

	/**
	 * Runs one command into a transcript.
	 *
	 * @var Stage_Runner
	 */
	private Stage_Runner $stages;

	/**
	 * The readable roots.
	 *
	 * @var Sandbox_Roots
	 */
	private Sandbox_Roots $roots;

	/**
	 * The budgets.
	 *
	 * @var Limits
	 */
	private Limits $limits;

	/**
	 * Where refused paths are recorded.
	 *
	 * @var Access_Audit
	 */
	private Access_Audit $audit;

	/**
	 * Where unexpected failures are reported.
	 *
	 * @var Failure_Reporter
	 */
	private Failure_Reporter $failures;

	/**
	 * Content that must not be read.
	 *
	 * @var Denylist
	 */
	private Denylist $denylist;

	/**
	 * Constructor.
	 *
	 * @param File_Command_Registry $registry The commands.
	 * @param Stage_Runner          $stages   Runs one command into a transcript.
	 * @param Sandbox_Roots         $roots    The readable roots.
	 * @param Limits                $limits   The budgets.
	 * @param Access_Audit          $audit    Where refused paths are recorded.
	 * @param Failure_Reporter      $failures Where unexpected failures are reported.
	 * @param Denylist              $denylist Content that must not be read.
	 */
	public function __construct( File_Command_Registry $registry, Stage_Runner $stages, Sandbox_Roots $roots, Limits $limits, Access_Audit $audit, Failure_Reporter $failures, Denylist $denylist ) {
		$this->registry = $registry;
		$this->stages   = $stages;
		$this->roots    = $roots;
		$this->limits   = $limits;
		$this->audit    = $audit;
		$this->failures = $failures;
		$this->denylist = $denylist;
	}

	/**
	 * Run one command.
	 *
	 * @param File_Command_Request $request The command and its arguments.
	 * @return Transcript
	 */
	public function execute( File_Command_Request $request ): Transcript {
		$transcript = new Transcript();
		$command    = $this->registry->get( $request->name() );
		if ( null === $command ) {
			$transcript->write( Stream::STDERR, sprintf( "files: %s: command not found\n", $request->name() ) );
			$transcript->finish( Exit_Status::of( 127 ) );
			return $transcript;
		}

		try {
			$roots = $this->roots->roots();
			if ( array() === $roots ) {
				$transcript->write( Stream::STDERR, self::NO_ROOTS );
				$transcript->finish( Exit_Status::trouble() );
				return $transcript;
			}
			$sandbox                  = Sandbox::from_roots( $roots, $this->denylist );
			list( $status, $denials ) = $this->stages->run( $command, $request, $sandbox, $this->limits, $transcript, null );
			foreach ( $denials as $requested ) {
				$this->audit->denied( $request->name(), $requested );
			}
		} catch ( \Throwable $failure ) {
			$this->failures->report( $request->name(), $failure );
			$context = $failure instanceof Unexpected_Failure ? ' while ' . $failure->operation() : '';
			$transcript->write( Stream::STDERR, sprintf( "%s: internal error%s; read current state before retrying\n", $request->name(), $context ) );
			$status = Exit_Status::failure();
		}
		$transcript->finish( $status );

		return $transcript;
	}
}
