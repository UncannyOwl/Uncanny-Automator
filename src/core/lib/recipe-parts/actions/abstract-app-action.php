<?php
/**
 * Abstract App Action
 *
 * Extends the base Action class to provide clean access to app integration dependencies.
 * This eliminates the need for array_shift and manual property setting in extending classes.
 *
 * @package Uncanny_Automator\Recipe
 * @since 4.14
 */

namespace Uncanny_Automator\Recipe;

use Exception;
use Uncanny_Automator\App\Infrastructure\Async_Jobs\Idempotency_Key_Minter;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Local_Site_Status;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Failure_Message;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\File_Serve\Allowed_Path_Policy;
use Uncanny_Automator\App\Recipe_Runner\Background\Background_Job_Failure;
use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;
use Uncanny_Automator\App\Recipe_Runner\Services\Error_Code;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Action_Error;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Background_Job_Result;
use function Uncanny_Automator\App\Infrastructure\automator_file_serve_signer;
use function Uncanny_Automator\App\Infrastructure\automator_json_canonical;
use function Uncanny_Automator\App\Infrastructure\automator_source_url_token;

/**
 * Abstract App Action
 *
 * Provides clean access to app integration dependencies:
 * - $this->helpers - Access to the integration's helper methods
 * - $this->api - Access to the integration's API methods
 * - $this->webhooks - Access to the integration's webhook methods (if available)
 *
 * @package Uncanny_Automator\Recipe
 */
abstract class App_Action extends Action {

	/**
	 * Integration API instance.
	 *
	 * @var \Uncanny_Automator\App_Integrations\Api_Caller|null
	 */
	protected $api;

	/**
	 * Integration webhooks instance.
	 *
	 * @var \Uncanny_Automator\App_Integrations\App_Webhooks|null
	 */
	protected $webhooks;

	/**
	 * Job id the platform accepted during process_action(), until deferred() records it; during complete_job(),
	 * the job being finished. While it is set no write in the run dispatches.
	 *
	 * @var string|null
	 */
	private $accepted_job_id = null;

	/**
	 * Values computed before the send that process_job() needs after it.
	 *
	 * @var array<string, mixed>
	 */
	private $remembered = array();

	/**
	 * Override the parent method to set all dependencies from the dependencies object.
	 * This provides clean access to helpers, api, and webhooks without manual property setting.
	 *
	 * @param array $dependencies Array of dependency objects.
	 * @return void
	 */
	protected function set_helpers_from_dependencies( $dependencies ) {
		if ( empty( $dependencies ) || ! isset( $dependencies[0] ) ) {
			return;
		}

		// Use existing available methods.
		$this->set_item_helpers( $dependencies[0] );
		$this->set_helpers( $this->item_helpers->helpers ?? null );

		// Set API if available.
		$this->set_api( $this->item_helpers->api ?? null );

		// Set webhooks if available (optional).
		$this->set_webhooks( $this->item_helpers->webhooks ?? null );
	}

	/**
	 * Set API helpers.
	 *
	 * @param \Uncanny_Automator\App_Integrations\Api_Caller|null $api API instance.
	 *
	 * @return void
	 */
	protected function set_api( $api ) {
		$this->api = $api;
	}

	/**
	 * Override the parent's run-wrapper to bind $this as the owner action on
	 * the api caller before process_action() executes.
	 *
	 * The api caller is shared across an integration's action classes (one
	 * instance per integration); binding here means whichever action is
	 * currently running owns the caller for the duration of its
	 * process_action(). The caller's api_request() reads owner_action
	 * automatically, so a v3 helper's call is logged against the action with
	 * zero per-action wiring at the call site:
	 *
	 *     $result = $this->api->copy_file( $from, $to );
	 *
	 * Recipes execute sequentially in a single PHP request, so concurrent
	 * owners aren't a concern.
	 *
	 * The binding ends with the run. The caller outlives the action (it is
	 * shared per integration), so a binding left in place hands this action's
	 * log ids to whatever calls the API next in the request — an option fetch,
	 * a webhook registration on settings save. The previous owner
	 * is put back rather than cleared, so an action that runs inside another
	 * action's process_action() does not strip the outer one's context.
	 *
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function do_action( $user_id, $action_data, $recipe_id, $args ) {

		// A run starts clean: instances are shared across the runs of one request, so nothing remembered or
		// accepted for a previous run may ride into this run's job.
		$this->remembered      = array();
		$this->accepted_job_id = null;

		$can_bind = null !== $this->api && method_exists( $this->api, 'set_owner_action' );
		$previous = $can_bind ? $this->api->set_owner_action( $this ) : null;

		try {
			parent::do_action( $user_id, $action_data, $recipe_id, $args );
		} finally {
			if ( $can_bind ) {
				$this->api->set_owner_action( $previous );
			}
		}
	}

	/**
	 * Get the integration API.
	 *
	 * @return \Uncanny_Automator\App_Integrations\Api_Caller|null
	 */
	protected function get_api() {
		return $this->api;
	}

