<?php
/**
 * core verify-checksums: compare WordPress core with the files wordpress.org ships.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Commands;

use Throwable;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Budget\Budget_Ledger;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Exit_Status;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Invocation;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Command\Specification;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Budget_Exceeded;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Directory_Entry;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Read_Only_Filesystem;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Sandbox\Sandbox;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Transcript\Stream;
use Uncanny_Automator\App\Site_Commands\Domain\Files\WordPress\Core_Checksum_Source;
use Uncanny_Automator\App\Site_Commands\Domain\Files\WordPress\Core_Checksums;

/**
 * Hashes every core file and lists the modified, missing and unexpected
 * ones, with the next step to take. Unexpected PHP files in wp-admin or
 * wp-includes are a classic sign of malware.
 *
 * The only file command that contacts another service: it fetches the
 * checksum list from api.wordpress.org (Domain/POLICY.md).
 */
final class Verify_Checksums extends Abstract_Command {

	public const NAME = 'core verify-checksums';

	/**
	 * Fetching the checksum list from wordpress.org stops after this.
	 */
	private const CHECKSUMS_SECONDS = 5.0;

	/**
	 * Rows shown per list.
	 */
	private const MAX_ROWS = 200;

	/**
	 * Time kept for printing what was found when the time runs out first.
	 */
	private const REPORT_SECONDS = 0.5;

	/**
	 * Where the checksum list comes from.
	 *
	 * @var Core_Checksum_Source
	 */
	private Core_Checksum_Source $source;

	/**
	 * Constructor.
	 *
	 * @param Core_Checksum_Source $source Where the checksum list comes from.
	 */
	public function __construct( Core_Checksum_Source $source ) {
		$this->source = $source;
	}

	/**
	 * The command's contract.
	 *
	 * @return Specification
	 */
	public static function specification(): Specification {
		return new Specification(
			self::NAME,
			'Compare WordPress core files with the checksums wordpress.org publishes.',
			'Hash every WordPress core file and compare it with the official checksum list for the installed version and language, fetched from api.wordpress.org. Lists MODIFIED and MISSING core files, and UNEXPECTED files in wp-admin and wp-includes, where an extra PHP file is a classic sign of malware, and UNVERIFIED core files it could not read, such as protected or very large ones. Files in wp-content are not core and are not checked. Exit 1 means findings; exit 2 means the check could not run or could not read every core file.',
			array(),
			array()
		);
	}

	/**
	 * Run the check.
	 *
	 * @param Invocation           $invocation The validated arguments.
	 * @param Sandbox              $sandbox    Resolves every path.
	 * @param Budget_Ledger        $ledger     The only way to write output.
	 * @param Read_Only_Filesystem $filesystem The only way to read.
	 * @return Exit_Status
	 *
	 * @throws Budget_Exceeded When a budget runs out.
	 */
	public function run( Invocation $invocation, Sandbox $sandbox, Budget_Ledger $ledger, Read_Only_Filesystem $filesystem ): Exit_Status {
		$list = $this->source->core_checksums( min( self::CHECKSUMS_SECONDS, $ledger->remaining_seconds() ) );
		if ( null === $list ) {
			$ledger->err( self::NAME . ": WordPress is not running here; this check needs the site\n" );

			return Exit_Status::trouble();
		}
		$checksums = $list->checksums();
		if ( null === $checksums ) {
			$ledger->err( sprintf( "%s: could not fetch the checksum list for WordPress %s (%s): %s\n", self::NAME, $list->version(), $list->locale(), (string) $list->error() ) );
			$ledger->out( "next: the site cannot reach api.wordpress.org; Site Health in wp-admin can run an active connectivity test. Meanwhile find wp-includes wp-admin -mmin -1440 shows recent changes\n" );

			return Exit_Status::trouble();
		}

		$modified       = array();
		$missing        = array();
		$unverified     = array();
		$bundled_absent = 0;
		$checked        = 0;
		$reached        = 0;
		$cap            = $ledger->limits()->max_hash_file_bytes();
		$unexpected     = array();
		$finished       = false;
		try {
			$finished = $this->compare( $checksums, $sandbox, $ledger, $filesystem, $cap, $modified, $missing, $unverified, $bundled_absent, $checked, $reached );
			foreach ( array( 'wp-admin', 'wp-includes' ) as $top ) {
				$finished = $finished && $this->collect_unexpected( $top, $checksums, $sandbox, $ledger, $filesystem, $unexpected, $unverified );
			}
		} catch ( Budget_Exceeded $exceeded ) {
			// A budget stopped the check and its notice is written; what was found so far stands.
			unset( $exceeded );
			$finished = false;
		}

		$this->print_results( $list, $checksums, $ledger, $finished, $reached, $checked, $bundled_absent, $modified, $missing, $unexpected, $unverified );

		// A check that could not read every file is incomplete, whatever it found.
		if ( array() !== $unverified || ! $finished ) {
			return Exit_Status::trouble();
		}

		return array() === $modified && array() === $missing && array() === $unexpected ? Exit_Status::success() : Exit_Status::failure();
	}

