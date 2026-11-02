<?php
/**
 * The time limits and safeguards around one command that changes the site.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command errors are plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Infrastructure\Manage;

use Uncanny_Automator\App\Site_Commands\Domain\Command_Error;
use Uncanny_Automator\App\Site_Commands\Domain\Time_Budget;
use Uncanny_Automator\App\Site_Commands\Infrastructure\Commands\Settings_Commands;
use WP_Error;

/**
 * Everything a command runs happens inside one session, so that:
 *
 * - every outgoing request, from this command or from any plugin's hooks,
 *   and every redirect it follows, gets only the time left after what must
 *   follow it, and is refused when that time is not there; so does sending
 *   email. A request is reported as stopped for time only when the time it
 *   was given ran out;
 * - an upgrade's download, unzip and file replacement each start only when
 *   the time they and everything after them need is left. Replacing files
 *   cannot be interrupted once it starts;
 * - packages for installs come only from downloads.wordpress.org, a parent
 *   theme's included;
 * - only one command that changes files runs at a time, on a network across
 *   all its sites, and WordPress's own background updater waits meanwhile. The locks are released when the
 *   request ends, even after a fatal error, and a lock left by a command
 *   that was cut off is recognised as site_manage's own and taken over;
 * - PHP keeps running if the caller stops waiting, so files are never left
 *   half replaced;
 * - WordPress's follow-up update checks after an upgrade are skipped, as its
 *   own background updater skips them, because they would spend the time;
 * - an update is finished within the request: its backup is removed when it
 *   succeeded, and the previous version is put back, and checked, when it
 *   failed. A backup that could not be put back is left where it is, and the
 *   answer says when WordPress's weekly cleanup removes it. Time for this is
 *   kept back before files are replaced;
 * - a .maintenance file an upgrade in this session wrote is removed when the
 *   request ends, even after a fatal error, so the site is not left showing
 *   its maintenance page.
 */
final class Change_Session {

	/** Error code for work refused, or stopped, because too little time was left. */
	public const OUT_OF_TIME = 'automator_time_budget';

	/** Error code for a package that does not come from WordPress.org. */
	public const NOT_WORDPRESS_ORG = 'automator_not_wordpress_org';

	/** Shortest time worth starting an outgoing request with. */
	private const MIN_REQUEST_SECONDS = 2.0;

	/** Time kept for unzipping a plugin, theme or translation package. */
	public const UNZIP_SECONDS = 6.0;

	/** Said of a failed update that never moved the previous version aside. */
	public const STILL_IN_PLACE = 'The previous version is still in place.';

	/** Time to remove an update's backup, or put the previous version back. */
	public const CLEANUP_SECONDS = 5.0;

	/** Steps PHPMailer may take to send one email, each limited on its own. */
	private const MAIL_STEPS = 16;

	/** Name of the lock that keeps two file-changing commands apart. */
	private const LOCK = 'automator_site_manage';

	/**
	 * How long a lock left by a request that was cut off blocks others:
	 * longer than any request may run, since WordPress raises PHP's limit to
	 * 300 seconds while it replaces files.
	 */
	public const LOCK_SECONDS = 600;

	/** Remembers which background updater lock this plugin took. */
	private const UPDATER_MARK = 'automator_site_manage_updater';

	/** How long WordPress's own core updater holds its lock. */
	private const CORE_LOCK_SECONDS = 900;

	/** Errors a request that ran out of time ends with. */
	private const REQUEST_ERRORS = array( 'download_failed', 'plugins_api_failed', 'themes_api_failed', 'http_request_failed' );

	/** How close to its time a request must stop to count as stopped by it. */
	private const CUT_MARGIN_SECONDS = 0.5;

	/**
	 * Follow-up checks WordPress hooks to upgrader_process_complete, with the
	 * number of arguments each takes.
	 *
	 * @var array<int, array{0: string|array{0: string, 1: string}, 1: int}>
	 */
	private const FOLLOW_UP_CHECKS = array(
		array( 'wp_version_check', 0 ),
		array( 'wp_update_plugins', 0 ),
		array( 'wp_update_themes', 0 ),
		array( array( 'Language_Pack_Upgrader', 'async_upgrade' ), 1 ),
	);

	/**
	 * Time left.
	 *
	 * @var Time_Budget
	 */
	private Time_Budget $budget;

	/**
	 * Time an upgrade needs once its download finished: unzipping, replacing
	 * files and answering. Outgoing requests leave this much.
	 *
	 * @var float
	 */
	private float $after_download = 0.0;

	/**
	 * Time replacing files and answering need.
	 *
	 * @var float
	 */
	private float $replace = 0.0;

	/**
	 * Whether packages must come from downloads.wordpress.org.
	 *
	 * @var bool
	 */
	private bool $wordpress_org_only = false;

	/**
	 * Local packages this session downloaded itself, which may be installed.
	 *
	 * @var array<string, string> Local path by package URL.
	 */
	private array $downloaded = array();

