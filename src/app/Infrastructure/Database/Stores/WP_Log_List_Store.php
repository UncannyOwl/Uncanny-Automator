<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Infrastructure\Database\Stores;

use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Action_Error_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Log_List_Store;
use Uncanny_Automator\App\Recipe_Logs\Lists\Enums\Log_List_Kind;
use Uncanny_Automator\App\Recipe_Logs\Lists\Value_Objects\Log_List_Query;
use Uncanny_Automator\Automator_Status;

/**
 * WordPress implementation of {@see Log_List_Store}.
 *
 * Every list is one query over its log table, joined one-to-one to its run
 * (`uap_recipe_log`), its recipe and item posts and its user, so a count is
 * a plain COUNT(*) of the same FROM and WHERE. A failed action joins only
 * its latest action API log row.
 *
 * SQL is built from fixed fragments chosen by the list's kind; every value
 * goes through a placeholder.
 *
 * @since 8.1.0
 */
final class WP_Log_List_Store implements Log_List_Store {

	/**
	 * The statuses an App action that can still be resent has: completed with
	 * errors, in progress with error, failed. Matches the Resend App actions
	 * tool it replaces.
	 *
	 * @var int[]
	 */
	const FAILED_STATUSES = array( Automator_Status::COMPLETED_WITH_ERRORS, Automator_Status::IN_PROGRESS_WITH_ERROR, Automator_Status::FAILED );

	/**
	 * The wpdb instance.
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Builds the notes search, the same one the legacy action log used.
	 *
	 * @var Action_Error_Store
	 */
	private $errors;

	/**
	 * Which optional tables exist, for this request.
	 *
	 * @var array<string, bool>
	 */
	private $tables = array();

	/**
	 * @param \wpdb              $wpdb   The wpdb instance.
	 * @param Action_Error_Store $errors The error store.
	 */
	public function __construct( \wpdb $wpdb, Action_Error_Store $errors ) {
		$this->wpdb   = $wpdb;
		$this->errors = $errors;
	}

