<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform\Webhooks;

use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Infrastructure\Async_Jobs\Inbox_Drainer;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Clock_Skew_Monitor;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Hmac_Validator;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Reverification_Ledger;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Site_Credentials_Store;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Value_Objects\Hmac_Verification_Result;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Background_Job_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Platform_Inbox_Store;
use Uncanny_Automator\App\Infrastructure\Webhooks\Answers_Own_Refusal;
use Uncanny_Automator\App\Infrastructure\Webhooks\Durable_Storage_Exception;
use Uncanny_Automator\App\Infrastructure\Webhooks\Durable_Webhook_Handler;
use Uncanny_Automator\App\Infrastructure\Webhooks\Malformed_Webhook_Batch_Exception;
use Uncanny_Automator\App\Infrastructure\Webhooks\Webhook_Router;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Handles integration fan-out from the Automator Platform:
 * async-job completion callbacks + third-party webhook deliveries that
 * the platform received on `webhooks.automatorplugin.com` and is fanning
 * out to this site. Registered at
 * `automator/platform/v1/webhooks/events`.
 *
 * The callback receiver of callback delivery contract §4. Its answers:
 *
 *  - `200 {"success":true}` — the delivery verified and was processed, its
 *    vendor events durably stored in the inbox (a repeat of the same batch is
 *    accepted again without effect: per-event dedup).
 *  - `401 {"error":{"type":"SIGNATURE_REJECTED","reason":…}}` — the delivery
 *    did not verify ({@see refusal_for()}); the reason is one of
 *    `timestamp_skew`, `signature_mismatch`, `stale_fingerprint`. Never the
 *    router's `403`: this handler answers its own refusal
 *    ({@see Answers_Own_Refusal}).
 *  - `422 {"error":{"type":"PAYLOAD_REJECTED","description":…}}` — a malformed
 *    batch ({@see Malformed_Webhook_Batch_Exception}); the one final refusal.
 *  - `5xx` — the batch could not be stored ({@see Durable_Storage_Exception},
 *    or any other throw); retried by the platform.
 *
 * Every one of them carries `X-Automator-Receiver: <plugin version>`, added
 * in one place for this route ({@see Receiver_Header}).
 *
 * Two event types (so far):
 *
 * - `async_completion` — the platform finished a job this site dispatched
 *   in the background. Lands in the durable inbox under the run's bucket;
 *   the drainer fires `automator_platform_async_completed`, which
 *   Background_Job_Completion_Subscriber answers.
 *
 * - `webhook_delivery` — platform received a third-party webhook (Slack,
 *   GitHub, etc.) on `webhooks.automatorplugin.com`, matched it against
 *   this site's subscriptions, and is forwarding the payload. Fires
 *   `automator_{integration}_event_received` (deliberately NOT the legacy
 *   `_webhook_received` hook — see ingest_vendor_event() for the collision
 *   rationale). Event-id-based deduplication with a 24-hour
 *   window absorbs platform retries.
 *
 * All events are HMAC-validated. No fresh-install exception here: the
 * Integrations webhook endpoint only starts receiving traffic after
 * credentials have been exchanged, so an empty store means a
 * misconfigured or unauthorized caller.
 *
 * @since 8.0.0
 */
final class Integrations_Webhook_Controller implements Durable_Webhook_Handler, Answers_Own_Refusal {

	/**
	 * The REST namespace this receiver registers under.
	 */
	public const REST_NAMESPACE = 'automator/platform/v1';

	/**
	 * This receiver's integration ID — the last segment of its route.
	 */
	public const INTEGRATION_ID = 'events';

	/**
	 * The request header in which a sender may claim the signing-secret
	 * fingerprint it signed with — the name the plugin's platform alignment
	 * plan gives the credentials fingerprint. The platform sends none today, so
	 * `stale_fingerprint` is produced only for a sender that does.
	 */
	public const FINGERPRINT_HEADER = 'X-Automator-Credentials-Fingerprint';

