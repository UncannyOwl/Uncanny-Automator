<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Background;

use Uncanny_Automator\App\Bridge\Automator_Recipe_Runner_Bridge;
use Uncanny_Automator\App\Bridge\Recipe_Runner_Bridge;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Background_Job_Store;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Background_Job_Result;

/**
 * Answers the inbox drainer's `automator_platform_async_completed` for a job this site recorded: the callback's
 * fields become a Background_Job_Result and the runner finishes the awaiting action with it. Background_Bootstrap
 * binds it to the hook.
 */
final class Background_Job_Completion_Subscriber {

	/**
	 * The jobs this site dispatched.
	 *
	 * @var Background_Job_Store
	 */
	private Background_Job_Store $jobs;

	/**
	 * The runner, through the bridge.
	 *
	 * @var Recipe_Runner_Bridge
	 */
	private Recipe_Runner_Bridge $runner;

	/**
	 * Bind the collaborators.
	 *
	 * @param Background_Job_Store|null $jobs   Defaults to the shared store.
	 * @param Recipe_Runner_Bridge|null $runner Defaults to the legacy-facade bridge.
	 */
	public function __construct( ?Background_Job_Store $jobs = null, ?Recipe_Runner_Bridge $runner = null ) {
		$this->jobs   = $jobs ?? Database::get_background_job_store();
		$this->runner = $runner ?? new Automator_Recipe_Runner_Bridge();
	}

	/**
	 * One completion. A job this site never recorded is logged and ignored; anything the runner throws propagates,
	 * so the drainer marks the inbox row failed and the sweep retries it.
	 *
	 * @param mixed $job_id     The platform job id.
	 * @param mixed $status     `completed` or `failed`.
	 * @param mixed $result     `{ status_code, data }` on success.
	 * @param mixed $error      `{ code, type, description, message }` on failure.
	 * @param mixed $credits    Credits block.
	 * @param mixed $request_id The platform request id.
	 *
	 * @return void
	 */
	public function on_completion( $job_id, $status = '', $result = array(), $error = array(), $credits = array(), $request_id = '' ): void {

		$job = $this->jobs->find_by_job_id( (string) $job_id );
		if ( null === $job ) {
			automator_log( sprintf( 'Async completion for job %s ignored: this site did not record it.', (string) $job_id ), 'Background_Actions' );
			return;
		}

		$this->runner->complete_background_action(
			$job,
			Background_Job_Result::from_callback(
				(string) $status,
				is_array( $result ) ? $result : array(),
				is_array( $error ) ? $error : array(),
				is_array( $credits ) ? $credits : array(),
				(string) $request_id
			)
		);
	}
}