	/**
	 * Set webhooks helpers.
	 *
	 * @param \Uncanny_Automator\App_Integrations\App_Webhooks|null $webhooks Webhooks instance.
	 *
	 * @return void
	 */
	protected function set_webhooks( $webhooks ) {
		$this->webhooks = $webhooks;
	}

	/**
	 * Get the integration webhooks.
	 *
	 * @return \Uncanny_Automator\App_Integrations\App_Webhooks|null
	 */
	protected function get_webhooks() {
		return $this->webhooks;
	}

	/**
	 * Map a vendor response into a `[TOKEN_ID => value]` map. Subclasses
	 * that emit output tokens override this method, and `process_action()`
	 * calls `$this->hydrate_tokens( $this->result_to_token_map( $response ) )`.
	 *
	 * @param array<string, mixed> $response Vendor response.
	 *
	 * @return array<string, mixed> Token map for `hydrate_tokens()`.
	 */
	protected function result_to_token_map( array $response ): array {

		return array();
	}

	/**
	 * Read the bound action_data array.
	 *
	 * Public accessor so the API caller (`Automator_Platform_Caller`) can
	 * pull `action_log_id` / `recipe_log_id` / etc. off the owner action
	 * when a v3 helper does not thread `$action_data` through
	 * `api_request()`. That is what gives the call its uap_api_log row.
	 *
	 * @return array
	 */
	public function get_action_data(): array {
		return is_array( $this->action_data ) ? $this->action_data : array();
	}

	/**
	 * Whether this action can complete in the background: it implements process_job(), by hand or through the
	 * Background_Job_Without_Tokens trait.
	 *
	 * @return bool
	 */
	public function is_background_capable(): bool {
		return method_exists( $this, 'process_job' );
	}

	/**
	 * The idempotency key the caller attaches to this action's write, or null when the write must stay synchronous:
	 * the action is not capable, the run is a loop entity, or the policy (setting, platform capability) refuses.
	 * Loop-scoped actions (action_data['loop'] set) always return null and run synchronously.
	 * One dispatching write per run: after the platform has accepted a job, later writes in the same run return null
	 * too, and so does every write inside process_job(), which is finishing that job.
	 *
	 * @return string|null
	 */
	public function background_dispatch_key(): ?string {
		if ( ! $this->is_background_capable() || isset( $this->action_data['loop'] ) ) {
			return null;
		}
		// One dispatching write per run: once the platform has accepted a job, deferred() has marked the row
		// awaiting, or complete_job() is finishing the job, later writes in the same run go out without the
		// headers. The row flag lives on this run's action data, which do_action() replaces for every run.
		if ( null !== $this->accepted_job_id || ! empty( $this->action_data['completed_awaiting'] ) ) {
			return null;
		}
		if ( ! automator_background_dispatch_allowed( $this->capability_slug() ) ) {
			return null;
		}

		return automator_background_idempotency_key( (int) ( $this->action_data['recipe_log_id'] ?? 0 ), (int) ( $this->action_data['ID'] ?? 0 ) );
	}

	/**
	 * The platform integration whose capabilities decide this action's dispatch: the one the caller's endpoint
	 * names (`v2/facebook` is `facebook`, which is Instagram's), or the integration code without a caller.
	 *
	 * @since 8.0.0
	 *
	 * @return string
	 */
	private function capability_slug(): string {
		$endpoint = is_object( $this->api ) && method_exists( $this->api, 'get_api_endpoint' ) ? basename( (string) $this->api->get_api_endpoint() ) : '';

		return '' !== $endpoint ? $endpoint : (string) $this->get_integration();
	}

	/**
	 * The caller saw the platform accept a job for this action's write.
	 *
	 * @param string $job_id The platform job id.
	 *
	 * @return void
	 */
	public function note_background_accepted( string $job_id ): void {
		$this->accepted_job_id = $job_id;
	}

	/**
	 * Whether the response is an accepted background job. When it is, the job is recorded against the run with
	 * the parsed fields and the remembered values, the row is marked awaiting, and process_action() returns true.
	 * Pass `$action_data` only to the write that may go to the background; earlier lookups and writes pass none and
	 * carry no headers, and after an accepted job the action returns.
	 *
	 * @param mixed $response What the api caller returned.
	 *
	 * @return bool
	 */
	protected function deferred( $response ): bool {
		if ( ! is_array( $response ) || 202 !== (int) ( $response['statusCode'] ?? 0 ) || '' === (string) ( $response['job_id'] ?? '' ) ) {
			return false;
		}

		automator_record_background_job(
			array(
				'job_id'          => (string) $response['job_id'],
				'idempotency_key' => (string) ( $response['idempotency_key'] ?? '' ),
				'connection_id'   => (string) ( $response['connection_id'] ?? '' ),
			) + $this->background_job_data( $this->remembered )
		);

		$this->accepted_job_id                   = null;
		$this->action_data['completed_awaiting'] = true;

		return true;
	}