	/**
	 * Outgoing requests that failed or were refused, by host.
	 *
	 * @var array<string, string>
	 */
	private array $failed_requests = array();

	/**
	 * Hosts whose requests this session refused for lack of time.
	 *
	 * @var array<string, true>
	 */
	private array $refused = array();

	/**
	 * When an upgrade that may write a .maintenance file started, while no
	 * such file existed; null otherwise.
	 *
	 * @var int|null
	 */
	private ?int $maintenance_from = null;

	/**
	 * Seconds a request to each host was cut to, since the last expect().
	 *
	 * @var array<string, float>
	 */
	private array $capped = array();

	/**
	 * Time left when the last request to each host was sent.
	 *
	 * @var array<string, float>
	 */
	private array $sent = array();

	/**
	 * Whether a request was stopped by its shortened time, since the last expect().
	 *
	 * @var float|null Seconds it was given.
	 */
	private ?float $cut_short = null;

	/**
	 * Whether WordPress finished putting the new files in place, since the
	 * last expect().
	 *
	 * @var bool
	 */
	private bool $replaced = false;

	/**
	 * Whether WordPress moved the old version aside whole, since the last
	 * expect().
	 *
	 * @var bool
	 */
	private bool $moved_aside = false;

	/**
	 * Locks this session holds, with the time each was taken, as stored.
	 *
	 * @var array<string, int|null>
	 */
	private array $locks = array();

	/**
	 * Hooks to remove when the session ends.
	 *
	 * @var array<int, array{0: string, 1: callable, 2: int}>
	 */
	private array $hooks = array();

	/**
	 * Run a command inside a session.
	 *
	 * @template T
	 * @param Time_Budget                $budget Time left.
	 * @param callable(Change_Session): T $work  The command.
	 * @return T
	 */
	public static function run( Time_Budget $budget, callable $work ) {
		Site_Changes::load_admin_files();
		$session = new self( $budget );
		$skipped = $session->start();
		try {
			return $work( $session );
		} finally {
			$session->stop( $skipped );
		}
	}

	/**
	 * Use this budget.
	 *
	 * @param Time_Budget $budget Time left.
	 */
	private function __construct( Time_Budget $budget ) {
		$this->budget = $budget;
	}

	/**
	 * The time left.
	 *
	 * @return Time_Budget
	 */
	public function budget(): Time_Budget {
		return $this->budget;
	}

	/**
	 * Set what the next steps need once a download finished, until changed.
	 *
	 * @param float $after_download Time unzipping, replacing files and answering need.
	 * @param float $replace        Time replacing files and answering need.
	 * @return void
	 */
	public function expect( float $after_download, float $replace ): void {
		$this->after_download = $after_download;
		$this->replace        = $replace;
		$this->capped         = array();
		$this->sent           = array();
		$this->cut_short      = null;
		$this->replaced       = false;
		$this->moved_aside    = false;
	}

	/**
	 * Whether WordPress reached the destination-cleared callback since the
	 * last expect(). A failed source read may happen after the backup move
	 * but before that callback, so false does not prove nothing was moved.
	 *
	 * @return bool
	 */
	public function moved_aside(): bool {
		return $this->moved_aside;
	}

	/**
	 * Whether WordPress finished putting the new files in place since the
	 * last expect(), so a failure after that left the new version.
	 *
	 * @return bool
	 */
	public function files_were_replaced(): bool {
		return $this->replaced;
	}

	/**
	 * Accept only packages from downloads.wordpress.org from now on, or
	 * stop requiring it.
	 *
	 * @param bool $only Whether to require it.
	 * @return void
	 */
	public function only_wordpress_org( bool $only = true ): void {
		$this->wordpress_org_only = $only;
	}

	/**
	 * Take the lock that keeps file-changing commands apart, and WordPress's
	 * background updater lock, so neither runs meanwhile.
	 *
	 * @return void
	 *
	 * @throws Command_Error While another command or WordPress's updater holds one.
	 */
	public function lock(): void {
		self::on_main_site(
			function (): void {
				$this->take_locks();
			}
		);
	}

	/**
	 * Take both locks in the current site's options.
	 *
	 * @return void
	 *
	 * @throws Command_Error While another command or WordPress's updater holds one.
	 */
	private function take_locks(): void {
		if ( ! \WP_Upgrader::create_lock( self::LOCK, self::LOCK_SECONDS ) ) {
			throw new Command_Error( self::another_command() );
		}
		$this->hold_lock( self::LOCK );
		if ( ! \WP_Upgrader::create_lock( 'auto_updater', HOUR_IN_SECONDS ) ) {
			if ( ! self::is_own_updater_lock() ) {
				throw new Command_Error( self::background_updates() );
			}
			// Left by a site_manage command that was cut off; this command holds site_manage's lock now.
			\WP_Upgrader::release_lock( 'auto_updater' );
			if ( ! \WP_Upgrader::create_lock( 'auto_updater', HOUR_IN_SECONDS ) ) {
				throw new Command_Error( self::background_updates() );
			}
		}
		$this->hold_lock( 'auto_updater' );
		update_option( self::UPDATER_MARK, (string) $this->locks['auto_updater'], false );
	}

