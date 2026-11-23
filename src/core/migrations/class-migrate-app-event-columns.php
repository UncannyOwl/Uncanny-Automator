<?php

namespace Uncanny_Automator\Migrations;

use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Platform_Inbox_Store;
use Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fills the App events columns of the app events a site stored before them.
 *
 * Database version 8.0.0.4 gives `uap_platform_inbox` an `integration`
 * column, which the Logs page's App events tab filters and groups by on an
 * index, and a `search_text` column, the event's values its search
 * matches. A vendor event stored before then has both empty; its payload
 * has always named the app first and holds the values, so this reads them
 * from there.
 *
 * Runs at the end of a request, a few thousand rows at a time, until no row
 * is left, and only once the schema has the columns.
 *
 * @since 8.1.0
 *
 * @package Uncanny_Automator
 */
class Migrate_App_Event_Columns extends Migration {

	/**
	 * The migration's name, recorded once it completes.
	 *
	 * @var string
	 */
	const NAME = '80_app_event_columns';

	/**
	 * The database version that added the columns.
	 *
	 * @var string
	 */
	const SCHEMA_VERSION = '8.0.0.4';

	/**
	 * Rows filled per statement.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 500;

	/**
	 * Statements per request; what is left waits for the next request.
	 *
	 * @var int
	 */
	const MAX_BATCHES = 10;

	/**
	 * The inbox store, or null to use the site's.
	 *
	 * @var Platform_Inbox_Store|null
	 */
	private $store;

	/**
	 * @param Platform_Inbox_Store|null $store The inbox store; the site's when null.
	 */
	public function __construct( ?Platform_Inbox_Store $store = null ) {
		$this->store = $store;

		parent::__construct( self::NAME );
	}

	/**
	 * Whether the schema has the columns to fill.
	 *
	 * @return bool
	 */
	public function conditions_met() {
		return version_compare( (string) automator_get_option( 'uap_database_version', '0' ), self::SCHEMA_VERSION, '>=' );
	}

	/**
	 * Fill a few batches, and complete once a batch comes back short.
	 *
	 * @return void
	 */
	public function migrate() {

		$store = $this->store ?? Database::get_platform_inbox_store();

		try {
			for ( $batch = 0; $batch < self::MAX_BATCHES; $batch++ ) {
				if ( $store->fill_app_event_columns( self::BATCH_SIZE ) < self::BATCH_SIZE ) {
					$this->complete();
					return;
				}
			}
		} catch ( Store_Exception $refused ) {
			automator_log( $refused->getMessage(), self::NAME, true );
		}
	}
}

new Migrate_App_Event_Columns();
