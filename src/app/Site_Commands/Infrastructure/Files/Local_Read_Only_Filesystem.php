<?php
/**
 * The local disk, read-only and streamed, for file commands.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command output is plain terminal text for AI clients, never rendered as HTML.

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors.Discouraged -- A streaming, read-only reader: WP_Filesystem cannot read part of a file, list a directory lazily, or report why a read failed.

namespace Uncanny_Automator\App\Site_Commands\Infrastructure\Files;

use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Filesystem_Exception;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Line_Read_Limit_Exceeded;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Tail_Read_Limit_Exceeded;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Unexpected_Failure;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Bounded_Lines;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Directory_Entry;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\File_Info;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\File_Type;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Read_Only_Filesystem;

/**
 * The local disk, streamed. Never loads a whole file. Every failure maps
 * to a Filesystem_Exception with the errno-style reason the shell prints.
 */
final class Local_Read_Only_Filesystem implements Read_Only_Filesystem {

	private const BLOCK = 8192;

	/** Maximum complete logical line; longer lines stop the command with a partial notice. */
	public const MAX_LINE = Bounded_Lines::MAX_BYTES;

	/**
	 * Canonical absolute path with symlinks resolved, or null when the path
	 * does not exist.
	 *
	 * @param string $path Path.
	 * @return string|null
	 */
	public function realpath( string $path ): ?string {
		$real = @realpath( $path );

		return false === $real ? null : $real;
	}

	/**
	 * Whether the path exists, a dangling link included.
	 *
	 * @param string $path Path.
	 * @return bool
	 */
	public function exists( string $path ): bool {
		return file_exists( $path ) || is_link( $path );
	}

	/**
	 * What stat() knows about a path.
	 *
	 * @param string $path         Path.
	 * @param bool   $follow_links Whether to follow a symbolic link.
	 * @return File_Info
	 *
	 * @throws Filesystem_Exception When it cannot be read.
	 */
	public function info( string $path, bool $follow_links = true ): File_Info {
		$stat = $follow_links ? @stat( $path ) : @lstat( $path );
		if ( false === $stat ) {
			throw $this->failure( $path );
		}
		$mode = (int) $stat['mode'];
		switch ( $mode & 0170000 ) {
			case 0100000:
				$type = File_Type::FILE;
				break;
			case 0040000:
				$type = File_Type::DIRECTORY;
				break;
			case 0120000:
				$type = File_Type::LINK;
				break;
			default:
				$type = File_Type::OTHER;
		}
		$owner = null;
		$group = null;
		if ( function_exists( 'posix_getpwuid' ) ) {
			$passwd = @posix_getpwuid( (int) $stat['uid'] );
			$owner  = is_array( $passwd ) ? (string) $passwd['name'] : null;
		}
		if ( function_exists( 'posix_getgrgid' ) ) {
			$entry = @posix_getgrgid( (int) $stat['gid'] );
			$group = is_array( $entry ) ? (string) $entry['name'] : null;
		}
		$blocks = (int) $stat['blocks'];
		if ( $blocks < 0 ) {
			$blocks = (int) ceil( (int) $stat['size'] / 512 );
		}
		$link_target = null;
		if ( File_Type::LINK === $type ) {
			$target      = @readlink( $path );
			$link_target = false === $target ? null : $target;
		}

		return new File_Info(
			$path,
			$type,
			(int) $stat['size'],
			(int) $stat['mtime'],
			(int) $stat['atime'],
			(int) $stat['ctime'],
			$mode,
			(int) $stat['nlink'],
			(int) $stat['uid'],
			(int) $stat['gid'],
			$owner,
			$group,
			$blocks,
			(int) $stat['ino'],
			(int) $stat['dev'],
			$link_target
		);
	}