	/**
	 * Refuse while another site_manage command, WordPress's background
	 * updater or WordPress's own update runs, so two updates never replace
	 * the same files at once.
	 *
	 * @return void
	 *
	 * @throws Command_Error When one is running.
	 */
	public static function require_no_other_updates(): void {
		self::on_main_site(
			static function (): void {
				self::check_other_updates();
			}
		);
	}

	/**
	 * Refuse while another update runs, reading the current site's locks.
	 *
	 * @return void
	 *
	 * @throws Command_Error When one is running.
	 */
	private static function check_other_updates(): void {
		$own = self::lock_time( self::LOCK );
		if ( null !== $own && $own > time() - self::LOCK_SECONDS ) {
			throw new Command_Error( self::another_command() );
		}
		$updater = self::lock_time( 'auto_updater' );
		if ( null !== $updater && $updater > time() - HOUR_IN_SECONDS && ! self::is_own_updater_lock() ) {
			throw new Command_Error( self::background_updates() );
		}
		$core = self::lock_time( 'core_updater' );
		if ( null !== $core && $core > time() - self::CORE_LOCK_SECONDS ) {
			throw new Command_Error( 'WordPress is updating itself. Nothing was changed. Try again in a few minutes.' );
		}
	}

	/**
	 * Whether WordPress's background updater lock is one site_manage took.
	 *
	 * @return bool
	 */
	private static function is_own_updater_lock(): bool {
		$lock = self::lock_time( 'auto_updater' );
		$mark = get_option( self::UPDATER_MARK );

		return null !== $lock && is_string( $mark ) && (string) $lock === $mark;
	}

