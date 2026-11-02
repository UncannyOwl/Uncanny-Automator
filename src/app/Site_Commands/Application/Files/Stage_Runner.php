<?php
/**
 * Runs one file command into a transcript.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command output is plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Application\Files;

use Uncanny_Automator\App\Site_Commands\Domain\Files\Budget\Budget_Ledger;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Budget\Clock;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Budget\Limits;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Budget\Memory_Gauge;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Exit_Status;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\File_Command;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Invocation;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Operand;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Specification;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Access_Denied;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Budget_Exceeded;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Invalid_Invocation;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Line_Read_Limit_Exceeded;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Read_Only_Filesystem;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Standard_Input;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Sandbox\Sandbox;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Transcript\Stream;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Transcript\Transcript;

/**
 * Runs one command into a transcript, alone or as a pipeline stage. Every
 * failure a command can raise becomes an exit status here; nothing
 * escapes.
 */
final class Stage_Runner {

	/**
	 * Time, for the wall-time budget.
	 *
	 * @var Clock
	 */
	private Clock $clock;

	/**
	 * The filesystem commands read.
	 *
	 * @var Read_Only_Filesystem
	 */
	private Read_Only_Filesystem $filesystem;

	/**
	 * Memory in use, so a command stops before PHP's own limit.
	 *
	 * @var Memory_Gauge|null
	 */
	private ?Memory_Gauge $memory;

	/**
	 * Constructor.
	 *
	 * @param Clock                $clock      Time, for the wall-time budget.
	 * @param Read_Only_Filesystem $filesystem The filesystem commands read.
	 * @param Memory_Gauge|null    $memory     Memory in use.
	 */
	public function __construct( Clock $clock, Read_Only_Filesystem $filesystem, ?Memory_Gauge $memory = null ) {
		$this->clock      = $clock;
		$this->filesystem = $filesystem;
		$this->memory     = $memory;
	}

	/**
	 * Run one command.
	 *
	 * @param File_Command         $command    The command.
	 * @param File_Command_Request $request    Its arguments.
	 * @param Sandbox              $sandbox    The readable roots.
	 * @param Limits               $limits     The budgets.
	 * @param Transcript           $transcript Where output goes.
	 * @param string|null          $stdin      The previous stage's output, or null when there is none. When
	 *                                          present and the command reads standard input, an omitted file
	 *                                          operand becomes `-`.
	 * @param float|null           $started_at When the call started, for a shared deadline.
	 * @param Budget_Ledger|null   $ledger     A ledger shared across xargs batches.
	 * @return array{0: Exit_Status, 1: string[]} The exit status and the sandbox denials to audit.
	 *
	 * @throws \Throwable An unexpected failure, for the caller to report.
	 */
	public function run( File_Command $command, File_Command_Request $request, Sandbox $sandbox, Limits $limits, Transcript $transcript, ?string $stdin, ?float $started_at = null, ?Budget_Ledger $ledger = null ): array {
		$ledger            = $ledger ?? $this->create_ledger( $request->name(), $limits, $transcript, $started_at );
		$previous_denials  = count( $ledger->denials() );
		$previous_withheld = count( $ledger->withheld() );
		$filesystem        = null === $stdin ? $this->filesystem : new Standard_Input( $this->filesystem, $stdin );
		$specification     = $command::specification();

		try {
			$arguments = $request->arguments();
			if ( null !== $stdin && $specification->reads_standard_input() ) {
				$arguments = self::default_to_standard_input( $specification->operands(), $arguments );
			}
			$invocation = $specification->invoke( $arguments );
			$sandbox    = $this->enter_working_directory( $invocation, $sandbox, $filesystem, $request->name() );
			if ( array() !== $request->globs() ) {
				$invocation = ( new Pathname_Expansion() )->expand( $invocation, $specification->operands(), $request->globs(), $sandbox, $filesystem, $ledger );
			}
			try {
				$status = $command->run( $invocation, $sandbox, $ledger, $filesystem );
			} finally {
				// A command an exception stopped may still hold what it printed:
				// the content policy reads it before anything else is written.
				$ledger->release();
			}
		} catch ( Invalid_Invocation $invalid ) {
			$transcript->write( Stream::STDERR, implode( "\n", $invalid->lines() ) . "\n" );
			$status = Exit_Status::of( $invalid->exit_code() );
		} catch ( Line_Read_Limit_Exceeded $exceeded ) {
			unset( $exceeded );
			$transcript->mark_truncated();
			$file = self::long_line_file( $ledger, $request );
			$transcript->write( Stream::STDERR, ( $transcript->ends_with_newline() ? '' : "\n" ) . sprintf( '%1$s: %2$sstopped at a line longer than 1 MB; read part of it with head -c N%3$s or tail -c N%3$s; results are partial', $request->name(), null === $file ? '' : $file . ': ', null === $file ? '' : ' ' . $file ) . "\n" );
			$status = Exit_Status::success();
		} catch ( Budget_Exceeded $exceeded ) {
			// The ledger already wrote the notice. What was produced stands.
			unset( $exceeded );
			$status = Exit_Status::success();
		} catch ( Access_Denied $denied ) {
			$ledger->record_denial( $denied->requested() );
			$transcript->write( Stream::STDERR, sprintf( "%s: %s: Permission denied\n", $request->name(), $denied->requested() ) );
			$status = Exit_Status::failure();
		}
		// A withheld source fails the command as a file it may not read
		// would, even when a budget stop ended it first.
		foreach ( array_slice( $ledger->withheld(), $previous_withheld ) as $source ) {
			if ( $source->status()->code() > $status->code() ) {
				$status = $source->status();
			}
		}

		return array( $status, array_slice( $ledger->denials(), $previous_denials ) );
	}

