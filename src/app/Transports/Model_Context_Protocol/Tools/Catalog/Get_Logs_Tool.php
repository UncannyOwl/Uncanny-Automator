<?php
/**
 * Consolidated log retrieval tool.
 *
 * Replaces: get_log, list_logs.
 * With the three run identifiers, returns selected fields from a single run.
 * Otherwise, returns filtered list of logs.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog;

use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects\User_Context;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Service;
use Uncanny_Automator\App\Recipe_Logs\Services\Recipe_Log_Query_Service;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Automator_Status;

/**
 * Get Logs Tool.
 *
 * Single log detail: provide recipe_id + run_number + recipe_log_id.
 * Log list: provide optional filters (recipe_id, user_id, date range, pagination).
 *
 * @since 7.1.0
 */
class Get_Logs_Tool extends Abstract_MCP_Tool {

	/**
	 * The status names a list returns, with the code each run stores.
	 *
	 * Written out so the published names do not change with a site's
	 * automator_status_get_class_name filter.
	 *
	 * @since 8.1.0
	 */
	private const STATUS_CODES = array(
		'not-completed'           => Automator_Status::NOT_COMPLETED,
		'completed'               => Automator_Status::COMPLETED,
		'completed-with-errors'   => Automator_Status::COMPLETED_WITH_ERRORS,
		'in-progress'             => Automator_Status::IN_PROGRESS,
		'cancelled'               => Automator_Status::CANCELLED,
		'skipped'                 => Automator_Status::SKIPPED,
		'completed-do-nothing'    => Automator_Status::DID_NOTHING,
		'completed-awaiting'      => Automator_Status::COMPLETED_AWAITING,
		'completed-with-notice'   => Automator_Status::COMPLETED_WITH_NOTICE,
		'queued'                  => Automator_Status::QUEUED,
		'in-progress-with-errors' => Automator_Status::IN_PROGRESS_WITH_ERROR,
		'failed'                  => Automator_Status::FAILED,
		'blocked-on-dependency'   => Automator_Status::BLOCKED_ON_DEPENDENCY,
	);

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'get_logs';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Retrieve recipe execution logs. '
			. 'For a single detailed log: provide recipe_id, run_number, and recipe_log_id. '
			. 'Detail defaults to the run summary. Request steps to see what happened: each trigger, action, filter, delay and loop with its path, status and result_message, where a failed step reports its error. Request flow only when you need the values each step used. execution.errors holds only errors recorded against the run as a whole. '
			. 'For a filtered list: provide optional filters (recipe_id, user_id, status, date_from, date_to). '
			. 'Use count_only for the number of matching runs or fields to select the information needed. User identifiers require explicit selection.';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'recipe_id'     => array(
					'type'        => 'integer',
					'description' => 'Filter by recipe ID, or identify a specific log (with run_number + recipe_log_id).',
					'minimum'     => 1,
				),
				'run_number'    => array(
					'type'        => 'integer',
					'description' => 'Run number for single log detail. Required with recipe_id + recipe_log_id. It counts one user\'s runs of the recipe, so it repeats across users, and every run nobody was signed in for is 1. Use recipe_log_id to tell runs apart.',
					'minimum'     => 1,
				),
				'recipe_log_id' => array(
					'type'        => 'integer',
					'description' => 'Recipe log ID for single log detail. Required with recipe_id + run_number. Unique for each run.',
					'minimum'     => 1,
				),
				'user_id'       => array(
					'type'        => 'integer',
					'description' => 'Filter logs by user ID.',
					'minimum'     => 1,
				),
				'signed_in'     => array(
					'type'        => 'boolean',
					'description' => 'true for runs a signed-in user triggered, false for runs nobody was signed in for, including runs where the recipe then assigned a WordPress account to a visitor who was not signed in. Applies to a list and a count. Older runs did not record the visitor, so an older run with an assigned account counts as signed in. To find the latest signed-in run, send signed_in true with limit 1 and name a user field only if the question needs that person.',
				),
				'count_only'    => array(
					'type'        => 'boolean',
					'description' => 'Return only the total matching the list filters. Pagination and field selection do not affect the count.',
				),
				'status'        => array(
					'type'        => 'array',
					'description' => 'Filter the run list or count by run status, using the names a list returns in status. A run matches when it is in any of them. When someone asks about failed runs or runs with errors, send completed-with-errors, in-progress-with-errors and failed together and report one total. The status named failed alone covers only runs that stopped while in progress.',
					'items'       => array(
						'type' => 'string',
						'enum' => array_keys( self::STATUS_CODES ),
					),
					'minItems'    => 1,
					'uniqueItems' => true,
				),
				'fields'        => array(
					'type'        => 'array',
					'description' => 'Return only the named fields. A list and a single run take different names, and a name from the other mode is refused. For a list: ' . implode( ', ', Recipe_Log_Fields::list_fields() ) . '. For a single run: ' . implode( ', ', Recipe_Log_Fields::detail_fields() ) . '. Name a user field only when the question needs that person. Counts and pagination are independent of the selection.',
					'items'       => array(
						'type' => 'string',
						'enum' => Recipe_Log_Fields::all(),
					),
					'minItems'    => 1,
					'uniqueItems' => true,
				),
				'include_meta'  => array(
					'type'        => 'boolean',
					'description' => 'Include list metadata when fields is omitted. With fields, select meta explicitly.',
					'default'     => false,
				),
				'date_from'     => array(
					'type'        => 'string',
					'description' => 'Filter logs from this date (YYYY-MM-DD).',
					'pattern'     => '^\d{4}-\d{2}-\d{2}$',
				),
				'date_to'       => array(
					'type'        => 'string',
					'description' => 'Include logs at or before midnight at the start of this date (YYYY-MM-DD).',
					'pattern'     => '^\d{4}-\d{2}-\d{2}$',
				),
				'limit'         => array(
					'type'        => 'integer',
					'description' => 'Max logs to return in list mode. A list is newest first, so limit 1 returns the latest matching run.',
					'minimum'     => 1,
					'maximum'     => 100,
					'default'     => 20,
				),
				'offset'        => array(
					'type'        => 'integer',
					'description' => 'Logs to skip (pagination).',
					'minimum'     => 0,
					'default'     => 0,
				),
			),
			'required'   => array(),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function output_schema_definition(): ?array {
		return array(
			'type'       => 'object',
			'properties' => array(
				// Single log detail mode (recipe_id + run_number + recipe_log_id all present).
				'success'    => array( 'type' => 'boolean' ),
				'log'        => array( 'type' => 'object' ),
				// List mode.
				'items'      => array(
					'type'        => 'array',
					'description' => 'One entry per run. An entry holds run fields by default. It holds a user field only when the request names that field in fields.',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'recipe_log_id' => array( 'type' => 'integer' ),
							'recipe_id'     => array( 'type' => 'integer' ),
							'run_number'    => array( 'type' => 'integer' ),
							'user_id'       => array(
								'type'        => 'integer',
								'description' => 'Not returned by default. Present only when fields names user_id. 0 for a run nobody was signed in for.',
							),
							'user_email'    => array(
								'type'        => array( 'string', 'null' ),
								'description' => 'Not returned by default. Present only when fields names user_email.',
							),
							'display_name'  => array(
								'type'        => array( 'string', 'null' ),
								'description' => 'Not returned by default. Present only when fields names display_name.',
							),
							'status'        => array( 'type' => 'string' ),
							'date_time'     => array( 'type' => 'string' ),
						),
					),
				),
				'total'      => array( 'type' => 'integer' ),
				'pagination' => array( 'type' => 'object' ),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {

		$recipe_id     = isset( $params['recipe_id'] ) ? (int) $params['recipe_id'] : 0;
		$run_number    = isset( $params['run_number'] ) ? (int) $params['run_number'] : 0;
		$recipe_log_id = isset( $params['recipe_log_id'] ) ? (int) $params['recipe_log_id'] : 0;

		if ( ! empty( $params['count_only'] ) && ( $run_number || $recipe_log_id ) ) {
			return Json_Rpc_Response::create_error_response( 'Use count_only with list filters. Omit run_number and recipe_log_id.' );
		}

		// Single log detail: all three identity fields provided.
		if ( $recipe_id > 0 && $run_number > 0 && $recipe_log_id > 0 ) {
			$fields = $params['fields'] ?? Recipe_Log_Fields::DETAIL_DEFAULTS;
			if ( array_diff( $fields, Recipe_Log_Fields::detail_fields() ) ) {
				return Json_Rpc_Response::create_error_response( 'Select detail fields from: ' . implode( ', ', Recipe_Log_Fields::detail_fields() ) );
			}
			return $this->get_single_log( $recipe_id, $run_number, $recipe_log_id, $fields, ! isset( $params['fields'] ) );
		}

		if ( $run_number > 0 || $recipe_log_id > 0 ) {
			return Json_Rpc_Response::create_error_response(
				'Single log detail requires recipe_id, run_number, and recipe_log_id together. Omit run_number and recipe_log_id for list mode.'
			);
		}

		if ( ! empty( $params['count_only'] ) ) {
			return $this->list_logs( $params, array() );
		}

		// List mode.
		$fields = $params['fields'] ?? Recipe_Log_Fields::LIST_DEFAULTS;
		if ( ! isset( $params['fields'] ) && ! empty( $params['include_meta'] ) ) {
			$fields[] = 'meta';
		}
		if ( array_diff( $fields, Recipe_Log_Fields::list_fields() ) ) {
			return Json_Rpc_Response::create_error_response( 'Select list fields from: ' . implode( ', ', Recipe_Log_Fields::list_fields() ) );
		}
		return $this->list_logs( $params, $fields );
	}

	// ──────────────────────────────────────────────────────────────────
	// SINGLE LOG
	// ──────────────────────────────────────────────────────────────────

	/**
	 * Say why a requested triggering user is missing, so an absent user is never
	 * read as an anonymous run. A deleted user keeps their ID when it was asked for.
	 *
	 * @param array|\stdClass $payload Projected detail.
	 * @param string[]        $fields Requested detail fields.
	 * @return array{payload: array|\stdClass, note: string}
	 */
	private function absent_user_note( $payload, array $fields, int $recipe_id, int $run_number, int $recipe_log_id ): array {
		$asked = array_filter( $fields, static fn( $field ) => 0 === strpos( $field, 'triggered_by_user.' ) );
		if ( array() === $asked || ( is_array( $payload ) && isset( $payload['triggered_by_user'] ) ) ) {
			return array(
				'payload' => $payload,
				'note'    => '',
			);
		}
		$row     = Database::get_execution_log_store()->get_recipe_log_row_by_ids( $recipe_id, $run_number, $recipe_log_id );
		$user_id = (int) ( $row['user_id'] ?? 0 );
		if ( $user_id <= 0 ) {
			return array(
				'payload' => $payload,
				'note'    => '. Nobody was signed in for this run.',
			);
		}
		if ( in_array( 'triggered_by_user.id', $fields, true ) ) {
			$payload                      = (array) $payload;
			$payload['triggered_by_user'] = array( 'id' => $user_id );
			return array(
				'payload' => $payload,
				'note'    => sprintf( '. User %d triggered this run but no longer exists, so their name and email are unavailable.', $user_id ),
			);
		}
		// The ID goes out only when the request names it (QA D105).
		return array(
			'payload' => $payload,
			'note'    => '. The user who triggered this run no longer exists, so their name and email are unavailable.',
		);
	}

	/**
	 * Select fields from the run tree used by the recipe log dialog.
	 *
	 * @param int $recipe_id     Recipe ID.
	 * @param int $run_number    Run number.
	 * @param int $recipe_log_id Recipe log ID.
	 * @param array $fields Requested detail fields.
	 * @param bool  $is_default Whether the caller left the selection to the default summary.
	 * @return array JSON-RPC response.
	 */
	private function get_single_log( int $recipe_id, int $run_number, int $recipe_log_id, array $fields, bool $is_default ): array {

		$payload = Recipe_Log_Query_Service::instance()->get_log_payload( $recipe_id, $run_number, $recipe_log_id );

		if ( is_wp_error( $payload ) ) {
			return Json_Rpc_Response::create_error_response( $payload->get_error_message() );
		}

		$payload = Recipe_Log_Fields::project_detail( $payload, $fields );
		$absent  = $this->absent_user_note( $payload, $fields, $recipe_id, $run_number, $recipe_log_id );
		$payload = $absent['payload'];

		// A caller that knows the earlier contract expects the steps here, so say how to ask for them.
		$message = $is_default
			? 'Run summary retrieved. To see what happened, select steps: each step with its path, status and result_message, where a failed step reports its error. Select flow only when you need the values each step used. execution.errors holds only errors recorded against the run as a whole.'
			: 'Recipe log retrieved successfully';
		if ( is_array( $payload ) && array() === ( $payload['execution']['errors'] ?? null ) ) {
			// An empty list is easy to read as "nothing went wrong" when the steps failed.
			// The default summary ends in a full stop; never print two.
			$message = rtrim( $message, '.' ) . ( in_array( 'steps', $fields, true )
				? '. No errors were recorded against the run as a whole. A failed step reports its error in its own result_message under steps.'
				: '. No errors were recorded against the run as a whole. A failed step reports its error in its own result_message; select steps to read them.' );
		}
		if ( '' !== $absent['note'] ) {
			$message = rtrim( $message, '.' ) . $absent['note'];
		}

		// Strip sensitive HTTP headers (cookies, auth tokens) from requested detail.
		return Json_Rpc_Response::create_success_response( $message, array( 'log' => $this->redact_sensitive_log_data( $payload ) ) );
	}

	// ──────────────────────────────────────────────────────────────────
	// LOG LIST — port of Get_Recipe_Logs_Tool::execute_tool()
	// ──────────────────────────────────────────────────────────────────

	/**
	 * List logs with filters.
	 *
	 * @param array $params Tool parameters.
	 * @param array $fields Requested list fields.
	 * @return array JSON-RPC response.
	 */
	private function list_logs( array $params, array $fields ): array {

		$service = Recipe_Service::instance();

		$limit  = isset( $params['limit'] ) ? (int) $params['limit'] : 20;
		$offset = isset( $params['offset'] ) ? (int) $params['offset'] : 0;

		$filters = array(
			'limit'        => max( 1, min( 100, $limit ) ),
			'offset'       => max( 0, $offset ),
			'include_meta' => in_array( 'meta', $fields, true ),
		);

		if ( ! empty( $params['count_only'] ) ) {
			$filters['count_only'] = true;
		}
		if ( isset( $params['status'] ) ) {
			// The input schema admits only these names. A list that named none of them matches no run.
			$filters['completed'] = array_values( array_intersect_key( self::STATUS_CODES, array_flip( (array) $params['status'] ) ) );
		}
		if ( ! empty( $params['recipe_id'] ) ) {
			$filters['recipe_id'] = (int) $params['recipe_id'];
		}
		if ( ! empty( $params['user_id'] ) ) {
			$filters['user_id'] = (int) $params['user_id'];
		}
		if ( isset( $params['signed_in'] ) ) {
			$filters['signed_in'] = (bool) $params['signed_in'];
		}
		if ( ! empty( $params['date_from'] ) ) {
			$filters['date_from'] = $params['date_from'];
		}
		if ( ! empty( $params['date_to'] ) ) {
			$filters['date_to'] = $params['date_to'];
		}

		$result = $service->get_recipe_logs( $filters );

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		if ( ! empty( $params['count_only'] ) ) {
			return Json_Rpc_Response::create_success_response( 'Matching recipe runs counted', array( 'total' => (int) $result['total_logs'] ) );
		}

		$logs       = $result['logs'] ?? array();
		$total_logs = $result['total_logs'] ?? count( $logs );
		$pagination = $result['pagination'] ?? null;

		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		$selected_keys = array_flip( $fields );
		$logs          = array_map(
			function ( $entry ) use ( $selected_keys ) {
				$entry           = is_array( $entry ) ? $entry : array();
				$status          = $entry['completed'] ?? '';
				$entry['status'] = Automator_Status::get_class_name( $status );
				if ( isset( $entry['automator_recipe_id'] ) ) {
					$entry['recipe_id'] = $entry['automator_recipe_id'];
				}
				if ( isset( $entry['recipe_date_time'] ) ) {
					$entry['date_time'] = $entry['recipe_date_time'];
				}
				$entry = $this->to_int(
					array(
						'recipe_log_id',
						'recipe_id',
						'run_number',
						'user_id',
					),
					array_intersect_key( $entry, $selected_keys )
				);
				return empty( $entry ) ? new \stdClass() : $this->redact_sensitive_log_data( $entry );
			},
			$logs
		);

		$response_data = array(
			'items' => $logs,
			'total' => (int) $total_logs,
		);

		if ( null !== $pagination ) {
			$response_data['pagination'] = $pagination;
		}

		return Json_Rpc_Response::create_success_response(
			sprintf( 'Retrieved %d of %d recipe log(s)', count( $logs ), (int) $total_logs ),
			$response_data
		);
	}

	/**
	 * Normalize a log list row to match the generated MCP output schema.
	 *
	 * The schema declares these fields as integers, and SDK clients reject a
	 * null (for example a NULL user_id on an anonymous run), so a missing or
	 * non-numeric value becomes 0 rather than being passed through.
	 *
	 * @param array $fields Fields that must be integers.
	 * @param array $entry Log list row.
	 * @return array Schema-compatible log list row.
	 */
	private function to_int( array $fields, array $entry ): array {

		foreach ( $fields as $field ) {
			if ( ! array_key_exists( $field, $entry ) ) {
				continue;
			}

			$entry[ $field ] = is_numeric( $entry[ $field ] ) ? (int) $entry[ $field ] : 0;
		}

		return $entry;
	}

	/**
	 * Recursively redact sensitive HTTP data from log entries.
	 *
	 * Trigger logs can capture raw request headers including auth cookies and
	 * session tokens. These are useful for server-side debugging but must not
	 * be exposed through the MCP transport to AI agents.
	 *
	 * @param mixed $data Log data (array or scalar).
	 *
	 * @return mixed Data with sensitive values replaced by '[REDACTED]'.
	 */
	private function redact_sensitive_log_data( $data ) {

		if ( ! is_array( $data ) ) {
			return $data;
		}

		$sensitive_keys = array( 'cookie', 'cookies', 'set-cookie', 'authorization', 'x-automator-creds' );

		foreach ( $data as $key => &$value ) {
			if ( in_array( strtolower( (string) $key ), $sensitive_keys, true ) ) {
				$value = '[REDACTED]';
				continue;
			}

			if ( is_array( $value ) ) {
				$value = $this->redact_sensitive_log_data( $value );
			}
		}

		return $data;
	}
}
