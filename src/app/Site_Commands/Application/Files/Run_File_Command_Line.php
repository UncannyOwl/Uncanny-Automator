<?php
/**
 * Runs one file-command line: one command, or stages joined by pipes.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Application\Files;

use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Command_Line;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Command_Word;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Transcript\Transcript;

/**
 * Reads the line the way a shell would split it, then runs it as one
 * command or as a pipeline. `xargs <command> [arguments]` is a stage that
 * passes the previous stage's lines to that command as file operands.
 */
final class Run_File_Command_Line {

	/**
	 * The commands.
	 *
	 * @var File_Command_Registry
	 */
	private File_Command_Registry $registry;

	/**
	 * Runs one command.
	 *
	 * @var Run_File_Command
	 */
	private Run_File_Command $command;

	/**
	 * Runs a pipeline.
	 *
	 * @var Run_File_Pipeline
	 */
	private Run_File_Pipeline $pipeline;

	/**
	 * Constructor.
	 *
	 * @param File_Command_Registry $registry The commands.
	 * @param Run_File_Command      $command  Runs one command.
	 * @param Run_File_Pipeline     $pipeline Runs a pipeline.
	 */
	public function __construct( File_Command_Registry $registry, Run_File_Command $command, Run_File_Pipeline $pipeline ) {
		$this->registry = $registry;
		$this->command  = $command;
		$this->pipeline = $pipeline;
	}

	/**
	 * Run a line.
	 *
	 * @param string $line The command line, such as "grep -rn add_action wp-content/plugins/x | head -n 20".
	 * @return Transcript
	 *
	 * @throws \Uncanny_Automator\App\Site_Commands\Domain\Command_Error When the line is malformed.
	 */
	public function execute( string $line ): Transcript {
		$requests = array();
		foreach ( Command_Line::parse( $line ) as $words ) {
			$requests[] = $this->request( $words );
		}
		if ( 1 === count( $requests ) && Run_File_Pipeline::XARGS !== $requests[0]->name() ) {
			return $this->command->execute( $requests[0] );
		}

		return $this->pipeline->execute( File_Pipeline_Request::of( $requests ) );
	}

	/**
	 * The request one stage's words make.
	 *
	 * @param Command_Word[] $words The stage's words; the first names the command.
	 * @return File_Command_Request
	 */
	private function request( array $words ): File_Command_Request {
		$name = array_shift( $words )->text();
		if ( Run_File_Pipeline::XARGS === $name ) {
			$target        = array() === $words ? '' : array_shift( $words )->text();
			$specification = $this->registry->specification( $target );
			$arguments     = null === $specification ? array() : Command_Line::arguments( $specification, $words );

			return new File_Command_Request( $name, array( 'command' => $target ) + $arguments );
		}
		$specification = $this->registry->specification( $name );
		if ( null === $specification ) {
			return new File_Command_Request( $name, array() );
		}
		list( $arguments, $globs ) = Command_Line::parse_arguments( $specification, $words );

		return new File_Command_Request( $name, $arguments, $globs );
	}
}
