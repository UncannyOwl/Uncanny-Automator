<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

/**
 * Legacy-backed {@see Log_Purge_Bridge}: the same four purges, in the same
 * order, as the legacy Logs page delete.
 *
 * @since 8.1.0
 */
final class Automator_Log_Purge_Bridge implements Log_Purge_Bridge {

	/**
	 * @inheritDoc
	 */
	public function purge_run( int $recipe_id, int $recipe_log_id ): void {
		automator_purge_api_logs( $recipe_id, $recipe_log_id );
		automator_purge_action_logs( $recipe_id, $recipe_log_id );
		automator_purge_trigger_logs( $recipe_id, $recipe_log_id );
		automator_purge_recipe_logs( $recipe_id, $recipe_log_id );
	}
}
