<?php
/**
 * Run one site command and print its result.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command errors are plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Application;

use Uncanny_Automator\App\Site_Commands\Domain\Command_Definition;
use Uncanny_Automator\App\Site_Commands\Domain\Command_Error;
use Uncanny_Automator\App\Site_Commands\Domain\Refusal;
use Uncanny_Automator\App\Site_Commands\Domain\Command_Line\Command_Parser;
use Uncanny_Automator\App\Site_Commands\Domain\Output\Command_Output;
use Uncanny_Automator\App\Site_Commands\Domain\Output\Command_Result;
use Uncanny_Automator\App\Site_Commands\Domain\Output\Formats;
use Uncanny_Automator\App\Site_Commands\Domain\Output\Formatter;
use Uncanny_Automator\App\Site_Commands\Domain\Output\Output_Budget;
use Uncanny_Automator\App\Site_Commands\Ports\Read_Isolation;

/**
 * The only entry point. Every transport calls execute(), so no path can skip
 * the checks. Everything is checked before the command runs: the command
 * exists, its arguments and options are valid, and a command that changes
 * the site has a caller allowed to change it. A refused command never runs,
 * and a command that changed the site is never reported as failed afterwards.
 */
final class Run_Site_Command {

	private Index_Site_Commands $commands;
	private Describe_Site_Commands $help;
	private Command_Parser $parser;
	private Formatter $formatter;
	private Output_Budget $budget;

	/** Largest stats block; stats summarise, so they stay short. */
	private const MAX_STATS_BYTES = 2000;

	/**
	 * Whether the capture buffer is sealed against removal by plugin code.
	 *
	 * @var bool
	 */
	private bool $seal_output;

	/**
	 * Keeps handlers from writing to the site, when set.
	 *
	 * @var Read_Isolation|null
	 */
	private ?Read_Isolation $isolation;

	/**
	 * Wire the collaborators; defaults suit production.
	 *
	 * @param Index_Site_Commands         $commands  Shared command index.
	 * @param Describe_Site_Commands|null $help      Help text.
	 * @param Command_Parser|null         $parser    Command line parser.
	 * @param Formatter|null              $formatter Output formatter.
	 * @param Output_Budget|null          $budget    Output size limit.
	 * @param bool                        $seal_output Seal the capture buffer, so plugin code that runs
	 *                                                 during a command cannot remove it and print into the
	 *                                                 response. A sealed buffer stays open, passing output
	 *                                                 through, until PHP ends the request, so the caller must
	 *                                                 flush it at shutdown without trying to remove it (see
	 *                                                 Output_Buffers::flush_all()). Test runners that check
	 *                                                 their own buffers leave it unsealed.
	 * @param Read_Isolation|null         $isolation   Keeps handlers from writing to the site's caches and
	 *                                                 database while they read. Production always sets it.
	 */
	public function __construct( Index_Site_Commands $commands, ?Describe_Site_Commands $help = null, ?Command_Parser $parser = null, ?Formatter $formatter = null, ?Output_Budget $budget = null, bool $seal_output = false, ?Read_Isolation $isolation = null ) {
		$this->commands    = $commands;
		$this->help        = $help ?? new Describe_Site_Commands( $commands );
		$this->parser      = $parser ?? new Command_Parser();
		$this->formatter   = $formatter ?? new Formatter();
		$this->budget      = $budget ?? new Output_Budget();
		$this->seal_output = $seal_output;
		$this->isolation   = $isolation;
	}