	/**
	 * Compare each listed core file with its checksum, collecting the
	 * findings in place, so a budget that stops the comparison keeps them.
	 *
	 * @param array<string, string> $checksums      Relative path => MD5.
	 * @param Sandbox               $sandbox        Resolves every path.
	 * @param Budget_Ledger         $ledger         Budgets.
	 * @param Read_Only_Filesystem  $filesystem     The only way to read.
	 * @param int                   $cap            Largest file hashed.
	 * @param string[]              $modified       Modified files.
	 * @param string[]              $missing        Missing files.
	 * @param string[]              $unverified     Files that could not be checked.
	 * @param int                   $bundled_absent Bundled theme or plugin files not present.
	 * @param int                   $checked        Files hashed and compared.
	 * @param int                   $reached        Files whose comparison began.
	 * @return bool False when the time ran out first.
	 *
	 * @throws Budget_Exceeded When a budget runs out.
	 */
	private function compare( array $checksums, Sandbox $sandbox, Budget_Ledger $ledger, Read_Only_Filesystem $filesystem, int $cap, array &$modified, array &$missing, array &$unverified, int &$bundled_absent, int &$checked, int &$reached ): bool {
		foreach ( $checksums as $relative => $expected ) {
			if ( $this->out_of_time( $ledger ) ) {
				return false;
			}
			$ledger->scan_file();
			++$reached;
			$relative = (string) $relative;
			try {
				$real = $sandbox->resolve( $relative, $filesystem );
				if ( ! $filesystem->exists( $real ) ) {
					if ( 0 === strpos( $relative, 'wp-content/' ) ) {
						++$bundled_absent;
					} else {
						$missing[] = $relative;
					}
					continue;
				}
				// A FIFO or device would block the read; protected content is never opened.
				$info = $filesystem->info( $real );
				if ( ! $info->is_file() ) {
					$unverified[] = $relative . '  (not a regular file; not read)';
					continue;
				}
				if ( ! $sandbox->allows_content( $real, $filesystem, $sandbox->physical( $relative ), $relative ) ) {
					$unverified[] = $relative . '  (protected on this site; not read)';
					continue;
				}
				if ( $info->size() > $cap ) {
					$unverified[] = sprintf( '%s  (%d bytes, more than the %d this check reads)', $relative, $info->size(), $cap );
					continue;
				}
				$actual = $filesystem->hash( $real, 'md5', $cap );
				if ( null === $actual ) {
					$unverified[] = $relative . '  (could not be read)';
					continue;
				}
				++$checked;
				if ( strtolower( $actual ) !== strtolower( (string) $expected ) ) {
					$modified[] = $relative;
				}
			} catch ( Budget_Exceeded $failure ) {
				throw $failure;
			} catch ( Throwable $failure ) {
				unset( $failure );
				$unverified[] = $relative . '  (could not be read)';
			}
		}

		return true;
	}

