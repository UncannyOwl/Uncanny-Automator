<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Database\Stores;

use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Infrastructure\Api_Log\Value_Objects\Api_Log_Entry;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Api_Log_Store;
use Uncanny_Automator\App\Infrastructure\Api_Client\Api_Request;
use Uncanny_Automator\App\Infrastructure\Api_Client\Api_Response;
use Uncanny_Automator\App\Infrastructure\Database\Database;

/**
 * WordPress implementation of the API log store.
 *
 * Persists {@see Api_Log_Entry} value objects into the `uap_api_log` table
 * via `$wpdb`. Retrieved through
 * {@see Database::get_api_log_store()}.
 *
 * @since 7.4.0
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
	public function log( Api_Request $request, Api_Response $response ): ?int {
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
			$request,
			$response,
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
		// Returning false from the filter suppresses response storage.
		$row['response'] = maybe_serialize(
			Dispatcher::filter( 'automator_log_api_responses', $row['response'], $row )
		);

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
}