	/**
	 * Event-id dedupe window (seconds). MVP Contract E (Layer 1) requires
	 * 24h to fully cover the platform's retry ladder; bumped from 10min.
	 */
	private const DEDUPE_TTL = 24 * HOUR_IN_SECONDS;

	/**
	 * @var Site_Credentials_Store
	 */
	private $store;

	/**
	 * @var Hmac_Validator
	 */
	private $validator;

	/**
	 * @var string|null
	 */
	private $validation_error = null;

	/**
	 * Durable ingress inbox — events land here BEFORE processing (mirror
	 * of the platform's callback_outbox), then the drain consumes them.
	 *
	 * @var Platform_Inbox_Store
	 */
	private $inbox;

	/**
	 * The background jobs this site dispatched; a completion drains under its job's run.
	 *
	 * @var Background_Job_Store
	 */
	private $jobs;

	/**
	 * Where the re-verification a refused delivery asks for is recorded.
	 *
	 * @var Reverification_Ledger
	 */
	private $ledger;

	/**
	 * Owner of the "this site's clock is off" record.
	 *
	 * @var Clock_Skew_Monitor
	 */
	private $clock;

	/**
	 * @param Site_Credentials_Store     $store     Per-site signing-secret store.
	 * @param Hmac_Validator             $validator HMAC verifier for the request signature.
	 * @param Platform_Inbox_Store|null  $inbox     Durable ingress inbox (defaults to the shared store).
	 * @param Background_Job_Store|null  $jobs      Background jobs (defaults to the shared store).
	 * @param Reverification_Ledger|null $ledger    Where the re-verification a refused delivery asks for is recorded
	 *                                              (defaults to the ledger over the same store).
	 * @param Clock_Skew_Monitor|null    $clock     Owner of the clock record.
	 */
	public function __construct(
		Site_Credentials_Store $store,
		Hmac_Validator $validator,
		?Platform_Inbox_Store $inbox = null,
		?Background_Job_Store $jobs = null,
		?Reverification_Ledger $ledger = null,
		?Clock_Skew_Monitor $clock = null
	) {

		$this->store     = $store;
		$this->validator = $validator;
		$this->inbox     = $inbox ?? Database::get_platform_inbox_store();
		$this->jobs      = $jobs ?? Database::get_background_job_store();
		$this->ledger    = $ledger ?? new Reverification_Ledger( $store );
		$this->clock     = $clock ?? new Clock_Skew_Monitor();
	}

	/**
	 * The REST route this receiver answers on — the route its answer header
	 * is matched on ({@see Receiver_Header}).
	 *
	 * @since 8.0.0
	 *
	 * @return string `/automator/platform/v1/webhooks/events`.
	 */
	public static function route(): string {

		return Webhook_Router::route( self::REST_NAMESPACE, self::INTEGRATION_ID );
	}

	/**
	 * Get integration id.
	 *
	 * @return string
	 */
	public function get_integration_id(): string {

		return self::INTEGRATION_ID;
	}
	/**
	 * Get rest namespace.
	 *
	 * @return string
	 */
	public function get_rest_namespace(): string {

		return self::REST_NAMESPACE;
	}
	/**
	 * Accepts get.
	 *
	 * @return bool
	 */
	public function accepts_get(): bool {

		return false;
	}
	/**
	 * Get validation error.
	 *
	 * @return string|null
	 */
	public function get_validation_error(): ?string {

		return $this->validation_error;
	}
	/**
	 * Whether the request verifies. Side-effect free: the router asks
	 * {@see refusal_for()} instead, which is the verdict, the answer and what a
	 * refusal starts in one call. Kept because the interface requires it.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return bool
	 */
	public function validate( WP_REST_Request $request ): bool {

		$result = $this->verify( $request );
		if ( ! $result->ok() ) {
			$this->validation_error = $result->rejection()->value();
			return false;
		}

		return true;
	}