	/**
	 * Run a command line.
	 *
	 * @param string $command_line  The command as written.
	 * @param bool   $allow_changes Whether this caller may run commands that change the site.
	 * @return Command_Result
	 */
	public function execute( string $command_line, bool $allow_changes ): Command_Result {
		$started = false;
		try {
			$line_command = $this->line_command( $command_line );
			if ( null !== $line_command ) {
				list( $definition, $arguments ) = $line_command;
				$options                        = array();
				$started                        = true;
				$output                         = $this->run_handler( $definition, $arguments, $options );
				if ( ! $output instanceof Command_Output ) {
					throw new \LogicException( 'A site command handler must return Command_Output: ' . $definition->path() );
				}
				$result = $this->budget->apply( $this->formatter->format( $definition, $output, $options ) );

				return $output->is_failure() ? Command_Result::failed( $result->output(), $result->is_truncated() ) : $result;
			}

			$parsed = $this->parser->parse( $command_line );
			$words  = $parsed->words();

			// As in WP-CLI, "<command> --help" shows the command's help. Only the
			// flag itself: --no-help and --help=<value> are refused like any unknown option.
			if ( array() !== $words && 'help' !== $words[0] && true === ( $parsed->options()['help'] ?? null ) ) {
				list( $definition ) = $this->commands->execute()->resolve( $words );

				return $this->budget->apply( $this->help->execute( explode( ' ', $definition->path() ) ) );
			}

			if ( array() === $words || 'help' === $words[0] ) {
				if ( array() !== $parsed->options() ) {
					throw new Command_Error( 'help takes no options. Run "help <command>".' );
				}
				return $this->budget->apply( $this->help->execute( array_slice( $words, 1 ) ) );
			}

			list( $definition, $rest ) = $this->commands->execute()->resolve( $words );
			$arguments                 = $this->bind_arguments( $definition, $rest );
			$options                   = $this->validate_options( $definition, $parsed->options() );

			if ( ! $definition->is_read_only() && ! $allow_changes ) {
				throw new Command_Error( sprintf( '"%s" changes the site, and this tool only runs commands that read.', $definition->path() ) );
			}

			$started = true;
			$output  = $this->run_handler( $definition, $arguments, $options );
			if ( ! $output instanceof Command_Output ) {
				throw new \LogicException( 'A site command handler must return Command_Output: ' . $definition->path() );
			}
			$limitation = null === $this->isolation ? null : $this->isolation->limitation();
			if ( null !== $limitation ) {
				$output = $output->with_stats( $limitation );
			}

			$format = $definition->lists_items() ? (string) ( $options[ Formats::FORMAT_OPTION ] ?? Formats::TABLE ) : Formats::TABLE;

			try {
				$result = $this->budget->apply( $this->formatter->format( $definition, $output, $options ), $format );
				$cut    = $result->is_truncated();
				$result = $this->formatter->was_truncated() && ! $cut ? Command_Result::truncated( $result->output() ) : $result;

				$stats = ( new Output_Budget( self::MAX_STATS_BYTES ) )->apply( $this->formatter->stats( $definition, $output, $options, $cut ) );
				if ( $output->is_failure() ) {
					// Nothing was done and something failed; the rows say what.
					$result = Command_Result::failed( $result->output(), $result->is_truncated() );
				}

				return $result->with_stats( $stats->output() );
			} catch ( Command_Error $error ) {
				if ( $definition->is_read_only() ) {
					throw $error;
				}
				// The change already happened; reporting failure would invite a retry.
				$note = 'The command ran, but its output could not be shown (' . rtrim( $error->getMessage(), '. ' ) . '). Do not run it again; check the result with site_command.';
				return $output->is_failure() ? Command_Result::failed( 'Error: ' . $note ) : Command_Result::success( 'Success: ' . $note );
			}
		} catch ( Command_Error $error ) {
			$message = $error->getMessage();
			if ( $allow_changes && ! $started && false === strpos( $message, 'Nothing was changed.' ) ) {
				// Refused before the command ran, so a caller that may change the site knows nothing did.
				$message = rtrim( $message );
				$message = ( '.' === substr( $message, -1 ) ? $message : $message . '.' ) . ' Nothing was changed.';
			}
			return Command_Result::error( $message );
		} catch ( Refusal $refusal ) {
			$message = rtrim( $refusal->getMessage() );

			return Command_Result::error( ( '.' === substr( $message, -1 ) ? $message : $message . '.' ) . ' If the command changes the site, check the result before running it again.' );
		} catch ( \Throwable $throwable ) {
			// Reported to the caller only: a read-only command writes nothing,
			// not even a line to the server's error log.
			$class = $this->class_name( $throwable );

			return Command_Result::error( sprintf( 'The command stopped unexpectedly (%s). If it changes the site, check the result before running it again.', $class ) );
		}
	}

