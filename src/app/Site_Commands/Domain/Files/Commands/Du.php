<?php
/**
 * du: estimate disk usage.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command output is plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Commands;

use Generator;
use Throwable;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Budget\Budget_Ledger;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Exit_Status;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Invocation;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Operand;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Option;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Specification;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Commands\Support\Human_Size;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Filesystem_Exception;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Invalid_Invocation;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Unexpected_Failure;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Directory_Entry;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\File_Info;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Read_Only_Filesystem;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Pattern\Glob_Pattern;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Sandbox\Sandbox;

/**
 * Disk usage in 1K blocks, directories in post-order like GNU du. Nothing
 * is excluded by default: du exists to find the big directories. As GNU du
 * counts each file once, a path already counted in this run, such as one a
 * linked operand reached first, is not printed again, and a directory that
 * holds one leaves it out of its total. Only what was measured counts: an
 * excluded or unreadable directory, and anything inside it, is measured when
 * a later operand names it. A file with several hard links is counted at the
 * first of its names only.
 */
final class Du extends Abstract_Command {

	/**
	 * The command's contract.
	 *
	 * @return Specification
	 */
	public static function specification(): Specification {
		return new Specification(
			'du',
			'Estimate disk usage.',
			'Measure directory size. Use -h for KB/MB/GB and -s for one total per path. Use -d (--max-depth) to limit detail. Nothing is excluded by default; --exclude-dir can skip irrelevant dependency or cache directories. A path that is a link is measured where it leads; links below it count as links.',
			array(
				Option::flag( 'summarize', 's', 'Display only a total for each argument.' ),
				Option::flag( 'all', 'a', 'Write counts for all files, not just directories.' ),
				Option::list( 'exclude_dir', null, 'Directory names not to count or descend into, for example --exclude-dir=.git --exclude-dir=node_modules. Nothing is excluded unless you say so.' ),
				Option::flag( 'human_readable', 'h', 'Print sizes like 1K, 234M, 2G.' ),
				Option::integer( 'max_depth', 'd', 'Print totals for directories at most this deep.', null, 0, 64 ),
			),
			array(
				Operand::paths( 'Paths to measure. Defaults to the site root.' ),
			)
		);
	}

	/**
	 * Directory name globs not counted or descended into.
	 *
	 * @var string[]
	 */
	private array $exclude_dirs = array();

	/**
	 * True for -a: files get a line too.
	 *
	 * @var bool
	 */
	private bool $all = false;

	/**
	 * Whether this run has more than one operand, so what was counted matters.
	 *
	 * @var bool
	 */
	private bool $track = false;

	/**
	 * Directories this run listed completely, by resolved path. Bounded by the
	 * ledger's file budget, like the walk itself.
	 *
	 * @var array<string, true>
	 */
	private array $measured = array();

	/**
	 * Files this run counted as operands, by resolved path.
	 *
	 * @var array<string, true>
	 */
	private array $counted_files = array();

	/**
	 * Directories whose listing failed in this run, by resolved path.
	 *
	 * @var array<string, true>
	 */
	private array $unreadable = array();

	/**
	 * Files with more than one hard link counted in this run, by device and
	 * inode.
	 *
	 * @var array<string, true>
	 */
	private array $hard_links = array();

