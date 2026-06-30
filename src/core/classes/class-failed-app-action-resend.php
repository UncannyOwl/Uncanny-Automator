<?php
namespace Uncanny_Automator;

/**
 * Engine for the "Resend App actions" Tools sub-tab. Lists failed App
 * actions in a selectable table and re-fires the user-selected rows via
 * Resend_Action_Service (blocking-sequential). A successfully resent action
 * drops off the next list (tracked via uap_api_log_response, never by mutating
 * the original uap_action_log row).
 */
class Failed_App_Action_Resend {

	/**
	 * Action statuses treated as failed and therefore resendable: the action
	 * reached the vendor and errored, so a logged request exists to replay.
	 * COMPLETED_WITH_ERRORS is the common case; IN_PROGRESS_WITH_ERROR and FAILED
	 * cover async/mid-run and hard failures. The EXISTS(api_log) gate in the query
	 * is what guarantees there is actually a request to resend.
	 */
	const FAILED_STATUSES = array(
		Automator_Status::COMPLETED_WITH_ERRORS,  // 2
		Automator_Status::IN_PROGRESS_WITH_ERROR, // 13
		Automator_Status::FAILED,                 // 14
	);

	/** Max rows per list page / max ids per resend request. */
	const PAGE_SIZE = 200;

	/**
	 * READ-ONLY, keyset-paginated list of failed App actions still needing a
	 * resend: completed IN FAILED_STATUSES, within $days, has a type='action'
	 * uap_api_log row, AND NOT already successfully resent (no 'completed'
	 * uap_api_log_response row for its item_log_id).
	 *
	 * @param int    $days     Look-back window.
	 * @param int    $last_id  Keyset cursor; 0 returns the newest page, otherwise returns rows with ID < $last_id (newest first).
	 * @param int    $batch    Rows per page (capped at PAGE_SIZE).
	 * @param string $endpoint Exact api_log endpoint to filter by; '' = no filter.
	 * @return array{ total:int, last_id:int, has_more:bool, rows:array<int,array>, endpoints:array<int,string> }
	 */
	public function list_failed( $days, $last_id = 0, $batch = self::PAGE_SIZE, $endpoint = '' ) {

		global $wpdb;

		$days     = max( 1, absint( $days ) );
		$last_id  = absint( $last_id );
		$batch    = min( self::PAGE_SIZE, max( 1, absint( $batch ) ) );
		$endpoint = trim( (string) $endpoint );

		$completed_class = Automator_Status::get_class_name( Automator_Status::COMPLETED );

		// Shared WHERE: failed App action in window, has an api_log action row, NOT yet
		// successfully resent. The 'completed' response row is what drops a row off the list.
		// When an endpoint filter is set, constrain the EXISTS action row to that endpoint so
		// the COUNT and the page rows stay in sync.
		$endpoint_filter = '' !== $endpoint ? ' AND api.endpoint = %s' : '';

		// Placeholder list for the failed-status set, e.g. "%d, %d, %d".
		$status_in = implode( ', ', array_fill( 0, count( self::FAILED_STATUSES ), '%d' ) );

		$where_args = array_merge( self::FAILED_STATUSES, array( $days ) );
		if ( '' !== $endpoint ) {
			$where_args[] = $endpoint;
		}
		$where_args[] = $completed_class;

		$where = $wpdb->prepare(
			"al.completed IN ( {$status_in} )
			 AND al.date_time >= DATE_SUB( NOW(), INTERVAL %d DAY )
			 AND EXISTS (
			     SELECT 1 FROM {$wpdb->prefix}uap_api_log api
			     WHERE api.item_log_id = al.ID AND api.type = 'action'{$endpoint_filter}
			 )
			 AND NOT EXISTS (
			     SELECT 1 FROM {$wpdb->prefix}uap_api_log_response r
			     WHERE r.item_log_id = al.ID AND r.result = %s
			 )",
			$where_args
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Header count: all matching the same WHERE (not just this page).
		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}uap_action_log al WHERE {$where}"
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// The INNER JOIN also restricts to the filtered endpoint so the returned rows match the count.
		$join_endpoint = '' !== $endpoint ? ' AND api.endpoint = %s' : '';
		// Descending keyset (newest first): the first page (last_id 0) starts from the
		// newest row; later pages continue below the oldest id already seen.
		$cursor_clause = $last_id > 0 ? ' AND al.ID < %d' : '';
		$page_args     = array();
		if ( '' !== $endpoint ) {
			$page_args[] = $endpoint;
		}
		if ( $last_id > 0 ) {
			$page_args[] = $last_id;
		}
		$page_args[] = $batch + 1; // One extra row beyond the page so has_more is exact (see below).

		// Page of rows, joined to api_log (action) + recipe_log (run_number) + a resent counter.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT al.ID AS action_log_id,
				        al.automator_recipe_id AS recipe_id,
				        al.automator_recipe_log_id AS recipe_log_id,
				        al.user_id AS user_id,
				        al.date_time AS date_time,
				        al.error_message AS error_message,
				        api.endpoint AS endpoint,
				        api.status AS status,
				        api.response AS response,
				        COALESCE( rl.run_number, 1 ) AS run_number,
				        ( SELECT COUNT(*) FROM {$wpdb->prefix}uap_api_log_response rc
				          WHERE rc.item_log_id = al.ID ) AS resent_count
				 FROM {$wpdb->prefix}uap_action_log al
				 INNER JOIN {$wpdb->prefix}uap_api_log api
				        ON api.item_log_id = al.ID AND api.type = 'action'{$join_endpoint}
				 LEFT JOIN {$wpdb->prefix}uap_recipe_log rl
				        ON rl.ID = al.automator_recipe_log_id
				 WHERE {$where}{$cursor_clause}
				 ORDER BY al.ID DESC
				 LIMIT %d",
				$page_args
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// We fetched one row beyond the page (LIMIT $batch + 1), so has_more is exact:
		// drop the probe row and report a next page only when it actually exists — no
		// spurious empty request when the total is an exact multiple of the page size.
		$has_more = count( $results ) > $batch;
		if ( $has_more ) {
			array_pop( $results );
		}

		// Prime the user + post caches up front so the per-row get_userdata() and
		// get_the_title() lookups below hit cache instead of issuing one query each
		// (cache_users dedups and ignores 0 / invalid ids).
		cache_users( wp_list_pluck( $results, 'user_id' ) );
		_prime_post_caches( array_unique( wp_list_pluck( $results, 'recipe_id' ) ), false, false );

		$rows   = array();
		$cursor = $last_id; // Ends as the smallest id on this page — the next-page keyset cursor (al.ID < cursor).
		foreach ( $results as $r ) {
			$action_log_id = (int) $r->action_log_id;
			$recipe_id     = (int) $r->recipe_id;
			$recipe_log_id = (int) $r->recipe_log_id;
			$user_id       = (int) $r->user_id;
			$run_number    = (int) $r->run_number;
			$cursor        = $action_log_id;

			$title = html_entity_decode( (string) get_the_title( $recipe_id ) );
			if ( '' === trim( $title ) ) {
				/* translators: %d: recipe id */
				$title = sprintf( __( 'Recipe #%d', 'uncanny-automator' ), $recipe_id );
			}

			$user_name  = '';
			$user_email = '';
			if ( $user_id > 0 ) {
				$u = get_userdata( $user_id );
				if ( $u ) {
					$name       = trim( (string) $u->display_name );
					$user_name  = '' !== $name ? $name : (string) $u->user_login;
					$user_email = (string) $u->user_email;
				}
			}

			$rows[] = array(
				'action_log_id' => $action_log_id,
				'recipe_id'     => $recipe_id,
				'recipe_title'  => $title,
				'user_id'       => $user_id,
				'user_name'     => $user_name,
				'user_email'    => $user_email,
				'recipe_log_id' => $recipe_log_id,
				'run_number'    => $run_number,
				'fail_date'     => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( (string) $r->date_time ) ),
				'endpoint'      => (string) $r->endpoint,
				'fail_reason'   => $this->derive_fail_reason( (string) $r->error_message, isset( $r->response ) ? $r->response : '', (string) $r->status ),
				'status'        => (string) $r->status,
				'resent_count'  => (int) $r->resent_count,
				'logs_url'      => $this->logs_url( $recipe_id, $recipe_log_id, $run_number, $user_id ),
			);
		}