	/**
	 * Finish this write as a site-originated background job: the platform answered that it cannot finish the write
	 * yet (Instagram's container is still processing), and the site retries it itself. Records the job with the
	 * parsed fields and `$remembered`, marks the row awaiting exactly as deferred() does, so the walker holds what
	 * depends on it, and schedules the first retry. The retries call retry_job() until it settles or `$max_attempts`
	 * is reached; the completion then runs process_job() and releases the waiting nodes. Declines inside a loop,
	 * whose rows never wait, and when the job cannot be recorded.
	 *
	 * @since 8.0.0
	 *
	 * @param array<string, mixed> $remembered    What retry_job() needs; must survive JSON.
	 * @param int                  $delay_seconds Seconds before each retry.
	 * @param int                  $max_attempts  Retries before the action fails.
	 *
	 * @return bool True when the job was recorded and process_action() should return true.
	 */
	protected function defer_to_site( array $remembered, int $delay_seconds, int $max_attempts ): bool {
		if ( isset( $this->action_data['loop'] ) ) {
			return false;
		}
		if ( ! automator_defer_background_job_to_site( $this->background_job_data( $remembered ), $delay_seconds, $max_attempts ) ) {
			return false;
		}

		$this->action_data['completed_awaiting'] = true;

		return true;
	}

	/**
	 * One synchronous re-attempt of a write this action deferred to the site. Return the outcome when the write
	 * settled (Background_Job_Result::from_site(), completed or failed), or null when it must wait again; values
	 * stored through remember() reach the next attempt. Every action that calls defer_to_site() implements it.
	 *
	 * @since 8.0.0
	 *
	 * @param array<string, mixed> $parsed     The parsed field values recorded at dispatch.
	 * @param array<string, mixed> $remembered The values remembered for the job.
	 *
	 * @return Background_Job_Result|null
	 *
	 * @throws \LogicException When the action defers to the site without implementing it.
	 */
	protected function retry_job( array $parsed, array $remembered ): ?Background_Job_Result {
		throw new \LogicException( esc_html( sprintf( '%s deferred a write to the site but does not implement retry_job().', static::class ) ) );
	}

	/**
	 * Run one retry of a site-originated job on this action. Binds the run the job belongs to and its accepted job,
	 * so nothing retry_job() writes goes out as another background job, and owns the shared caller for the duration,
	 * as complete_job() does.
	 *
	 * @since 8.0.0
	 *
	 * @param array<string, mixed> $action_data The action data, with recipe_log_id, action_log_id and background_job_id.
	 * @param array<string, mixed> $parsed      The parsed field values recorded at dispatch.
	 * @param array<string, mixed> $remembered  The job's remembered values, the framework's retry counters removed.
	 *
	 * @return array{result: Background_Job_Result|null, remembered: array<string, mixed>} The outcome, and what the
	 *                                                                                      action remembers now.
	 */
	public function retry_site_job( array $action_data, array $parsed, array $remembered ): array {
		$this->action_data     = $action_data;
		$this->maybe_parsed    = $parsed;
		$this->remembered      = $remembered;
		$this->accepted_job_id = (string) ( $action_data['background_job_id'] ?? '' );

		$can_bind = null !== $this->api && method_exists( $this->api, 'set_owner_action' );
		$previous = $can_bind ? $this->api->set_owner_action( $this ) : null;

		try {
			return array(
				'result'     => $this->retry_job( $parsed, $remembered ),
				'remembered' => $this->remembered,
			);
		} finally {
			$this->accepted_job_id = null;
			if ( $can_bind ) {
				$this->api->set_owner_action( $previous );
			}
		}
	}

	/**
	 * The run, row and values a background job for this run's write records; the job id comes from the platform or
	 * the site.
	 *
	 * @param array<string, mixed> $remembered The values the completion gets back.
	 *
	 * @return array<string, mixed> Background_Job constructor data without the job id and key.
	 */
	private function background_job_data( array $remembered ): array {
		return array(
			'recipe_log_id'    => (int) ( $this->action_data['recipe_log_id'] ?? 0 ),
			'recipe_id'        => (int) $this->recipe_id,
			'action_id'        => (int) ( $this->action_data['ID'] ?? 0 ),
			'action_log_id'    => (int) ( $this->action_data['action_log_id'] ?? 0 ),
			'user_id'          => (int) $this->user_id,
			'integration_code' => (string) $this->get_integration(),
			'parsed'           => is_array( $this->maybe_parsed ) ? $this->maybe_parsed : array(),
			'remembered'       => $remembered,
		);
	}

