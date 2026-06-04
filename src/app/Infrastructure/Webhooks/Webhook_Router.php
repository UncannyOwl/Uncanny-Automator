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
 * @since 7.0.0
 * @package Uncanny_Automator\App\Infrastructure\Webhooks
 */
class Webhook_Router {

	/**
	 * Registered webhook handlers keyed by integration ID.
	 *
	 * @var Webhook_Handler_Interface[]
	 */
	private $handlers = array();

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

			register_rest_route(
				'uap/v2',
				"/webhooks/{$id}",
				array(
					'methods'             => $methods,
					'callback'            => function ( $request ) use ( $handler ) {
						return $this->handle( $handler, $request );
					},
					'permission_callback' => function ( $request ) use ( $handler ) {
						if ( $handler->validate( $request ) ) {
							return true;
						}
						$error = $handler->get_validation_error();
						return new \WP_Error(
							'webhook_validation_failed',
							$error ?? 'Webhook validation failed',
							array( 'status' => 403 )
						);
					},
				)
			);
		}
	}

	/**
	 * Dispatch to handler, defer processing to shutdown.
	 *
	 * Current App_Webhooks uses register_shutdown_function() to defer heavy processing.
	 * Third-party services (Slack, Discord) have strict 3-5 second timeout windows.
	 *
	 * @param Webhook_Handler_Interface $handler The handler to dispatch to.
	 * @param \WP_REST_Request          $request The incoming request.
	 *
	 * @return \WP_REST_Response
	 */
	private function handle( Webhook_Handler_Interface $handler, \WP_REST_Request $request ): \WP_REST_Response {
		// Defer to shutdown — same pattern as App_Webhooks::process_shutdown_webhook().
		// Third-party services have strict 3-5 second timeout windows; processing
		// must happen after the 200 response is sent.
		//
		// Caveat: the 200-early pattern means an exception in shutdown is
		// invisible to the caller (Meta/WhatsApp/Instagram won't retry).
		// We can't change that without breaking external contracts. What we
		// can do is make the failure *visible* downstream — write the error
		// to uap_error_log so admin UIs can surface "webhook processing
		// failed" instead of the user seeing a recipe stuck indefinitely.
		register_shutdown_function(
			function () use ( $handler, $request ) {
				try {
					$handler->process( $request );
				} catch ( \Throwable $e ) {
					$this->log_shutdown_failure( $handler, $request, $e );
					Dispatcher::action( 'automator_webhook_process_error', $e, $handler, $request );
				}
			}
		);

		return new \WP_REST_Response( array( 'success' => true ), 200 );
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
