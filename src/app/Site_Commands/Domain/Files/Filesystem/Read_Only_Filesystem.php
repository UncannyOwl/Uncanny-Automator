<?php
/**
 * The only way the file command domain looks at a filesystem.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem;

use Closure;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Filesystem_Exception;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Line_Read_Limit_Exceeded;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Tail_Read_Limit_Exceeded;

/**
 * The only way the domain looks at a filesystem. It cannot express a write.
 *
 * Every method takes a path the sandbox has already resolved. Adapters map
 * real failures to Filesystem_Exception so commands can print the strerror
 * text the shell would print.
 */
interface Read_Only_Filesystem {

	/**
	 * Canonical absolute path with symlinks resolved, or null when the path
	 * does not exist.
	 *
	 * @param string $path An absolute path.
	 * @return string|null
	 */
	public function realpath( string $path ): ?string;

	/**
	 * Whether the path exists.
	 *
	 * @param string $path An absolute path.
	 * @return bool
	 */
	public function exists( string $path ): bool;

	/**
	 * Stat, or lstat when links are not followed.
	 *
	 * @param string $path         An absolute path.
	 * @param bool   $follow_links False for lstat.
	 * @return File_Info
	 *
	 * @throws Filesystem_Exception When the path cannot be examined.
	 */
	public function info( string $path, bool $follow_links = true ): File_Info;

	/**
	 * Entries in the order the underlying directory returns them, which is
	 * what GNU find and grep -r print. Excludes `.` and `..`.
	 *
	 * @param string $directory An absolute path.
	 * @return iterable<Directory_Entry>
	 *
	 * @throws Filesystem_Exception When the directory cannot be listed.
	 */
	public function list( string $directory ): iterable;

	/**
	 * Complete lines with their terminators intact, streamed from the start.
	 * A line exceeding Bounded_Lines::MAX_BYTES raises Line_Read_Limit_Exceeded
	 * before yielding any part of that line; callers must report partial results.
	 *
	 * @param string $path An absolute path.
	 * @return iterable<string>
	 *
	 * @throws Filesystem_Exception     When the file cannot be read.
	 * @throws Line_Read_Limit_Exceeded When one line is too long.
	 */
	public function lines( string $path ): iterable;

	/**
	 * The last $count lines in file order, terminators intact, read from
	 * the end of the file without touching the front.
	 *
	 * The checkpoint runs during backward reads to enforce request budgets.
	 * Reads requiring more than 1 MB raise Tail_Read_Limit_Exceeded.
	 *
	 * @param string       $path       An absolute path.
	 * @param int          $count      Lines wanted.
	 * @param Closure|null $checkpoint Runs during the read; may throw to stop it.
	 * @return string[]
	 *
	 * @throws Filesystem_Exception     When the file cannot be read.
	 * @throws Tail_Read_Limit_Exceeded When the read would need more than 1 MB.
	 */
	public function tail_lines( string $path, int $count, ?Closure $checkpoint = null ): array;

	/**
	 * The last $bytes bytes of the file.
	 *
	 * @param string $path  An absolute path.
	 * @param int    $bytes Bytes wanted.
	 * @return string
	 *
	 * @throws Filesystem_Exception When the file cannot be read.
	 */
	public function tail_bytes( string $path, int $bytes ): string;

	/**
	 * The first $bytes bytes of the file, for content-kind detection.
	 *
	 * @param string $path  An absolute path.
	 * @param int    $bytes Bytes wanted.
	 * @return string
	 *
	 * @throws Filesystem_Exception When the file cannot be read.
	 */
	public function sample( string $path, int $bytes ): string;

	/**
	 * Hex digest, or null when the file is larger than $max_bytes.
	 *
	 * @param string $path      An absolute path.
	 * @param string $algorithm A hash() algorithm name.
	 * @param int    $max_bytes The largest file to hash.
	 * @return string|null
	 *
	 * @throws Filesystem_Exception When the file cannot be read.
	 */
	public function hash( string $path, string $algorithm, int $max_bytes ): ?string;
}