	/**
	 * The exception's class name, without the file path PHP embeds in the
	 * name of an anonymous class: the nearest named ancestor is used instead.
	 *
	 * @param \Throwable $throwable Exception.
	 * @return string
	 */
	private function class_name( \Throwable $throwable ): string {
		$class = get_class( $throwable );
		if ( false === strpos( $class, '@anonymous' ) ) {
			return $class;
		}
		for ( $parent = get_parent_class( $throwable ); false !== $parent; $parent = get_parent_class( $parent ) ) {
			if ( false === strpos( $parent, '@anonymous' ) ) {
				return $parent . ' (anonymous subclass)';
			}
		}

		return 'anonymous class';
	}

	/**
	 * Call the handler. Handlers run WordPress and other plugins' code (filters,
	 * transients), which may print stray output. Everything printed, even if a
	 * plugin flushes it, is discarded while the handler runs, so it cannot
	 * corrupt the response. A sealed capture buffer cannot be removed by the
	 * handler either; afterwards it is emptied and passes later output through.
	 *
	 * Buffers the handler opened are closed afterwards. A buffer that a plugin
	 * made unremovable is emptied if it can be, and stops the cleanup instead
	 * of looping. If it still holds output, PHP releases that output through
	 * the capture buffer when it shuts down: those bytes are dropped then, and
	 * later output passes through. When the size cannot be known, because a
	 * stranded buffer transforms what it holds, everything is dropped instead.
	 *
	 * @param Command_Definition         $definition Command.
	 * @param array<string, string>      $arguments  Named arguments.
	 * @param array<string, string|bool> $options    Validated options.
	 * @return mixed The handler's return value.
	 */
	private function run_handler( Command_Definition $definition, array $arguments, array $options ) {
		$level   = ob_get_level();
		$discard = true;
		$skip    = 0;
		ob_start(
			static function ( string $buffer ) use ( &$discard, &$skip ): string {
				if ( $discard ) {
					return '';
				}
				$dropped = min( $skip, strlen( $buffer ) );
				$skip   -= $dropped;

				return (string) substr( $buffer, $dropped );
			},
			0,
			$this->seal_output ? PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_FLUSHABLE : PHP_OUTPUT_HANDLER_STDFLAGS
		);
		try {
			$handler = $definition->handler();
			$work    = static function () use ( $handler, $arguments, $options ) {
				return call_user_func( $handler, $arguments, $options );
			};

			return null === $this->isolation ? $work() : $this->isolation->run( $work );
		} finally {
			while ( ob_get_level() > $level ) {
				$status = ob_get_status();
				$flags  = is_array( $status ) ? (int) ( $status['flags'] ?? 0 ) : 0;
				if ( 0 === ( $flags & PHP_OUTPUT_HANDLER_REMOVABLE ) ) {
					if ( 0 !== ( $flags & PHP_OUTPUT_HANDLER_CLEANABLE ) ) {
						ob_clean();
					}
					break;
				}
				ob_end_clean();
			}
			$skip    = $this->stranded_bytes( $level );
			$discard = false;
		}
	}

	/**
	 * The output still held by the capture buffer and the buffers stranded
	 * above it, which PHP will release through the capture buffer.
	 *
	 * @param int $level Buffer level before the capture buffer started.
	 * @return int Bytes, or PHP_INT_MAX when a stranded buffer transforms its output.
	 */
	private function stranded_bytes( int $level ): int {
		if ( ob_get_level() <= $level ) {
			return 0;
		}

		$bytes = 0;
		foreach ( array_slice( ob_get_status( true ), $level ) as $position => $status ) {
			if ( $position > 0 && 'default output handler' !== ( $status['name'] ?? '' ) ) {
				return PHP_INT_MAX;
			}
			$bytes += (int) ( $status['buffer_used'] ?? 0 );
		}

		return $bytes;
	}

