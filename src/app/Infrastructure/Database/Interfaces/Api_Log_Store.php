<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Database\Interfaces;

use Uncanny_Automator\App\Infrastructure\Api_Log\Value_Objects\Api_Log_Entry;
use Uncanny_Automator\App\Infrastructure\Api_Client\Api_Request;
use Uncanny_Automator\App\Infrastructure\Api_Client\Api_Response;
use Uncanny_Automator\App\Infrastructure\Database\Database;

/**
 * Contract for the API log store.
 *
 * Persists records into the `uap_api_log` table. Implementations are
 * registered through {@see Database::get_api_log_store()}.
 *
 * @since 7.4.0
 */
interface Api_Log_Store {

	/**
	 * Log an API request/response pair.
	 *
	 * Builds an {@see Api_Log_Entry} from the request/response objects and
	 * persists it to the database. Returns null when the request lacks the
	 * action_data needed to associate the call with a recipe.
	 *
	 * @param Api_Request  $request  The outbound API request.
	 * @param Api_Response $response The parsed API response.
	 *
	 * @return int|null The insert ID, or null when nothing was persisted.
	 */
	public function log( Api_Request $request, Api_Response $response ): ?int;

	/**
	 * Persist a pre-built log entry directly.
	 *
	 * Useful for tests and for callers that have already constructed an
	 * {@see Api_Log_Entry} from non-Api_Request data.
	 *
	 * @param Api_Log_Entry $entry The log entry to persist.
	 *
	 * @return int The insert ID.
	 */
	public function persist( Api_Log_Entry $entry ): int;
}
