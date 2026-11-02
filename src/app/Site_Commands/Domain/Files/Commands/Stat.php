<?php
/**
 * stat: show file status.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command output is plain terminal text for AI clients, never rendered as HTML.
// phpcs:disable WordPress.DateTime.RestrictedFunctions.date_date -- GNU stat prints server-local time, which is what date() formats.

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
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\File_Info;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\File_Type;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Read_Only_Filesystem;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Sandbox\Sandbox;

/**
 * GNU stat: the full status block, or one line per path with --format.
 */
final class Stat extends Abstract_Command {

	/**
	 * The command's contract.
	 *
	 * @return Specification
	 */
	public static function specification(): Specification {
		return new Specification(
			'stat',
			'Show file status.',
			"Show file size, permissions, owner, and timestamps. For one line per path, use -c '%Y %n' (modified time and name) or -c '%s %n' (bytes and name). Timestamps alone do not prove tampering.",
			array(
				Option::text( 'format', 'c', "Print one line per file using GNU specifiers, for example '%Y %n' or '%s %A %n'." ),
			),
			array(
				Operand::paths( 'Files or directories to inspect.', true ),
			)
		);
	}

	/**
	 * Print the status of each operand.
	 *
	 * @param Invocation           $invocation The validated arguments.
	 * @param Sandbox              $sandbox    Resolves every path.
	 * @param Budget_Ledger        $ledger     The only way to write output.
	 * @param Read_Only_Filesystem $filesystem The only way to read.
	 * @return Exit_Status
	 */
	public function run( Invocation $invocation, Sandbox $sandbox, Budget_Ledger $ledger, Read_Only_Filesystem $filesystem ): Exit_Status {
		$status = Exit_Status::success();

		foreach ( $invocation->paths() as $given ) {
			$resolved = $this->resolve( $given, $sandbox, $ledger, $filesystem, 'cannot statx' );
			if ( null === $resolved ) {
				$status = Exit_Status::failure();
				continue;
			}
			try {
				$info   = $filesystem->info( $resolved->physical(), false );
				$format = $invocation->option( 'format' );
				$ledger->out( null === $format || '' === $format ? $this->format( $given, $info ) : self::custom( (string) $format, $given, $info ) . "\n" );
			} catch ( Filesystem_Exception $failure ) {
				$this->report_filesystem( $failure, $given, $ledger, 'cannot statx' );
				$status = Exit_Status::failure();
			} catch ( Throwable $failure ) {
				// Preserve expected control-flow exceptions; contextualize unexpected failures.
				throw Unexpected_Failure::during( 'inspecting file metadata', $failure );
			}
		}

		return $status;
	}

	/**
	 * GNU stat quotes operands in messages.
	 *
	 * @return bool
	 */
	protected function quotes_operands(): bool {
		return true;
	}

	/**
	 * The default GNU status block.
	 *
	 * @param string    $given The operand as given.
	 * @param File_Info $info  Its lstat information.
	 * @return string
	 */
	private function format( string $given, File_Info $info ): string {
		$name = $info->is_link() && null !== $info->link_target() ? sprintf( '%s -> %s', $given, $info->link_target() ) : $given;
		$kind = self::kind( $info );

		return sprintf( "  File: %s\n", $name )
			. sprintf( "  Size: %-10s\tBlocks: %-10d IO Block: %-6d %s\n", (string) $info->size(), $info->blocks(), 4096, $kind )
			. sprintf( "Device: %d,%d\tInode: %-10d  Links: %d\n", ( $info->device() >> 8 ) & 0xfff, ( $info->device() & 0xff ) | ( ( $info->device() >> 12 ) & 0xfff00 ), $info->inode(), $info->nlink() )
			. sprintf( "Access: (%04o/%10.10s)  Uid: (%5d/%8s)   Gid: (%5d/%8s)\n", $info->mode() & 07777, $info->permission_string(), $info->uid(), $info->owner_name(), $info->gid(), $info->group_name() )
			. sprintf( "Access: %s\n", $this->timestamp( $info->atime() ) )
			. sprintf( "Modify: %s\n", $this->timestamp( $info->mtime() ) )
			. sprintf( "Change: %s\n", $this->timestamp( $info->ctime() ) )
			. " Birth: -\n";
	}

	/**
	 * GNU --format: the specifiers people actually use.
	 *
	 * @param string    $format The format as given.
	 * @param string    $given  The operand as given.
	 * @param File_Info $info   Its lstat information.
	 * @return string
	 */
	private static function custom( string $format, string $given, File_Info $info ): string {
		$map = array(
			'%n' => $given,
			'%N' => "'" . $given . "'" . ( $info->is_link() && null !== $info->link_target() ? " -> '" . $info->link_target() . "'" : '' ),
			'%s' => (string) $info->size(),
			'%Y' => (string) $info->mtime(),
			'%y' => date( 'Y-m-d H:i:s', $info->mtime() ) . '.000000000 ' . date( 'O', $info->mtime() ),
			'%X' => (string) $info->atime(),
			'%Z' => (string) $info->ctime(),
			'%U' => $info->owner_name(),
			'%G' => $info->group_name(),
			'%u' => (string) $info->uid(),
			'%g' => (string) $info->gid(),
			'%a' => sprintf( '%o', $info->mode() & 07777 ),
			'%A' => $info->permission_string(),
			'%F' => self::kind( $info ),
			'%i' => (string) $info->inode(),
			'%h' => (string) $info->nlink(),
			'%b' => (string) $info->blocks(),
			'%%' => '%',
		);

		return str_replace( array( '\\n', '\\t' ), array( "\n", "\t" ), strtr( $format, $map ) );
	}

	/**
	 * GNU's words for the file type.
	 *
	 * @param File_Info $info The file's information.
	 * @return string
	 */
	private static function kind( File_Info $info ): string {
		switch ( $info->type() ) {
			case File_Type::FILE:
				return 0 === $info->size() ? 'regular empty file' : 'regular file';
			case File_Type::DIRECTORY:
				return 'directory';
			case File_Type::LINK:
				return 'symbolic link';
			default:
				return 'other';
		}
	}

	/**
	 * A GNU timestamp with nanoseconds and offset.
	 *
	 * @param int $seconds Unix time.
	 * @return string
	 */
	private function timestamp( int $seconds ): string {
		return date( 'Y-m-d H:i:s', $seconds ) . '.000000000 ' . date( 'O', $seconds );
	}
}