	/**
	 * Verify a delivery, act on the verdict, and answer a refusal (callback
	 * delivery contract §4 and §6).
	 *
	 * Verified: what the last refusals started is settled — a delivery that
	 * verifies says the platform signs with a secret this site holds, so a
	 * re-verification a refused delivery asked for is withdrawn, and the clock
	 * record is cleared or kept the way an accepted outbound request clears or
	 * keeps it. Returns null: the router processes the delivery.
	 *
	 * Refused: answers `401 {"error":{"type":"SIGNATURE_REJECTED","reason":…}}`
	 * and starts what fixes it:
	 *  - `timestamp_skew` → the measured difference (this site's clock − the
	 *    platform's timestamp, signed) goes to the one clock record, which the
	 *    clock notice names. Nothing is re-verified: re-registering fixes no clock.
	 *  - `signature_mismatch`, `stale_fingerprint` → a re-verification of this
	 *    site's registration, through its owner — on an install that has
	 *    registered: one that never did gave the platform no callback URL, so
	 *    the refusal did not come from the platform and nothing is asked. Asked
	 *    once for a burst; a re-verification owed only for a refused delivery is
	 *    sent at most once an hour. An unverifiable request is all it takes, so
	 *    anyone who can post to a registered install can ask — which costs one
	 *    option write per burst and at most one register an hour, outside the
	 *    poster's request, until a delivery verifies again or the register
	 *    settles it.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response|null The 401 refusal, or null when the delivery verified.
	 */
	public function refusal_for( WP_REST_Request $request ): ?WP_REST_Response {

		$result = $this->verify( $request );

		if ( $result->ok() ) {
			$this->ledger->forget_refused_delivery();
			$this->clock->accepted( $result->clock_difference() );
			return null;
		}

		$reason = $result->rejection();

		if ( $reason->is_timestamp_skew() ) {
			$this->clock->refused( $result->clock_difference() );
		} else {
			$this->ledger->request_reverification_for_refused_delivery( $reason );
		}

		automator_log(
			sprintf(
				'Refused a platform delivery to %s: %s (batch_id=%s request_id=%s)',
				(string) $request->get_route(),
				$reason->value(),
				(string) $request->get_header( 'X-Automator-Batch-Id' ),
				(string) $request->get_header( 'X-Automator-Request-Id' )
			),
			'Integrations_Webhook'
		);

		return new WP_REST_Response(
			array(
				'error' => array(
					'type'   => 'SIGNATURE_REJECTED',
					'reason' => $reason->value(),
				),
			),
			401
		);
	}

	/**
	 * The validator's verdict on the request's signature headers.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return Hmac_Verification_Result
	 */
	private function verify( WP_REST_Request $request ): Hmac_Verification_Result {

		$claimed_fingerprint = $request->get_header( self::FINGERPRINT_HEADER );

		return $this->validator->verify(
			(string) $request->get_body(),
			(string) $request->get_header( 'X-Automator-Timestamp' ),
			(string) $request->get_header( 'X-Automator-Signature' ),
			null === $claimed_fingerprint ? null : (string) $claimed_fingerprint
		);
	}