	/**
	 * When a lock was taken, read from the database as WordPress writes it.
	 * WordPress's option cache is skipped: a lock read before it existed
	 * would stay cached as missing, also for WordPress's own updater.
	 *
	 * @param string $name Lock name.
	 * @return int|null
	 */
	public static function lock_time( string $name ): ?int {
		return self::on_main_site(
			static function () use ( $name ): ?int {
				global $wpdb;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WP_Upgrader writes its locks with direct queries too.
				$time = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name . '.lock' ) );

				return is_numeric( $time ) ? (int) $time : null;
			}
		);
	}

	/**
	 * Run lock work in the main site's options. On a network every site
	 * shares the plugin, theme and WordPress files, and WordPress's own
	 * updaters keep their locks on the main site, so locks for file changes
	 * live there for every site.
	 *
	 * @template T
	 * @param callable(): T $work Lock work.
	 * @return T
	 */
	private static function on_main_site( callable $work ) {
		$main = is_multisite() ? (int) get_main_site_id( get_main_network_id() ) : 0;
		if ( 0 === $main || get_current_blog_id() === $main ) {
			return $work();
		}
		switch_to_blog( $main );
		try {
			return $work();
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * The message for another site_manage command still running.
	 *
	 * @return string
	 */
	private static function another_command(): string {
		$since = self::lock_time( self::LOCK );

		return sprintf( 'Another site_manage command started %d seconds ago and has not finished. Nothing was changed. Wait for it, check the result with site_command, then run this command again if it is still needed. If that command was cut off, this clears by itself %d minutes after it started.', null !== $since ? max( 0, time() - $since ) : 0, (int) ( self::LOCK_SECONDS / MINUTE_IN_SECONDS ) );
	}

	/**
	 * The message for WordPress's background updater running.
	 *
	 * @return string
	 */
	private static function background_updates(): string {
		$since = self::lock_time( 'auto_updater' );

		return sprintf( 'WordPress\'s automatic updates have been running for %d seconds. Nothing was changed. Try again in a few minutes.', null !== $since ? max( 0, time() - $since ) : 0 );
	}

	/**
	 * Hold a lock until the session ends, and release it when the request
	 * ends in any case, a fatal error included.
	 *
	 * @param string $lock Lock name.
	 * @return void
	 */
	private function hold_lock( string $lock ): void {
		if ( array() === $this->locks ) {
			// After WordPress's own work with the files (priorities 10 and 100).
			add_action( 'shutdown', array( $this, 'release_locks' ), 101 );
		}
		$this->locks[ $lock ] = self::lock_time( $lock );
	}

	/**
	 * Release the locks this session holds, once, unless another command
	 * has taken one over since.
	 *
	 * @return void
	 */
	public function release_locks(): void {
		// At shutdown, after the answer: a failure must not become a fatal
		// error page. The locks expire on their own.
		try {
			self::on_main_site(
				function (): void {
					$this->drop_locks();
				}
			);
		} catch ( \Throwable $error ) {
			unset( $error );
		}
	}

	/**
	 * Release the locks in the current site's options.
	 *
	 * @return void
	 */
	private function drop_locks(): void {
		foreach ( $this->locks as $lock => $taken ) {
			if ( null === $taken || self::lock_time( $lock ) !== $taken ) {
				continue;
			}
			\WP_Upgrader::release_lock( $lock );
			if ( 'auto_updater' === $lock && get_option( self::UPDATER_MARK ) === (string) $taken ) {
				delete_option( self::UPDATER_MARK );
			}
		}
		$this->locks = array();
	}

	/**
	 * Download a package now, within the time left, so the upgrader can use
	 * the local file. An upgrade turns maintenance mode on before it would
	 * download, so this keeps maintenance mode to unzipping and replacing.
	 *
	 * @param \WP_Upgrader $upgrader   The upgrader that will install it.
	 * @param string       $package    Package URL.
	 * @param array        $hook_extra What the package is for, as the upgrader passes it.
	 * @return string|WP_Error Local file, or why it could not be downloaded.
	 */
	public function download( \WP_Upgrader $upgrader, string $package, array $hook_extra ) {
		$upgrader->init();
		$upgrader->upgrade_strings();
		$file = $upgrader->download_package( $package, false, $hook_extra );
		if ( is_string( $file ) ) {
			$this->downloaded[ $package ] = $file;
			return $file;
		}

		return $this->explain( $file );
	}

	/**
	 * Finish an update within the request. When it succeeded, its backup is
	 * removed. When it failed after WordPress moved the old version aside, the
	 * old version is put back and checked; a backup that could not be put
	 * back is kept, so nothing removes it when the request ends.
	 *
	 * Commands refuse to update while an earlier backup is left, so any
	 * backup found here is this update's.
	 *
	 * @param \WP_Upgrader $upgrader The upgrader that ran.
	 * @param mixed        $error    The update's error, or null when it succeeded.
	 * @param string       $dir      "plugins" or "themes".
	 * @param string       $slug     Folder name.
	 * @param string       $kind     "plugin" or "theme", for the message.
	 * @return string For a failure, what happened to the previous version; for a success, "" or why its backup is left. Sentences.
	 */
	public function finish( \WP_Upgrader $upgrader, $error, string $dir, string $slug, string $kind ): string {
		$backup = WP_CONTENT_DIR . '/upgrade-temp-backup/' . $dir . '/' . $slug;
		if ( '' === $slug || '.' === $slug || ! method_exists( $upgrader, 'move_to_temp_backup_dir' ) ) {
			// WordPress keeps no backup of a single-file plugin, nor before version 6.3; for "." it would
			// otherwise remove every plugin's backup.
			remove_action( 'shutdown', array( $upgrader, 'restore_temp_backup' ), 10 );
			remove_action( 'shutdown', array( $upgrader, 'delete_temp_backup' ), 100 );
			if ( null === $error ) {
				return '';
			}
			return Site_Changes::changed_nothing( $error ) ? self::STILL_IN_PLACE : sprintf( 'WordPress kept no backup of this %s during the update, so its files may be missing or partly replaced.', $kind );
		}
		if ( null === $error ) {
			remove_action( 'shutdown', array( $upgrader, 'delete_temp_backup' ), 100 );
			$deleted = $upgrader->delete_temp_backup();
			return true === $deleted || false === $deleted || ! file_exists( $backup )
				? ''
				: sprintf( 'The previous version\'s backup could not be removed from wp-content/upgrade-temp-backup/%1$s/%2$s; remove it before updating this %3$s again.', $dir, $slug, $kind );
		}
		remove_action( 'shutdown', array( $upgrader, 'restore_temp_backup' ), 10 );
		remove_action( 'shutdown', array( $upgrader, 'delete_temp_backup' ), 100 );
		$root   = 'plugins' === $dir ? WP_PLUGIN_DIR : get_theme_root( $slug );
		$target = $root . '/' . $slug;
		clearstatcache();
		if ( ! file_exists( $backup ) ) {
			return is_dir( $target ) && ! $this->moved_aside
				? self::STILL_IN_PLACE
				: sprintf( 'The previous version could not be verified and WordPress kept no backup of this %s. Its files may be missing or partly replaced; check the site and restore a site backup before relying on it.', $kind );
		}
		if ( ! $this->moved_aside && is_dir( $target ) ) {
			// A failed copy can leave both folders. Only an empty backup is safe to
			// discard: either folder may otherwise contain the only copy of a file.
			global $wp_filesystem;
			if ( is_object( $wp_filesystem ) && array() === $wp_filesystem->dirlist( $backup ) && $wp_filesystem->delete( $backup, true ) ) {
				return self::STILL_IN_PLACE;
			}

			return sprintf( 'The update stopped before its backup could be verified. The current %1$s files were kept, together with the backup in wp-content/upgrade-temp-backup/%2$s/%3$s%4$s. Check both copies and restore the complete previous version before relying on the %1$s or updating it again.', $kind, $dir, $slug, self::cleanup_note() );
		}
		// Core moves the original before it reads the new source directory.
		// That read can fail before upgrader_clear_destination marks moved_aside.

		return $this->restore( $upgrader, $dir, $slug, $kind );
	}

	/**
	 * Put a plugin's or theme's previous version back from its backup now,
	 * and check that it is back. A backup that could not be put back is kept.
	 *
	 * @param \WP_Upgrader $upgrader The upgrader that moved it aside.
	 * @param string       $dir      "plugins" or "themes".
	 * @param string       $slug     Folder name.
	 * @param string       $kind     "plugin" or "theme", for the message.
	 * @return string What happened, as a sentence.
	 */
	public function restore( \WP_Upgrader $upgrader, string $dir, string $slug, string $kind ): string {
		remove_action( 'shutdown', array( $upgrader, 'restore_temp_backup' ), 10 );
		remove_action( 'shutdown', array( $upgrader, 'delete_temp_backup' ), 100 );
		$root   = 'plugins' === $dir ? WP_PLUGIN_DIR : get_theme_root( $slug );
		$backup = WP_CONTENT_DIR . '/upgrade-temp-backup/' . $dir . '/' . $slug;
		if ( 0 === ( new \ReflectionMethod( $upgrader, 'restore_temp_backup' ) )->getNumberOfParameters() ) {
			// Before WordPress 6.6 it puts back only what its own run queued, so move the backup back here.
			global $wp_filesystem;
			$target = $root . '/' . $slug;
			// phpcs:ignore PluginCheck.CodeAnalysis.WriteFile.PluginDirectoryWrite -- Puts WordPress's own upgrade-temp-backup copy of a plugin back in its folder after a failed update, as WP_Upgrader::restore_temp_backup() does from 6.6; no plugin data is stored there.
			$result = is_object( $wp_filesystem ) && ( ! $wp_filesystem->is_dir( $target ) || $wp_filesystem->delete( $target, true ) ) ? move_dir( $backup, $target, true ) : false;
		} else {
			$result = $upgrader->restore_temp_backup(
				array(
					array(
						'slug' => $slug,
						'src'  => $root,
						'dir'  => $dir,
					),
				)
			);
		}
		clearstatcache();
		if ( true === $result && ! file_exists( $backup ) && is_dir( $root . '/' . $slug ) ) {
			return 'The previous version was put back.';
		}

		return sprintf( 'WordPress could not put the previous version back. The %1$s\'s files may be missing or mixed; the previous version is in wp-content/upgrade-temp-backup/%2$s/%3$s%4$s. Restore it from there now, before relying on the %1$s, and do not update the %1$s anywhere until then: an update replaces that copy.', $kind, $dir, $slug, self::cleanup_note() );
	}

	/**
	 * When WordPress's weekly cleanup next empties its backup folder, as a
	 * clause.
	 *
	 * @return string
	 */
	private static function cleanup_note(): string {
		$next = wp_next_scheduled( 'wp_delete_temp_updater_backups' );

		return is_int( $next ) && $next > 0
			? sprintf( ', which WordPress empties in its weekly cleanup, next on %s UTC', gmdate( 'Y-m-d H:i', $next ) )
			: ', which WordPress empties in its weekly cleanup';
	}

	/**
	 * Whether WordPress still keeps a backup of this plugin or theme from an
	 * earlier update that was cut off. WordPress puts such a backup back when
	 * an update fails, so an update must not start while it is there.
	 *
	 * @param string $dir  "plugins" or "themes".
	 * @param string $slug Folder name.
	 * @return bool
	 */
	public static function has_old_backup( string $dir, string $slug ): bool {
		return '' !== $slug && '.' !== $slug && file_exists( WP_CONTENT_DIR . '/upgrade-temp-backup/' . $dir . '/' . $slug );
	}

	/**
	 * A failed download or lookup, or the error to report instead when its
	 * request was stopped by the time this session gave it. Other failures
	 * are reported as they are.
	 *
	 * @param mixed $error The failure.
	 * @return mixed
	 */
	public function explain( $error ) {
		if ( null === $this->cut_short || ! $error instanceof WP_Error || ! in_array( $error->get_error_code(), self::REQUEST_ERRORS, true ) ) {
			return $error;
		}

		return new WP_Error( self::OUT_OF_TIME, sprintf( 'It was stopped after %d seconds so that the answer arrives in time.', (int) ceil( $this->cut_short ) ) );
	}

	/**
	 * Hosts whose requests failed or were refused, with why.
	 *
	 * @return array<string, string>
	 */
	public function failed_requests(): array {
		return $this->failed_requests;
	}

	/**
	 * Hosts whose requests this session refused for lack of time, so their
	 * failure says nothing about the host.
	 *
	 * @return string[]
	 */
	public function refused_hosts(): array {
		return array_keys( $this->refused );
	}

	/**
	 * Note that an upgrade which may turn on maintenance mode starts now, so
	 * a .maintenance file it writes is removed when the request ends, even
	 * after a fatal error. A file that is already there is someone else's.
	 *
	 * @return void
	 */
	public function own_maintenance(): void {
		clearstatcache();
		if ( file_exists( ABSPATH . '.maintenance' ) ) {
			return;
		}
		$this->maintenance_from = time();
		if ( false === has_action( 'shutdown', array( $this, 'end_own_maintenance' ) ) ) {
			// After WordPress puts back a failed update's backup (10), before the locks are released (101).
			add_action( 'shutdown', array( $this, 'end_own_maintenance' ), 100 );
		}
	}

	/**
	 * Remove the .maintenance file this session's upgrade wrote: one in
	 * WordPress's own form, written since the upgrade started, while
	 * WordPress itself is not updating.
	 *
	 * @return void
	 */
	public function end_own_maintenance(): void {
		$from                   = $this->maintenance_from;
		$this->maintenance_from = null;
		$file                   = ABSPATH . '.maintenance';
		// At shutdown, after the answer: a failure must not become a fatal
		// error page. WordPress ignores a .maintenance file after 10 minutes.
		try {
			clearstatcache();
			if ( null === $from || ! file_exists( $file ) ) {
				return;
			}
			$contents = is_readable( $file ) ? file_get_contents( $file ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local file, read as text instead of run.
			$started  = is_string( $contents ) ? Settings_Commands::maintenance_started( $contents ) : null;
			$core     = self::lock_time( 'core_updater' );
			if ( null === $started || $started < $from || ( null !== $core && $core > time() - self::CORE_LOCK_SECONDS ) ) {
				return;
			}
			wp_delete_file( $file );
		} catch ( \Throwable $error ) {
			unset( $error );
		}
	}

	/**
	 * Install the hooks.
	 *
	 * @return array<int, array{0: callable, 1: int, 2: int}> Follow-up checks to restore.
	 */
	private function start(): array {
		// A caller that stops waiting must not stop PHP while files are being replaced.
		ignore_user_abort( true );

		$this->hook( 'http_request_args', array( $this, 'cap_timeout' ), PHP_INT_MAX, 2 );
		$this->hook( 'http_api_curl', array( $this, 'cap_curl' ), PHP_INT_MAX, 3 );
		$this->hook( 'pre_http_request', array( $this, 'refuse_request' ), PHP_INT_MAX, 3 );
		$this->hook( 'http_api_debug', array( $this, 'record_response' ), 10, 5 );
		$this->hook( 'pre_wp_mail', array( $this, 'refuse_mail' ), PHP_INT_MAX, 1 );
		$this->hook( 'phpmailer_init', array( $this, 'cap_mail' ), PHP_INT_MAX, 1 );
		$this->hook( 'upgrader_pre_download', array( $this, 'refuse_download' ), 1, 2 );
		$this->hook( 'pre_unzip_file', array( $this, 'refuse_unzip' ), 1, 1 );
		// Before WordPress moves anything aside to replace it.
		$this->hook( 'upgrader_pre_install', array( $this, 'refuse_install' ), 1, 1 );
		// WordPress runs this filter only after it moved the old version aside whole.
		$this->hook( 'upgrader_clear_destination', array( $this, 'old_version_moved' ), PHP_INT_MIN, 1 );
		// WordPress runs this filter once all the new files are copied.
		$this->hook( 'upgrader_post_install', array( $this, 'files_copied' ), PHP_INT_MIN, 1 );
		$this->hook( 'upgrader_install_package_result', array( $this, 'files_replaced' ), 1, 1 );

		$skipped = array();
		foreach ( self::FOLLOW_UP_CHECKS as list( $callback, $accepted_args ) ) {
			if ( ! is_callable( $callback ) ) {
				continue;
			}
			$priority = has_action( 'upgrader_process_complete', $callback );
			if ( false !== $priority ) {
				remove_action( 'upgrader_process_complete', $callback, $priority );
				$skipped[] = array( $callback, (int) $priority, $accepted_args );
			}
		}

		return $skipped;
	}

	/**
	 * Remove the hooks, restore WordPress's follow-up checks, and release the
	 * locks.
	 *
	 * @param array<int, array{0: callable, 1: int, 2: int}> $skipped Follow-up checks to restore.
	 * @return void
	 */
	private function stop( array $skipped ): void {
		foreach ( $this->hooks as list( $hook, $callback, $priority ) ) {
			remove_filter( $hook, $callback, $priority );
		}
		foreach ( $skipped as list( $callback, $priority, $accepted_args ) ) {
			add_action( 'upgrader_process_complete', $callback, $priority, $accepted_args );
		}
		foreach ( $this->downloaded as $file ) {
			if ( file_exists( $file ) ) {
				wp_delete_file( $file );
			}
		}
		$this->end_own_maintenance();
		remove_action( 'shutdown', array( $this, 'end_own_maintenance' ), 100 );
		$this->release_locks();
	}

	/**
	 * Add a hook for the session's length.
	 *
	 * @param string   $hook          Hook name.
	 * @param callable $callback      Callback.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Arguments.
	 * @return void
	 */
	private function hook( string $hook, callable $callback, int $priority, int $accepted_args ): void {
		add_filter( $hook, $callback, $priority, $accepted_args );
		$this->hooks[] = array( $hook, $callback, $priority );
	}

	/**
	 * The seconds a request may take now.
	 *
	 * @return float
	 */
	private function request_seconds(): float {
		// A redirect followed without time left fails at once.
		return max( 0.001, $this->budget->remaining() - $this->after_download );
	}

	/**
	 * Give a request only the time left after what follows it.
	 *
	 * @param mixed $args Request arguments.
	 * @param mixed $url  Request URL.
	 * @return mixed
	 */
	public function cap_timeout( $args, $url = '' ) {
		if ( is_array( $args ) ) {
			$timeout = isset( $args['timeout'] ) && is_numeric( $args['timeout'] ) ? (float) $args['timeout'] : 5.0;
			$cap     = $this->request_seconds();
			if ( $cap < $timeout ) {
				$this->capped[ self::host( $url ) ] = $cap;
			}
			$args['timeout'] = min( $timeout, $cap );
		}

		return $args;
	}

	/**
	 * Give each connection, redirects included, only the time left, and
	 * never more than the request asked for. A request that does not wait
	 * for its answer keeps its own short time.
	 *
	 * @param mixed $handle cURL handle.
	 * @param mixed $args   Request arguments.
	 * @param mixed $url    Request URL.
	 * @return void
	 */
	public function cap_curl( $handle, $args = array(), $url = '' ): void {
		unset( $url );
		if ( is_array( $args ) && isset( $args['blocking'] ) && false === $args['blocking'] ) {
			return;
		}
		if ( ( is_resource( $handle ) || $handle instanceof \CurlHandle ) && function_exists( 'curl_setopt' ) ) {
			$seconds      = $this->request_seconds();
			$own          = is_array( $args ) && isset( $args['timeout'] ) && is_numeric( $args['timeout'] ) ? (float) $args['timeout'] : $seconds;
			$milliseconds = (int) ( max( 0.001, min( $seconds, $own ) ) * 1000 );
			curl_setopt( $handle, CURLOPT_TIMEOUT_MS, $milliseconds ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt -- WordPress's own handle, inside its HTTP API.
			curl_setopt( $handle, CURLOPT_CONNECTTIMEOUT_MS, min( $milliseconds, 10000 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt -- WordPress's own handle, inside its HTTP API.
		}
	}

	/**
	 * Refuse to send email when too little time is left for it and what
	 * follows.
	 *
	 * @param mixed $reply Short-circuited result, or null.
	 * @return mixed
	 */
	public function refuse_mail( $reply ) {
		if ( null !== $reply || $this->budget->allows( $this->after_download + self::MAIL_STEPS ) ) {
			return $reply;
		}
		$this->failed_requests['email'] = 'not sent, too little time was left';

		return false;
	}

	/**
	 * Give sending email only the time left. PHPMailer applies its limits to
	 * each step of talking to the mail server, up to about sixteen for one
	 * email with encryption, a login and several recipients, so each step
	 * gets a sixteenth of the time.
	 *
	 * @param mixed $phpmailer PHPMailer instance.
	 * @return void
	 */
	public function cap_mail( $phpmailer ): void {
		if ( ! is_object( $phpmailer ) ) {
			return;
		}
		$seconds = (int) max( 1, floor( $this->request_seconds() / self::MAIL_STEPS ) );
		if ( property_exists( $phpmailer, 'Timeout' ) ) {
			$phpmailer->Timeout = min( (int) $phpmailer->Timeout, $seconds ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer's property.
		}
		if ( method_exists( $phpmailer, 'getSMTPInstance' ) ) {
			$smtp = $phpmailer->getSMTPInstance();
			if ( is_object( $smtp ) && property_exists( $smtp, 'Timelimit' ) ) {
				$smtp->Timelimit = min( (int) $smtp->Timelimit, $seconds ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer's property.
			}
		}
	}

	/**
	 * Refuse a request when too little time is left for it and what follows.
	 *
	 * @param mixed $response Short-circuited response, or false.
	 * @param mixed $args     Request arguments.
	 * @param mixed $url      Request URL.
	 * @return mixed
	 */
	public function refuse_request( $response, $args, $url ) {
		unset( $args );
		if ( false === $response && ! $this->budget->allows( $this->after_download + self::MIN_REQUEST_SECONDS ) ) {
			$response                            = new WP_Error( self::OUT_OF_TIME, $this->budget->shortfall( $this->after_download + self::MIN_REQUEST_SECONDS, sprintf( 'Contacting %s', self::host( $url ) ) ) );
			$this->refused[ self::host( $url ) ] = true;
		}
		if ( false === $response ) {
			// This filter runs last, so the request is sent now.
			$this->sent[ self::host( $url ) ] = $this->budget->remaining();
			return $response;
		}
		// WordPress reports only requests it sent; note answers given in their place too.
		$this->record_response( $response, null, null, null, $url );

		return $response;
	}

	/**
	 * Note a request that failed, so stale update information is reported,
	 * and whether the time this session gave it stopped it.
	 *
	 * @param mixed $response Response or error.
	 * @param mixed $context  Debug context.
	 * @param mixed $class    Transport class.
	 * @param mixed $args     Request arguments.
	 * @param mixed $url      Request URL.
	 * @return void
	 */
	public function record_response( $response, $context, $class, $args, $url ): void {
		unset( $context, $class, $args );
		$host = self::host( $url );
		if ( ! is_wp_error( $response ) ) {
			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( $code >= 400 ) {
				// Do not echo the provider body, reason phrase or authenticated URL.
				$this->failed_requests[ $host ] = sprintf( 'HTTP %d', $code );
			}
			return;
		}
		$this->failed_requests[ $host ] = Site_Changes::text( $response->get_error_message() );
		$took                           = isset( $this->sent[ $host ] ) ? $this->sent[ $host ] - $this->budget->remaining() : 0.0;
		if ( isset( $this->capped[ $host ] ) && $took >= $this->capped[ $host ] - self::CUT_MARGIN_SECONDS && 1 === preg_match( '/timed out|cURL error 28/i', $response->get_error_message() ) ) {
			$this->cut_short = $this->capped[ $host ];
		}
	}

	/**
	 * Refuse a download when too little time is left, or a package that must
	 * come from WordPress.org and does not.
	 *
	 * @param mixed $reply   Short-circuited download, or false.
	 * @param mixed $package Package URL or path.
	 * @return mixed
	 */
	public function refuse_download( $reply, $package ) {
		$package = is_string( $package ) ? $package : '';
		if ( false === $reply && isset( $this->downloaded[ $package ] ) ) {
			// Downloaded earlier in this session; unpacking removes the file.
			$reply = $this->downloaded[ $package ];
			unset( $this->downloaded[ $package ] );
			return $reply;
		}
		if ( false !== $reply ) {
			return $reply;
		}
		if ( $this->wordpress_org_only && ! Packages::is_wordpress_org_download( $package ) ) {
			return new WP_Error( self::NOT_WORDPRESS_ORG, sprintf( 'The package from %s does not come from downloads.wordpress.org, so it was not downloaded.', self::host( $package ) ) );
		}
		if ( ! $this->budget->allows( $this->after_download + self::MIN_REQUEST_SECONDS ) ) {
			return new WP_Error( self::OUT_OF_TIME, $this->budget->shortfall( $this->after_download + self::MIN_REQUEST_SECONDS, 'The download' ) );
		}

		return $reply;
	}

	/**
	 * Refuse to unzip when too little time is left to unzip, replace files and
	 * answer. Translations are checked here only: WordPress's language pack
	 * upgrader removes every other install filter.
	 *
	 * @param mixed $result Short-circuited result, or null.
	 * @return mixed
	 */
	public function refuse_unzip( $result ) {
		if ( null !== $result || $this->budget->allows( $this->after_download ) ) {
			return $result;
		}

		return new WP_Error( self::OUT_OF_TIME, $this->budget->shortfall( $this->after_download, 'Unpacking the download' ) );
	}

	/**
	 * Refuse to replace files when too little time is left to finish and answer.
	 *
	 * @param mixed $response Installation response.
	 * @return mixed
	 */
	public function refuse_install( $response ) {
		if ( is_wp_error( $response ) || $this->budget->allows( $this->replace ) ) {
			return $response;
		}

		return new WP_Error( self::OUT_OF_TIME, $this->budget->shortfall( $this->replace, 'Replacing the files' ) . ' The download was unpacked, but the files were left as they were.' );
	}

	/**
	 * Once the new files are in place, outgoing requests from the hooks that
	 * follow, such as cache purges, need leave only the time for removing the
	 * backup or putting it back.
	 *
	 * @param mixed $result The installation's result.
	 * @return mixed
	 */
	public function files_replaced( $result ) {
		$this->after_download = self::CLEANUP_SECONDS;
		$this->replace        = 0.0;

		return $result;
	}

	/**
	 * Note that the old version was moved aside whole, before other code
	 * reacts to it.
	 *
	 * @param mixed $removed Whether the destination was cleared.
	 * @return mixed
	 */
	public function old_version_moved( $removed ) {
		$this->moved_aside = true;

		return $removed;
	}

	/**
	 * Note that all the new files were copied, before other code reacts to it.
	 *
	 * @param mixed $response The installation's response so far.
	 * @return mixed
	 */
	public function files_copied( $response ) {
		$this->replaced = true;

		return $response;
	}

	/**
	 * A URL's host, for messages.
	 *
	 * @param mixed $url URL.
	 * @return string
	 */
	private static function host( $url ): string {
		$host = wp_parse_url( is_string( $url ) ? $url : '', PHP_URL_HOST );

		return is_string( $host ) && '' !== $host ? $host : 'another server';
	}
}