		// Distinct endpoints for the dropdown: same base criteria as the list, but NOT
		// constrained by $endpoint (so picking a filter never shrinks the dropdown).
		// Computed only on the first page; later pages return array() (the client caches it).
		$endpoints = 0 === $last_id ? $this->list_endpoints( $days, $completed_class ) : array();

		return array(
			'total'     => $total,
			'last_id'   => $cursor,
			'has_more'  => $has_more,
			'rows'      => $rows,
			'endpoints' => $endpoints,
		);
	}

	/**
	 * Distinct api_log endpoints among the failed App actions in the window,
	 * sorted ascending. Mirrors the list's base criteria (failed App action with
	 * a type='action' api_log row, NOT yet successfully resent, within $days) but
	 * is intentionally NOT constrained by the selected endpoint filter.
	 *
	 * @param int    $days            Look-back window.
	 * @param string $completed_class The 'completed' response class name.
	 * @return array<int,string>
	 */
	private function list_endpoints( $days, $completed_class ) {

		global $wpdb;

		$status_in = implode( ', ', array_fill( 0, count( self::FAILED_STATUSES ), '%d' ) );

		$endpoints = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT api.endpoint
				 FROM {$wpdb->prefix}uap_action_log al
				 INNER JOIN {$wpdb->prefix}uap_api_log api
				        ON api.item_log_id = al.ID AND api.type = 'action'
				 WHERE al.completed IN ( {$status_in} )
				   AND al.date_time >= DATE_SUB( NOW(), INTERVAL %d DAY )
				   AND NOT EXISTS (
				       SELECT 1 FROM {$wpdb->prefix}uap_api_log_response r
				       WHERE r.item_log_id = al.ID AND r.result = %s
				   )
				 ORDER BY api.endpoint ASC",
				array_merge( self::FAILED_STATUSES, array( $days, $completed_class ) )
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array_map( 'strval', (array) $endpoints );
	}

	/**
	 * Re-fire exactly the provided action_log ids, blocking-sequential, each via
	 * Resend_Action_Service::resend_action() (which gates non-failed / no-api-log
	 * ids to 'skipped'). Non-ints are ignored; the list is capped at PAGE_SIZE.
	 *
	 * @param array              $action_log_ids
	 * @param Resend_Action_Service|null $service Optional override (testing seam).
	 * @return array{ resent:int, failed:int, skipped:int, results:array<int,array> }
	 */
	public function resend_selected( $action_log_ids, $service = null ) {

		// Designed for small batches: the UI posts ONE id per request so each call
		// stays short. Every id triggers a real outbound HTTP call, so a direct caller
		// passing many ids chains that many requests in a single PHP execution and can
		// hit max execution time — the PAGE_SIZE cap below only bounds the worst case.
		$ids = array();
		foreach ( (array) $action_log_ids as $id ) {
			if ( is_int( $id ) || ( is_string( $id ) && ctype_digit( $id ) ) ) {
				$id = (int) $id;
				if ( $id > 0 ) {
					$ids[] = $id;
				}
			}
		}
		$ids = array_slice( array_values( array_unique( $ids ) ), 0, self::PAGE_SIZE );

		$tally   = array( 'resent' => 0, 'failed' => 0, 'skipped' => 0, 'results' => array() );
		$service = $service instanceof Resend_Action_Service ? $service : new Resend_Action_Service();

		foreach ( $ids as $id ) {
			try {
				$out     = $service->resend_action( $id );
				$outcome = isset( $out['outcome'] ) ? (string) $out['outcome'] : 'skipped';
				$message = isset( $out['message'] ) ? (string) $out['message'] : '';
			} catch ( \Throwable $e ) {
				$outcome = 'skipped';
				$message = $e->getMessage();
				automator_log( 'Resend selected skipped action ' . $id . ': ' . $message, 'resend-selected', false );
			}

			if ( ! in_array( $outcome, array( 'resent', 'failed', 'skipped' ), true ) ) {
				$outcome = 'skipped';
			}
			++$tally[ $outcome ];
			$tally['results'][] = array(
				'action_log_id' => $id,
				'outcome'       => $outcome,
				'message'       => $message,
			);
		}

		return $tally;
	}

	/**
	 * Derive a human-meaningful fail reason for a listed row. The action_log
	 * error_message is frequently EMPTY for App actions (the real reason lives
	 * in the api_log response), so fall back to extracting the error out of the
	 * stored api_log response, then to the bare HTTP status.
	 *
	 * @param string $error_message uap_action_log.error_message (may be empty).
	 * @param mixed  $response_raw  uap_api_log.response (serialized/gz/JSON blob, or object).
	 * @param string $status        uap_api_log.status (HTTP status string).
	 * @return string
	 */
	private function derive_fail_reason( string $error_message, $response_raw, $status ): string {

		// 1) An explicit error_message always wins.
		$message = trim( $error_message );
		if ( '' !== $message ) {
			return $this->clamp_reason( $message );
		}

		// 2) Best-effort extract the error out of the api_log response.
		$extracted = $this->extract_response_error( $response_raw );
		if ( '' !== $extracted ) {
			return $this->clamp_reason( $extracted );
		}

		// 3) Fall back to the bare HTTP status.
		$status = trim( (string) $status );
		return '' !== $status ? 'HTTP ' . $status : '';
	}

	/**
	 * Pull the first meaningful error string out of a stored api_log response.
	 * Handles the decoded-array shape (gz1:/JSON/serialized array) AND the
	 * legacy serialized Api_Response object/blob (via a raw regex fallback).
	 *
	 * @param mixed $response_raw
	 * @return string Empty string when nothing extractable.
	 */
	private function extract_response_error( $response_raw ) {

		$decoded = Resend_Action_Service::decode_api_log_value( $response_raw );

		if ( is_array( $decoded ) ) {
			$candidates = array(
				isset( $decoded['error']['description'] ) ? $decoded['error']['description'] : null,
				isset( $decoded['error']['message'] ) ? $decoded['error']['message'] : null,
				isset( $decoded['message'] ) ? $decoded['message'] : null,
				isset( $decoded['data']['error'] ) ? $decoded['data']['error'] : null,
			);
			foreach ( $candidates as $candidate ) {
				if ( is_string( $candidate ) && '' !== trim( $candidate ) ) {
					return trim( $candidate );
				}
			}
		}

		// Legacy serialized Api_Response OBJECT (or otherwise unextractable):
		// regex the RAW serialized string for a serialized description/message.
		if ( is_string( $response_raw ) && '' !== $response_raw ) {
			foreach ( array( '/"description";s:\d+:"([^"]*)"/', '/"message";s:\d+:"([^"]*)"/' ) as $pattern ) {
				if ( preg_match( $pattern, $response_raw, $matches ) && '' !== trim( $matches[1] ) ) {
					return trim( $matches[1] );
				}
			}
		}

		return '';
	}

	/**
	 * Trim and clamp a fail reason to a sane display length.
	 *
	 * @param string $reason
	 * @return string
	 */
	private function clamp_reason( $reason ) {
		$reason = trim( (string) $reason );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $reason, 0, 300 );
		}
		return substr( $reason, 0, 300 );
	}

	/**
	 * Build the recipe-log dialog URL on the logs page, mirroring the Pro
	 * recovery tool's log_dialog_url(): filter the logs list by recipe + user,
	 * then deep-link the run's dialog via log_dialog_id / log_recipe_id /
	 * log_run_number.
	 *
	 * @param int $recipe_id
	 * @param int $recipe_log_id
	 * @param int $run_number
	 * @param int $user_id
	 * @return string
	 */
	private function logs_url( $recipe_id, $recipe_log_id, $run_number, $user_id ) {

		return add_query_arg(
			array(
				'post_type'      => 'uo-recipe',
				'page'           => 'uncanny-automator-admin-logs',
				'recipe_id'      => (int) $recipe_id,
				'user_id'        => (int) $user_id,
				'filter_action'  => 'Filter',
				'tab'            => 'recipe',
				'log_dialog_id'  => (int) $recipe_log_id,
				'log_recipe_id'  => (int) $recipe_id,
				'log_run_number' => (int) $run_number,
			),
			admin_url( 'edit.php' )
		);
	}
}
