<?php
/**
 * Recipe Log Service
 *
 * Handles all recipe log retrieval and querying operations.
 *
 * @since 7.0.0
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Services;

use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Utilities\Recipe_Formatter;
use Uncanny_Automator\Automator_DB;
use WP_Error;

/**
 * Recipe_Log_Service Class
 *
 * Handles recipe log retrieval using sophisticated logging system.
 */
class Recipe_Log_Service {

	/**
	 * Recipe formatter.
	 *
	 * @var Recipe_Formatter
	 */
	private $formatter;


	/**
	 * Constructor.
	 *
	 * @param Recipe_Formatter|null $formatter Recipe formatter.
	 */
	public function __construct( $formatter = null ) {

		$this->formatter = $formatter ?? new Recipe_Formatter();
	}


	/**
	 * Merge filters with defaults.
	 *
	 * @param array $filters User-provided filters.
	 * @return array Merged filters with defaults.
	 */
	public function merge_filter_defaults( array $filters ): array {
		$defaults = array(
			'limit'        => 20,
			'offset'       => 0,
			'include_meta' => false,
		);
		return array_merge( $defaults, $filters );
	}

	/**
	 * Build WHERE conditions from filters.
	 *
	 * @param array $filters Filters to apply.
	 * @return array Array with 'conditions' and 'values' keys.
	 */
	public function build_where_conditions( array $filters ): array {
		$where_conditions = array( '1=1' );
		$where_values     = array();

		if ( ! empty( $filters['recipe_id'] ) ) {
			$where_conditions[] = 'automator_recipe_id = %d';
			$where_values[]     = (int) $filters['recipe_id'];
		}

		if ( ! empty( $filters['user_id'] ) ) {
			$where_conditions[] = 'user_id = %d';
			$where_values[]     = (int) $filters['user_id'];
		}

		if ( isset( $filters['completed'] ) ) {
			$where_conditions[] = 'recipe_completed = %d';
			$where_values[]     = (int) $filters['completed'];
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$where_conditions[] = 'recipe_date_time >= %s';
			$where_values[]     = $filters['date_from'];
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where_conditions[] = 'recipe_date_time <= %s';
			$where_values[]     = $filters['date_to'];
		}

		return array(
			'conditions' => $where_conditions,
			'values'     => $where_values,
		);
	}

	/**
	 * Enhance logs with metadata.
	 *
	 * @param array $logs Array of log entries.
	 * @return array Logs enhanced with metadata.
	 */
	public function enhance_logs_with_metadata( array $logs ): array {
		if ( empty( $logs ) ) {
			return $logs;
		}

		foreach ( $logs as &$log ) {
			$log['meta'] = $this->get_log_metadata( intval( $log['recipe_log_id'] ?? 0 ) );
		}

		return $logs;
	}

	/**
	 * Build recipe logs response.
	 *
	 * @param array $logs Array of log entries.
	 * @param int   $total_count Total count of logs.
	 * @param array $filters Applied filters.
	 * @return array Response array.
	 */
	public function build_logs_response( array $logs, int $total_count, array $filters ): array {
		return array(
			'success'    => true,
			'message'    => sprintf( 'Found %d recipe logs', count( $logs ) ),
			'logs'       => $logs,
			'log_count'  => count( $logs ),
			'total_logs' => $total_count,
			'pagination' => array(
				'limit'  => $filters['limit'],
				'offset' => $filters['offset'],
				'total'  => $total_count,
			),
		);
	}

	/**
	 * Get recipe execution logs using sophisticated logging system.
	 *
	 * @param array $filters Optional filters (recipe_id, user_id, completed, limit, offset, include_meta).
	 * @return array|\WP_Error Array of logs on success, WP_Error on failure.
	 */
	public function get_recipe_logs( array $filters = array() ) {

		try {
			// Merge filters with defaults
			$filters = $this->merge_filter_defaults( $filters );

			// Query logs
			$logs = $this->query_recipe_logs_with_filters( $filters );

			// Enhance logs with metadata if requested
			if ( $filters['include_meta'] ) {
				$logs = $this->enhance_logs_with_metadata( $logs );
			}

			// Get total count for pagination
			$total_count = $this->count_recipe_logs_with_filters( $filters );

			return $this->build_logs_response( $logs, $total_count, $filters );

		} catch ( \Exception $e ) {
			return $this->formatter->error_response( 'recipe_logs_query_failed', 'Failed to query recipe logs: ' . $e->getMessage() );
		}
	}


	/**
	 * Get recipe execution logs by recipe ID.
	 *
	 * @param int   $recipe_id          Recipe ID to get logs for.
	 * @param array $additional_filters Additional filters to apply.
	 * @return array|\WP_Error Array of logs on success, WP_Error on failure.
	 */
	public function get_recipe_logs_by_recipe_id( int $recipe_id, array $additional_filters = array() ) {

		$filters = array_merge( $additional_filters, array( 'recipe_id' => $recipe_id ) );

		return $this->get_recipe_logs( $filters );
	}