	/**
	 * Process.
	 *
	 * A durable handler steers the router's answer by throwing (see
	 * {@see Webhook_Router::handle()}): a {@see Malformed_Webhook_Batch_Exception}
	 * → `422 PAYLOAD_REJECTED` (the platform must NOT retry a body it can never
	 * satisfy; it dead-letters it). A silent return here would answer 200,
	 * which the platform reads as "delivered" and files nothing for requeue —
	 * so a body that is not a valid callback has to throw, not return.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @throws Malformed_Webhook_Batch_Exception When the body is not a JSON
	 *         object, or is batch-shaped but missing a well-formed events array.
	 * @throws Durable_Storage_Exception         When a durable write hard-fails.
	 */
	public function process( WP_REST_Request $request ): void {

		$envelope = json_decode( (string) $request->get_body(), true );
		if ( ! is_array( $envelope ) ) {
			// Unparseable / non-object body — never a valid callback. 422, not
			// a silent 200 the platform mistakes for a successful delivery.
			throw new Malformed_Webhook_Batch_Exception(
				esc_html__( 'Webhook body is not a JSON object', 'uncanny-automator' )
			);
		}

		// Phase 3.3 batched-callback shape: {batch_id, site_id, events[]}.
		// HMAC scope + per-event dedup are unchanged; only the body envelope.
		// Iterate and reproject each event into the single-event shape so
		// the existing per-payload-type handlers stay untouched.
		//
		// `batch_id` is the batch discriminator: once present the envelope MUST
		// carry a well-formed `events` array. A missing or non-array `events` is
		// a malformed batch (422) — it must NOT fall through to the single-event
		// path, which would let a batch-shaped body dodge validation entirely.
		if ( isset( $envelope['batch_id'] ) ) {
			if ( ! isset( $envelope['events'] ) || ! is_array( $envelope['events'] ) ) {
				throw new Malformed_Webhook_Batch_Exception(
					esc_html( sprintf( 'Batch %s is missing a well-formed events array', (string) $envelope['batch_id'] ) )
				);
			}
			$this->process_batch( $envelope, $request );
			return;
		}

		$bucket = $this->process_single_event( $envelope, $request );
		if ( null !== $bucket ) {
			$this->schedule_drain( $bucket );
		}
	}

	/**
	 * Queue a bucket drain for `shutdown`, after the response has flushed.
	 *
	 * This handler is a {@see Durable_Webhook_Handler}, so `process()` runs
	 * INSIDE the request — the row has to be durable before the platform is
	 * told the callback was delivered. Draining is the opposite case: it runs
	 * recipes, takes arbitrarily long, and its work is already crash-safe
	 * because the row survives in the inbox and the recovery sweep re-fires
	 * anything left pending. So the durable half is synchronous and the slow
	 * half is not, which keeps response latency where it was before.
	 *
	 * Priority 100 places it after `wp_ob_end_flush_all` (priority 1) and
	 * before Trigger_Queue's late drain (priority 900) — the same window the
	 * router used, and the one where dispatched triggers still get processed.
	 *
	 * @param int $bucket Drain bucket (0 = shared vendor bucket).
	 *
	 * @return void
	 */
	private function schedule_drain( int $bucket ): void {

		add_action(
			'shutdown',
			static function () use ( $bucket ) {
				Dispatcher::action( Inbox_Drainer::DRAIN_HOOK, $bucket );
			},
			100
		);
	}

