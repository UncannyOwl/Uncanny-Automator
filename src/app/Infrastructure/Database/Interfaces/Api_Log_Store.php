<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Database\Interfaces;

use Uncanny_Automator\App\Infrastructure\Api_Log\Value_Objects\Api_Log_Entry;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Request;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Response;
use Uncanny_Automator\App\Infrastructure\Database\Database;

/**
 * Contract for the API log store.
 *
 * Persists records into the `uap_api_log` table. Implementations are
 * registered through {@see Database::get_api_log_store()}.
 *
 * @since 8.0.0
 */
interface Api_Log_Store {

	/**
	 * Log an API request/response pair.
	 *
	 * Builds an {@see Api_Log_Entry} from the request/response objects and
	 * persists it to the database. Returns null when the request lacks the
	 * action_data needed to associate the call with a recipe.
	 *
	 * @param Request  $request  The outbound API request.
	 * @param Response $response The parsed API response.
	 *
	 * @return int|null The insert ID, or null when nothing was persisted.
	 */
	public function log( Request $request, Response $response ): ?int;

	/**
	 * Persist a pre-built log entry directly.
	 *
	 * Useful for tests and for callers that have already constructed an
	 * {@see Api_Log_Entry} from non-Request data.
	 *
	 * @param Api_Log_Entry $entry The log entry to persist.
	 *
	 * @return int The insert ID.
	 */
	public function persist( Api_Log_Entry $entry ): int;

	/**
	 * All retry-response rows for a recipe-log, indexed by item_log_id.
	 *
	 * @param int $recipe_log_id Recipe-log row id.
	 * @return array<int,array<int,array<string,mixed>>>
	 */
	public function get_api_retries( int $recipe_log_id ): array;

	/**
	 * The run's action-log ids that have an API log row, which is what a resend replays.
	 *
	 * @param int $recipe_log_id Recipe-log row id.
	 * @return int[]
	 */
	public function get_api_logged_action_log_ids( int $recipe_log_id ): array;
}
