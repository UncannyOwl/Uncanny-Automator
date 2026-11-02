<?php
/**
 * Runs a file-command pipeline.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Application\Files;

use Uncanny_Automator\App\Site_Commands\Domain\Files\Budget\Clock;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Budget\Limits;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Exit_Status;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Budget_Exceeded;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Unexpected_Failure;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Sandbox\Denylist;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Sandbox\Sandbox;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Transcript\Stream;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Transcript\Transcript;
use Uncanny_Automator\App\Site_Commands\Ports\Files\Access_Audit;
use Uncanny_Automator\App\Site_Commands\Ports\Files\Failure_Reporter;
use Uncanny_Automator\App\Site_Commands\Ports\Files\Sandbox_Roots;

/**
 * `a | b | c` without a shell. Each stage runs through the same command
 * classes as a single call, with the previous stage's stdout as its
 * standard input. The transcript carries every stage's stderr in order
 * and only the last stage's stdout, and the exit status is the last
 * stage's, which is what a shell reports.
 *
 * `xargs` is pipeline glue rather than a command: the stage `xargs md5sum`
 * turns the previous stage's lines into file operands for md5sum, in batches
 * the size of that command's operand limit, and exits 123 when any batch
 * failed, as GNU xargs does. Empty input runs nothing.
 */
final class Run_File_Pipeline {

	public const XARGS = 'xargs';

	public const MAX_XARGS_BATCHES = 200;

	public const NO_ROOTS = Run_File_Command::NO_ROOTS;

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
	 * Time, for the shared deadline.
	 *
	 * @var Clock
	 */
	private Clock $clock;

	/**
	 * The budgets of the last stage.
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
	 * @param Clock                 $clock    Time, for the shared deadline.
	 * @param Limits                $limits   The budgets of the last stage.
	 * @param Access_Audit          $audit    Where refused paths are recorded.
	 * @param Failure_Reporter      $failures Where unexpected failures are reported.
	 * @param Denylist              $denylist Content that must not be read.
	 */
	public function __construct( File_Command_Registry $registry, Stage_Runner $stages, Sandbox_Roots $roots, Clock $clock, Limits $limits, Access_Audit $audit, Failure_Reporter $failures, Denylist $denylist ) {
		$this->registry = $registry;
		$this->stages   = $stages;
		$this->roots    = $roots;
		$this->clock    = $clock;
		$this->limits   = $limits;
		$this->audit    = $audit;
		$this->failures = $failures;
		$this->denylist = $denylist;
	}

	/**
	 * Run the pipeline.
	 *
	 * @param File_Pipeline_Request $pipeline The stages.
	 * @return Transcript
	 */
	public function execute( File_Pipeline_Request $pipeline ): Transcript {
		$transcript      = new Transcript();
		$started_at      = $this->clock->now();
		$status          = Exit_Status::success();
		$partial         = array();
		$pending_notices = array();
		$active_stage    = null;
		$output          = $this->stages->create_ledger( 'pipeline', $this->limits, $transcript, $started_at );

		try {
			$roots = $this->roots->roots();
			if ( array() === $roots ) {
				$transcript->write( Stream::STDERR, self::NO_ROOTS );
				$transcript->finish( Exit_Status::trouble() );
				return $transcript;
			}
			$sandbox = Sandbox::from_roots( $roots, $this->denylist );
			$stdin   = null;
			$stages  = $pipeline->stages();
			$last    = count( $stages ) - 1;
			foreach ( $stages as $index => $stage ) {
				$active_stage     = sprintf( 'stage %d (%s)', $index + 1, $stage->name() );
				$is_last          = $index === $last;
				$limits           = $is_last ? $this->limits : $this->limits->for_intermediate_stage();
				$stage_sandbox    = $is_last ? $sandbox : $sandbox->for_intermediate_stage();
				$stage_transcript = new Transcript();
				$pending_notices  = array();
				if ( self::XARGS === $stage->name() ) {
					$status = $this->run_xargs( $stage, $stdin ?? '', $stage_sandbox, $limits, $stage_transcript, $started_at, $pending_notices );
				} else {
					$status = $this->run_stage( $stage, $stdin, $stage_sandbox, $limits, $stage_transcript, $started_at );
				}
				if ( $stage_transcript->is_truncated() ) {
					$partial[] = sprintf( 'stage %d (%s)', $index + 1, $stage->name() );
					$transcript->mark_truncated();
				}
				// Every visible byte shares the final response budget. Keep
				// chunk order, including diagnostics interleaved with results.
				foreach ( $stage_transcript->chunks() as $chunk ) {
					list( $stream, $text ) = $chunk;
					if ( Stream::STDERR === $stream ) {
						$output->err( $text );
					} elseif ( $is_last ) {
						$output->out( $text );
					}
				}
				if ( ! $is_last ) {
					$stdin = $stage_transcript->stdout();
				}
			}
		} catch ( Budget_Exceeded $exceeded ) {
			// Data can fill the aggregate budget before the stage's final
			// progress notice is copied. Preserve only these bounded, generated
			// notices (never arbitrary stderr), then always finish with partial.
			unset( $exceeded );
			foreach ( $pending_notices as $notice ) {
				$transcript->write( Stream::STDERR, $notice );
			}
			if ( null !== $active_stage && ! in_array( $active_stage, $partial, true ) ) {
				$partial[] = $active_stage;
			}
		} catch ( \Throwable $failure ) {
			$this->failures->report( 'pipeline', $failure );
			$context = null === $active_stage ? '' : ' in ' . $active_stage;
			if ( $failure instanceof Unexpected_Failure ) {
				$context .= ' while ' . $failure->operation();
			}
			$transcript->write( Stream::STDERR, 'pipeline: internal error' . $context . "; read current state before retrying\n" );
			$status = Exit_Status::failure();
		}

		if ( $transcript->is_truncated() ) {
			$detail = array() === $partial ? 'the final output budget was reached' : 'a budget stopped ' . implode( ' and ', $partial ) . ' early';
			$transcript->write( Stream::STDERR, sprintf( "pipeline: results are partial; %s, see the notice above\n", $detail ) );
		}
		$transcript->finish( $status );

		return $transcript;
	}