	/**
	 * Iterate a Phase 3.3 batched envelope and reproject each event into the
	 * pre-3.3 single-event envelope shape so {@see process_single_event()}
	 * dispatches exactly as it does for the older one-event-per-POST path.
	 *
	 * "2xx = durably stored" is a batch-level contract, so this is atomic:
	 *
	 * - The batch is validated in FULL before anything is stored. A structurally
	 *   malformed event (not an array, or missing the job_id / payload_type the
	 *   inbox is keyed and dispatched on) throws a
	 *   {@see Malformed_Webhook_Batch_Exception} — nothing is stored, and the
	 *   router answers 422 so the platform stops retrying a batch it can never
	 *   satisfy (it files it for requeue instead).
	 * - A hard durable-write failure in the storage pass throws a
	 *   {@see Durable_Storage_Exception} (router → 500). Storage is all-or-RETRY,
	 *   not all-or-nothing: the platform retries the whole delivery, where the
	 *   rows that already landed dedup on the inbox UNIQUE key and the
	 *   previously-failed row is stored.
	 * - Drains are scheduled only once every event has landed durably.
	 *
	 * @param array<string, mixed> $envelope
	 *
	 * @return void
	 *
	 * @throws Malformed_Webhook_Batch_Exception When any event is structurally invalid.
	 * @throws Durable_Storage_Exception         When a durable write hard-fails.
	 */
	private function process_batch( array $envelope, WP_REST_Request $request ): void {

		$batch_id = (string) $envelope['batch_id'];

		automator_log(
			sprintf(
				'IN %s batch_id=%s site_id=%s events=%d request_id=%s',
				(string) $request->get_route(),
				$batch_id,
				(string) ( $envelope['site_id'] ?? '' ),
				count( $envelope['events'] ),
				(string) ( $request->get_header( 'X-Automator-Request-Id' ) ?? '' )
			),
			'Integrations_Webhook'
		);

		// Pass 1 — validate the whole batch. Throws before any storage.
		$this->assert_events_well_formed( $envelope['events'], $batch_id );

		// Pass 2 — store/dispatch each event. A durable-write failure throws
		// out of process_single_event(), signalling a whole-batch retry.
		$drain_buckets = array();
		foreach ( $envelope['events'] as $event ) {

			$job_id      = (string) $event['job_id'];
			$integration = (string) ( $event['integration'] ?? '' );
			$payload     = isset( $event['payload'] ) && is_array( $event['payload'] ) ? $event['payload'] : array();

			// Move event-level job_id / integration into the inner data
			// block so dedup + dispatch read the same keys as the
			// pre-3.3 single-event path.
			$payload['job_id']      = $job_id;
			$payload['integration'] = '' !== (string) ( $payload['integration'] ?? '' )
				? (string) $payload['integration']
				: $integration;

			$bucket = $this->process_single_event(
				array(
					'job_id'       => $job_id,
					'integration'  => $integration,
					'payload_type' => (string) $event['payload_type'],
					'data'         => $payload,
					// Platform's per-event delivery clock, carried through
					// the reprojection because it is the drain's ordering
					// key — see resolve_ordering_key().
					'committed_at' => (string) ( $event['committed_at'] ?? '' ),
				),
				$request
			);
			if ( null !== $bucket ) {
				$drain_buckets[ $bucket ] = true;
			}
		}

		// Pass 3 — one drain per bucket AFTER the whole batch landed durably,
		// so a mid-drain fatal loses nothing (the recovery sweep re-fires).
		foreach ( array_keys( $drain_buckets ) as $bucket ) {
			$this->schedule_drain( (int) $bucket );
		}
	}

	/**
	 * Validate every event in a batch BEFORE any is stored. A batch is a unit:
	 * an event that is not an array, or that omits the `job_id` / `payload_type`
	 * the inbox is keyed and dispatched on, makes the whole batch unprocessable.
	 * Throwing here — ahead of the storage pass — is what guarantees "reject →
	 * store nothing".
	 *
	 * Note this is STRUCTURAL validation only. A well-formed event carrying an
	 * unknown `payload_type` is NOT malformed: {@see process_single_event()}
	 * routes it to the forward-compat observability hook so a newer platform
	 * shape never 422s an otherwise-valid batch.
	 *
	 * @param array<int, mixed> $events
	 * @param string            $batch_id
	 *
	 * @return void
	 *
	 * @throws Malformed_Webhook_Batch_Exception
	 */
	private function assert_events_well_formed( array $events, string $batch_id ): void {

		foreach ( $events as $event ) {

			if ( ! is_array( $event ) ) {
				throw new Malformed_Webhook_Batch_Exception(
					esc_html( sprintf( 'Batch %s contains a non-array event', $batch_id ) )
				);
			}

			$job_id       = (string) ( $event['job_id'] ?? '' );
			$payload_type = (string) ( $event['payload_type'] ?? '' );
			if ( '' === $job_id || '' === $payload_type ) {
				throw new Malformed_Webhook_Batch_Exception(
					esc_html( sprintf( 'Batch %s contains an event missing job_id or payload_type', $batch_id ) )
				);
			}
		}
	}

