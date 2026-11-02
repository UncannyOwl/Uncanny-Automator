<?php

namespace Uncanny_Automator\Migrations;

use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Error_Message_Rewrite_Store;
use Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception;
use Uncanny_Automator\App\Recipe_Runner\Services\Gemini_Key_Redactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Removes the Gemini API keys that run-log error text has held since 6.6.0.
 *
 * A Gemini action that got an answer that was not JSON failed with the address it called, and the
 * key was part of that address: `Invalid JSON response from
 * https://generativelanguage.googleapis.com/v1beta/models/<model>:generateContent?key=<key>. JSON Error: ...`.
 * The text is in uap_action_log.error_message (6.6.0 to 7.2.x), in uap_error_log.error_message (7.3.0 on,
 * and the rows the 7.3 backfill copied) and, for a Gemini action in a loop, in Pro's
 * uap_loop_entries_items.error_message, which Pro scrubs with its own instance of this class. Each key
 * becomes self::MARKER; the rest of the text stays as it is.
 *
 * A key is the run of letters, digits, `-` and `_` (what a Google API key is made of) right after `?key=`
 * in an address on generativelanguage.googleapis.com. It ends where the address ends: at the full stop
 * that closes the sentence, or at the `<`, `"` or `&` of a page the error quotes, escaped or not. The
 * marker starts with `[`, so a scrubbed address holds no key and a second pass leaves it alone.
 *
 * The request that runs migrations only schedules the batch hook. Each run of that hook reads the next
 * rows of one table by ID, rewrites at most a set number of them, saves where it stopped and schedules
 * the next run. A run that cannot finish a row (a failed query, or a row that changed after it was read)
 * stops before it, and the next run reads that row again. The migration is recorded as complete only
 * after every table has been read to its end with every key in it replaced.
 *
 * Two runs at once are safe: a row is written only while it still holds the text that was read, and the
 * saved position only ever passes rows that hold no key.
 *
 * @since 8.0.0
 *
 * @package Uncanny_Automator\Migrations
 */
class Migrate_Gemini_Key_Scrub extends Migration {

	/**
	 * Free's migration name, stamped in Migration::OPTION_NAME when it completes.
	 *
	 * @since 8.0.0
	 *
	 * @var string
	 */
	const NAME = '80_gemini_key_scrub';

	/**
	 * Free's batch hook.
	 *
	 * @since 8.0.0
	 *
	 * @var string
	 */
	const CRON_HOOK = 'automator_gemini_key_scrub_batch';

	/**
	 * Free's option holding where the scrub stopped: the table and the last row ID it finished.
	 *
	 * @since 8.0.0
	 *
	 * @var string
	 */
	const CURSOR_OPTION = 'automator_gemini_key_scrub_cursor';

	/**
	 * Rows one run reads.
	 *
	 * @since 8.0.0
	 *
	 * @var int
	 */
	const SCAN_ROWS = 10000;

	/**
	 * Rows holding the address one run reads and rewrites, at most.
	 *
	 * @since 8.0.0
	 *
	 * @var int
	 */
	const REWRITES_PER_RUN = 500;

	/**
	 * Seconds between one run and the next.
	 *
	 * @since 8.0.0
	 *
	 * @var int
	 */
	const RUN_INTERVAL = 10;

	/**
	 * What a key is replaced with.
	 *
	 * @since 8.0.0
	 *
	 * @var string
	 */
	const MARKER = Gemini_Key_Redactor::MARKER;

	/**
	 * Text a message holding a key contains, in this order. Matches every copy, scrubbed ones included.
	 *
	 * @since 8.0.0
	 *
	 * @var string[]
	 */
	const FRAGMENTS = array( 'generativelanguage.googleapis.com', '?key=' );

	/**
	 * The address up to `?key=` (group 1), then the key.
	 *
	 * The path is possessive: it cannot contain `?`, so giving characters back never helps, and a long
	 * token without `?key=` costs one pass.
	 *
	 * @since 8.0.0
	 *
	 * @var string
	 */
	const KEY_PATTERN = Gemini_Key_Redactor::KEY_PATTERN;

	/**
	 * The tables to scrub, in order, by name.
	 *
	 * @var array<string, Error_Message_Rewrite_Store>
	 */
	private $stores;

	/**
	 * @var string
	 */
	private $cron_hook;

	/**
	 * @var string
	 */
	private $cursor_option;

	/**
	 * @var int
	 */
	private $scan_rows;

	/**
	 * @var int
	 */
	private $rewrites_per_run;

	/**
	 * Register the migration and its batch hook.
	 *
	 * @since 8.0.0
	 *
	 * @param array<string, Error_Message_Rewrite_Store> $stores           The tables to scrub, in order, keyed by table name.
	 * @param string                                     $name             Migration name.
	 * @param string                                     $cron_hook        Batch hook.
	 * @param string                                     $cursor_option    Option holding where the scrub stopped.
	 * @param int                                        $scan_rows        Rows one run reads.
	 * @param int                                        $rewrites_per_run Rows holding the address one run rewrites, at most.
	 *
	 * @throws \InvalidArgumentException When a store, a name or a limit is not usable.
	 */
	public function __construct( array $stores, $name = self::NAME, $cron_hook = self::CRON_HOOK, $cursor_option = self::CURSOR_OPTION, $scan_rows = self::SCAN_ROWS, $rewrites_per_run = self::REWRITES_PER_RUN ) {

		if ( array() === $stores ) {
			throw new \InvalidArgumentException( 'The scrub needs at least one table.' );
		}

		foreach ( $stores as $table => $store ) {
			if ( ! is_string( $table ) || ! $store instanceof Error_Message_Rewrite_Store ) {
				throw new \InvalidArgumentException( 'Each table is an Error_Message_Rewrite_Store keyed by its name.' );
			}
		}

		if ( 1 > (int) $scan_rows || 1 > (int) $rewrites_per_run ) {
			throw new \InvalidArgumentException( 'A run reads and rewrites at least one row.' );
		}

		parent::__construct( $name );

		$this->stores           = $stores;
		$this->cron_hook        = (string) $cron_hook;
		$this->cursor_option    = (string) $cursor_option;
		$this->scan_rows        = (int) $scan_rows;
		$this->rewrites_per_run = (int) $rewrites_per_run;

		add_action( $this->cron_hook, array( $this, 'run_batch' ) );
	}