	/**
	 * Print the summary, the lists and the next step. Also after a budget
	 * stopped the check, so what it found is never lost (Budget_Ledger::report()).
	 *
	 * @param Core_Checksums        $core_checksums The checksum list.
	 * @param array<string, string> $checksums      Relative path => MD5.
	 * @param Budget_Ledger         $ledger         Budgets.
	 * @param bool                  $finished       Whether every file and folder was checked.
	 * @param int                   $reached        Files whose comparison began.
	 * @param int                   $checked        Files hashed and compared.
	 * @param int                   $bundled_absent Bundled theme or plugin files not present.
	 * @param string[]              $modified       Modified files.
	 * @param string[]              $missing        Missing files.
	 * @param string[]              $unexpected     Unexpected files.
	 * @param string[]              $unverified     Files that could not be checked.
	 * @return void
	 *
	 * @throws Budget_Exceeded When the output budget runs out.
	 */
	private function print_results( Core_Checksums $core_checksums, array $checksums, Budget_Ledger $ledger, bool $finished, int $reached, int $checked, int $bundled_absent, array $modified, array $missing, array $unexpected, array $unverified ): void {
		$ledger->report(
			sprintf(
				"Core %s (%s): %d files checked against wordpress.org; %d modified, %d missing, %d unexpected%s%s\n\n",
				$core_checksums->version(),
				$core_checksums->locale(),
				$checked,
				count( $modified ),
				count( $missing ),
				count( $unexpected ),
				array() === $unverified ? '' : sprintf( ', %d not verified', count( $unverified ) ),
				$bundled_absent > 0 ? sprintf( '; %d bundled theme or plugin files not present (normal when defaults were removed)', $bundled_absent ) : ''
			)
		);
		if ( ! $finished ) {
			// What was found stands; the rest was not looked at.
			$ledger->transcript()->mark_truncated();
			$ledger->report(
				sprintf(
					"The check did not finish within its %.1f seconds: %d of %d listed core files were compared%s, so these results are partial.\n\n",
					$ledger->limits()->wall_time_seconds(),
					$reached,
					count( $checksums ),
					count( $checksums ) === $reached ? ' and the search for unexpected files in wp-admin and wp-includes did not finish' : ' and unexpected files in wp-admin and wp-includes were not looked for'
				)
			);
		}
		$lists = array(
			'MODIFIED'   => $modified,
			'MISSING'    => $missing,
			'UNEXPECTED' => $unexpected,
			'UNVERIFIED' => $unverified,
		);
		foreach ( $lists as $label => $paths ) {
			if ( array() === $paths ) {
				continue;
			}
			$ledger->report( $label . "\n" );
			$shown = 0;
			foreach ( $paths as $path ) {
				if ( ++$shown > self::MAX_ROWS ) {
					$ledger->report( sprintf( "%s: %d more %s files not shown\n", self::NAME, count( $paths ) - self::MAX_ROWS, strtolower( $label ) ), Stream::STDERR );
					break;
				}
				$ledger->report( '  ' . $path . "\n" );
			}
			$ledger->report( "\n" );
		}
		if ( ! $finished ) {
			$ledger->report( "next: run core verify-checksums again, as each call has its own time, and act on what it found meanwhile; find wp-admin wp-includes -mmin -1440 lists core files changed in the last day\n" );
		} elseif ( array() !== $modified || array() !== $unexpected ) {
			$first = $modified[0] ?? explode( '  (', $unexpected[0], 2 )[0];
			$ledger->report( sprintf( "next: stat %s for its date, then head -c 600 %s to see what was added; unexpected PHP files in wp-includes or wp-admin are a classic malware sign\n", $first, $first ) );
		} elseif ( array() !== $missing ) {
			$ledger->report( "next: missing core files break the features that load them; the site owner can restore WordPress's own files with Re-install under Dashboard › Updates, which leaves plugins, themes and uploads alone\n" );
		} elseif ( array() !== $unverified ) {
			$first = explode( '  (', $unverified[0], 2 )[0];
			$ledger->report( sprintf( "next: some core files could not be checked, so core is not confirmed intact; stat %s shows what it is\n", $first ) );
		} else {
			$ledger->report( "next: core is intact; if the site still misbehaves the cause is in wp-content: find wp-content -mmin -1440 -name '*.php'\n" );
		}
	}