	/**
	 * Route one envelope by payload type. Both kinds land in the durable
	 * inbox and return their drain bucket (the caller fires the drain).
	 * Shared by the pre-3.3 single-event path and the 3.3 batched path.
	 *
	 * @param array<string, mixed> $envelope
	 *
	 * @return int|null Drain bucket the event landed in (0 = shared vendor
	 *                  bucket), or null when nothing to drain (no job id,
	 *                  dedup hit, missing integration, unknown type).
	 */
	private function process_single_event( array $envelope, WP_REST_Request $request ): ?int {

		// MVP Contract I: discriminator is the top-level `payload_type`.
		// Two branches: `async_completion` (D.5) and `vendor_event` (E.6).
		// Legacy fallbacks retained for the in-flight platform shapes
		// until Contract I fully lands platform-side:
		//   - top-level `event_type` (Dropbox webhook fan-out today)
		//   - nested `data.event_type` (older spec note)
		$payload_type = (string) ( $envelope['payload_type'] ?? '' );
		$data         = isset( $envelope['data'] ) && is_array( $envelope['data'] ) ? $envelope['data'] : array();

		if ( '' === $payload_type ) {
			$payload_type = (string) ( $envelope['event_type'] ?? $data['event_type'] ?? '' );
		}

		switch ( $payload_type ) {
			case 'async_completion':
			case 'async_job_completed': // legacy alias
				// The single-event envelope carries the fields at the top level; the batched shape nests them under data.
				return $this->ingest_async_completion( ! empty( $data ) ? $data : $envelope, $envelope );

			case 'vendor_event':
			case 'webhook_delivery': // legacy alias
			case 'webhook':           // platform Dropbox-fanout shape
				// Pass the full envelope so the ingest can fall back to
				// top-level keys (event_id, integration) when the nested
				// `data` block omits them. Dropbox webhook pings carry
				// event_type / integration / connection_id / event_id at the
				// top level, with the Contract-J block nested under `data`
				// (integration, external_account_id, received_at, events).
				return $this->ingest_vendor_event( $envelope );

			default:
				// Forward-compat: unknown payload_type → observability hook
				// only, no inbox row, no dispatch to integration handlers.
				Dispatcher::action( 'automator_platform_unknown_event', $payload_type, $data );
				return null;
		}
	}

	/**
	 * Land an async completion in the durable inbox under its run's bucket; the drainer fires
	 * `automator_platform_async_completed` from the row. A job this site never recorded drains under the shared
	 * bucket, where the subscriber logs and ignores it. The inbox's UNIQUE (job_id, payload_type) is the dedupe.
	 *
	 * @param array<string, mixed> $data     The completion: job_id, status, result, error, credits, request_id.
	 * @param array<string, mixed> $envelope The full envelope, for the ordering key.
	 *
	 * @return int|null The drain bucket, or null when the event carries no job id.
	 *
	 * @throws Durable_Storage_Exception When the durable write hard-fails.
	 */
	private function ingest_async_completion( array $data, array $envelope ): ?int {

		$job_id = (string) ( $data['job_id'] ?? '' );
		if ( '' === $job_id ) {
			return null;
		}

		$job    = $this->jobs->find_by_job_id( $job_id );
		$bucket = null !== $job ? $job->recipe_log_id() : 0;

		$landed = $this->inbox->insert_event(
			'async_completion',
			$bucket,
			$job_id,
			array(
				'job_id'     => $job_id,
				'status'     => (string) ( $data['status'] ?? '' ),
				'result'     => isset( $data['result'] ) && is_array( $data['result'] ) ? $data['result'] : array(),
				'error'      => isset( $data['error'] ) && is_array( $data['error'] ) ? $data['error'] : array(),
				'credits'    => isset( $data['credits'] ) && is_array( $data['credits'] ) ? $data['credits'] : array(),
				'request_id' => (string) ( $data['request_id'] ?? '' ),
			),
			$this->resolve_ordering_key( $envelope )
		);

		if ( null === $landed ) {
			automator_log( sprintf( 'Async completion durable inbox write failed, signalling retry: %s', $job_id ), 'Integrations_Webhook', false );
			throw new Durable_Storage_Exception( esc_html( sprintf( 'Durable inbox write failed for job %s', $job_id ) ) );
		}

		return $bucket;
	}

