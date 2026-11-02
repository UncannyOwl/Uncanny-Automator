<?php
/**
 * ls: list directory contents.
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
use Uncanny_Automator\App\Site_Commands\Domain\Files\Commands\Support\Human_Size;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Commands\Support\Ls_Date;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Filesystem_Exception;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Unexpected_Failure;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\File_Info;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Read_Only_Filesystem;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Sandbox\Sandbox;

/**
 * Lists directories the way GNU ls does: operands that are files first,
 * then each directory's entries, sorted by name or by time.
 */
final class Ls extends Abstract_Command {

	/**
	 * The command's contract.
	 *
	 * @return Specification
	 */
	public static function specification(): Specification {
		return new Specification(
			'ls',
			'List directory contents.',
			'List files in a directory. Use this to discover real names before reading files. Use -l for permissions, size, and modified time; -t for newest first; -S for largest first. Output is always one name per line.',
			array(
				Option::flag( 'long', 'l', 'Use the long listing format.' ),
				Option::flag( 'all', 'a', 'Include entries starting with a dot. The parent entry is omitted when it is outside readable roots.' ),
				Option::flag( 'sort_by_time', 't', 'Sort by modification time, newest first.' ),
				Option::flag( 'sort_by_size', 'S', 'Sort by file size, largest first. Wins over -t.' ),
				Option::flag( 'one_per_line', '1', 'One name per line; accepted for compatibility, as output is always one per line.' ),
				Option::flag( 'reverse', 'r', 'Reverse the sort order.' ),
				Option::flag( 'human_readable', 'h', 'Print sizes like 1K, 234M, 2G.' ),
				Option::flag( 'directory', 'd', 'List directories themselves, not their contents.' ),
			),
			array(
				Operand::paths( 'Paths to list. Defaults to the site root.' ),
			)
		);
	}

	/**
	 * List each operand.
	 *
	 * @param Invocation           $invocation The validated arguments.
	 * @param Sandbox              $sandbox    Resolves every path.
	 * @param Budget_Ledger        $ledger     The only way to write output.
	 * @param Read_Only_Filesystem $filesystem The only way to read.
	 * @return Exit_Status
	 */
	public function run( Invocation $invocation, Sandbox $sandbox, Budget_Ledger $ledger, Read_Only_Filesystem $filesystem ): Exit_Status {
		$status = Exit_Status::success();
		$paths  = $invocation->paths();
		if ( array() === $paths ) {
			$paths = array( '.' );
		}

		$files       = array();
		$directories = array();
		foreach ( $paths as $given ) {
			$resolved = $this->resolve( $given, $sandbox, $ledger, $filesystem, 'cannot access' );
			if ( null === $resolved ) {
				$status = Exit_Status::trouble();
				continue;
			}
			try {
				$info             = $filesystem->info( $resolved->physical(), false );
				$target           = $info->is_link() ? $filesystem->info( $resolved->real(), true ) : $info;
				$show_link_itself = $info->is_link() && $invocation->flag( 'long' ) && '/' !== substr( $given, -1 );
				if ( $target->is_directory() && ! $invocation->flag( 'directory' ) && ! $show_link_itself ) {
					$directories[] = array( $resolved, $target );
				} else {
					$files[] = array( $given, $info );
				}
			} catch ( Filesystem_Exception $failure ) {
				$this->report_filesystem( $failure, $given, $ledger, 'cannot access' );
				$status = Exit_Status::trouble();
			} catch ( Throwable $failure ) {
				// Preserve expected control-flow exceptions; contextualize unexpected failures.
				throw Unexpected_Failure::during( 'listing directory entries', $failure );
			}
		}

		$printed_something = false;
		if ( array() !== $files ) {
			$this->print_block( $files, $invocation, $ledger, false );
			$printed_something = true;
		}

		$show_headers = count( $paths ) > 1;
		foreach ( $directories as list( $resolved, $info ) ) {
			if ( $printed_something ) {
				$ledger->out( "\n" );
			}
			if ( $show_headers ) {
				$ledger->out( $resolved->given() . ":\n" );
			}
			try {
				$entries = array();
				if ( $invocation->flag( 'all' ) ) {
					$entries[] = array( '.', $info );
					// A sandbox root has no visible parent. Never stat outside it.
					$parent = dirname( $resolved->real() );
					if ( $sandbox->contains( $parent ) ) {
						$parent    = $sandbox->resolve( $parent, $filesystem );
						$entries[] = array( '..', $filesystem->info( $parent, true ) );
					}
				}
				foreach ( $filesystem->list( $resolved->real() ) as $entry ) {
					$ledger->scan_file();
					if ( ! $invocation->flag( 'all' ) && 0 === strpos( $entry->name(), '.' ) ) {
						continue;
					}
					$entries[] = array( $entry->name(), $entry->info() );
				}
				$this->print_block( $entries, $invocation, $ledger, true );
			} catch ( Filesystem_Exception $failure ) {
				$this->report_filesystem( $failure, $resolved->given(), $ledger, 'cannot open directory' );
				$status = Exit_Status::trouble();
			} catch ( Throwable $failure ) {
				// Preserve expected control-flow exceptions; contextualize unexpected failures.
				throw Unexpected_Failure::during( 'listing directory entries', $failure );
			}
			$printed_something = true;
		}

		return $status;
	}