	/**
	 * Keep a value computed before the send for process_job() after it.
	 *
	 * @param string $key   The key.
	 * @param mixed  $value The value; must survive JSON.
	 *
	 * @return void
	 */
	protected function remember( string $key, $value ): void {
		$this->remembered[ $key ] = $value;
	}

	/**
	 * Read a remembered value.
	 *
	 * @param string $key           The key.
	 * @param mixed  $default_value Returned when nothing was remembered under the key.
	 *
	 * @return mixed
	 */
	protected function recall( string $key, $default_value = null ) {
		return $this->remembered[ $key ] ?? $default_value;
	}

	/**
	 * A job the platform accepted that process_action() returned without deferring is a system error:
	 * the request went out, nothing recorded it, and nothing will complete the row.
	 *
	 * @param mixed $result What process_action() returned.
	 *
	 * @return mixed
	 */
	protected function settle_result( $result ) {
		if ( null === $this->accepted_job_id ) {
			return $result;
		}

		$this->add_error(
			new Action_Error(
				Error_Code::SYSTEM_ERROR,
				sprintf( 'The platform accepted background job %s for %s, but the action returned without deferring; the job was not recorded.', $this->accepted_job_id, static::class )
			)
		);
		$this->accepted_job_id = null;

		return false;
	}

	/**
	 * Finish a background job on this action: bind the run context the walker bound at dispatch, restore the
	 * remembered values, run process_job() for a completed job or record the error for a failed one, and complete
	 * the row through the same path a synchronous run uses. The action owns the shared caller for the duration, as
	 * in do_action(), so a write process_job() makes is asked of this action, whose accepted job answers no key.
	 *
	 * @param int                  $user_id     The user the run executes as.
	 * @param array                $action_data The action data, with recipe_log_id, action_log_id and background_job_id.
	 * @param int                  $recipe_id   The recipe.
	 * @param array                $args        The run's trigger args from the snapshot.
	 * @param array<string, mixed> $parsed      The parsed field values recorded at dispatch.
	 * @param array<string, mixed> $remembered  The values remembered at dispatch.
	 * @param array<string, mixed> $result      The sync-shaped envelope; `error` present on failure.
	 *
	 * @return void
	 */
	public function complete_job( $user_id, array $action_data, $recipe_id, array $args, array $parsed, array $remembered, array $result ) {

		$this->errors            = array();
		$this->structured_errors = array();

		$this->user_id      = $user_id;
		$this->action_data  = $action_data;
		$this->recipe_id    = $recipe_id;
		$this->args         = $args;
		$this->maybe_parsed = $parsed;
		$this->remembered   = $remembered;
		// The job being finished is this run's one accepted job: nothing process_job() writes may dispatch another.
		$this->accepted_job_id = (string) $action_data['background_job_id'];

		$this->action_data['maybe_parsed'] = $parsed;

		// The caller asks its bound owner for the dispatch key. Bound here, this action is the one asked for every
		// write process_job() makes, and the accepted job above is what makes the answer null.
		$can_bind = null !== $this->api && method_exists( $this->api, 'set_owner_action' );
		$previous = $can_bind ? $this->api->set_owner_action( $this ) : null;

		try {
			if ( isset( $result['error'] ) && is_array( $result['error'] ) ) {
				$this->add_error( $this->background_failure( $result ) );
				$this->complete_from_result( false );
				return;
			}

			try {
				$outcome = $this->process_job( $result, $parsed, $this->action_data );
			} catch ( Action_Error_Exception $e ) {
				$outcome = false;
				$this->add_error( $e->to_action_error() );
			} catch ( \Throwable $e ) {
				$outcome = false;
				$this->add_log_error( $e->getMessage() );
			}

			$this->complete_from_result( $outcome );
		} finally {
			if ( $can_bind ) {
				$this->api->set_owner_action( $previous );
			}
		}
	}

	/**
	 * The error a failed job leaves on the action. A lost job is the plugin's own finding and keeps its own code
	 * and words. Any other failure is a platform answer: applied to the connection the job's write was sent with
	 * and told to the customer exactly as the same answer given at once is ({@see Background_Job_Failure}).
	 *
	 * @param array<string, mixed> $result The job's failed result: statusCode and error.
	 *
	 * @return Action_Error
	 */
	private function background_failure( array $result ): Action_Error {
		$error = (array) $result['error'];

		if ( 'BACKGROUND_JOB_LOST' === (string) ( $error['type'] ?? '' ) ) {
			return new Action_Error( Error_Code::BACKGROUND_JOB_LOST, (string) ( $error['description'] ?? $error['message'] ?? 'The background job was lost.' ) );
		}

		$message = ( new Background_Job_Failure() )->message( $result, (string) ( $this->action_data['background_connection_id'] ?? '' ), (string) $this->get_integration() );

		// The platform's own words are for the log, not the step: kept beside the customer's sentence.
		$context = array_filter(
			array(
				'platform_type'        => (string) ( $error['type'] ?? '' ),
				'platform_description' => (string) ( $error['description'] ?? '' ),
				'request_id'           => (string) ( $result['requestID'] ?? '' ),
			),
			'strlen'
		);

		return new Action_Error( $message->error_code(), $message->text(), $context );
	}