	/**
	 * MVP Contract I + E.6/E.7 vendor_event ingest.
	 *
	 * Lands the event in the durable inbox; {@see Inbox_Drainer} then fires
	 * `automator_{integration}_event_received` with the raw envelope so
	 * per-trigger classes (E.8) can pattern-match against recipe config.
	 * Deliberately does NOT fire the legacy
	 * `automator_{integration}_webhook_received` hook — see the inline
	 * comment below for the collision rationale.
	 *
	 * Accepts the full envelope (NOT just the nested `data` block) so
	 * top-level keys are reachable when the nested block is sparse —
	 * e.g. Dropbox webhook fan-outs today carry `integration` +
	 * `connection_id` at the top level and only `external_account_id`
	 * inside `data`.
	 *
	 * Dedup key resolution (Contract E): `data.event_id` → top-level
	 * `event_id` → top-level `job_id` → body hash. Dropbox-style
	 * notifications without a stable upstream event id fall through
	 * to job_id, which the platform's outgoing delivery job mints
	 * uniquely. Bailing on missing event_id (the prior behavior)
	 * silently dropped every Dropbox webhook → triggers never fired.
	 *
	 * @param array<string, mixed> $envelope
	 *
	 * @return int|null 0 (the shared vendor drain bucket) when the event
	 *                  landed in the inbox, or null on dedup hit / missing
	 *                  integration.
	 */
	private function ingest_vendor_event( array $envelope ): ?int {

		$data = isset( $envelope['data'] ) && is_array( $envelope['data'] )
			? $envelope['data']
			: array();

		$integration = strtolower( (string) ( $data['integration'] ?? $envelope['integration'] ?? '' ) );
		if ( '' === $integration ) {
			return null;
		}

		// Resolve a stable dedup key. Cascades through the most-
		// specific candidates first; falls back to a hash of the full
		// envelope so retried-identical payloads still dedupe even
		// when no id is supplied.
		$event_id = (string) ( $data['event_id'] ?? $envelope['event_id'] ?? '' );
		if ( '' === $event_id ) {
			$event_id = (string) ( $envelope['job_id'] ?? '' );
		}
		if ( '' === $event_id ) {
			$event_id = 'hash_' . substr( md5( wp_json_encode( $envelope ) ), 0, 16 );
		}

		// Transient-based dedupe (24h TTL — matches Contract E retry
		// window) so platform retries don't double-fire the integration's
		// downstream hook. READ here; the WRITE happens only after the
		// durable insert succeeds — see below.
		$seen_key = 'uap_webhook_evt_' . md5( $event_id );
		if ( get_transient( $seen_key ) ) {
			return null;
		}

		$event_name = (string) ( $data['event_name'] ?? '' );

		// Drain-ordering key, resolved from the platform's own delivery clock.
		// See resolve_ordering_key() for why it reads `committed_at` and
		// deliberately not the deeper, Dropbox-only `received_at`.
		$platform_received = $this->resolve_ordering_key( $envelope );

		// Vendor events land in the shared 0 bucket (no recipe binding
		// pre-trigger) with the RESOLVED dedup key as the row's job_id, so
		// the inbox UNIQUE (job_id, payload_type) preserves the event-id
		// cascade semantics. The drain fires
		// `automator_{integration}_event_received` (per MVP E.7) with the
		// exact args the pre-inbox receiver dispatched; per-integration
		// fanout listeners (e.g. Dropbox_Event_Fanout) translate the
		// envelope into each integration's per-trigger shape.
		//
		// The legacy `automator_{integration}_webhook_received` hook is
		// still deliberately NOT fired — it is owned by the legacy
		// App_Webhooks REST receiver (ActiveCampaign, Asana, GitHub,
		// Helpscout, Telegram, Facebook Lead Ads) with integration-specific
		// arg shapes (`$payload, $event_name, ...`); firing it here would
		// collide with those existing consumers.
		$landed = $this->inbox->insert_event(
			'vendor_event',
			0,
			$event_id,
			array(
				'integration' => $integration,
				'data'        => $data,
				'event_name'  => $event_name,
			),
			$platform_received
		);

		// null = the durable write hard-failed (store already logged the DB
		// error — the classic cause is a uap_platform_inbox missing because a
		// schema upgrade was skipped). The event is NOT safely stored, so the
		// request MUST NOT answer 2xx; throw so process() surfaces a 5xx and the
		// platform retries the whole delivery. false (redelivery duplicate)
		// still means the row is present.
		if ( null === $landed ) {
			automator_log(
				sprintf( 'Vendor event durable inbox write failed, signalling retry: %s / %s', $integration, $event_id ),
				'Integrations_Webhook',
				false
			);
			// Thrown BEFORE any set_transient(): a failed durable write must
			// leave the event re-deliverable. Marking it seen here would make
			// the platform's whole 24h retry ladder a no-op and lose the event
			// permanently — the dedup transient would suppress retries of
			// something that was never stored.
			throw new Durable_Storage_Exception(
				esc_html( sprintf( 'Durable inbox write failed for %s / %s', $integration, $event_id ) )
			);
		}

		// Durable now (true = inserted, false = the UNIQUE key already held
		// it), so it is safe to remember. The inbox's UNIQUE
		// (job_id, payload_type) is the authoritative dedup layer; this
		// transient is only the cheap front door that avoids a DB round trip
		// on the common retry.
		set_transient( $seen_key, 1, self::DEDUPE_TTL );

		return 0;
	}

