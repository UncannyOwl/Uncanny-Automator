<?php
/**
 * Depth-first directory walk in directory order.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command output is plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Commands;

use Generator;
use Throwable;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Budget\Budget_Ledger;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Filesystem_Exception;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Unexpected_Failure;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Directory_Entry;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\File_Info;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Read_Only_Filesystem;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Pattern\Glob_Pattern;

/**
 * Iterative depth-first pre-order directory walk over the filesystem port,
 * in directory order: exactly the order GNU find and grep -r print.
 *
 * Honours a maximum depth, excluded directory names, the files-scanned
 * budget, and wall time. Does not descend into symbolic links below the
 * start, which is what `find -H` and `grep -r` do; the caller decides, from
 * the start's information, whether a starting point that is a link is a
 * directory to walk.
 */
final class Tree_Walker {

	/**
	 * Skipped at any depth by default: third-party code trees, caches, and
	 * backups. Never uploads: a malware sweep or a user's own files live
	 * there, and the extension filters keep the walk small.
	 */
	public const DEFAULT_EXCLUDE_DIRS = array( 'node_modules', 'vendor', '.git', 'cache', '.cache', 'upgrade', 'backups' );

	/**
	 * The filesystem.
	 *
	 * @var Read_Only_Filesystem
	 */
	private Read_Only_Filesystem $filesystem;

	/**
	 * The budget every visit counts against.
	 *
	 * @var Budget_Ledger
	 */
	private Budget_Ledger $ledger;

	/**
	 * Directories not descended into because of exclude_dir.
	 *
	 * @var int
	 */
	private int $skipped = 0;

	/**
	 * Walk over this filesystem within this budget.
	 *
	 * @param Read_Only_Filesystem $filesystem The filesystem.
	 * @param Budget_Ledger        $ledger     The budget every visit counts against.
	 */
	public function __construct( Read_Only_Filesystem $filesystem, Budget_Ledger $ledger ) {
		$this->filesystem = $filesystem;
		$this->ledger     = $ledger;
	}

	/**
	 * Directories not descended into because of exclude_dir, so a zero result
	 * can say whether the excludes might be why.
	 *
	 * @return int
	 */
	public function skipped(): int {
		return $this->skipped;
	}

	/**
	 * Walk the tree, start first.
	 *
	 * @param Resolved_Path $start        Where to start.
	 * @param File_Info     $start_info   Its lstat information.
	 * @param int           $max_depth    Deepest level to yield.
	 * @param string[]      $exclude_dirs Glob patterns matched against directory names; never descended into.
	 * @param callable      $on_error     Called with (Resolved_Path, Filesystem_Exception) for a directory that cannot be listed.
	 * @return Generator<Walk_Entry>
	 */
	public function walk( Resolved_Path $start, File_Info $start_info, int $max_depth, array $exclude_dirs, callable $on_error ): Generator {
		yield new Walk_Entry( $start, $start_info, 0 );

		if ( ! $start_info->is_directory() || $max_depth < 1 ) {
			return;
		}

		/**
		 * Each open directory, its entry iterator and the depth of its entries.
		 *
		 * @var array<int, array{0: Resolved_Path, 1: Generator<Directory_Entry>, 2: int}> $stack
		 */
		$stack = array( array( $start, $this->entries( $start, $on_error ), 1 ) );

		while ( array() !== $stack ) {
			list( $directory, $entries, $depth ) = $stack[ count( $stack ) - 1 ];
			$this->ledger->check_time();
			if ( ! $entries->valid() ) {
				array_pop( $stack );
				continue;
			}

			$entry = $entries->current();
			$child = $directory->child( $entry->name() );
			$this->ledger->scan_file( $child->given() );
			yield new Walk_Entry( $child, $entry->info(), $depth );

			// Keep only the iterator for each ancestor, rather than all its
			// children. One wide directory must not be buffered before budgets
			// can stop the walk. Advancing retains at most one next entry.
			$entries->next();
			if ( $entry->info()->is_directory() && $depth < $max_depth ) {
				if ( Glob_Pattern::any_matches( $exclude_dirs, $entry->name() ) ) {
					++$this->skipped;
				} else {
					$stack[] = array( $child, $this->entries( $child, $on_error ), $depth + 1 );
				}
			}
		}
	}

	/**
	 * The entries of one directory, reporting a listing failure through $on_error.
	 *
	 * @param Resolved_Path $directory The directory.
	 * @param callable      $on_error  Called with (Resolved_Path, Filesystem_Exception).
	 * @return Generator<Directory_Entry>
	 */
	private function entries( Resolved_Path $directory, callable $on_error ): Generator {
		try {
			yield from $this->filesystem->list( $directory->real() );
		} catch ( Filesystem_Exception $failure ) {
			$on_error( $directory, $failure );
		} catch ( Throwable $failure ) {
			// Preserve expected control-flow exceptions; contextualize unexpected failures.
			throw Unexpected_Failure::during( 'walking directories', $failure );
		}
	}
}