	/**
	 * Run one ordinary stage.
	 *
	 * @param File_Command_Request $stage      The stage.
	 * @param string|null          $stdin      The previous stage's output.
	 * @param Sandbox              $sandbox    The readable roots.
	 * @param Limits               $limits     The stage's budgets.
	 * @param Transcript           $transcript Where the stage's output goes.
	 * @param float                $started_at When the pipeline started.
	 * @return Exit_Status
	 */
	private function run_stage( File_Command_Request $stage, ?string $stdin, Sandbox $sandbox, Limits $limits, Transcript $transcript, float $started_at ): Exit_Status {
		$command = $this->registry->get( $stage->name() );
		if ( null === $command ) {
			$transcript->write( Stream::STDERR, $this->not_found( 'files', $stage->name() ) );
			return Exit_Status::of( 127 );
		}
		if ( null !== $stdin && ! $command::specification()->reads_standard_input() && ! self::names_input( $stage ) ) {
			// ls, find, stat, du, file would ignore the previous stage and run on
			// their own defaults, silently answering a different question.
			$takes_files = false;
			foreach ( array_keys( $command::specification()->operands() ) as $operand_name ) {
				if ( 'paths' === $operand_name || 'path' === $operand_name ) {
					$takes_files = true;
				}
			}
			$transcript->write(
				Stream::STDERR,
				$takes_files
					? sprintf( "pipeline: %1\$s does not read the previous stage; use \"xargs %1\$s\" to pass its lines as paths\n", $stage->name() )
					: sprintf( "pipeline: %s does not read the previous stage and takes no files; run it on its own\n", $stage->name() )
			);
			return Exit_Status::trouble();
		}
		list( $status, $denials ) = $this->stages->run( $command, $stage, $sandbox, $limits, $transcript, $stdin, $started_at );
		foreach ( $denials as $requested ) {
			$this->audit->denied( $stage->name(), $requested );
		}

		return $status;
	}