	/**
	 * Add the files under one core folder that the checksum list does not name.
	 *
	 * @param string                $top        "wp-admin" or "wp-includes".
	 * @param array<string, string> $checksums  Relative path => MD5.
	 * @param Sandbox               $sandbox    Resolves every path.
	 * @param Budget_Ledger         $ledger     Budgets.
	 * @param Read_Only_Filesystem  $filesystem The only way to read.
	 * @param string[]              $unexpected Collected rows.
	 * @param string[]              $unverified Folders that could not be listed, so their files went unchecked.
	 * @return bool False when the time ran out first.
	 *
	 * @throws Budget_Exceeded When a budget runs out.
	 */
	private function collect_unexpected( string $top, array $checksums, Sandbox $sandbox, Budget_Ledger $ledger, Read_Only_Filesystem $filesystem, array &$unexpected, array &$unverified ): bool {
		try {
			$root = $sandbox->resolve( $top, $filesystem );
		} catch ( Throwable $failure ) {
			unset( $failure );
			$unverified[] = $top . '/  (folder could not be read; files in it not checked)';
			return true;
		}
		if ( ! $filesystem->exists( $root ) ) {
			return true;
		}
		$stack = array( array( $top, $root ) );
		while ( array() !== $stack ) {
			if ( $this->out_of_time( $ledger ) ) {
				return false;
			}
			list( $relative, $real ) = array_pop( $stack );
			$ledger->scan_file();
			try {
				$entries = array();
				foreach ( $filesystem->list( $real ) as $entry ) {
					$ledger->scan_file( $relative . '/' . $entry->name() );
					$ledger->check_memory();
					$entries[] = $entry;
				}
			} catch ( Budget_Exceeded $failure ) {
				throw $failure;
			} catch ( Throwable $failure ) {
				unset( $failure );
				$unverified[] = $relative . '/  (folder could not be listed; files in it not checked)';
				continue;
			}
			usort(
				$entries,
				static function ( Directory_Entry $a, Directory_Entry $b ) use ( $ledger ): int {
					$ledger->tick();

					return strcmp( $b->name(), $a->name() );
				}
			);
			foreach ( $entries as $entry ) {
				$ledger->check_time();
				$ledger->check_memory();
				$child_relative = $relative . '/' . $entry->name();
				$info           = $entry->info();
				if ( $info->is_directory() ) {
					$stack[] = array( $child_relative, rtrim( $real, '/' ) . '/' . $entry->name() );
					continue;
				}
				if ( ! isset( $checksums[ $child_relative ] ) ) {
					$extension    = pathinfo( $entry->name(), PATHINFO_EXTENSION );
					$unexpected[] = sprintf(
						'%s  (name exactly as on disk; %d bytes, modified %s%s)',
						$child_relative,
						$info->size(),
						gmdate( 'Y-m-d H:i', $info->mtime() ),
						'' === $extension ? '; no extension' : ( 'php' === strtolower( $extension ) ? '; a PHP file core does not ship' : '' )
					);
				}
			}
		}

		return true;
	}

	/**
	 * Whether only the time to print the results is left, so the check stops
	 * before its budget does and still reports what it found.
	 *
	 * @param Budget_Ledger $ledger Budgets.
	 * @return bool
	 *
	 * @throws Budget_Exceeded When no time is left at all.
	 */
	private function out_of_time( Budget_Ledger $ledger ): bool {
		return $ledger->remaining_seconds() < self::REPORT_SECONDS;
	}
}
