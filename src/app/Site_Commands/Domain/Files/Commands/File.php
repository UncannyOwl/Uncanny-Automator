<?php
/**
 * file: describe file type.
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
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Content_Kind;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\File_Type;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Read_Only_Filesystem;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Sandbox\Sandbox;

/**
 * GNU file, for the cases that matter before reading: directories, links,
 * empty files, text encodings and common binary formats.
 */
final class File extends Abstract_Command {

	/**
	 * Leading bytes of common binary formats and how file names them.
	 *
	 * @var array<string, string>
	 */
	private const MAGIC = array(
		"\x89PNG\r\n\x1a\n"     => 'PNG image data',
		"\xFF\xD8\xFF"          => 'JPEG image data',
		'GIF87a'                => 'GIF image data',
		'GIF89a'                => 'GIF image data',
		'%PDF-'                 => 'PDF document',
		"PK\x03\x04"            => 'Zip archive data',
		"\x1f\x8b"              => 'gzip compressed data',
		'BZh'                   => 'bzip2 compressed data',
		"\x7fELF"               => 'ELF executable',
		'MZ'                    => 'PE32 executable',
		"SQLite format 3\x00"   => 'SQLite 3.x database',
		'OggS'                  => 'Ogg data',
		"\x00\x00\x00\x18ftyp"  => 'ISO Media, MP4',
		"\x00\x00\x00\x20ftyp"  => 'ISO Media, MP4',
		'ID3'                   => 'Audio file with ID3',
	);

	/**
	 * The command's contract.
	 *
	 * @return Specification
	 */
	public static function specification(): Specification {
		return new Specification(
			'file',
			'Describe file type.',
			'Report whether a path is a directory, a symbolic link, empty, text (ASCII or UTF-8), or binary data. Run it before cat on an unfamiliar file.',
			array(),
			array(
				Operand::paths( 'Paths to describe.', true ),
			)
		);
	}

	/**
	 * Describe each operand.
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
			$resolved = $this->resolve( $given, $sandbox, $ledger, $filesystem );
			if ( null === $resolved ) {
				$status = Exit_Status::failure();
				continue;
			}
			try {
				$info = $filesystem->info( $resolved->physical(), false );
				if ( $info->is_link() ) {
					$ledger->out( sprintf( "%s: symbolic link to %s\n", $given, $info->link_target() ?? '?' ) );
					continue;
				}
				if ( $info->is_directory() ) {
					$ledger->out( sprintf( "%s: directory\n", $given ) );
					continue;
				}
				if ( File_Type::OTHER === $info->type() ) {
					$ledger->out( sprintf( "%s: %s\n", $given, $this->special( $info->mode() ) ) );
					continue;
				}
				if ( 0 === $info->size() ) {
					$ledger->out( sprintf( "%s: empty\n", $given ) );
					continue;
				}
				if ( ! $sandbox->allows_content( $resolved->real(), $filesystem, $resolved->physical(), $given ) ) {
					$ledger->out( sprintf( "%s: regular file, no read permission\n", $given ) );
					continue;
				}
				$sample = $filesystem->sample( $resolved->real(), Content_Kind::SAMPLE_BYTES );
				$ledger->out( sprintf( "%s: %s\n", $given, $this->describe( $sample, $info->size() ) ) );
			} catch ( Filesystem_Exception $failure ) {
				$ledger->out( sprintf( "%s: cannot open `%s' (%s)\n", $given, $given, $failure->describe() ) );
				$status = Exit_Status::failure();
			} catch ( Throwable $failure ) {
				// Preserve expected control-flow exceptions; contextualize unexpected failures.
				throw Unexpected_Failure::during( 'inspecting a file type', $failure );
			}
		}

		return $status;
	}

	/**
	 * GNU's words for a special file, from its mode's type bits.
	 *
	 * @param int $mode The st_mode.
	 * @return string
	 */
	private function special( int $mode ): string {
		switch ( $mode & 0170000 ) {
			case 0010000:
				return 'fifo (named pipe)';
			case 0140000:
				return 'socket';
			case 0020000:
				return 'character special';
			case 0060000:
				return 'block special';
			default:
				return 'data';
		}
	}

	/**
	 * Describe a regular file from its first bytes.
	 *
	 * @param string $sample The first bytes.
	 * @param int    $size   The file size.
	 * @return string
	 */
	private function describe( string $sample, int $size ): string {
		foreach ( self::MAGIC as $magic => $description ) {
			if ( substr( $sample, 0, strlen( (string) $magic ) ) === (string) $magic ) {
				return $description;
			}
		}
		if ( 'RIFF' === substr( $sample, 0, 4 ) && 'WEBP' === substr( $sample, 8, 4 ) ) {
			return 'RIFF (little-endian) data, Web/P image';
		}
		if ( Content_Kind::BINARY === Content_Kind::detect( $sample ) ) {
			return 'data';
		}

		$description = preg_match( '/[\x80-\xFF]/', $sample ) ? ( preg_match( '//u', $sample ) ? 'Unicode text, UTF-8 text' : 'data' ) : 'ASCII text';
		if ( 'data' === $description ) {
			return $description;
		}
		if ( false !== strpos( $sample, "\r\n" ) ) {
			$description .= ', with CRLF line terminators';
		}
		if ( false === strpos( $sample, "\n" ) && strlen( $sample ) >= $size ) {
			$description .= ', with no line terminators';
		}

		return $description;
	}
}
