<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Webhooks;

use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Webhooks\Webhook_Handler_Interface;
use Uncanny_Automator\App\Recipe_Runner\Services\Error_Code;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Action_Error;

/**
 * Class Webhook_Router
 *
 * Central webhook router that registers REST routes and dispatches
 * incoming requests to the appropriate handler.
 *
 * Each handler gets one route, `/{namespace}/webhooks/{integration_id}`
 * ({@see route()}). How a request that fails verification is answered depends
 * on the handler:
 *
 *  - by default, the route's `permission_callback` runs `validate()` and, on
 *    false, refuses with `403 webhook_validation_failed` — WordPress renders
 *    the `WP_Error` as `{code, message, data}`. Third-party vendor handlers get
 *    exactly that;
 *  - a handler that implements {@see Answers_Own_Refusal} owns its refusal:
 *    its route lets every request through to the callback, which asks
 *    `refusal_for()` first and answers that response as-is (the Automator
 *    Platform receiver's `401 SIGNATURE_REJECTED`).
 *
 * A request that verified is processed as {@see handle()} describes: inline
 * for a {@see Durable_Webhook_Handler} (a throw steers the status), deferred to
 * `shutdown` for every other handler.
 *
 * @since 7.0.0
 * @package Uncanny_Automator\App\Infrastructure\Webhooks
 */
class Webhook_Router {

	/**
	 * What every handler's route is under, inside its REST namespace.
	 */
	private const ROUTE_BASE = '/webhooks/';

	/**
	 * Registered webhook handlers keyed by integration ID.
	 *
	 * @var Webhook_Handler_Interface[]
	 */
	private $handlers = array();

	/**
	 * The full REST route a handler is registered at — the one owner of the
	 * `/{namespace}/webhooks/{integration_id}` shape, for anything that has to
	 * recognise a handler's requests (the platform receiver's answer header).
	 *
	 * @since 8.0.0
	 *
	 * @param string $rest_namespace REST namespace, e.g. `automator/platform/v1`.
	 * @param string $integration_id The handler's integration ID.
	 *
	 * @return string E.g. `/automator/platform/v1/webhooks/events`.
	 */
	public static function route( string $rest_namespace, string $integration_id ): string {
		return '/' . trim( $rest_namespace, '/' ) . self::ROUTE_BASE . $integration_id;
	}

	/**
	 * Register a webhook handler.
	 *
	 * @param Webhook_Handler_Interface $handler The handler to register.
	 *
	 * @return void
	 */
	public function register( Webhook_Handler_Interface $handler ): void {
		$this->handlers[ $handler->get_integration_id() ] = $handler;
	}

	/**
	 * Register all REST routes. Called on rest_api_init.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		foreach ( $this->handlers as $id => $handler ) {
			$methods = 'POST';
			if ( $handler->accepts_get() ) {
				$methods = 'GET, POST';
			}

			// Per-handler REST namespace — legacy handlers return `uap/v2`
			// (explicitly or via Namespace_Default_Trait); platform controllers
			// return `automator/platform/v1`. Defensive fallback handles any
			// edge case where an older implementer somehow slips through
			// autoload before the interface update lands.
			$namespace = method_exists( $handler, 'get_rest_namespace' )
				? $handler->get_rest_namespace()
				: 'uap/v2';

			register_rest_route(
				$namespace,
				self::ROUTE_BASE . $id,
				array(
					'methods'             => $methods,
					'callback'            => function ( $request ) use ( $handler ) {
						return $this->handle( $handler, $request );
					},
					'permission_callback' => $this->permission_callback( $handler ),
				)
			);
		}
	}

	/**
	 * The route's permission check.
	 *
	 * A handler that answers its own refusal is let through: its refusal is not
	 * WordPress's to give, and {@see handle()} asks the handler for it before
	 * anything is processed. Every other handler is refused here, on a false
	 * `validate()`, with `403 webhook_validation_failed` — unchanged for
	 * third-party vendors.
	 *
	 * @param Webhook_Handler_Interface $handler The route's handler.
	 *
	 * @return callable
	 */
	private function permission_callback( Webhook_Handler_Interface $handler ): callable {

		if ( $handler instanceof Answers_Own_Refusal ) {
			return '__return_true';
		}

		return static function ( $request ) use ( $handler ) {
			if ( $handler->validate( $request ) ) {
				return true;
			}
			$error = $handler->get_validation_error();
			return new \WP_Error(
				'webhook_validation_failed',
				$error ?? 'Webhook validation failed',
				array( 'status' => 403 )
			);
		};
	}

