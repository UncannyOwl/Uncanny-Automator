<?php
/**
 * find: search for files by name, type, time, or size.
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
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Option;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Specification;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Commands\Support\Ls_Date;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Commands\Support\Numeric_Test;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Commands\Support\Size_Test;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Filesystem_Exception;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Invalid_Invocation;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Unexpected_Failure;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\File_Info;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\File_Type;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Read_Only_Filesystem;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Pattern\Glob_Pattern;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Sandbox\Sandbox;

/**
 * Walks the starting points in GNU find's pre-order and prints the paths
 * that pass every test.
 */
final class Find extends Abstract_Command {

	/**
	 * The command's contract.
	 *
	 * @return Specification
	 */
	public static function specification(): Specification {
		return new Specification(
			'find',
			'Search for files by name, type, time, or size.',
			"Find paths by filename, type, size, or modification time. It does not search file contents; use grep for that. Start in a known directory with a path operand or --cwd. Example: find wp-content -name '*.php' -type f -maxdepth 3. Default exclusions skip dependency/cache/backup directories, not uploads; see --exclude-dir. A starting point that is a link is followed, as with find -H; links below it are listed, not followed. Empty output means no matching paths within the searched scope. If output is partial, narrow the directory or filters.",
			array(
				Option::list( 'name', null, "Glob on the base name, for example '*.php'. Given more than once, every glob must match, as each test in GNU find must pass." ),
				Option::list( 'names', null, "Several base-name globs; a file matching any one is printed, for example -names '*.bak' -names '*.old' -names '*.sql'." ),
				Option::flag( 'ls', null, 'Print each entry in ls -l form: permissions, links, owner, group, size, modification time, path. GNU find -ls.' ),
				Option::list( 'iname', null, 'Case-insensitive glob on the base name.' ),
				Option::list( 'type', null, 'Entry type: f, d or l.' ),
				Option::integer( 'maxdepth', null, 'Descend at most this many levels below the starting points.', null, 0, 64 ),
				Option::list( 'mmin', null, "Modified n minutes ago: '-60' less than 60, '+60' more than 60, '60' exactly." ),
				Option::list( 'mtime', null, 'Modified n days ago, same forms as mmin.' ),
				Option::list( 'newer', null, 'Modified more recently than this file.' ),
				Option::list( 'size', null, "Size test with GNU units: '+1M', '-100k', '10M'. No unit means 512-byte blocks." ),
				Option::list( 'not_path', null, 'Exclude paths matching these globs.' ),
				Option::list( 'exclude_dir', null, "Directory names not to descend into. Defaults to node_modules, vendor, .git, cache, .cache, upgrade, backups; uploads is not excluded. Pass --exclude-dir='' to descend into everything. Giving --exclude-dir replaces the defaults, so repeat it for every name to skip, such as uploads.", 20, Tree_Walker::DEFAULT_EXCLUDE_DIRS ),
			),
			array(
				Operand::paths( 'Starting points. Defaults to the site root.' ),
			)
		);
	}

	/**
	 * Walk and print matching paths.
	 *
	 * @param Invocation           $invocation The validated arguments.
	 * @param Sandbox              $sandbox    Resolves every path.
	 * @param Budget_Ledger        $ledger     The only way to write output.
	 * @param Read_Only_Filesystem $filesystem The only way to read.
	 * @return Exit_Status
	 *
	 * @throws Invalid_Invocation When a type, size, mmin or mtime test is malformed.
	 */
	public function run( Invocation $invocation, Sandbox $sandbox, Budget_Ledger $ledger, Read_Only_Filesystem $filesystem ): Exit_Status {
		$status  = Exit_Status::success();
		$printed = 0;
		$paths   = $invocation->paths();
		if ( array() === $paths ) {
			$paths = array( '.' );
		}

		$long_form    = $invocation->flag( 'ls' );
		$now          = $ledger->clock()->wall_time();
		$max_depth    = $invocation->integer( 'maxdepth' ) ?? 64;
		$exclude_dirs = $invocation->list( 'exclude_dir' );
		$tests        = $this->tests( $invocation, $now );

		// Every -newer file must be older than the entry: the newest of them decides.
		$newer_than = null;
		foreach ( $invocation->list( 'newer' ) as $newer ) {
			$reference = $this->resolve( $newer, $sandbox, $ledger, $filesystem );
			if ( null === $reference ) {
				return Exit_Status::failure();
			}
			try {
				$newer_than = max( $newer_than ?? PHP_INT_MIN, $filesystem->info( $reference->real() )->mtime() );
			} catch ( Filesystem_Exception $failure ) {
				$this->report_filesystem( $failure, $newer, $ledger );

				return Exit_Status::failure();
			} catch ( Throwable $failure ) {
				// Preserve expected control-flow exceptions; contextualize unexpected failures.
				throw Unexpected_Failure::during( 'searching directory entries', $failure );
			}
		}
		if ( null !== $newer_than ) {
			$tests[] = static function ( File_Info $info ) use ( $newer_than ): bool {
				return $info->mtime() > $newer_than;
			};
		}

		$walker = new Tree_Walker( $filesystem, $ledger );

		foreach ( $paths as $given ) {
			$resolved = $this->resolve( $given, $sandbox, $ledger, $filesystem );
			if ( null === $resolved ) {
				$status = Exit_Status::failure();
				continue;
			}
			try {
				// As find -H and grep -r: a starting point that is a link is followed. The
				// sandbox has already refused one that leads outside the readable folders.
				$start_info = $filesystem->info( $resolved->real(), true );
			} catch ( Filesystem_Exception $failure ) {
				$this->report_filesystem( $failure, $given, $ledger );
				$status = Exit_Status::failure();
				continue;
			} catch ( Throwable $failure ) {
				// Preserve expected control-flow exceptions; contextualize unexpected failures.
				throw Unexpected_Failure::during( 'searching directory entries', $failure );
			}

			$on_error = function ( Resolved_Path $path, Filesystem_Exception $failure ) use ( $ledger, &$status ): void {
				$this->report_filesystem( $failure, $path->given(), $ledger );
				$status = Exit_Status::failure();
			};

			foreach ( $walker->walk( $resolved, $start_info, $max_depth, $exclude_dirs, $on_error ) as $entry ) {
				$info = $entry->info();
				$base = basename( $entry->path()->given() );
				foreach ( $tests as $test ) {
					if ( ! $test( $info, $base, $entry->path()->given() ) ) {
						continue 2;
					}
				}
				++$printed;
				if ( $long_form ) {
					$ledger->out(
						sprintf(
							"%9d %6d %s %3d %-8s %-8s %8d %s %s%s\n",
							$info->inode(),
							(int) ceil( $info->blocks() / 2 ),
							$info->permission_string(),
							$info->nlink(),
							$info->owner_name(),
							$info->group_name(),
							$info->size(),
							Ls_Date::format( $info->mtime(), $now ),
							$entry->path()->given(),
							$info->is_link() && null !== $info->link_target() ? ' -> ' . $info->link_target() : ''
						)
					);
					continue;
				}
				$ledger->out( $entry->path()->given() . "\n" );
			}
		}
		if ( 0 === $printed && Tree_Walker::DEFAULT_EXCLUDE_DIRS === $exclude_dirs && $walker->skipped() > 0 ) {
			$ledger->err( 'find: nothing matched; subdirectories named ' . implode( ', ', Tree_Walker::DEFAULT_EXCLUDE_DIRS ) . " were skipped by default (uploads was walked); pass --exclude-dir='' to walk them too\n" );
		}

		return $status;
	}