	/**
	 * GNU ls quotes operands in messages.
	 *
	 * @return bool
	 */
	protected function quotes_operands(): bool {
		return true;
	}

	/**
	 * Print one block of entries, short or long.
	 *
	 * @param array<int, array{0: string, 1: File_Info}> $entries    Names and their information.
	 * @param Invocation                                 $invocation The validated arguments.
	 * @param Budget_Ledger                              $ledger     The ledger.
	 * @param bool                                       $with_total Whether to print the "total" line first.
	 * @return void
	 */
	private function print_block( array $entries, Invocation $invocation, Budget_Ledger $ledger, bool $with_total ): void {
		$entries = $this->sort( $entries, $invocation );
		$human   = $invocation->flag( 'human_readable' );

		if ( ! $invocation->flag( 'long' ) ) {
			foreach ( $entries as list( $name ) ) {
				$ledger->out( $name . "\n" );
			}

			return;
		}

		if ( $with_total ) {
			$blocks = 0;
			foreach ( $entries as list( , $info ) ) {
				$blocks += $info->blocks();
			}
			$total = (int) ceil( $blocks / 2 );
			$ledger->out( 'total ' . ( $human ? Human_Size::format( $total * 1024 ) : (string) $total ) . "\n" );
		}

		$widths = array(
			'nlink' => 1,
			'owner' => 1,
			'group' => 1,
			'size'  => 1,
		);
		$rows   = array();
		foreach ( $entries as list( $name, $info ) ) {
			$size            = $human ? Human_Size::format( $info->size() ) : (string) $info->size();
			$rows[]          = array( $info, $name, $size );
			$widths['nlink'] = max( $widths['nlink'], strlen( (string) $info->nlink() ) );
			$widths['owner'] = max( $widths['owner'], strlen( $info->owner_name() ) );
			$widths['group'] = max( $widths['group'], strlen( $info->group_name() ) );
			$widths['size']  = max( $widths['size'], strlen( $size ) );
		}

		$now = $ledger->clock()->wall_time();
		foreach ( $rows as list( $info, $name, $size ) ) {
			$display = $info->is_link() && null !== $info->link_target() ? $name . ' -> ' . $info->link_target() : $name;
			$ledger->out(
				sprintf(
					"%s %s %s %s %s %s %s\n",
					$info->permission_string(),
					str_pad( (string) $info->nlink(), $widths['nlink'], ' ', STR_PAD_LEFT ),
					str_pad( $info->owner_name(), $widths['owner'] ),
					str_pad( $info->group_name(), $widths['group'] ),
					str_pad( $size, $widths['size'], ' ', STR_PAD_LEFT ),
					Ls_Date::format( $info->mtime(), $now ),
					$display
				)
			);
		}
	}

	/**
	 * Sort by name, by size largest first, or by time newest first, with
	 * name as the tie-break.
	 *
	 * @param array<int, array{0: string, 1: File_Info}> $entries    Names and their information.
	 * @param Invocation                                 $invocation The validated arguments.
	 * @return array<int, array{0: string, 1: File_Info}>
	 */
	private function sort( array $entries, Invocation $invocation ): array {
		if ( $invocation->flag( 'sort_by_size' ) ) {
			usort(
				$entries,
				static function ( array $a, array $b ): int {
					$larger = $b[1]->size() <=> $a[1]->size();

					return 0 !== $larger ? $larger : strcmp( $a[0], $b[0] );
				}
			);
		} elseif ( $invocation->flag( 'sort_by_time' ) ) {
			usort( $entries, static fn ( array $a, array $b ): int => array( $b[1]->mtime(), $a[0] ) <=> array( $a[1]->mtime(), $b[0] ) );
		} else {
			usort( $entries, static fn ( array $a, array $b ): int => strcmp( $a[0], $b[0] ) );
		}

		return $invocation->flag( 'reverse' ) ? array_reverse( $entries ) : $entries;
	}
}