	/**
	 * Dispatch to handler, defer processing to shutdown.
	 *
	 * Current App_Webhooks uses register_shutdown_function() to defer heavy processing.
	 * Third-party services (Slack, Discord) have strict 3-5 second timeout windows.
	 *
	 * A handler that answers its own refusal ({@see Answers_Own_Refusal}) is
	 * asked first; a refusal is returned as the handler built it, and the
	 * request is not processed.
	 *
	 * @param Webhook_Handler_Interface $handler The handler to dispatch to.
	 * @param \WP_REST_Request          $request The incoming request.
	 *
	 * @return \WP_REST_Response
	 */
	private function handle( Webhook_Handler_Interface $handler, \WP_REST_Request $request ): \WP_REST_Response {

		if ( $handler instanceof Answers_Own_Refusal ) {
			$refusal = $handler->refusal_for( $request );
			if ( null !== $refusal ) {
				return $refusal;
			}
		}

		// Durable handlers (the Automator Platform callback path) run inside
		// the request. The platform reads 2xx as proof of delivery and flushes
		// its outbox row without retrying, so answering before the durable
		// write turns any fatal, OOM or failed INSERT after the response into
		// permanent event loss — with both sides believing it was handled.
		//
		// Such handlers keep this section short by deferring their own
		// post-storage work (draining, recipe execution) to `shutdown`, so
		// response latency is effectively unchanged. A throw here becomes a
		// 5xx, which is exactly what should happen: it is the signal that
		// tells the platform to retry.
		if ( $handler instanceof Durable_Webhook_Handler ) {
			try {
				$handler->process( $request );
			} catch ( \Throwable $e ) {
				// A durable handler steers the answer by throwing: a
				// Provides_Http_Status exception names its own status (422 for
				// a malformed batch the platform must NOT retry), and a
				// Provides_Http_Body one its own body (the platform's
				// PAYLOAD_REJECTED). Any other Throwable defaults to 500 — the
				// safe assumption for an unclassified failure is that the
				// platform should retry rather than lose the event to a
				// premature 2xx.
				$status = $e instanceof Provides_Http_Status ? $e->get_http_status() : 500;

				$this->log_shutdown_failure( $handler, $request, $e );
				Dispatcher::action( 'automator_webhook_process_error', $e, $handler, $request );

				return new \WP_REST_Response( $this->failure_body( $e, $status ), $status );
			}

			return new \WP_REST_Response( array( 'success' => true ), 200 );
		}

		// Defer to the WP `shutdown` action — runs after the 200
		// response is flushed (wp_ob_end_flush_all is priority 1) AND
		// before Trigger_Queue's late drain (priority 900). This
		// ordering matters: the prior `register_shutdown_function`
		// implementation ran AFTER WP's shutdown action completed,
		// which meant any actions our handler dispatched got
		// `Trigger_Queue::enqueue`d but never `process_queue`d — every
		// inbound webhook was a silent no-op for triggers.
		// SHUTDOWN_PRIORITY_LATE on Trigger_Queue is documented as
		// "catches triggers that fire during shutdown" — that's
		// exactly what we want.
		//
		// Caveat: the 200-early pattern means an exception during
		// processing is invisible to the caller (Meta/WhatsApp/Instagram
		// won't retry). We can't change that without breaking external
		// contracts. What we can do is make the failure *visible*
		// downstream — write the error to uap_error_log so admin UIs
		// can surface "webhook processing failed" instead of the user
		// seeing a recipe stuck indefinitely.
		add_action(
			'shutdown',
			function () use ( $handler, $request ) {
				try {
					$handler->process( $request );
				} catch ( \Throwable $e ) {
					$this->log_shutdown_failure( $handler, $request, $e );
					Dispatcher::action( 'automator_webhook_process_error', $e, $handler, $request );
				}
			},
			100
		);

		return new \WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * The answer a handler that answers its own refusal gives outside its REST
	 * route: the Automator Platform receiver at its second door, the site's own
	 * address ({@see \Uncanny_Automator\App\Infrastructure\Automator_Platform\Query_Door}).
	 * Exactly its route's answer: that route's permission check lets every
	 * request through, and {@see handle()} asks `refusal_for()` before anything
	 * is processed.
	 *
	 * The one public way to dispatch a request, and only for such a handler.
	 * Every other handler is verified by its route's permission check
	 * (`validate()`, {@see permission_callback()}), which does not run here, so
	 * the parameter's type refuses it: a TypeError, never an unvalidated
	 * dispatch.
	 *
	 * @since 8.0.4
	 *
	 * @param Answers_Own_Refusal $handler The handler, which verifies the request itself.
	 * @param \WP_REST_Request    $request The incoming request.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_answering_own_refusal( Answers_Own_Refusal $handler, \WP_REST_Request $request ): \WP_REST_Response {

		return $this->handle( $handler, $request );
	}

	/**
	 * The body a durable handler's failure is answered with: the exception's
	 * own ({@see Provides_Http_Body}), or the router's.
	 *
	 * @param \Throwable $error  What escaped `process()`.
	 * @param int        $status The status the answer carries.
	 *
	 * @return array<string, mixed>
	 */
	private function failure_body( \Throwable $error, int $status ): array {

		if ( $error instanceof Provides_Http_Body ) {
			return $error->get_http_body();
		}

		return array(
			'success' => false,
			'message' => $status >= 500
				? 'Webhook processing failed; retry expected.'
				: 'Webhook batch rejected; not retryable.',
		);
	}

	/**
	 * Record a shutdown-handler failure to both the file log and uap_error_log.
	 *
	 * Self-contained: the file-log breadcrumb is written FIRST, before any DB
	 * work, so a subsequent failure inside store_system_error() can never lose
	 * the original error. Admin UIs that query uap_error_log can surface
	 * router-level failures under a "System / Webhooks" bucket by filtering
	 * on item_type='system' + source=webhook_shutdown. recipe_log_id is 0
	 * because the handler would have resolved one downstream; at this point
	 * we only know which webhook integration was being routed.
	 *
	 * Scope is "process() threw a Throwable." True fatals (OOM, timeout,
	 * uncatchable E_ERROR) never enter the outer catch block and are NOT
	 * covered here — capturing those needs a separate shutdown-wide
	 * error_get_last() mechanism registered at router init time.
	 *
	 * @param Webhook_Handler_Interface $handler The handler whose process() threw.
	 * @param \WP_REST_Request          $request The original request.
	 * @param \Throwable                $error   The thrown exception.
	 *
	 * @return void
	 */
	private function log_shutdown_failure( Webhook_Handler_Interface $handler, \WP_REST_Request $request, \Throwable $error ): void {

		// File log first — single source of truth if the DB write fails.
		automator_log( 'Webhook processing error: ' . $error->getMessage(), 'Webhook_Router' );

		try {
			Database::get_action_error_store()->store_system_error(
				0,
				new Action_Error(
					Error_Code::EXECUTION_FAILED,
					$error->getMessage(),
					array(
						'integration_id' => $handler->get_integration_id(),
						'route'          => $request->get_route(),
						'method'         => $request->get_method(),
						'exception'      => get_class( $error ),
						'source'         => 'webhook_shutdown',
					)
				)
			);
		} catch ( \Throwable $store_error ) {
			// DB write failed (connection closed, table missing, etc.).
			// The original error is already in the file log above — this
			// breadcrumb just explains why it isn't in uap_error_log too.
			automator_log( 'Failed to persist webhook failure to error_log: ' . $store_error->getMessage(), 'Webhook_Router' );
		}
	}

	/**
	 * Get all registered handlers.
	 *
	 * @return Webhook_Handler_Interface[]
	 */
	public function get_handlers(): array {
		return $this->handlers;
	}

	/**
	 * Check if a handler is registered for the given integration ID.
	 *
	 * @param string $integration_id The integration ID to check.
	 *
	 * @return bool
	 */
	public function has_handler( string $integration_id ): bool {
		return isset( $this->handlers[ $integration_id ] );
	}
}
