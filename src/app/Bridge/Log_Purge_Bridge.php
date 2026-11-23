<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

/**
 * Anti-corruption boundary for the legacy purge of one run's logs.
 *
 * Wraps the four `automator_purge_*_logs()` helpers the Logs page's legacy
 * delete called (they reach `Automator()->db->{api,action,trigger,recipe}`),
 * so a run deleted from `src/app/` removes exactly what it always removed:
 * its API logs and responses, error rows, action and trigger logs and meta,
 * recipe meta, tokens log, finished background jobs, run snapshots and the
 * run row. Consumer: `Recipe_Run_Delete_Service`.
 *
 * @since 8.1.0
 */
interface Log_Purge_Bridge {

	/**
	 * Deletes every log row of one run.
	 *
	 * @param int $recipe_id     Recipe post ID.
	 * @param int $recipe_log_id Recipe log ID.
	 *
	 * @return void
	 */
	public function purge_run( int $recipe_id, int $recipe_log_id ): void;
}
