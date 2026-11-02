<?php
/**
 * What every file command shares.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command output is plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Commands;

use Throwable;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Budget\Budget_Ledger;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Exit_Status;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\File_Command;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Access_Denied;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Filesystem_Exception;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Unexpected_Failure;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Content_Kind;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\File_Type;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Read_Only_Filesystem;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Standard_Input;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Sandbox\Printed_Source;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Sandbox\Sandbox;

/**
 * What every command shares: resolving operands through the sandbox and
 * reporting failures the way the shell reports them.
 */
abstract class Abstract_Command implements File_Command {

	/**
	 * Resolve one operand. On denial or a missing ancestor, write the shell
	 * error and return null so the caller can continue with the next one.
	 *
	 * @param string               $given      The operand as the caller wrote it.
	 * @param Sandbox              $sandbox    The sandbox.
	 * @param Budget_Ledger        $ledger     The ledger.
	 * @param Read_Only_Filesystem $filesystem The filesystem.
	 * @param string               $verb       Words before the operand in messages, such as "cannot open".
	 * @return Resolved_Path|null
	 */
	protected function resolve( string $given, Sandbox $sandbox, Budget_Ledger $ledger, Read_Only_Filesystem $filesystem, string $verb = '' ): ?Resolved_Path {
		if ( ( '-' === $given || Standard_Input::IMPLICIT === $given ) && $filesystem->exists( Standard_Input::PATH ) ) {
			return new Resolved_Path( $given, Standard_Input::PATH, Standard_Input::PATH );
		}
		try {
			return new Resolved_Path( $given, $sandbox->resolve( $given, $filesystem ), $sandbox->physical( $given ) );
		} catch ( Access_Denied $denied ) {
			$this->report_denied( $denied, $ledger, $verb );

			return null;
		} catch ( Throwable $failure ) {
			// Preserve expected control-flow exceptions; contextualize unexpected failures.
			throw Unexpected_Failure::during( 'checking file access', $failure );
		}
	}

	/**
	 * Resolve an operand whose content will be read. Denylisted files are
	 * refused here, with the policy line, before anything is opened.
	 *
	 * @param string               $given      The operand as the caller wrote it.
	 * @param Sandbox              $sandbox    The sandbox.
	 * @param Budget_Ledger        $ledger     The ledger.
	 * @param Read_Only_Filesystem $filesystem The filesystem.
	 * @param string               $verb       Words before the operand in messages.
	 * @return Resolved_Path|null
	 */
	protected function resolve_for_reading( string $given, Sandbox $sandbox, Budget_Ledger $ledger, Read_Only_Filesystem $filesystem, string $verb = '' ): ?Resolved_Path {
		$resolved = $this->resolve( $given, $sandbox, $ledger, $filesystem, $verb );
		if ( null === $resolved ) {
			return null;
		}
		if ( ! $resolved->is_standard_input() ) {
			try {
				$info = $filesystem->info( $resolved->real() );
				if ( File_Type::OTHER === $info->type() ) {
					// A FIFO or a device would block the worker on open; no budget can fire inside fopen().
					$ledger->error_line( '' === $verb ? $this->quote( $given ) : sprintf( '%s %s', $verb, $this->quote( $given ) ), 'not a regular file' );

					return null;
				}
			} catch ( Filesystem_Exception $missing ) {
				// Stop before content authorization: an incomplete path trace must
				// never grant access, even if a later lookup sees the file appear.
				if ( $sandbox->excludes_content_names( $given, $resolved->real() ) ) {
					$this->report_denied( Access_Denied::policy( $given ), $ledger, $verb );
				} else {
					$this->report_filesystem( $missing, $given, $ledger, $verb );
				}

				return null;
			} catch ( Throwable $failure ) {
				// Preserve expected control-flow exceptions; contextualize unexpected failures.
				throw Unexpected_Failure::during( 'checking file access', $failure );
			}
		}
		try {
			$sandbox->assert_content_readable( $given, $resolved->real(), $filesystem );
			if ( ! static::reads_binary() && ! $resolved->is_standard_input() && $this->is_binary( $resolved, $filesystem ) ) {
				$ledger->error_line( '' === $verb ? $this->quote( $given ) : sprintf( '%s %s', $verb, $this->quote( $given ) ), 'binary file; use file, stat, or md5sum' );

				return null;
			}
		} catch ( Access_Denied $denied ) {
			$this->report_denied( $denied, $ledger, $verb, $resolved->is_standard_input() );

			return null;
		} catch ( Filesystem_Exception $unreadable ) {
			// The file exists but cannot be opened (no permission, or it went
			// away): the command's own GNU error, as when its read fails.
			$this->report_filesystem( $unreadable, $given, $ledger, $verb );

			return null;
		} catch ( Throwable $failure ) {
			// Preserve expected control-flow exceptions; contextualize unexpected failures.
			throw Unexpected_Failure::during( 'checking file access', $failure );
		}

		return $resolved;
	}

	/**
	 * Whether the command may read binary files. Commands that print parts
	 * of a file refuse them: a binary file, such as an archive, can hold
	 * text, such as a configuration file, past the part the content checks
	 * read. Commands that only count, hash or judge a file read it.
	 *
	 * @return bool
	 */
	protected static function reads_binary(): bool {
		return false;
	}