	/**
	 * The text with every Gemini key replaced by the marker.
	 *
	 * @since 8.0.0
	 *
	 * @param string $text Stored error text.
	 *
	 * @return string
	 *
	 * @throws \RuntimeException When the pattern cannot be applied to the text.
	 */
	public static function scrub( $text ) {

		return Gemini_Key_Redactor::scrub( (string) $text );
	}

	/**
	 * Whether this request should start the scrub: only while no run is scheduled.
	 *
	 * The scrub stays pending until its last run, and every request runs pending migrations at shutdown,
	 * taking the migrations lock and logging a start. While a run is scheduled there is nothing to start.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function conditions_met() {
		return false === wp_next_scheduled( $this->cron_hook );
	}

	/**
	 * Schedule the first run. Nothing is read or written in this request.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	public function migrate() {
		$this->schedule( time() );
	}

	/**
	 * One run: scrub the next rows, then schedule the next run or record completion.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	public function run_batch() {

		// A run scheduled before another one completed the scrub.
		if ( $this->migration_completed_before( true ) ) {
			automator_delete_option( $this->cursor_option );

			return;
		}

		try {
			$finished = $this->scrub_next_rows();
		} catch ( Store_Exception | \RuntimeException $e ) {
			automator_log( 'Stopped; the next run retries: ' . $e->getMessage(), $this->name );
			$this->schedule( time() + self::RUN_INTERVAL );

			return;
		}

		if ( ! $finished ) {
			$this->schedule( time() + self::RUN_INTERVAL );

			return;
		}

		automator_delete_option( $this->cursor_option );
		$this->complete();
	}

	/**
	 * Scrub the rows after the saved position, within this run's limits, and save the new position.
	 *
	 * @return bool True when the last table has been read to its end.
	 *
	 * @throws Store_Exception When a query fails.
	 * @throws \RuntimeException When the pattern cannot be applied to a message.
	 */
	private function scrub_next_rows() {

		list( $table, $after_id ) = $this->cursor();

		$store    = $this->stores[ $table ];
		$scanned  = $store->scan_error_messages( $after_id, $this->scan_rows, self::FRAGMENTS );
		$rewrites = 0;

		foreach ( $scanned as $id => $holds_address ) {

			if ( $holds_address ) {
				if ( $this->rewrites_per_run === $rewrites || ! $this->scrub_row( $store, $id ) ) {
					$this->save_cursor( $table, $after_id );

					return false;
				}

				++$rewrites;
			}

			$after_id = $id;
		}

		// A full read: the table may go on.
		if ( count( $scanned ) === $this->scan_rows ) {
			$this->save_cursor( $table, $after_id );

			return false;
		}

		$tables = array_keys( $this->stores );
		$next   = array_search( $table, $tables, true ) + 1;

		if ( ! isset( $tables[ $next ] ) ) {
			return true;
		}

		$this->save_cursor( $tables[ $next ], 0 );

		return false;
	}

	/**
	 * Replace the keys in one row.
	 *
	 * @param Error_Message_Rewrite_Store $store The row's table.
	 * @param int                         $id    Row ID.
	 *
	 * @return bool False when the row changed after it was read, so it is read again in the next run.
	 *
	 * @throws Store_Exception When a query fails.
	 * @throws \RuntimeException When the pattern cannot be applied to the message.
	 */
	private function scrub_row( Error_Message_Rewrite_Store $store, $id ) {

		$message = $store->find_error_message( $id );

		// Deleted since the scan.
		if ( null === $message ) {
			return true;
		}

		$scrubbed = self::scrub( $message );

		if ( $scrubbed === $message ) {
			return true;
		}

		return $store->replace_error_message( $id, $message, $scrubbed );
	}

	/**
	 * Where the scrub stopped: a table to scrub and the last row ID finished in it.
	 *
	 * @return array{0: string, 1: int}
	 */
	private function cursor() {

		$cursor = automator_get_option( $this->cursor_option, array(), true );

		if ( is_array( $cursor ) && isset( $cursor['table'], $cursor['after'] ) && is_string( $cursor['table'] ) && isset( $this->stores[ $cursor['table'] ] ) ) {
			return array( $cursor['table'], max( 0, (int) $cursor['after'] ) );
		}

		return array( (string) array_key_first( $this->stores ), 0 );
	}

	/**
	 * Save where the scrub stopped.
	 *
	 * @param string $table    Table name.
	 * @param int    $after_id Last row ID finished in it.
	 *
	 * @return void
	 */
	private function save_cursor( $table, $after_id ) {
		automator_update_option(
			$this->cursor_option,
			array(
				'table' => $table,
				'after' => (int) $after_id,
			),
			false
		);
	}

	/**
	 * Schedule a run unless one is already scheduled.
	 *
	 * @param int $timestamp When.
	 *
	 * @return void
	 */
	private function schedule( $timestamp ) {
		if ( false === wp_next_scheduled( $this->cron_hook ) ) {
			wp_schedule_single_event( $timestamp, $this->cron_hook );
		}
	}
}