	/**
	 * Entries in the order the directory returns them, which is what GNU
	 * find and grep -r print. Excludes `.` and `..`.
	 *
	 * @param string $directory Directory.
	 * @return iterable<Directory_Entry>
	 *
	 * @throws Filesystem_Exception When it cannot be opened.
	 * @throws Unexpected_Failure When reading fails unexpectedly.
	 */
	public function list( string $directory ): iterable {
		$handle = @opendir( $directory );
		if ( false === $handle ) {
			throw $this->failure( $directory );
		}
		try {
			while ( false !== ( $name = readdir( $handle ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- The idiomatic readdir() loop.
				if ( '.' === $name || '..' === $name ) {
					continue;
				}
				$path = rtrim( $directory, '/' ) . '/' . $name;
				try {
					yield new Directory_Entry( $name, $path, $this->info( $path, false ) );
				} catch ( Filesystem_Exception $vanished ) {
					// Vanished between readdir and lstat. Skip it.
					unset( $vanished );
				}
			}
		} catch ( \Throwable $failure ) {
			// Preserve expected control-flow exceptions; contextualize unexpected failures.
			throw Unexpected_Failure::during( 'reading the filesystem', $failure );
		} finally {
			closedir( $handle );
		}
	}

	/**
	 * Complete lines with their terminators intact, streamed from the start.
	 *
	 * @param string $path File.
	 * @return iterable<string>
	 *
	 * @throws Filesystem_Exception When it cannot be opened.
	 * @throws Unexpected_Failure When reading fails unexpectedly.
	 */
	public function lines( string $path ): iterable {
		$handle = @fopen( $path, 'rb' );
		if ( false === $handle ) {
			throw $this->failure( $path );
		}
		try {
			while ( false !== ( $line = fgets( $handle, self::MAX_LINE + 2 ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- The idiomatic fgets() loop.
				// Read one extra byte to distinguish an exact-limit final line
				// from a longer line. Never present its pieces as separate lines.
				if ( strlen( $line ) > self::MAX_LINE ) {
					throw new Line_Read_Limit_Exceeded();
				}
				yield $line;
			}
		} catch ( \Throwable $failure ) {
			// Preserve expected control-flow exceptions; contextualize unexpected failures.
			throw Unexpected_Failure::during( 'reading the filesystem', $failure );
		} finally {
			fclose( $handle );
		}
	}

	/**
	 * The last $count lines in file order, read from the end of the file.
	 *
	 * @param string        $path       File.
	 * @param int           $count      Lines wanted.
	 * @param \Closure|null $checkpoint Called during backward reads to enforce request budgets.
	 * @return string[]
	 *
	 * @throws Filesystem_Exception When it cannot be opened.
	 * @throws Unexpected_Failure When reading fails unexpectedly.
	 */
	public function tail_lines( string $path, int $count, ?\Closure $checkpoint = null ): array {
		if ( $count <= 0 ) {
			return array();
		}
		$handle = @fopen( $path, 'rb' );
		if ( false === $handle ) {
			throw $this->failure( $path );
		}
		$buffer = '';
		try {
			$size     = $this->size_of( $handle );
			$position = $size;
			$newlines = 0;
			while ( $position > 0 && $newlines <= $count ) {
				if ( null !== $checkpoint ) {
					$checkpoint();
				}
				if ( strlen( $buffer ) >= self::MAX_LINE ) {
					throw new Tail_Read_Limit_Exceeded();
				}
				$read      = min( self::BLOCK, $position, self::MAX_LINE - strlen( $buffer ) );
				$position -= $read;
				fseek( $handle, $position );
				$chunk = fread( $handle, $read );
				if ( false === $chunk ) {
					break;
				}
				$buffer    = $chunk . $buffer;
				$newlines += substr_count( $chunk, "\n" );
			}
		} catch ( \Throwable $failure ) {
			// Preserve expected control-flow exceptions; contextualize unexpected failures.
			throw Unexpected_Failure::during( 'reading the filesystem', $failure );
		} finally {
			fclose( $handle );
		}
		if ( '' === $buffer ) {
			return array();
		}
		$lines = preg_split( '/(?<=\n)/', $buffer, -1, PREG_SPLIT_NO_EMPTY );
		$lines = is_array( $lines ) ? $lines : array();

		return array_slice( $lines, -$count );
	}

	/**
	 * The last $bytes bytes of the file.
	 *
	 * @param string $path  File.
	 * @param int    $bytes Bytes wanted.
	 * @return string
	 *
	 * @throws Filesystem_Exception When it cannot be opened.
	 * @throws Unexpected_Failure When reading fails unexpectedly.
	 */
	public function tail_bytes( string $path, int $bytes ): string {
		if ( $bytes <= 0 ) {
			return '';
		}
		$handle = @fopen( $path, 'rb' );
		if ( false === $handle ) {
			throw $this->failure( $path );
		}
		try {
			$size  = $this->size_of( $handle );
			$start = max( 0, $size - $bytes );
			fseek( $handle, $start );
			$data = stream_get_contents( $handle, $size - $start );

			return false === $data ? '' : $data;
		} catch ( \Throwable $failure ) {
			// Preserve expected control-flow exceptions; contextualize unexpected failures.
			throw Unexpected_Failure::during( 'reading the filesystem', $failure );
		} finally {
			fclose( $handle );
		}
	}

	/**
	 * The first $bytes bytes of the file, for content-kind detection.
	 *
	 * @param string $path  File.
	 * @param int    $bytes Bytes wanted.
	 * @return string
	 *
	 * @throws Filesystem_Exception When it cannot be opened.
	 * @throws Unexpected_Failure When reading fails unexpectedly.
	 */
	public function sample( string $path, int $bytes ): string {
		$handle = @fopen( $path, 'rb' );
		if ( false === $handle ) {
			throw $this->failure( $path );
		}
		try {
			$data = $bytes > 0 ? fread( $handle, $bytes ) : '';

			return false === $data ? '' : $data;
		} catch ( \Throwable $failure ) {
			// Preserve expected control-flow exceptions; contextualize unexpected failures.
			throw Unexpected_Failure::during( 'reading the filesystem', $failure );
		} finally {
			fclose( $handle );
		}
	}

	/**
	 * Hex digest, or null when the file is larger than $max_bytes.
	 *
	 * @param string $path      File.
	 * @param string $algorithm Hash algorithm, such as "md5".
	 * @param int    $max_bytes Largest file to hash.
	 * @return string|null
	 *
	 * @throws Filesystem_Exception When it cannot be read.
	 */
	public function hash( string $path, string $algorithm, int $max_bytes ): ?string {
		$size = @filesize( $path );
		if ( false === $size ) {
			throw $this->failure( $path );
		}
		if ( $size > $max_bytes ) {
			return null;
		}
		$digest = @hash_file( $algorithm, $path );
		if ( false === $digest ) {
			throw $this->failure( $path );
		}

		return $digest;
	}

	/**
	 * The size of an open file.
	 *
	 * @param resource $handle Open file.
	 * @return int
	 */
	private function size_of( $handle ): int {
		$stat = fstat( $handle );

		return false === $stat ? 0 : (int) $stat['size'];
	}

	/**
	 * Why the last operation on a path failed, as the shell reports it.
	 *
	 * @param string $path Path.
	 * @return Filesystem_Exception
	 */
	private function failure( string $path ): Filesystem_Exception {
		$last    = error_get_last();
		$message = is_array( $last ) ? (string) $last['message'] : '';
		if ( false !== strpos( $message, 'Permission denied' ) ) {
			return Filesystem_Exception::permission_denied( $path );
		}
		if ( false !== strpos( $message, 'Not a directory' ) ) {
			return Filesystem_Exception::not_a_directory( $path );
		}
		if ( false !== strpos( $message, 'Is a directory' ) ) {
			return Filesystem_Exception::is_a_directory( $path );
		}
		if ( false !== strpos( $message, 'No such file' ) ) {
			return Filesystem_Exception::not_found( $path );
		}
		if ( file_exists( $path ) || is_link( $path ) ) {
			return is_dir( $path ) ? Filesystem_Exception::is_a_directory( $path ) : Filesystem_Exception::permission_denied( $path );
		}

		return Filesystem_Exception::not_found( $path );
	}
}