	/**
	 * Get the most recent recipe log.
	 *
	 * @param array $filters Optional filters to apply before getting most recent.
	 * @return array|\WP_Error Most recent log or error.
	 */
	public function get_most_recent_recipe_log( array $filters = array() ) {

		$filters['limit']  = 1;
		$filters['offset'] = 0;

		$result = $this->get_recipe_logs( $filters );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( empty( $result['logs'] ) ) {
			return $this->formatter->error_response( 'recipe_no_logs_found', 'No recipe logs found' );
		}

		return array(
			'success' => true,
			'message' => 'Most recent recipe log retrieved',
			'log'     => $result['logs'][0],
		);
	}


	/**
	 * Query recipe logs with filtering using the sophisticated view.
	 *
	 * @param array $filters Filters to apply.
	 * @return array Array of log entries.
	 */
	public function query_recipe_logs_with_filters( array $filters ) {

		global $wpdb;

		// Build WHERE conditions using extracted method
		$where_data       = $this->build_where_conditions( $filters );
		$where_conditions = $where_data['conditions'];
		$where_values     = $where_data['values'];

		$where_clause = implode( ' AND ', $where_conditions );

		$source         = $this->recipe_log_source();
		$query          = "SELECT * FROM {$source} WHERE {$where_clause} ORDER BY recipe_date_time DESC LIMIT %d OFFSET %d";
		$where_values[] = (int) $filters['limit'];
		$where_values[] = (int) $filters['offset'];

		if ( ! empty( $where_values ) ) {
			$query = $wpdb->prepare( $query, $where_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table or view; live state read, must not be cached. $source is a trusted view name or derived table and $where_clause holds literal conditions with placeholders; every value is bound by prepare() above.
		$logs = $wpdb->get_results( $query, ARRAY_A );
		if ( '' !== $wpdb->last_error ) {
			throw new \RuntimeException( 'Recipe log storage could not be read.' );
		}
		return $logs;
	}


	/**
	 * Count recipe logs with filtering for pagination.
	 *
	 * @param array $filters Filters to apply.
	 * @return int Total count of matching logs.
	 */
	private function count_recipe_logs_with_filters( array $filters ) {

		global $wpdb;

		// Build WHERE conditions using extracted method
		$where_data       = $this->build_where_conditions( $filters );
		$where_conditions = $where_data['conditions'];
		$where_values     = $where_data['values'];

		$where_clause = implode( ' AND ', $where_conditions );

		$source      = $this->recipe_log_source();
		$count_query = "SELECT COUNT(*) FROM {$source} WHERE {$where_clause}";

		if ( ! empty( $where_values ) ) {
			$count_query = $wpdb->prepare( $count_query, $where_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table or view; live state read, must not be cached. $source is a trusted view name or derived table and $where_clause holds literal conditions with placeholders; every value is bound by prepare() above.
		$count = $wpdb->get_var( $count_query );
		if ( '' !== $wpdb->last_error ) {
			throw new \RuntimeException( 'Recipe log storage could not be counted.' );
		}
		return (int) $count;
	}

	/**
	 * Use the same log projection when a host cannot create database views.
	 *
	 * @return string Trusted table or derived-table expression.
	 */
	private function recipe_log_source(): string {
		global $wpdb;

		if ( Automator_DB::is_view_exists( 'recipe' ) ) {
			return $wpdb->prefix . 'uap_recipe_logs_view';
		}

		// A missing optional view is not an empty history. The shared projection
		// keeps aliases and filters identical to the admin log fallback.
		return '(' . Automator_DB::recipe_log_view_query() . ') AS recipe_logs';
	}


	/**
	 * Get metadata for a specific log entry.
	 *
	 * One value per key, read in the order the rows were written. A key stored more than once reads as its last row,
	 * except `actions_flow`: an older run can hold two flow rows. The first is Run_Log_Flow_Translator's, with the
	 * actions inside blocks (it writes before the legacy logger, and never once a row exists); the second is the legacy
	 * logger's, with only the top-level actions. The translator's row is kept, the row the run log screen reads.
	 *
	 * @param int $log_id Log ID to get metadata for.
	 * @return array Array of metadata key-value pairs.
	 */
	private function get_log_metadata( int $log_id ) {

		global $wpdb;

		$meta_query = $wpdb->prepare(
			"SELECT meta_key, meta_value FROM {$wpdb->prefix}uap_recipe_log_meta WHERE recipe_log_id = %d ORDER BY ID ASC",
			$log_id
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom table; live state read, must not be cached. $meta_query is already prepared above.
		$meta_results = $wpdb->get_results( $meta_query, ARRAY_A );
		$metadata     = array();

		foreach ( $meta_results as $meta ) {
			// The translator's flow row came first; the legacy logger's partial copy after it is left out.
			if ( 'actions_flow' === $meta['meta_key'] && array_key_exists( 'actions_flow', $metadata ) ) {
				continue;
			}

			$value = $meta['meta_value'];

			// Try to decode JSON values
			$decoded = json_decode( $value, true );

			if ( json_last_error() === JSON_ERROR_NONE ) {
				$value = $decoded;
			}

			$metadata[ $meta['meta_key'] ] = $value;
		}

		return $metadata;
	}
}
