<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe\Routes;

use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Execution_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Violation_Service;
use Uncanny_Automator\App\Transports\Restful\Recipe\Traits\Recipe_Entity;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Rest_Responses;
use WP_REST_Request;

/**
 * Run Now REST handler.
 *
 * `POST /uap/v2/recipe/{recipe_id}/run-now` — runs the same publish
 * validation as set-status, publishes the recipe server-side, fires the Pro
 * run seam, and returns the canonical wire recipe plus the new run's
 * `recipe_log_id` / `run_number`. The recipe is the only thing published
 * here; the run-now trigger keeps whatever status the user gave it. A
 * validation refusal is the 422 violations envelope; nothing is published
 * or fired.
 *
 * Fixes the publish-vs-run race: the legacy auto-publish was Pro's
 * `Run_Now_Integration::publish_recipe()` on `automator_recipe_trigger_created`,
 * a hook the new `src/app` trigger-creation pipeline never fires. Owning the
 * publish-first step in base version-locks the correctness-critical behaviour
 * to the builder FE's own plugin, not the user's (often-lagging) Pro version.
 *
 * Like `Recipe_Settings_Rest`, the route has no `type` segment, so this class
 * carries its own small static `dispatch()` rather than extending
 * `Recipe_Entity_Routes`. Thin per `src/app` Rule 11 — all orchestration lives
 * in `Recipe_Execution_Service::run_now()`.
 */
final class Run_Now_Rest {

	use Rest_Responses;
	use Recipe_Entity;

	/**
	 * REST callback. Referenced from `Recipe_Rest_Controller::register_routes()`
	 * as `[ Run_Now_Rest::class, 'dispatch' ]`.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function dispatch( WP_REST_Request $request ) {
		$handler = new self( (int) $request['recipe_id'], $request );
		return $handler->run();
	}

	/**
	 * Validate, publish-first, fire, and shape the response.
	 *
	 * A publish-validation refusal answers HTTP 422 with the FE violations
	 * envelope (`data` = exactly `{ violations, recipe }`) — the same shape
	 * `Recipe_Settings_Rest::set_status()` returns. Every other error keeps
	 * the WP_Error shape and its own status.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function run() {
		$result = Recipe_Execution_Service::instance()->run_now( $this->get_recipe_id() );

		if ( is_wp_error( $result ) ) {
			if ( Recipe_Violation_Service::REJECTION_CODE === $result->get_error_code() ) {
				return $this->rejection_from_error( $result );
			}
			$status = (int) ( $result->get_error_data()['status'] ?? 400 );
			return $this->failure( $result->get_error_message(), $status, $result->get_error_code() );
		}

		return $this->success(
			array(
				'success'              => true,
				'is_user_logged_in'    => is_user_logged_in(),
				'recipe_id'            => $this->get_recipe_id(),
				'recipe'               => $this->get_recipe_wire(),
				'recipe_run_completed' => $result['recipe_run_completed'],
				'recipe_log_id'        => $result['recipe_log_id'],
				'run_number'           => $result['run_number'],
			)
		);
	}
}