	/**
	 * Hard-cap on upload source size — 32 MB (33,554,432 bytes).
	 *
	 * Mirrors the platform's R3 SIZE_LIMIT_EXCEEDED ceiling in v1; no
	 * per-license override exists by design.
	 */
	const UPLOAD_SIZE_CAP_BYTES = 33554432;

	/**
	 * uap_action_log_meta key under which the JCS-canonical
	 * `{body, headers}` bundle is persisted for retry-safe replay.
	 */
	const UPLOAD_DISPATCH_REQUEST_META_KEY = '_automator_upload_dispatch_request';

	/**
	 * Source-token TTL — must match {@see Signed_Url::MAX_TTL_SECONDS}.
	 * The token's `exp` and the signed URL's `exp` both bound the
	 * 5-minute TTL window for replay.
	 */
	const UPLOAD_SOURCE_TTL_SECONDS = 300;

	/**
	 * Run the platform-mandated pre-flight (P1–P7) and produce the
	 * canonical `{body, headers}` bundle for an upload-from-url platform
	 * call.
	 *
	 * Per-action subclasses pass their resolved source / vendor body
	 * extras; the abstract handles signing (P1), size assertion (P2),
	 * MIME assertion (P3), conflict-mode hardcoding (P5), idempotency
	 * key (P6), and source token (P7).
	 *
	 * Body is JCS-canonicalized for R6 idempotency: same logical request
	 * ⇒ byte-identical body ⇒ same idempotency-key cache hit on the
	 * platform side. The canonical bytes are persisted to
	 * `uap_action_log_meta` under
	 * {@see UPLOAD_DISPATCH_REQUEST_META_KEY} so retries replay EXACT
	 * bytes — rebuilding via a fresh `prepare_upload_request` call
	 * would produce different signed-URL `exp` values and trip the
	 * platform's 422 VALIDATION_ERROR (Idempotency-Key collision).
	 *
	 * Persistence envelope shape:
	 *
	 *     { "body": <R5-canonical>, "headers": <map>, "exp": <unix> }
	 *
	 * `exp` lives at the envelope root, NOT inside `body` — the wire
	 * body must mirror the platform's R5 schema for upload-from-url
	 * exactly (no extra fields). The TTL gate is internal to the
	 * persistence layer; readers consult `envelope.exp` to decide
	 * replay-or-throw, and only return the R5-canonical `body` to
	 * callers.
	 *
	 * On retry / second invocation for the same `(recipe_log_id,
	 * action_id)`, the persisted bytes are returned verbatim. If the
	 * envelope's `exp` has already expired (> 5 min since first
	 * dispatch), this throws — the caller cannot recover by
	 * regenerating without violating R6, so the recipe must be re-run.
	 *
	 * @param array{
	 *   source: string,
	 *   vendor_body: array<string, mixed>,
	 *   create_shared_link?: bool
	 * } $request
	 *
	 * @return array{
	 *   body: array<string, mixed>,
	 *   headers: array<string, string>
	 * }
	 *
	 * @throws Exception When pre-flight fails locally (a local site, which
	 *                   the platform cannot fetch from; P2 size cap
	 *                   exceeded, source unresolvable, MIME unknown,
	 *                   persisted-bytes TTL expired). Action-side abort —
	 *                   never reaches platform.
	 */
	protected function prepare_upload_request( array $request ) {

		// L247 — the platform fetches every upload from a signed link to this
		// site (P1 below), and it never reaches a local site: refuse here, so
		// nothing is inspected or sent and no credit is spent.
		if ( ( new Local_Site_Status() )->is_local() ) {
			throw Platform_Failure_Message::local_site_upload()->to_exception();
		}

		$action_data = is_array( $this->action_data ) ? $this->action_data : array();
		$args        = is_array( $this->args ) ? $this->args : array();

		$recipe_log_id = (int) ( $args['recipe_log_id'] ?? 0 );
		$action_id     = isset( $action_data['ID'] ) ? (int) $action_data['ID'] : 0;
		$action_log_id = isset( $action_data['action_log_id'] ) ? (int) $action_data['action_log_id'] : 0;
		$user_id       = (int) ( $this->user_id ?? 0 );

		// 1. Read-side persistence — replay previously-built bytes when
		// a record exists and its envelope-level `exp` is still inside
		// the 5-min window. read_persisted_upload_request() throws on
		// expired records (cannot rebuild without regenerating `exp`,
		// which would break R6) and returns null on first dispatch.
		$persisted = $this->read_persisted_upload_request( $action_log_id );
		if ( null !== $persisted ) {
			return $persisted;
		}

		// 2. P1: resolve source → signed URL.
		$source = isset( $request['source'] ) ? (string) $request['source'] : '';
		if ( '' === $source ) {
			throw new Exception( esc_html__( 'Upload pre-flight: source is empty.', 'uncanny-automator' ) );
		}

		// A site-relative path (`/wp-content/uploads/...`) becomes absolute HERE,
		// ahead of the gate, so the gate, the signed URL and the inspection all
		// see the same file. Rebasing it after the gate rejected every such path:
		// the policy resolved it against the filesystem root.
		$source = $this->rebase_site_relative_source( $source );

		// P1 gate: validate the source against the SAME allow-list the serve
		// endpoint enforces, BEFORE minting a signed URL or running the
		// pre-flight. Without this, inspect_upload_source() would fetch/stat an
		// attacker-influenced source (FILE_PATH supports tokens) — an internal
		// URL (SSRF) or an out-of-uploads path — that the policy is built to
		// reject at serve time; gating here fails fast and keeps dispatch and
		// serve classifying identically.
		if ( ! ( new Allowed_Path_Policy() )->is_allowed( $source ) ) {
			throw new Exception(
				esc_html_x( "The file to upload must be in this site's Media Library or uploads folder, or at a public https:// URL. Change the file and run the step again.", 'Automator', 'uncanny-automator' )
			);
		}

		$signer = automator_file_serve_signer();
		if ( null === $signer ) {
			throw new Exception( esc_html__( 'Upload pre-flight: signed-URL signer unavailable.', 'uncanny-automator' ) );
		}

		$signed_url = $signer->mint( $source, $recipe_log_id, $action_id, self::UPLOAD_SOURCE_TTL_SECONDS );

		// 3. P2 + P3: size + MIME inspection.
		$inspection = $this->inspect_upload_source( $source );
		$size_bytes = (int) $inspection['size'];
		$mime_type  = (string) $inspection['mime'];
		$file_label = (string) $inspection['label'];

		if ( $size_bytes > self::UPLOAD_SIZE_CAP_BYTES ) {
			throw new Exception(
				esc_html(
					sprintf(
						/* translators: 1: file label, 2: actual size in MB */
						esc_html_x( 'Upload limit is 32 MB. File "%1$s" is %2$s MB. Reduce file size and try again.', 'Automator', 'uncanny-automator' ),
						$file_label,
						number_format( $size_bytes / 1048576, 1 )
					)
				)
			);
		}

		if ( '' === $mime_type ) {
			throw new Exception(
				esc_html(
					sprintf(
						/* translators: %s: file label */
						esc_html_x( 'Upload pre-flight: could not determine MIME type for "%s".', 'Upload pre-flight', 'uncanny-automator' ),
						$file_label
					)
				)
			);
		}

		// 4. P5: hardcode on_conflict='rename'. Field is required for
		// forward-compat; only "rename" is valid in v1. Overrides any
		// vendor_body attempt to set it.
		$vendor_body = is_array( $request['vendor_body'] ?? null ) ? $request['vendor_body'] : array();

		// 5. P6: mint Idempotency-Key (bare header name).
		$minter          = new Idempotency_Key_Minter();
		$idempotency_key = $minter->mint_for_upload( $recipe_log_id, $action_id );

		// 6. P7: mint X-Automator-Source-Token.
		$exp          = time() + self::UPLOAD_SOURCE_TTL_SECONDS;
		$token_minter = automator_source_url_token();
		if ( null === $token_minter ) {
			throw new Exception( esc_html__( 'Upload pre-flight: source-token minter unavailable.', 'uncanny-automator' ) );
		}
		$source_token = $token_minter->mint( $signed_url, $exp, $recipe_log_id, $action_id );

		// 7. Build canonical body. Vendor fields merged first; reserved
		// keys overwrite to enforce P5 + persistence semantics. The
		// signed-URL TTL (`exp`) is intentionally NOT in the body — it
		// rides on the persistence envelope only, so the wire body
		// matches the platform's R5 schema for upload-from-url
		// exactly. See class docblock for envelope shape.
		$create_shared_link = isset( $request['create_shared_link'] )
			? (bool) $request['create_shared_link']
			: true;

		$body = array_merge(
			$vendor_body,
			array(
				'source_url'         => $signed_url,
				'source_size_bytes'  => $size_bytes,
				'source_mime_type'   => $mime_type,
				'on_conflict'        => 'rename',
				'create_shared_link' => $create_shared_link,
			)
		);

		$headers = array(
			'Idempotency-Key'           => $idempotency_key,
			'X-Automator-Source-Token'  => $source_token,
		);

		// 8. Persist canonical bytes. JCS-sort the body's keys recursively
		//    so the stored array is byte-deterministic on retry: the
		//    api_caller's downstream wp_json_encode emits the same key
		//    order regardless of build-time insertion order, so the
		//    platform's R6 hash matches across retries.
		$canonical = automator_json_canonical();
		if ( null === $canonical ) {
			throw new Exception( esc_html__( 'Upload pre-flight: JSON canonicalizer unavailable.', 'uncanny-automator' ) );
		}
		$body = $canonical->sort_recursive( $body );

		$persisted_payload = wp_json_encode(
			array(
				'body'    => $body,
				'headers' => $headers,
				'exp'     => $exp,
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);

		if ( false !== $persisted_payload && $action_log_id > 0 ) {
			Database::get_execution_log_store()->add_action_meta(
				$user_id,
				$action_log_id,
				$action_id,
				self::UPLOAD_DISPATCH_REQUEST_META_KEY,
				(string) $persisted_payload
			);
		}

		return array(
			'body'    => $body,
			'headers' => $headers,
		);
	}

	/**
	 * Read the persisted dispatch-request envelope for retry-safe replay.
	 *
	 * The persistence envelope shape is:
	 *
	 *     { "body": <R5-canonical>, "headers": <map>, "exp": <unix> }
	 *
	 * `exp` lives at the envelope root — it controls TTL re-evaluation
	 * on read and is NEVER returned to callers. The returned bundle
	 * carries only R5-canonical body keys plus the headers map, so the
	 * wire body stays schema-clean.
	 *
	 * Returns null on first dispatch (no record) or when the JSON blob
	 * fails to decode. Throws when the envelope's `exp` has elapsed —
	 * the caller cannot recover by rebuilding without violating R6
	 * idempotency (a fresh `exp` would change the body-hash dedup
	 * tuple), so the recipe must be re-run.
	 *
	 * @param int $action_log_id uap_action_log primary key.
	 *
	 * @return array{body: array<string, mixed>, headers: array<string, string>}|null
	 *
	 * @throws Exception When the persisted envelope's TTL has expired.
	 */
	protected function read_persisted_upload_request( int $action_log_id ) {

		if ( 0 === $action_log_id ) {
			return null;
		}

		$raw = Database::get_execution_log_store()->get_action_meta_value(
			$action_log_id,
			self::UPLOAD_DISPATCH_REQUEST_META_KEY
		);

		if ( null === $raw || '' === $raw ) {
			return null;
		}

		$envelope = json_decode( $raw, true );
		if ( ! is_array( $envelope ) ) {
			return null;
		}

		$body    = is_array( $envelope['body'] ?? null ) ? $envelope['body'] : null;
		$headers = is_array( $envelope['headers'] ?? null ) ? $envelope['headers'] : null;
		$exp     = isset( $envelope['exp'] ) ? (int) $envelope['exp'] : 0;

		if ( null === $body || null === $headers ) {
			return null;
		}

		if ( $exp <= time() ) {
			throw new Exception(
				esc_html__( 'Async dispatch could not be recovered (5-min source-URL TTL expired). Re-run the recipe to retry.', 'uncanny-automator' )
			);
		}

		return array(
			'body'    => $body,
			'headers' => $headers,
		);
	}

	/**
	 * Turn a site-relative source (`/wp-content/uploads/2026/04/photo.jpg`)
	 * into the absolute path of the file it names.
	 *
	 * Anything else comes back untouched: a URL, a path that already exists as
	 * written, or a relative path with no file behind it. This only names the
	 * file — whether it may be served is still the allow-list's decision.
	 *
	 * @since 8.0.0
	 *
	 * @param string $source Source as the recipe author supplied it.
	 *
	 * @return string
	 */
	private function rebase_site_relative_source( $source ) {

		if ( '/' !== substr( $source, 0, 1 ) || file_exists( $source ) ) {
			return $source;
		}

		$rebased = ABSPATH . ltrim( $source, '/' );

		return file_exists( $rebased ) ? $rebased : $source;
	}

	/**
	 * Resolve the source's size + MIME + display label for P2 + P3.
	 *
	 * Three input shapes:
	 *  - Numeric attachment id → media-library row.
	 *  - Local path under `wp-content/uploads/` → filesystem inspection.
	 *  - External URL (`https?://`) → wp_remote_head, requires explicit
	 *    Content-Length + Content-Type.
	 *
	 * @param string $source Raw source from the recipe author.
	 *
	 * @return array{size: int, mime: string, label: string}
	 *
	 * @throws Exception When source can't be resolved or HEAD response
	 *                   is malformed.
	 */
	private function inspect_upload_source( $source ) {

		// Attachment id.
		if ( is_numeric( $source ) ) {
			$attachment_id = (int) $source;
			$path          = get_attached_file( $attachment_id );
			if ( false === $path || '' === (string) $path || ! file_exists( $path ) ) {
				throw new Exception(
					esc_html(
						sprintf(
							/* translators: %d: attachment ID */
							esc_html_x( 'Upload pre-flight: attachment %d could not be resolved on disk.', 'Upload pre-flight', 'uncanny-automator' ),
							$attachment_id
						)
					)
				);
			}
			$mime = (string) get_post_mime_type( $attachment_id );
			if ( '' === $mime ) {
				$mime = (string) self::detect_mime_from_file( $path );
			}
			return array(
				'size'  => (int) filesize( $path ),
				'mime'  => $mime,
				'label' => basename( $path ),
			);
		}

		// External URL.
		if ( 1 === preg_match( '#^https?://#i', $source ) ) {
			return self::inspect_remote_url( $source );
		}

		// Local path — already absolute: prepare_upload_request() rebased a
		// site-relative one before the gate.
		$abs = $source;
		if ( ! file_exists( $abs ) ) {
			throw new Exception(
				esc_html(
					sprintf(
						/* translators: %s: source path */
						esc_html_x( 'Upload pre-flight: source file not found at %s.', 'Upload pre-flight', 'uncanny-automator' ),
						$source
					)
				)
			);
		}

		return array(
			'size'  => (int) filesize( $abs ),
			'mime'  => (string) self::detect_mime_from_file( $abs ),
			'label' => basename( $abs ),
		);
	}

	/**
	 * HEAD an external URL and extract Content-Length + Content-Type.
	 *
	 * Strict: explicit Content-Length is required (chunked transfers
	 * with no length header are rejected — we can't enforce the 32 MB
	 * cap blind, and the platform would refuse them anyway). Content-Type
	 * is required for P3.
	 *
	 * @param string $url External URL.
	 *
	 * @return array{size: int, mime: string, label: string}
	 *
	 * @throws Exception When the HEAD response is missing required
	 *                   headers or returns a non-2xx status.
	 */
	private static function inspect_remote_url( $url ) {

		// Safe variant + no redirects: the P1 gate already ran is_allowed(),
		// but wp_safe_remote_head re-validates the target at fetch time
		// (closing the resolve-then-fetch DNS-rebind gap) and redirection=0
		// stops a public URL from 302-ing to an internal host post-gate.
		$response = wp_safe_remote_head(
			$url,
			array(
				'timeout'     => 5,
				'redirection' => 0,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new Exception(
				esc_html(
					sprintf(
						/* translators: %s: error message */
						esc_html_x( 'Upload pre-flight: HEAD request failed (%s).', 'Upload pre-flight', 'uncanny-automator' ),
						$response->get_error_message()
					)
				)
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			throw new Exception(
				esc_html(
					sprintf(
						/* translators: %d: HTTP status */
						esc_html_x( 'Upload pre-flight: HEAD returned HTTP %d.', 'Upload pre-flight', 'uncanny-automator' ),
						$status
					)
				)
			);
		}

		$length_header = wp_remote_retrieve_header( $response, 'content-length' );
		$type_header   = wp_remote_retrieve_header( $response, 'content-type' );

		if ( '' === (string) $length_header ) {
			throw new Exception(
				esc_html__( 'Upload pre-flight: external source must report a Content-Length header.', 'uncanny-automator' )
			);
		}

		if ( '' === (string) $type_header ) {
			throw new Exception(
				esc_html__( 'Upload pre-flight: external source must report a Content-Type header.', 'uncanny-automator' )
			);
		}

		// Strip charset / boundary suffix from Content-Type (e.g.
		// `image/png; charset=binary`).
		$mime = (string) $type_header;
		$semi = strpos( $mime, ';' );
		if ( false !== $semi ) {
			$mime = trim( substr( $mime, 0, $semi ) );
		}

		$parsed_path = wp_parse_url( $url, PHP_URL_PATH );
		$basename    = basename( ( is_string( $parsed_path ) && '' !== $parsed_path ) ? $parsed_path : $url );

		return array(
			'size'  => (int) $length_header,
			'mime'  => $mime,
			'label' => '' === $basename ? $url : $basename,
		);
	}

	/**
	 * Detect MIME for a local file via WordPress's check + PHP fallback.
	 *
	 * @param string $path Absolute filesystem path.
	 *
	 * @return string Detected MIME type or empty string.
	 */
	private static function detect_mime_from_file( $path ) {

		$check = wp_check_filetype( $path );
		if ( is_array( $check ) && ! empty( $check['type'] ) ) {
			return (string) $check['type'];
		}

		if ( function_exists( 'mime_content_type' ) ) {
			$detected = @mime_content_type( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( false !== $detected ) {
				return (string) $detected;
			}
		}

		return '';
	}
}