	/**
	 * @inheritDoc
	 */
	public function find_rows( Log_List_Query $query ): array {
		$wpdb  = $this->wpdb;
		$shape = $this->shape( $query );

		$sql    = "SELECT {$shape['select']} FROM {$shape['from']} WHERE {$shape['where']} ORDER BY {$shape['order']} LIMIT %d OFFSET %d";
		$params = array_merge( $shape['params'], array( $query->get_per_page(), $query->get_offset() ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Live log read; the SQL is fixed fragments from shape(), and every value is bound in prepare().
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Fixed fragments from shape(); every value is a placeholder.

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @inheritDoc
	 */
	public function count_rows( Log_List_Query $query ): int {
		$wpdb  = $this->wpdb;
		$shape = $this->shape( $query );

		$sql = "SELECT COUNT(*) FROM {$shape['from']} WHERE {$shape['where']}";

		if ( array() !== $shape['params'] ) {
			$sql = $wpdb->prepare( $sql, $shape['params'] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Fixed fragments from shape(); every value is a placeholder.
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Live log count; prepared above whenever it carries a value.
		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * @inheritDoc
	 */
	public function get_run_steps( array $recipe_log_ids ): array {
		$ids = $this->ids( $recipe_log_ids );

		if ( array() === $ids ) {
			return array();
		}

		$wpdb = $this->wpdb;
		$in   = implode( ', ', $ids );

		// `rank` orders triggers before everything else; then each step by
		// the time it first ran and its own row id.
		$parts = array(
			"SELECT automator_recipe_log_id AS recipe_log_id, 'item' AS kind, automator_trigger_id AS id, 0 AS step_rank, date_time AS ran_at, ID AS seq FROM {$wpdb->prefix}uap_trigger_log WHERE automator_recipe_log_id IN ( {$in} )",
			"SELECT automator_recipe_log_id AS recipe_log_id, 'item' AS kind, automator_action_id AS id, 1 AS step_rank, date_time AS ran_at, ID AS seq FROM {$wpdb->prefix}uap_action_log WHERE automator_recipe_log_id IN ( {$in} )",
		);

		if ( $this->table_exists( 'uap_loop_entries' ) ) {
			$parts[] = "SELECT recipe_log_id, 'loop' AS kind, loop_id AS id, 1 AS step_rank, MIN( date_added ) AS ran_at, MIN( ID ) AS seq FROM {$wpdb->prefix}uap_loop_entries WHERE recipe_log_id IN ( {$in} ) GROUP BY recipe_log_id, loop_id";
		}

		if ( $this->table_exists( 'uap_loop_entries_items' ) ) {
			$parts[] = "SELECT recipe_log_id, 'item' AS kind, action_id AS id, 1 AS step_rank, MIN( date_added ) AS ran_at, MIN( ID ) AS seq FROM {$wpdb->prefix}uap_loop_entries_items WHERE recipe_log_id IN ( {$in} ) GROUP BY recipe_log_id, action_id";
		}

		$sql = '( ' . implode( ' ) UNION ALL ( ', $parts ) . ' ) ORDER BY recipe_log_id ASC, step_rank ASC, ran_at ASC, seq ASC';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- The id list is cast to integers in ids(); the rest is fixed SQL.
		$rows = $wpdb->get_results( $sql, ARRAY_A );

		$steps = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$steps[ (int) $row['recipe_log_id'] ][] = array(
				'kind' => (string) $row['kind'],
				'id'   => (int) $row['id'],
			);
		}

		return $steps;
	}

	/**
	 * @inheritDoc
	 */
	public function get_post_meta_values( array $post_ids, string $meta_key ): array {
		$ids = $this->ids( $post_ids );

		if ( array() === $ids ) {
			return array();
		}

		$wpdb = $this->wpdb;
		$in   = implode( ', ', $ids );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Batch read of one key for a page of posts.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND post_id IN ( {$in} ) ORDER BY meta_id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The id list is cast to integers in ids().
				$meta_key
			),
			ARRAY_A
		);

		$values = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( ! isset( $values[ (int) $row['post_id'] ] ) ) {
				$values[ (int) $row['post_id'] ] = (string) $row['meta_value'];
			}
		}

		return $values;
	}

	/**
	 * @inheritDoc
	 */
	public function get_trigger_sentences( array $trigger_log_ids ): array {
		$ids = $this->ids( $trigger_log_ids );

		if ( array() === $ids ) {
			return array();
		}

		$wpdb = $this->wpdb;
		$in   = implode( ', ', $ids );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The id list is cast to integers in ids(); the rest is fixed SQL.
		$rows = $wpdb->get_results( "SELECT automator_trigger_log_id AS log_id, meta_value FROM {$wpdb->prefix}uap_trigger_log_meta WHERE meta_key = 'sentence_human_readable' AND automator_trigger_log_id IN ( {$in} ) ORDER BY ID ASC", ARRAY_A );

		$sentences = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$sentences[ (int) $row['log_id'] ] = (string) $row['meta_value'];
		}

		return $sentences;
	}

	/**
	 * @inheritDoc
	 */
	public function get_action_sentences( array $action_log_ids ): array {
		$ids = $this->ids( $action_log_ids );

		if ( array() === $ids ) {
			return array();
		}

		$wpdb = $this->wpdb;
		$in   = implode( ', ', $ids );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The id list is cast to integers in ids(); the rest is fixed SQL.
		$rows = $wpdb->get_results( "SELECT automator_action_log_id AS log_id, meta_key, meta_value FROM {$wpdb->prefix}uap_action_log_meta WHERE meta_key IN ( 'sentence_human_readable', 'sentence_human_readable_html' ) AND automator_action_log_id IN ( {$in} ) ORDER BY ID ASC", ARRAY_A );

		$sentences = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$log_id = (int) $row['log_id'];
			$html   = 'sentence_human_readable_html' === $row['meta_key'];

			// The HTML form marks each field's label, so it wins over the bracket form.
			if ( isset( $sentences[ $log_id ] ) && $sentences[ $log_id ]['html'] && ! $html ) {
				continue;
			}

			if ( '' === (string) $row['meta_value'] ) {
				continue;
			}

			$sentences[ $log_id ] = array(
				'text' => (string) $row['meta_value'],
				'html' => $html,
			);
		}

		return $sentences;
	}