	/**
	 * Run the target command over the previous stage's lines as file operands.
	 *
	 * @param File_Command_Request $stage          The xargs stage; its "command" argument names the target.
	 * @param string               $stdin          The previous stage's output.
	 * @param Sandbox              $sandbox        The readable roots.
	 * @param Limits               $limits         The stage's budgets.
	 * @param Transcript           $transcript     Where the stage's output goes.
	 * @param float                $started_at     When the pipeline started.
	 * @param string[]             $budget_notices Bounded progress diagnostics kept if final output fills its budget.
	 * @return Exit_Status
	 *
	 * @throws \Throwable An unexpected failure, for the caller to report.
	 */
	private function run_xargs( File_Command_Request $stage, string $stdin, Sandbox $sandbox, Limits $limits, Transcript $transcript, float $started_at, array &$budget_notices ): Exit_Status {
		$arguments = $stage->arguments();
		$target    = $arguments['command'] ?? null;
		unset( $arguments['command'] );
		if ( ! is_string( $target ) || '' === $target ) {
			$transcript->write( Stream::STDERR, "xargs: missing command; write it after xargs, such as \"xargs md5sum\"\n" );
			return Exit_Status::trouble();
		}
		if ( 0 === strpos( $target, '-' ) ) {
			// -n1, -I{}, -0 and the like: GNU options this xargs does not have.
			$transcript->write( Stream::STDERR, sprintf( "xargs: %s: this xargs takes no options; it passes every input line to the command as an operand, in batches the command accepts. Write the command right after xargs, such as \"xargs md5sum\" or \"xargs grep -n add_action\"\n", $target ) );
			return Exit_Status::failure();
		}
		$command = $this->registry->get( $target );
		if ( null === $command ) {
			$transcript->write( Stream::STDERR, $this->not_found( 'xargs', $target ) );
			return Exit_Status::of( 127 );
		}
		$operand = null;
		foreach ( $command::specification()->operands() as $name => $candidate ) {
			if ( 'paths' === $name || 'path' === $name ) {
				$operand = $candidate;
				break;
			}
		}
		if ( null === $operand ) {
			$transcript->write( Stream::STDERR, sprintf( "xargs: %s: does not take file operands\n", $target ) );
			return Exit_Status::trouble();
		}
		$batch_size    = $operand->many() ? max( 1, $operand->max_items() ) : 1;
		$maximum_items = self::MAX_XARGS_BATCHES * $batch_size;
		$ledger        = $this->stages->create_ledger( $target, $limits, $transcript, $started_at );
		$items         = array();
		$item_count    = 0;

		try {
			$ledger->check_time();
			$ledger->check_memory();
			foreach ( self::input_lines( $stdin ) as $line ) {
				$ledger->tick();
				$line = trim( $line );
				if ( '' !== $line ) {
					++$item_count;
					// Count the remainder for an honest truncation message,
					// but retain only operands that can actually be executed.
					if ( $item_count <= $maximum_items ) {
						$items[] = $line;
					}
				}
			}
		} catch ( Budget_Exceeded $exceeded ) {
			unset( $exceeded );
			return Exit_Status::success();
		}
		if ( array() === $items ) {
			return Exit_Status::success();
		}
		if ( array() !== ( $arguments[ $operand->name() ] ?? array() ) ) {
			$transcript->write( Stream::STDERR, sprintf( "xargs: %s: the previous stage supplies the %s; remove the argument from the xargs stage\n", $target, $operand->name() ) );
			return Exit_Status::trouble();
		}

		$batches        = array_chunk( $items, $batch_size );
		$batches_capped = $item_count > $maximum_items;
		$any_failed     = false;
		$done           = 0;
		foreach ( $batches as $batch ) {
			// Accepted empty operands must not shadow the filenames from stdin.
			$request                  = new File_Command_Request( $target, array_replace( $arguments, array( $operand->name() => $operand->many() ? $batch : $batch[0] ) ) );
			list( $status, $denials ) = $this->stages->run( $command, $request, $sandbox, $limits, $transcript, null, $started_at, $ledger );
			foreach ( $denials as $requested ) {
				$this->audit->denied( $target, $requested );
			}
			$any_failed = $any_failed || ! $status->is_success();
			++$done;
			if ( $transcript->is_truncated() && $done < count( $batches ) ) {
				// One budget for the whole xargs stage, not one per batch.
				$notice           = sprintf( "xargs: stopped after %d of %d batches; results are partial. Narrow the previous stage\n", $done, count( $batches ) );
				$budget_notices[] = $notice;
				$transcript->write( Stream::STDERR, $notice );
				break;
			}
		}
		if ( $batches_capped ) {
			$notice           = sprintf( "xargs: input limited to the first %d of %d items; results are partial. Narrow the previous stage\n", self::MAX_XARGS_BATCHES * $batch_size, $item_count );
			$budget_notices[] = $notice;
			$transcript->write( Stream::STDERR, $notice );
			$transcript->mark_truncated();
		}

		return $any_failed ? Exit_Status::of( 123 ) : Exit_Status::success();
	}

	/**
	 * The message for a name that is not a file command, listing the ones
	 * that are. cd gets the per-call working directory instead.
	 *
	 * @param string $prefix Who reports it: files or xargs.
	 * @param string $name   The name given.
	 * @return string
	 */
	private function not_found( string $prefix, string $name ): string {
		if ( 'cd' === $name ) {
			return sprintf( "%s: cd: there is no shell, so no directory to change to; give each command --cwd=DIR instead, such as ls --cwd=wp-content/plugins\n", $prefix );
		}

		return sprintf( "%s: %s: command not found; the file commands are %s\n", $prefix, $name, implode( ', ', $this->registry->names() ) );
	}

	/**
	 * Lines without allocating an array for all input.
	 *
	 * @param string $input The previous stage's output.
	 * @return \Generator<string>
	 */
	private static function input_lines( string $input ): \Generator {
		$offset = 0;
		$length = strlen( $input );
		while ( $offset < $length ) {
			$end = strpos( $input, "\n", $offset );
			if ( false === $end ) {
				yield substr( $input, $offset );
				break;
			}
			yield substr( $input, $offset, $end - $offset );
			$offset = $end + 1;
		}
	}

	/**
	 * Whether the stage names its own files.
	 *
	 * @param File_Command_Request $stage The stage.
	 * @return bool
	 */
	private static function names_input( File_Command_Request $stage ): bool {
		$arguments = $stage->arguments();

		return array() !== ( $arguments['paths'] ?? array() ) || array() !== ( $arguments['path'] ?? array() );
	}
}