	/**
	 * Resolve the drain-ordering key stamped onto the inbox row.
	 *
	 * Reads the platform's per-event `committed_at` (ISO8601) and converts it
	 * to a UTC MySQL DATETIME. The platform stamps this on EVERY batched
	 * event — both payload types, every integration — and builds each batch
	 * with `->orderBy('committed_at')`. Ordering the inbox by the same field
	 * therefore makes the plugin's drain order identical to the platform's
	 * delivery order, which is the strongest ordering the two sides can agree
	 * on: a batch swept and delivered late still drains in commit order
	 * rather than plugin-receipt order.
	 *
	 * Deliberately NOT the `received_at` nested deeper in the payload:
	 *
	 * - It is Dropbox-only. The cursor-fetch job is the single place platform
	 *   -wide that mints it, so every other integration would fall back to
	 *   insert time while Dropbox used a fetch clock. `claim_pending()` sorts
	 *   the shared vendor bucket ACROSS integrations, so a mixed scale can
	 *   invert two rows that carry different key sources.
	 * - It is per-fetch, not per-event: one cursor fetch stamps one
	 *   `received_at` across every entry it returns, so it is no closer to
	 *   true vendor event time than the commit clock is.
	 *
	 * Ordering WITHIN a row never depends on this — a row holds one delivery
	 * and the fanout walks its events array in order. This key only orders
	 * rows (separate deliveries) relative to each other.
	 *
	 * @param array<string, mixed> $envelope Reprojected single-event envelope.
	 *
	 * @return string|null UTC `Y-m-d H:i:s`, or null when the field is absent
	 *                     or unparseable — the store then resolves the row to
	 *                     insert-time UTC so the column is never null.
	 */
	private function resolve_ordering_key( array $envelope ): ?string {

		$committed_at = (string) ( $envelope['committed_at'] ?? '' );
		if ( '' === $committed_at ) {
			return null;
		}

		$timestamp = strtotime( $committed_at );

		return false !== $timestamp ? gmdate( 'Y-m-d H:i:s', $timestamp ) : null;
	}
}
