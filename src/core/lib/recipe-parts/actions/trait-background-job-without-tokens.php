<?php
namespace Uncanny_Automator\Recipe;

/**
 * The opt-in for an app action whose platform write may complete in the background and whose job yields no
 * tokens: a completed job needs nothing from the platform's answer, and a failed job never reaches process_job().
 * Use it instead of writing process_job() by hand whenever the action turns no result data into tokens.
 */
trait Background_Job_Without_Tokens {

	/**
	 * Finish the write. The platform's answer carries nothing this action turns into tokens, so a completed job
	 * is done.
	 *
	 * @param array $response    The sync-shaped envelope.
	 * @param array $parsed      The parsed field values recorded at dispatch.
	 * @param array $action_data The action data.
	 *
	 * @return bool
	 */
	protected function process_job( array $response, array $parsed, array $action_data ): bool {
		return true;
	}
}