	/**
	 * The command that reads this line itself, such as "grep -rn x | head",
	 * with the line as its argument; null for every other command. "help
	 * <command>" and "<command> --help" still show its help.
	 *
	 * @param string $command_line The command as written.
	 * @return array{0: Command_Definition, 1: array<string, string>}|null
	 */
	private function line_command( string $command_line ): ?array {
		$line = trim( $command_line );
		if ( 1 === preg_match( '/^wp(?:\s|$)/', $line ) ) {
			$line = ltrim( substr( $line, 2 ) );
		}
		if ( 1 !== preg_match( '/^([a-z][a-z0-9-]*)(?:\s|$)/', $line, $match ) || '--help' === trim( substr( $line, strlen( $match[1] ) ) ) ) {
			return null;
		}
		$all = $this->commands->execute()->all();
		if ( 'cd' === $match[1] && isset( $all['pwd'] ) && $all['pwd']->takes_line() ) {
			throw new Command_Error( 'There is no cd: each command runs on its own. Give a file command the folder instead, such as "ls -la --cwd=wp-content/plugins" or "grep -rn add_action --cwd=wp-content/plugins/x .".' );
		}
		if ( ! isset( $all[ $match[1] ] ) || ! $all[ $match[1] ]->takes_line() ) {
			return null;
		}

		return array( $all[ $match[1] ], array( 'line' => $line ) );
	}

	/**
	 * Name the positional arguments, refusing missing or extra ones.
	 *
	 * @param Command_Definition $definition Command.
	 * @param string[]           $words      Words after the command path.
	 * @return array<string, string> Arguments by name.
	 *
	 * @throws Command_Error When arguments are missing or extra.
	 */
	private function bind_arguments( Command_Definition $definition, array $words ): array {
		$declared = $definition->arguments();
		if ( count( $words ) > count( $declared ) ) {
			throw new Command_Error( sprintf( 'Too many arguments for "%s". Usage: %s', $definition->path(), $definition->synopsis() ) );
		}

		$bound = array();
		foreach ( $declared as $position => $argument ) {
			if ( isset( $words[ $position ] ) ) {
				$bound[ $argument->name() ] = $words[ $position ];
			} elseif ( $argument->is_required() ) {
				throw new Command_Error( sprintf( 'Missing argument <%s>. Usage: %s', $argument->name(), $definition->synopsis() ) );
			}
		}

		return $bound;
	}

	/**
	 * Refuse unknown options, invalid values and unknown fields.
	 *
	 * @param Command_Definition         $definition Command.
	 * @param array<string, string|bool> $options    Parsed options.
	 * @return array<string, string|bool>
	 *
	 * @throws Command_Error When an option is not accepted.
	 */
	private function validate_options( Command_Definition $definition, array $options ): array {
		$accepted = $definition->options();
		foreach ( $options as $name => $value ) {
			if ( ! isset( $accepted[ $name ] ) ) {
				throw new Command_Error( sprintf( 'Unknown option --%1$s for "%2$s". Run "help %2$s" for its options.', $name, $definition->path() ) );
			}
			$accepted[ $name ]->validate( $value );
		}

		if ( ! $definition->lists_items() ) {
			return $options;
		}

		if ( Formats::JSON === ( $options[ Formats::FORMAT_OPTION ] ?? null ) && ! function_exists( 'json_encode' ) ) {
			throw new Command_Error( 'JSON output needs the PHP json extension, which this site does not have. Use --format=table or --format=csv.' );
		}
		if ( isset( $options[ Formats::FIELD_OPTION ] ) && isset( $options[ Formats::FIELDS_OPTION ] ) ) {
			throw new Command_Error( 'Use either --field or --fields, not both.' );
		}

		$requested = array();
		if ( isset( $options[ Formats::FIELDS_OPTION ] ) ) {
			$requested = Formatter::split_fields( (string) $options[ Formats::FIELDS_OPTION ] );
		}
		if ( isset( $options[ Formats::FIELD_OPTION ] ) ) {
			$requested[] = (string) $options[ Formats::FIELD_OPTION ];
		}
		foreach ( $requested as $field ) {
			if ( ! in_array( $field, $definition->fields(), true ) ) {
				throw new Command_Error( sprintf( 'Invalid field: %s. Available fields: %s.', $field, implode( ', ', $definition->fields() ) ) );
			}
		}

		return $options;
	}
}