	/**
	 * @inheritDoc
	 */
	public function get_recipe_log_meta_values( array $recipe_log_ids, string $meta_key ): array {
		$ids = $this->ids( $recipe_log_ids );

		if ( array() === $ids ) {
			return array();
		}

		$wpdb = $this->wpdb;
		$in   = implode( ', ', $ids );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Batch read of one key for a page of runs.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT recipe_log_id, meta_value FROM {$wpdb->prefix}uap_recipe_log_meta WHERE meta_key = %s AND recipe_log_id IN ( {$in} ) ORDER BY ID ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The id list is cast to integers in ids().
				$meta_key
			),
			ARRAY_A
		);

		$values = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$values[ (int) $row['recipe_log_id'] ] = (string) $row['meta_value'];
		}

		return $values;
	}

	/**
	 * @inheritDoc
	 */
	public function get_runs( array $recipe_log_ids ): array {
		$ids = $this->ids( $recipe_log_ids );

		if ( array() === $ids ) {
			return array();
		}

		$wpdb = $this->wpdb;
		$in   = implode( ', ', $ids );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The id list is cast to integers in ids(); the rest is fixed SQL.
		$rows = $wpdb->get_results( "SELECT ID, automator_recipe_id, run_number FROM {$wpdb->prefix}uap_recipe_log WHERE completed <> -1 AND ID IN ( {$in} )", ARRAY_A );

		$runs = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$runs[ (int) $row['ID'] ] = array(
				'recipe_id'  => (int) $row['automator_recipe_id'],
				'run_number' => (int) $row['run_number'],
			);
		}

		return $runs;
	}

	/**
	 * @inheritDoc
	 */
	public function get_logged_recipes(): array {
		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixed SQL; the filter options must reflect the logs as they are now.
		$rows = $wpdb->get_results( "SELECT p.ID, p.post_title FROM {$wpdb->posts} p WHERE p.ID IN ( SELECT DISTINCT r.automator_recipe_id FROM {$wpdb->prefix}uap_recipe_log r WHERE r.completed <> -1 ) ORDER BY p.post_title ASC, p.ID ASC", ARRAY_A );

		$recipes = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$recipes[] = array(
				'id'    => (int) $row['ID'],
				'title' => (string) $row['post_title'],
			);
		}

		return $recipes;
	}

	/**
	 * @inheritDoc
	 */
	public function get_logged_users(): array {
		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixed SQL; the filter options must reflect the logs as they are now.
		$rows = $wpdb->get_results( "SELECT l.user_id, u.ID AS found_id, u.display_name, u.user_email FROM ( SELECT DISTINCT user_id FROM {$wpdb->prefix}uap_recipe_log WHERE completed <> -1 ) l LEFT JOIN {$wpdb->users} u ON u.ID = l.user_id ORDER BY ( l.user_id = 0 ) DESC, u.display_name ASC, l.user_id ASC", ARRAY_A );

		$users = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$users[] = array(
				'id'           => (int) $row['user_id'],
				'found'        => null !== $row['found_id'],
				'display_name' => (string) $row['display_name'],
				'user_email'   => (string) $row['user_email'],
			);
		}

		return $users;
	}

	/**
	 * @inheritDoc
	 */
	public function get_logged_statuses(): array {
		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixed SQL; the filter options must reflect the logs as they are now.
		$values = $wpdb->get_col( "( SELECT DISTINCT completed FROM {$wpdb->prefix}uap_recipe_log WHERE completed <> -1 ) UNION ( SELECT DISTINCT completed FROM {$wpdb->prefix}uap_trigger_log ) UNION ( SELECT DISTINCT completed FROM {$wpdb->prefix}uap_action_log )" );

		$statuses = array_values( array_unique( array_map( 'intval', is_array( $values ) ? $values : array() ) ) );
		sort( $statuses );

		return $statuses;
	}

	/**
	 * @inheritDoc
	 */
	public function get_logged_integrations(): array {
		$wpdb = $this->wpdb;

		$items = array(
			"SELECT DISTINCT automator_trigger_id AS id FROM {$wpdb->prefix}uap_trigger_log",
			"SELECT DISTINCT automator_action_id AS id FROM {$wpdb->prefix}uap_action_log",
		);

		if ( $this->table_exists( 'uap_loop_entries_items' ) ) {
			$items[] = "SELECT DISTINCT action_id AS id FROM {$wpdb->prefix}uap_loop_entries_items";
		}

		$sql = "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'integration' AND meta_value <> '' AND post_id IN ( ( " . implode( ' ) UNION ( ', $items ) . ' ) )';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Fixed SQL; the filter options must reflect the logs as they are now.
		$codes = $wpdb->get_col( $sql );

		$has_loops = false;

		if ( $this->table_exists( 'uap_loop_entries' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixed SQL.
			$has_loops = null !== $wpdb->get_var( "SELECT 1 FROM {$wpdb->prefix}uap_loop_entries LIMIT 1" );
		}

		return array(
			'codes'     => array_values( array_map( 'strval', is_array( $codes ) ? $codes : array() ) ),
			'has_loops' => $has_loops,
		);
	}

	/**
	 * @inheritDoc
	 */
	public function get_logged_item_codes( string $item_type ): array {
		$wpdb = $this->wpdb;

		if ( 'trigger' === $item_type ) {
			$items = "SELECT DISTINCT automator_trigger_id FROM {$wpdb->prefix}uap_trigger_log";
		} elseif ( 'action' === $item_type ) {
			$items = "SELECT DISTINCT automator_action_id FROM {$wpdb->prefix}uap_action_log";
		} else {
			throw new \InvalidArgumentException( 'An item type is trigger or action.' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixed SQL; the filter options must reflect the logs as they are now.
		$codes = $wpdb->get_col( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'code' AND meta_value <> '' AND post_id IN ( {$items} ) ORDER BY meta_value ASC" );

		return array_values( array_map( 'strval', is_array( $codes ) ? $codes : array() ) );
	}

	/**
	 * @inheritDoc
	 */
	public function get_failed_endpoints(): array {
		$wpdb   = $this->wpdb;
		$failed = implode( ', ', self::FAILED_STATUSES );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Fixed SQL (not_resent_sql() is a literal); the filter options must reflect the logs as they are now.
		$endpoints = $wpdb->get_col( "SELECT DISTINCT api.endpoint FROM {$wpdb->prefix}uap_action_log a INNER JOIN {$wpdb->prefix}uap_api_log api ON api.ID = ( SELECT MAX( x.ID ) FROM {$wpdb->prefix}uap_api_log x WHERE x.item_log_id = a.ID AND x.type = 'action' ) WHERE a.completed IN ( {$failed} ) AND api.endpoint <> '' AND " . $this->not_resent_sql() . ' ORDER BY api.endpoint ASC' );

		return array_values( array_map( 'strval', is_array( $endpoints ) ? $endpoints : array() ) );
	}

	/**
	 * The SELECT, FROM, WHERE and ORDER BY of a list read.
	 *
	 * @param Log_List_Query $query The read.
	 *
	 * @return array{select: string, from: string, where: string, order: string, params: array<int, int|string>}
	 */
	private function shape( Log_List_Query $query ): array {
		$kind    = $query->get_kind();
		$columns = $this->columns( $kind );
		$where   = array( 'r.completed <> -1', 'r.run_number > 0' );
		$params  = array();

		if ( Log_List_Kind::RECIPE_RUNS === $kind ) {
			$where[] = $this->run_date_sql() . ' IS NOT NULL';
		}

		if ( Log_List_Kind::FAILED_ACTIONS === $kind ) {
			$where[] = 'a.completed IN ( ' . implode( ', ', self::FAILED_STATUSES ) . ' )';
			$where[] = $this->not_resent_sql();
		}

		$this->add_filters( $query, $columns, $where, $params );

		if ( null !== $query->get_search() ) {
			$where[] = $this->search_sql( $kind, $query->get_search() );
		}

		return array(
			'select' => $this->select( $kind ),
			'from'   => $this->from( $kind ),
			'where'  => implode( ' AND ', $where ),
			'order'  => $this->order( $query, $columns ),
			'params' => $params,
		);
	}

	/**
	 * The columns a list's filters and sort read.
	 *
	 * @param string $kind The list.
	 *
	 * @return array{id: string, date: string, status: string, user: string, recipe: string, item: string}
	 */
	private function columns( string $kind ): array {
		$alias = array(
			Log_List_Kind::RECIPE_RUNS    => 'r',
			Log_List_Kind::TRIGGERS       => 't',
			Log_List_Kind::ACTIONS        => 'a',
			Log_List_Kind::FAILED_ACTIONS => 'a',
		)[ $kind ];

		return array(
			'id'     => "{$alias}.ID",
			'date'   => Log_List_Kind::RECIPE_RUNS === $kind ? $this->run_date_sql() : "{$alias}.date_time",
			'status' => "{$alias}.completed",
			'user'   => "{$alias}.user_id",
			'recipe' => "{$alias}.automator_recipe_id",
			'item'   => Log_List_Kind::TRIGGERS === $kind ? 't.automator_trigger_id' : 'a.automator_action_id',
		);
	}

	/**
	 * The columns a list's rows carry.
	 *
	 * @param string $kind The list.
	 *
	 * @return string
	 */
	private function select( string $kind ): string {
		$run = 'r.ID AS recipe_log_id, r.automator_recipe_id AS recipe_id, r.run_number, r.completed AS recipe_completed, p.post_title AS recipe_title, u.ID AS user_found, u.display_name, u.user_email';

		switch ( $kind ) {
			case Log_List_Kind::TRIGGERS:
				return "t.ID AS entry_id, t.completed, t.date_time, t.user_id, t.automator_trigger_id AS item_id, ip.post_title AS item_title, {$run}";
			case Log_List_Kind::ACTIONS:
				return "a.ID AS entry_id, a.completed, a.date_time, a.user_id, a.automator_action_id AS item_id, ip.post_title AS item_title, a.error_message, {$run}";
			case Log_List_Kind::FAILED_ACTIONS:
				$wpdb = $this->wpdb;
				return "a.ID AS entry_id, a.completed, a.date_time, a.user_id, a.automator_action_id AS item_id, ip.post_title AS item_title, a.error_message, api.ID AS api_log_id, api.endpoint, api.status AS api_status, api.response, api.params, ( SELECT COUNT(*) FROM {$wpdb->prefix}uap_api_log_response rc WHERE rc.item_log_id = a.ID ) AS resent_count, {$run}";
		}

		return "r.ID AS entry_id, r.completed, {$this->run_date_sql()} AS date_time, r.user_id, {$run}";
	}

	/**
	 * The tables a list reads, joined one row to one.
	 *
	 * @param string $kind The list.
	 *
	 * @return string
	 */
	private function from( string $kind ): string {
		$wpdb = $this->wpdb;

		switch ( $kind ) {
			case Log_List_Kind::TRIGGERS:
				return "{$wpdb->prefix}uap_trigger_log t INNER JOIN {$wpdb->prefix}uap_recipe_log r ON r.ID = t.automator_recipe_log_id INNER JOIN {$wpdb->posts} p ON p.ID = t.automator_recipe_id LEFT JOIN {$wpdb->users} u ON u.ID = t.user_id LEFT JOIN {$wpdb->posts} ip ON ip.ID = t.automator_trigger_id";
			case Log_List_Kind::ACTIONS:
				return $this->actions_from();
			case Log_List_Kind::FAILED_ACTIONS:
				return $this->actions_from() . " INNER JOIN {$wpdb->prefix}uap_api_log api ON api.ID = ( SELECT MAX( x.ID ) FROM {$wpdb->prefix}uap_api_log x WHERE x.item_log_id = a.ID AND x.type = 'action' )";
		}

		return "{$wpdb->prefix}uap_recipe_log r INNER JOIN {$wpdb->posts} p ON p.ID = r.automator_recipe_id LEFT JOIN {$wpdb->users} u ON u.ID = r.user_id";
	}

	/**
	 * The tables the action lists read.
	 *
	 * @return string
	 */
	private function actions_from(): string {
		$wpdb = $this->wpdb;

		return "{$wpdb->prefix}uap_action_log a INNER JOIN {$wpdb->prefix}uap_recipe_log r ON r.ID = a.automator_recipe_log_id INNER JOIN {$wpdb->posts} p ON p.ID = a.automator_recipe_id LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id LEFT JOIN {$wpdb->posts} ip ON ip.ID = a.automator_action_id";
	}

	/**
	 * A run's time: when it completed, else when its first trigger ran.
	 *
	 * `uap_recipe_log.date_time` is written only when a run completes; a run
	 * still in progress holds `0000-00-00 00:00:00` there. Its first trigger
	 * log row says when it started. A run with neither (a placeholder row,
	 * stored as -1) has no time and is left out.
	 *
	 * @return string
	 */
	private function run_date_sql(): string {
		$wpdb = $this->wpdb;

		return "CASE WHEN r.date_time >= '1970-01-02 00:00:00' THEN r.date_time ELSE ( SELECT MIN( rt.date_time ) FROM {$wpdb->prefix}uap_trigger_log rt WHERE rt.automator_recipe_log_id = r.ID AND rt.date_time >= '1970-01-02 00:00:00' ) END";
	}

	/**
	 * An action that a resend already replayed successfully is no longer failed.
	 *
	 * @return string
	 */
	private function not_resent_sql(): string {
		$wpdb = $this->wpdb;

		return "NOT EXISTS ( SELECT 1 FROM {$wpdb->prefix}uap_api_log_response rs WHERE rs.item_log_id = a.ID AND rs.result = 'completed' )";
	}

	/**
	 * Adds the query's filters to the WHERE.
	 *
	 * @param Log_List_Query                $query   The read.
	 * @param array<string, string>         $columns The list's columns.
	 * @param string[]                      $where   The WHERE conditions.
	 * @param array<int, int|string>        $params  The values they bind.
	 *
	 * @return void
	 */
	private function add_filters( Log_List_Query $query, array $columns, array &$where, array &$params ): void {
		$wpdb = $this->wpdb;

		if ( null !== $query->get_recipe_id() ) {
			$where[]  = "{$columns['recipe']} = %d";
			$params[] = $query->get_recipe_id();
		}

		if ( null !== $query->get_user_id() ) {
			$where[]  = "{$columns['user']} = %d";
			$params[] = $query->get_user_id();
		}

		if ( null !== $query->get_status_ints() ) {
			$where[] = "{$columns['status']} IN ( " . implode( ', ', array_fill( 0, count( $query->get_status_ints() ), '%d' ) ) . ' )';
			$params  = array_merge( $params, $query->get_status_ints() );
		}

		if ( null !== $query->get_date_from() ) {
			$where[]  = "{$columns['date']} >= %s";
			$params[] = $query->get_date_from();
		}

		if ( null !== $query->get_date_to() ) {
			$where[]  = "{$columns['date']} <= %s";
			$params[] = $query->get_date_to();
		}

		if ( null !== $query->get_item_code() ) {
			$where[]  = "EXISTS ( SELECT 1 FROM {$wpdb->postmeta} ic WHERE ic.post_id = {$columns['item']} AND ic.meta_key = 'code' AND ic.meta_value = %s )";
			$params[] = $query->get_item_code();
		}

		if ( null !== $query->get_endpoint() ) {
			$where[]  = 'api.endpoint = %s';
			$params[] = $query->get_endpoint();
		}

		if ( null !== $query->get_item_log_id() ) {
			$where[]  = 'a.ID = %d';
			$params[] = $query->get_item_log_id();
		}

		if ( null !== $query->get_integration() ) {
			$where[] = $this->integration_sql( $query->get_integration(), $params );
		}
	}

	/**
	 * Runs that ran an integration: one of their triggers or actions, a loop's
	 * actions included, is that integration's; a run that holds a loop ran
	 * LOOP. The same set an entry's integrations lists.
	 *
	 * @param string                 $code   The integration code.
	 * @param array<int, int|string> $params The values the WHERE binds.
	 *
	 * @return string
	 */
	private function integration_sql( string $code, array &$params ): string {
		$wpdb = $this->wpdb;

		$any = array(
			"EXISTS ( SELECT 1 FROM {$wpdb->prefix}uap_trigger_log it INNER JOIN {$wpdb->postmeta} itm ON itm.post_id = it.automator_trigger_id AND itm.meta_key = 'integration' WHERE it.automator_recipe_log_id = r.ID AND itm.meta_value = %s )",
			"EXISTS ( SELECT 1 FROM {$wpdb->prefix}uap_action_log ia INNER JOIN {$wpdb->postmeta} iam ON iam.post_id = ia.automator_action_id AND iam.meta_key = 'integration' WHERE ia.automator_recipe_log_id = r.ID AND iam.meta_value = %s )",
		);
		array_push( $params, $code, $code );

		if ( $this->table_exists( 'uap_loop_entries_items' ) ) {
			$any[]    = "EXISTS ( SELECT 1 FROM {$wpdb->prefix}uap_loop_entries_items ili INNER JOIN {$wpdb->postmeta} ilm ON ilm.post_id = ili.action_id AND ilm.meta_key = 'integration' WHERE ili.recipe_log_id = r.ID AND ilm.meta_value = %s )";
			$params[] = $code;
		}

		if ( 'LOOP' === $code && $this->table_exists( 'uap_loop_entries' ) ) {
			$any[] = "EXISTS ( SELECT 1 FROM {$wpdb->prefix}uap_loop_entries ile WHERE ile.recipe_log_id = r.ID )";
		}

		return '( ' . implode( ' OR ', $any ) . ' )';
	}

	/**
	 * The search: the recipe's title and the user's name and email on every
	 * list, the item's title and sentence on the trigger and action lists,
	 * the action's notes on the action lists, and the endpoint on failed
	 * actions. Already prepared.
	 *
	 * @param string $kind   The list.
	 * @param string $search The search text.
	 *
	 * @return string
	 */
	private function search_sql( string $kind, string $search ): string {
		$wpdb = $this->wpdb;
		$like = '%' . $wpdb->esc_like( $search ) . '%';

		$any = array(
			$wpdb->prepare( 'p.post_title LIKE %s', $like ),
			$wpdb->prepare( 'u.display_name LIKE %s', $like ),
			$wpdb->prepare( 'u.user_email LIKE %s', $like ),
		);

		if ( Log_List_Kind::TRIGGERS === $kind ) {
			$any[] = $wpdb->prepare( 'ip.post_title LIKE %s', $like );
			$any[] = $wpdb->prepare( "EXISTS ( SELECT 1 FROM {$wpdb->prefix}uap_trigger_log_meta stm WHERE stm.automator_trigger_log_id = t.ID AND stm.meta_key = 'sentence_human_readable' AND stm.meta_value LIKE %s )", $like ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table prefix only.
		}

		if ( Log_List_Kind::lists_actions( $kind ) ) {
			$any[] = $wpdb->prepare( 'ip.post_title LIKE %s', $like );
			$any[] = $wpdb->prepare( "EXISTS ( SELECT 1 FROM {$wpdb->prefix}uap_action_log_meta sam WHERE sam.automator_action_log_id = a.ID AND sam.meta_key IN ( 'sentence_human_readable', 'sentence_human_readable_html' ) AND sam.meta_value LIKE %s )", $like ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table prefix only.
			$any[] = $this->errors->notes_search_sql( 'a.ID', 'a.error_message', $search );
		}

		if ( Log_List_Kind::FAILED_ACTIONS === $kind ) {
			$any[] = $wpdb->prepare( 'api.endpoint LIKE %s', $like );
		}

		return '( ' . implode( ' OR ', $any ) . ' )';
	}

	/**
	 * The ORDER BY: the sort column, then the row id so pages never overlap.
	 *
	 * @param Log_List_Query        $query   The read.
	 * @param array<string, string> $columns The list's columns.
	 *
	 * @return string
	 */
	private function order( Log_List_Query $query, array $columns ): string {
		$direction = 'asc' === $query->get_order() ? 'ASC' : 'DESC';

		$column = array(
			'date'   => $columns['date'],
			'recipe' => 'p.post_title',
			'user'   => 'u.display_name',
			'status' => $columns['status'],
		)[ $query->get_orderby() ];

		$tie = 'date' === $query->get_orderby() ? $direction : 'DESC';

		return "{$column} {$direction}, {$columns['id']} {$tie}";
	}

	/**
	 * Positive integer ids, each once.
	 *
	 * @param array<int, mixed> $ids Ids.
	 *
	 * @return int[]
	 */
	private function ids( array $ids ): array {
		return array_values(
			array_unique(
				array_filter(
					array_map( 'intval', $ids ),
					static function ( int $id ): bool {
						return $id > 0;
					}
				)
			)
		);
	}

	/**
	 * Whether one of Pro's optional tables exists.
	 *
	 * @param string $table The table, without the prefix.
	 *
	 * @return bool
	 */
	private function table_exists( string $table ): bool {
		if ( ! isset( $this->tables[ $table ] ) ) {
			$wpdb = $this->wpdb;
			$name = $wpdb->prefix . $table;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check, once per request.
			$this->tables[ $table ] = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $name ) ) ) === $name;
		}

		return $this->tables[ $table ];
	}
}