	/**
	 * A ledger for one command. A pipeline stage may share it across its
	 * xargs batches.
	 *
	 * @param string     $name       The command name.
	 * @param Limits     $limits     The budgets.
	 * @param Transcript $transcript Where output goes.
	 * @param float|null $started_at When the call started.
	 * @return Budget_Ledger
	 */
	public function create_ledger( string $name, Limits $limits, Transcript $transcript, ?float $started_at = null ): Budget_Ledger {
		return new Budget_Ledger( $name, $limits, $this->clock, $transcript, $started_at, $this->memory );
	}

	/**
	 * The file a line was too long in: the one the command was reading,
	 * else its only file operand; null for the previous stage's output or
	 * when that cannot be told.
	 *
	 * @param Budget_Ledger        $ledger  The command's ledger.
	 * @param File_Command_Request $request The command's arguments.
	 * @return string|null
	 */
	private static function long_line_file( Budget_Ledger $ledger, File_Command_Request $request ): ?string {
		$current = $ledger->current_path();
		if ( null !== $current ) {
			return $current;
		}
		$arguments = $request->arguments();
		$paths     = $arguments['paths'] ?? ( $arguments['path'] ?? array() );
		$paths     = is_array( $paths ) ? $paths : array( $paths );
		if ( 1 === count( $paths ) && is_string( $paths[0] ) && '' !== $paths[0] && '-' !== $paths[0] && array() === $request->globs() ) {
			return $paths[0];
		}

		return null;
	}

	/**
	 * Point an omitted file operand at standard input.
	 *
	 * @param array<string, Operand> $operands  The command's operands.
	 * @param array<string, mixed>   $arguments The arguments given.
	 * @return array<string, mixed>
	 */
	private static function default_to_standard_input( array $operands, array $arguments ): array {
		foreach ( $operands as $name => $operand ) {
			if ( 'paths' !== $name && 'path' !== $name ) {
				continue;
			}
			$value = $arguments[ $name ] ?? null;
			if ( null === $value || array() === $value || '' === $value ) {
				$arguments[ $name ] = $operand->many() ? array( Standard_Input::IMPLICIT ) : Standard_Input::IMPLICIT;
			}
		}

		return $arguments;
	}

	/**
	 * A cwd argument scopes the call: it must resolve inside the sandbox and
	 * be a directory. Denials propagate as any other refused path.
	 *
	 * @param Invocation           $invocation The invocation.
	 * @param Sandbox              $sandbox    The readable roots.
	 * @param Read_Only_Filesystem $filesystem The filesystem.
	 * @param string               $command    The command name.
	 * @return Sandbox
	 *
	 * @throws Invalid_Invocation When it is not a directory.
	 */
	private function enter_working_directory( Invocation $invocation, Sandbox $sandbox, Read_Only_Filesystem $filesystem, string $command ): Sandbox {
		$cwd = $invocation->option( Specification::CWD );
		if ( null === $cwd || '' === $cwd ) {
			return $sandbox;
		}
		$real = $sandbox->resolve( (string) $cwd, $filesystem );
		try {
			$is_directory = $filesystem->info( $real )->is_directory();
		} catch ( \Throwable $failure ) {
			unset( $failure );
			$is_directory = false;
		}
		if ( ! $is_directory ) {
			throw Invalid_Invocation::custom(
				$command,
				array(
					sprintf( '%s: cwd: %s: not a directory', $command, (string) $cwd ),
					'Set --cwd to the containing directory and pass the file name as an operand. Use ls or find to verify the path before retrying.',
				)
			);
		}

		return $sandbox->with_working_directory( $real, (string) $cwd, $filesystem );
	}
}
