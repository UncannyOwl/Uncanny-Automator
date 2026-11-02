<?php
/**
 * The previous pipeline stage's output, served as a file.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command output is plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem;

use Closure;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Filesystem_Exception;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Tail_Read_Limit_Exceeded;

/**
 * The previous pipeline stage's output, served to the next stage as the
 * file `-`, the way a shell does. Decorates the real filesystem: one
 * reserved path answers from memory, everything else passes through.
 *
 * The reserved path cannot exist on disk, so a command that names `-`
 * outside a pipeline falls through to the sandbox and gets the shell's
 * "No such file or directory".
 */
final class Standard_Input implements Read_Only_Filesystem {

	/** The reserved path that answers from memory. */
	public const PATH = '/dev/uncanny-coder-standard-input';

	/** How GNU names standard input in prefixes and messages. */
	public const DISPLAY = '(standard input)';

	/**
	 * The operand the stage runner supplies when a command was given no
	 * file: standard input without a name, as a shell pipe provides it.
	 */
	public const IMPLICIT = '';

	/**
	 * The real filesystem.
	 *
	 * @var Read_Only_Filesystem
	 */
	private Read_Only_Filesystem $inner;

	/**
	 * The previous stage's output.
	 *
	 * @var string
	 */
	private string $text;

	/**
	 * Decorate the real filesystem.
	 *
	 * @param Read_Only_Filesystem $inner The real filesystem.
	 * @param string               $text  The previous stage's output.
	 */
	public function __construct( Read_Only_Filesystem $inner, string $text ) {
		$this->inner = $inner;
		$this->text  = $text;
	}

	/**
	 * Canonical absolute path, or null when the path does not exist.
	 *
	 * @param string $path An absolute path.
	 * @return string|null
	 */
	public function realpath( string $path ): ?string {
		return self::PATH === $path ? self::PATH : $this->inner->realpath( $path );
	}

	/**
	 * Whether the path exists.
	 *
	 * @param string $path An absolute path.
	 * @return bool
	 */
	public function exists( string $path ): bool {
		return self::PATH === $path || $this->inner->exists( $path );
	}

	/**
	 * Stat, or lstat when links are not followed.
	 *
	 * @param string $path         An absolute path.
	 * @param bool   $follow_links False for lstat.
	 * @return File_Info
	 */
	public function info( string $path, bool $follow_links = true ): File_Info {
		if ( self::PATH !== $path ) {
			return $this->inner->info( $path, $follow_links );
		}

		return new File_Info( self::PATH, File_Type::FILE, strlen( $this->text ), 0, 0, 0, 0100600, 1, 0, 0, null, null, 0, 0, 0 );
	}

	/**
	 * Directory entries; standard input is not a directory.
	 *
	 * @param string $directory An absolute path.
	 * @return iterable<Directory_Entry>
	 *
	 * @throws Filesystem_Exception When asked to list standard input.
	 */
	public function list( string $directory ): iterable {
		if ( self::PATH === $directory ) {
			throw Filesystem_Exception::not_a_directory( $directory );
		}

		return $this->inner->list( $directory );
	}

	/**
	 * Complete lines with their terminators intact.
	 *
	 * @param string $path An absolute path.
	 * @return iterable<string>
	 */
	public function lines( string $path ): iterable {
		if ( self::PATH !== $path ) {
			yield from $this->inner->lines( $path );

			return;
		}
		if ( '' === $this->text ) {
			return;
		}
		yield from Bounded_Lines::from_string( $this->text );
	}

	/**
	 * The last $count lines in file order.
	 *
	 * @param string       $path       An absolute path.
	 * @param int          $count      Lines wanted.
	 * @param Closure|null $checkpoint Runs during the read; may throw to stop it.
	 * @return string[]
	 *
	 * @throws Tail_Read_Limit_Exceeded When the tail is larger than 1 MB.
	 */
	public function tail_lines( string $path, int $count, ?Closure $checkpoint = null ): array {
		if ( self::PATH !== $path ) {
			return $this->inner->tail_lines( $path, $count, $checkpoint );
		}
		$lines = array();
		foreach ( $this->lines( $path ) as $line ) {
			$lines[] = $line;
		}

		if ( null !== $checkpoint ) {
			$checkpoint();
		}
		$tail = $count <= 0 ? array() : array_slice( $lines, -$count );
		if ( strlen( implode( '', $tail ) ) > 1048576 ) {
			throw new Tail_Read_Limit_Exceeded();
		}

		return $tail;
	}

	/**
	 * The last $bytes bytes.
	 *
	 * @param string $path  An absolute path.
	 * @param int    $bytes Bytes wanted.
	 * @return string
	 */
	public function tail_bytes( string $path, int $bytes ): string {
		if ( self::PATH !== $path ) {
			return $this->inner->tail_bytes( $path, $bytes );
		}

		return $bytes <= 0 ? '' : substr( $this->text, -min( $bytes, strlen( $this->text ) ) );
	}

	/**
	 * The first $bytes bytes.
	 *
	 * @param string $path  An absolute path.
	 * @param int    $bytes Bytes wanted.
	 * @return string
	 */
	public function sample( string $path, int $bytes ): string {
		return self::PATH === $path ? substr( $this->text, 0, $bytes ) : $this->inner->sample( $path, $bytes );
	}

	/**
	 * Hex digest, or null when larger than $max_bytes.
	 *
	 * @param string $path      An absolute path.
	 * @param string $algorithm A hash() algorithm name.
	 * @param int    $max_bytes The largest input to hash.
	 * @return string|null
	 */
	public function hash( string $path, string $algorithm, int $max_bytes ): ?string {
		if ( self::PATH !== $path ) {
			return $this->inner->hash( $path, $algorithm, $max_bytes );
		}

		return strlen( $this->text ) > $max_bytes ? null : hash( $algorithm, $this->text );
	}
}