	/**
	 * Whether an existing regular file is binary, judged by its first bytes.
	 *
	 * @param Resolved_Path        $resolved   The file.
	 * @param Read_Only_Filesystem $filesystem The filesystem.
	 * @return bool
	 *
	 * @throws Filesystem_Exception When the file cannot be examined or opened; the caller reports it in the command's words.
	 */
	private function is_binary( Resolved_Path $resolved, Read_Only_Filesystem $filesystem ): bool {
		if ( ! $filesystem->info( $resolved->real() )->is_file() ) {
			return false;
		}

		return Content_Kind::BINARY === Content_Kind::detect( $filesystem->sample( $resolved->real(), Content_Kind::SAMPLE_BYTES ) );
	}

	/**
	 * Hold what this command prints from a file, or from standard input,
	 * until the content policy has read it (Printed_Source). Call before
	 * printing from the source, and release the ledger when done with it.
	 *
	 * @param Resolved_Path $resolved The file or standard input, as resolve_for_reading() returned it.
	 * @param Sandbox       $sandbox  The sandbox, whose content policy reads the printed text.
	 * @param Budget_Ledger $ledger   The ledger.
	 * @param string        $verb     Words before the operand in messages, as for a file the command may not open.
	 * @return void
	 */
	protected function hold_printed( Resolved_Path $resolved, Sandbox $sandbox, Budget_Ledger $ledger, string $verb = '' ): void {
		$ledger->hold(
			new Printed_Source(
				$sandbox,
				$resolved->given(),
				$this->denial_text( Access_Denied::policy( $resolved->given() ), $ledger, $verb, $resolved->is_standard_input() ),
				static::refused_status()
			)
		);
	}

	/**
	 * The exit status for a file the command may not read: 1, as most GNU
	 * tools exit. Commands that exit otherwise override it.
	 *
	 * @return Exit_Status
	 */
	protected static function refused_status(): Exit_Status {
		return Exit_Status::failure();
	}

	/**
	 * Write the shell's permission error, then the policy or roots line.
	 *
	 * @param Access_Denied $denied         The refusal.
	 * @param Budget_Ledger $ledger         The ledger.
	 * @param string        $verb           Words before the operand in messages.
	 * @param bool          $standard_input Whether the refused input is standard input, which GNU names in its own way.
	 * @return void
	 */
	protected function report_denied( Access_Denied $denied, Budget_Ledger $ledger, string $verb = '', bool $standard_input = false ): void {
		$ledger->record_denial( $denied->requested() );
		$ledger->err( $this->denial_text( $denied, $ledger, $verb, $standard_input ) );
	}

	/**
	 * The shell's permission error, then the policy or roots line.
	 *
	 * @param Access_Denied $denied         The refusal.
	 * @param Budget_Ledger $ledger         The ledger, which knows the command's name.
	 * @param string        $verb           Words before the operand in messages.
	 * @param bool          $standard_input Whether the refused input is standard input.
	 * @return string
	 */
	protected function denial_text( Access_Denied $denied, Budget_Ledger $ledger, string $verb = '', bool $standard_input = false ): string {
		$name    = $standard_input ? $this->standard_input_name( $denied->requested() ) : $denied->requested();
		$subject = '' === $verb ? $this->quote( $name ) : sprintf( '%s %s%s', $verb, $this->quote( $name ), $this->open_failure_suffix() );
		$text    = $ledger->error_text( $subject, 'Permission denied' );
		if ( $denied->is_policy() ) {
			return $text . sprintf( "%s: %s is denied by policy; it may hold credentials\n", static::specification()->name(), $name );
		}

		return $text . sprintf( "%s: readable roots: %s\n", static::specification()->name(), implode( ', ', $denied->roots() ) );
	}

	/**
	 * How GNU names standard input in this command's messages, before any
	 * quoting: "-" for most commands, whether the caller wrote "-" or the
	 * stage runner supplied it. Commands that name it otherwise override it.
	 *
	 * @param string $given "-" when the caller named it; empty when the stage runner supplied it.
	 * @return string
	 */
	protected function standard_input_name( string $given ): string {
		unset( $given );

		return '-';
	}

	/**
	 * Write the shell's error line for a filesystem condition.
	 *
	 * @param Filesystem_Exception $failure The condition.
	 * @param string               $given   The operand as the caller wrote it.
	 * @param Budget_Ledger        $ledger  The ledger.
	 * @param string               $verb    Words before the operand in messages.
	 * @return void
	 */
	protected function report_filesystem( Filesystem_Exception $failure, string $given, Budget_Ledger $ledger, string $verb = '' ): void {
		$subject = '' === $verb ? $this->quote( $given ) : sprintf( '%s %s%s', $verb, $this->quote( $given ), $this->open_failure_suffix() );
		$ledger->error_line( $subject, $failure->describe() );
	}

	/**
	 * GNU quotes operands in messages only for some commands (ls, find).
	 * Commands that quote override quotes_operands().
	 *
	 * @param string $path The operand.
	 * @return string
	 */
	protected function quote( string $path ): string {
		return $this->quotes_operands() ? "'" . $path . "'" : $path;
	}

	/**
	 * Whether this command quotes operands in messages.
	 *
	 * @return bool
	 */
	protected function quotes_operands(): bool {
		return false;
	}

	/**
	 * Text after the quoted operand in an open failure, e.g. " for reading".
	 * Applies to both filesystem failures and policy denials.
	 *
	 * @return string
	 */
	protected function open_failure_suffix(): string {
		return '';
	}
}
