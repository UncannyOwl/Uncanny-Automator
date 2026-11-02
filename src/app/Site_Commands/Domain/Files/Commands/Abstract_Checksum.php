<?php
/**
 * What md5sum and sha256sum share.
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
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Specification;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Filesystem_Exception;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Unexpected_Failure;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Read_Only_Filesystem;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Sandbox\Sandbox;

/**
 * A checksum command: the digest of each file, two spaces, then the path.
 */
abstract class Abstract_Checksum extends Abstract_Command {

	/**
	 * The hash() algorithm name, such as "md5".
	 *
	 * @return string
	 */
	abstract protected static function algorithm(): string;

	/**
	 * The command name, such as "md5sum".
	 *
	 * @return string
	 */
	abstract protected static function command_name(): string;

	/**
	 * Counting or hashing a binary file prints none of it.
	 *
	 * @return bool
	 */
	protected static function reads_binary(): bool {
		return true;
	}

	/**
	 * The command's contract.
	 *
	 * @return Specification
	 */
	public static function specification(): Specification {
		return new Specification(
			static::command_name(),
			sprintf( 'Print %s checksums.', strtoupper( static::algorithm() ) ),
			sprintf( 'Print the %s checksum of each file, two spaces, then the path. Use it to detect modified core or plugin files and to confirm a file is unchanged between reads.', strtoupper( static::algorithm() ) ),
			array(),
			array(
				Operand::paths( 'Files to checksum.', true ),
			),
			true
		);
	}

	/**
	 * Print each file's digest.
	 *
	 * @param Invocation           $invocation The validated arguments.
	 * @param Sandbox              $sandbox    Resolves every path.
	 * @param Budget_Ledger        $ledger     The only way to write output.
	 * @param Read_Only_Filesystem $filesystem The only way to read.
	 * @return Exit_Status
	 */
	public function run( Invocation $invocation, Sandbox $sandbox, Budget_Ledger $ledger, Read_Only_Filesystem $filesystem ): Exit_Status {
		$status = Exit_Status::success();
		$cap    = $ledger->limits()->max_hash_file_bytes();

		foreach ( $invocation->paths() as $given ) {
			$resolved = $this->resolve_for_reading( $given, $sandbox, $ledger, $filesystem );
			if ( null === $resolved ) {
				$status = Exit_Status::failure();
				continue;
			}
			try {
				$info = $filesystem->info( $resolved->real() );
				if ( $info->is_directory() ) {
					throw Filesystem_Exception::is_a_directory( $given );
				}
				$ledger->scan_file();
				$digest = $filesystem->hash( $resolved->real(), static::algorithm(), $cap );
				if ( null === $digest ) {
					$ledger->error_line( $given, sprintf( 'file too large (limit %d bytes)', $cap ) );
					$status = Exit_Status::failure();
					continue;
				}
				$ledger->out( sprintf( "%s  %s\n", $digest, $resolved->is_standard_input() ? '-' : $given ) );
			} catch ( Filesystem_Exception $failure ) {
				$this->report_filesystem( $failure, $given, $ledger );
				$status = Exit_Status::failure();
			} catch ( Throwable $failure ) {
				// Preserve expected control-flow exceptions; contextualize unexpected failures.
				throw Unexpected_Failure::during( 'calculating a file checksum', $failure );
			}
		}

		return $status;
	}
}
