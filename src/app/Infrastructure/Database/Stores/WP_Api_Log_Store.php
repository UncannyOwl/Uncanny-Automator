<?php
declare( strict_types=1 );

// phpcs:disable WordPress.DB.PreparedSQL -- this store receives $wpdb by
// injection and queries through $this->wpdb. The sniff only recognises the
// global $wpdb->prepare() form, so it reports every prepared call here as
// unprepared. Every value below goes through a prepare() placeholder; the
// only interpolation is the table prefix, which cannot be a placeholder.

namespace Uncanny_Automator\App\Infrastructure\Database\Stores;

use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Infrastructure\Api_Log\Api_Log_Compressor;
use Uncanny_Automator\App\Infrastructure\Api_Log\Value_Objects\Api_Log_Entry;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Api_Log_Store;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Request;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Response;
use Uncanny_Automator\App\Infrastructure\Database\Database;

/**
 * WordPress implementation of the API log store.
 *
 * Persists {@see Api_Log_Entry} value objects into the `uap_api_log` table
 * via `$wpdb`. Retrieved through
 * {@see Database::get_api_log_store()}.
 *
 * @since 8.0.0
 * @package Uncanny_Automator\App\Infrastructure\Database\Stores
 */
final class WP_Api_Log_Store implements Api_Log_Store {

	/**
	 * The wpdb instance.
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Constructor.
	 *
	 * @param \wpdb $wpdb wpdb instance (injected for testability).
	 */
	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * @inheritDoc
	 */
	public function log( Request $request, Response $response ): ?int {
		$action_data = $request->action_data();

		if ( null === $action_data ) {
			return null;
		}

		$credits = $response->credits();

		$entry = new Api_Log_Entry(
			$action_data['type'] ?? 'action',
			(int) ( $action_data['recipe_log_id'] ?? 0 ),
			(int) ( $action_data['action_log_id'] ?? $action_data['trigger_log_id'] ?? 0 ),
			$request->endpoint(),
			$request->to_legacy_params(),
			$response->to_legacy_array(),
			$response->status_code(),
			$credits ? ( $credits['price'] ?? null ) : null,
			$credits ? ( $credits['balance'] ?? null ) : null,
			(int) $response->time_spent_ms()
		);

		return $this->persist( $entry );
	}

	/**
	 * @inheritDoc
	 */
	public function persist( Api_Log_Entry $entry ): int {
		$row = $entry->to_row();

		// Filter at persistence layer — VO stays pure (no side effects).
		// The filter operates on the raw payload (array shape) so
		// consumers can suppress / shape it; the compressor encodes
		// after, so legacy filter implementations don't need to know
		// about the storage encoding.
		$row['response'] = Api_Log_Compressor::encode(
			Dispatcher::filter( 'automator_log_api_responses', $row['response'], $row )
		);

		// `params` is the bare-minimum replay payload assembled by
		// Api_Log_Entry::compact_params(); compress here so the table
		// row is the smallest it can be.
		$row['params'] = Api_Log_Compressor::encode( $row['params'] );

		$result = $this->wpdb->insert(
			$this->wpdb->prefix . 'uap_api_log',
			$row,
			$entry->format()
		);

		if ( false === $result ) {
			automator_log( 'Failed to persist API log entry.', 'WP_Api_Log_Store' );
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * @inheritDoc
	 */
	public function get_api_retries( int $recipe_log_id ): array {
		$wpdb = $this->wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.item_log_id, r.result, r.message, r.date_added FROM `{$wpdb->prefix}uap_api_log_response` r INNER JOIN `{$wpdb->prefix}uap_api_log` l ON l.ID = r.api_log_id WHERE l.recipe_log_id = %d ORDER BY r.ID ASC",
				$recipe_log_id
			),
			ARRAY_A
		);
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['item_log_id'] ][] = $row;
		}
		return $out;
	}

	/**
	 * @inheritDoc
	 */
	public function get_api_logged_action_log_ids( int $recipe_log_id ): array {
		$wpdb = $this->wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT item_log_id FROM `{$wpdb->prefix}uap_api_log` WHERE recipe_log_id = %d AND type = 'action'",
				$recipe_log_id
			)
		);
		return array_map( 'intval', (array) $ids );
	}
}