	/**
	 * Measure each operand.
	 *
	 * @param Invocation           $invocation The validated arguments.
	 * @param Sandbox              $sandbox    Resolves every path.
	 * @param Budget_Ledger        $ledger     The only way to write output.
	 * @param Read_Only_Filesystem $filesystem The only way to read.
	 * @return Exit_Status
	 *
	 * @throws Invalid_Invocation When -s and -a are both given.
	 */
	public function run( Invocation $invocation, Sandbox $sandbox, Budget_Ledger $ledger, Read_Only_Filesystem $filesystem ): Exit_Status {
		$status = Exit_Status::success();
		$paths  = $invocation->paths();
		if ( array() === $paths ) {
			$paths = array( '.' );
		}
		if ( $invocation->flag( 'summarize' ) && $invocation->flag( 'all' ) ) {
			throw Invalid_Invocation::custom( 'du', array( 'du: cannot both summarize and show all entries', "Try 'du --help' for more information." ), 1 );
		}
		$this->all           = $invocation->flag( 'all' );
		$max_depth           = $invocation->flag( 'summarize' ) ? 0 : ( $invocation->integer( 'max_depth' ) ?? PHP_INT_MAX );
		$human               = $invocation->flag( 'human_readable' );
		$this->exclude_dirs  = $invocation->list( 'exclude_dir' );
		$this->track         = count( $paths ) > 1;
		$this->measured      = array();
		$this->counted_files = array();
		$this->unreadable    = array();
		$this->hard_links    = array();

		foreach ( $paths as $given ) {
			$resolved = $this->resolve( $given, $sandbox, $ledger, $filesystem, 'cannot access' );
			if ( null === $resolved ) {
				$status = Exit_Status::failure();
				continue;
			}
			try {
				// As du -D and grep -r: an operand that is a link is followed. The sandbox
				// has already refused one that leads outside the readable folders.
				$info = $filesystem->info( $resolved->real(), true );
			} catch ( Filesystem_Exception $failure ) {
				$this->report_filesystem( $failure, $given, $ledger, 'cannot access' );
				$status = Exit_Status::failure();
				continue;
			} catch ( Throwable $failure ) {
				// Preserve expected control-flow exceptions; contextualize unexpected failures.
				throw Unexpected_Failure::during( 'measuring directory usage', $failure );
			}

			$real = rtrim( $resolved->real(), '/' );
			if ( $this->track && $this->was_counted( $real, $info->is_directory() ) ) {
				// GNU du prints nothing for what it has counted already.
				continue;
			}
			if ( ! $info->is_directory() ) {
				if ( $this->counted_hard_link( $info ) ) {
					continue;
				}
				$ledger->out( $this->line( $info->blocks(), $resolved->given(), $human ) );
				if ( $this->track ) {
					$this->counted_files[ $real ] = true;
				}
				continue;
			}

			$this->measure( $resolved, $info->blocks(), 0, $max_depth, $human, $ledger, $filesystem, $status );
		}

		return $status;
	}

	/**
	 * Whether this run already counted a path. A file was counted with the
	 * directory that listed it; a directory only if it was listed itself, so
	 * one an earlier walk excluded or could not read was not.
	 *
	 * @param string $real         Resolved path.
	 * @param bool   $is_directory Whether the path is a directory.
	 * @return bool
	 */
	private function was_counted( string $real, bool $is_directory ): bool {
		if ( isset( $this->measured[ $real ] ) || isset( $this->counted_files[ $real ] ) ) {
			return true;
		}

		return ! $is_directory && isset( $this->measured[ dirname( $real ) ] );
	}

	/**
	 * Whether a file is another name of one this run already counted, as GNU
	 * du counts a file with several hard links once. Records it otherwise.
	 *
	 * @param File_Info $info The file.
	 * @return bool
	 */
	private function counted_hard_link( File_Info $info ): bool {
		if ( $info->is_directory() || $info->nlink() < 2 ) {
			return false;
		}
		$key = $info->device() . ':' . $info->inode();
		if ( isset( $this->hard_links[ $key ] ) ) {
			return true;
		}
		$this->hard_links[ $key ] = true;

		return false;
	}

	/**
	 * GNU du quotes operands in messages.
	 *
	 * @return bool
	 */
	protected function quotes_operands(): bool {
		return true;
	}