	/**
	 * GNU find quotes operands in messages.
	 *
	 * @return bool
	 */
	protected function quotes_operands(): bool {
		return true;
	}

	/**
	 * The tests every printed entry must pass, from the options. A test
	 * given more than once must pass each time, as in GNU find, where
	 * predicates written one after another are joined by -a.
	 *
	 * @param Invocation $invocation The validated arguments.
	 * @param int        $now        Unix time, for -mmin and -mtime.
	 * @return array<int, callable(File_Info, string, string): bool> Each takes the entry, its base name and its path.
	 *
	 * @throws Invalid_Invocation When a type, size, mmin or mtime test is malformed.
	 */
	private function tests( Invocation $invocation, int $now ): array {
		$tests = array();
		foreach ( $invocation->list( 'type' ) as $type ) {
			if ( ! in_array( $type, array( 'f', 'd', 'l' ), true ) ) {
				throw Invalid_Invocation::invalid_argument( 'find', 'type', $type, 'expected one of f, d, l' );
			}
			$tests[] = static function ( File_Info $info ) use ( $type ): bool {
				return File_Type::code( $info->type() ) === $type;
			};
		}
		$case_folds = array(
			'name'  => false,
			'iname' => true,
		);
		foreach ( $case_folds as $option => $case_fold ) {
			foreach ( $invocation->list( $option ) as $glob ) {
				$pattern = new Glob_Pattern( $glob, $case_fold );
				$tests[] = static function ( File_Info $info, string $base ) use ( $pattern ): bool {
					return $pattern->matches( $base );
				};
			}
		}
		$names = $invocation->list( 'names' );
		if ( array() !== $names ) {
			$tests[] = static function ( File_Info $info, string $base ) use ( $names ): bool {
				return Glob_Pattern::any_matches( $names, $base );
			};
		}
		$units = array(
			'mmin'  => 60,
			'mtime' => 86400,
		);
		foreach ( $units as $option => $unit ) {
			foreach ( $invocation->list( $option ) as $value ) {
				$numeric = Numeric_Test::parse( $value );
				if ( null === $numeric ) {
					throw Invalid_Invocation::invalid_argument( 'find', $option, $value, 'expected a number like -60, +60, or 60' );
				}
				$tests[] = static function ( File_Info $info ) use ( $numeric, $now, $unit ): bool {
					return $numeric->accepts( (int) floor( ( $now - $info->mtime() ) / $unit ) );
				};
			}
		}
		foreach ( $invocation->list( 'size' ) as $value ) {
			$size = Size_Test::parse( $value );
			if ( null === $size ) {
				throw Invalid_Invocation::invalid_argument( 'find', 'size', $value, 'expected a size like +1M, -100k, or 10M' );
			}
			$tests[] = static function ( File_Info $info ) use ( $size ): bool {
				return $size->accepts( $info->size() );
			};
		}
		$not_path = $invocation->list( 'not_path' );
		if ( array() !== $not_path ) {
			$tests[] = static function ( File_Info $info, string $base, string $path ) use ( $not_path ): bool {
				return ! Glob_Pattern::any_matches( $not_path, $path );
			};
		}

		return $tests;
	}
}