	/**
	 * Walk iteratively so directory depth does not consume PHP call frames.
	 *
	 * @param Resolved_Path        $directory  The directory to measure.
	 * @param int                  $own_blocks Its own 512-byte blocks.
	 * @param int                  $depth      Its depth below the operand.
	 * @param int                  $max_depth  Deepest level whose total is printed.
	 * @param bool                 $human      Whether to print human-readable sizes.
	 * @param Budget_Ledger        $ledger     The ledger.
	 * @param Read_Only_Filesystem $filesystem The filesystem.
	 * @param Exit_Status          $status     Set to failure when a directory cannot be read.
	 * @return void
	 */
	private function measure( Resolved_Path $directory, int $own_blocks, int $depth, int $max_depth, bool $human, Budget_Ledger $ledger, Read_Only_Filesystem $filesystem, Exit_Status &$status ): void {
		/**
		 * Each open directory, its blocks so far, its depth and its entries.
		 *
		 * @var array<int, array{0: Resolved_Path, 1: int, 2: int, 3: Generator<Directory_Entry>}> $stack
		 */
		$stack = array( array( $directory, $own_blocks, $depth, $this->entries( $directory, $ledger, $filesystem, $status ) ) );
		while ( array() !== $stack ) {
			$ledger->check_time();
			$ledger->check_memory();
			$top = count( $stack ) - 1;
			list( $current, $blocks, $current_depth, $entries ) = $stack[ $top ];
			if ( ! $entries->valid() ) {
				array_pop( $stack );
				$path = rtrim( $current->real(), '/' );
				if ( $this->track && ! isset( $this->unreadable[ $path ] ) ) {
					$this->measured[ $path ] = true;
				}
				$parent = array_pop( $stack );
				if ( null !== $parent ) {
					$stack[] = array( $parent[0], $parent[1] + $blocks, $parent[2], $parent[3] );
				}
				if ( $current_depth <= $max_depth ) {
					$ledger->out( $this->line( $blocks, $current->given(), $human ) );
				}
				continue;
			}

			$entry = $entries->current();
			$entries->next();
			if ( $entry->info()->is_directory() && Glob_Pattern::any_matches( $this->exclude_dirs, $entry->name() ) ) {
				continue;
			}
			if ( $this->track && $this->was_counted( rtrim( $current->real(), '/' ) . '/' . $entry->name(), $entry->info()->is_directory() ) ) {
				// An earlier operand counted it; GNU du leaves it out of this total.
				continue;
			}
			$ledger->scan_file( $current->given() . '/' . $entry->name() );
			if ( $entry->info()->is_directory() ) {
				$child   = $current->child( $entry->name() );
				$stack[] = array( $child, $entry->info()->blocks(), $current_depth + 1, $this->entries( $child, $ledger, $filesystem, $status ) );
			} else {
				if ( $this->counted_hard_link( $entry->info() ) ) {
					continue;
				}
				$stack[ $top ] = array( $current, $blocks + $entry->info()->blocks(), $current_depth, $entries );
				if ( $this->all && $current_depth + 1 <= $max_depth ) {
					$ledger->out( $this->line( $entry->info()->blocks(), $current->child( $entry->name() )->given(), $human ) );
				}
			}
		}
	}

	/**
	 * The entries of one directory, reporting a listing failure.
	 *
	 * @param Resolved_Path        $directory  The directory.
	 * @param Budget_Ledger        $ledger     The ledger.
	 * @param Read_Only_Filesystem $filesystem The filesystem.
	 * @param Exit_Status          $status     Set to failure when the directory cannot be read.
	 * @return Generator<Directory_Entry>
	 */
	private function entries( Resolved_Path $directory, Budget_Ledger $ledger, Read_Only_Filesystem $filesystem, Exit_Status &$status ): Generator {
		try {
			yield from $filesystem->list( $directory->real() );
		} catch ( Filesystem_Exception $failure ) {
			$this->report_filesystem( $failure, $directory->given(), $ledger, 'cannot read directory' );
			$this->unreadable[ rtrim( $directory->real(), '/' ) ] = true;
			$status = Exit_Status::failure();
		} catch ( Throwable $failure ) {
			// Preserve expected control-flow exceptions; contextualize unexpected failures.
			throw Unexpected_Failure::during( 'measuring directory usage', $failure );
		}
	}

	/**
	 * One output line: kilobytes (or a human size), a tab, the path.
	 *
	 * @param int    $blocks512 Size in 512-byte blocks.
	 * @param string $path      The path as given.
	 * @param bool   $human     Whether to print a human-readable size.
	 * @return string
	 */
	private function line( int $blocks512, string $path, bool $human ): string {
		$kilobytes = (int) ceil( $blocks512 / 2 );

		return ( $human ? Human_Size::format( $kilobytes * 1024 ) : (string) $kilobytes ) . "\t" . $path . "\n";
	}
}
