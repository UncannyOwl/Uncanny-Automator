<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Webhooks;

/**
 * Interface Webhook_Handler_Interface
 *
 * Contract for integration-specific webhook handlers. Lives flat alongside
 * `Webhook_Router` per the app-layer skill rule against `contracts/`
 * subdirectories.
 *
 * @since 7.0.0
 * @package Uncanny_Automator\App\Infrastructure\Webhooks
 */
interface Webhook_Handler_Interface {

	/**
	 * Get the integration identifier used for route registration.
	 *
	 * @return string
	 */
	public function get_integration_id(): string;

	/**
	 * Validate the incoming webhook request.
	 *
	 * Used as the REST route permission_callback. Return false to reject.
	 * Set a validation error via get_validation_error() for detailed
	 * REST API error responses.
	 *
	 * @param \WP_REST_Request $request The incoming request.
	 *
	 * @return bool
	 */
	public function validate( \WP_REST_Request $request ): bool;

	/**
	 * Get the last validation error message.
	 *
	 * Called by Webhook_Router when validate() returns false to provide
	 * a detailed WP_Error to the REST API caller.
	 *
	 * @return string|null Error message, or null if no error detail available.
	 */
	public function get_validation_error(): ?string;

	/**
	 * Process the webhook payload.
	 *
	 * @param \WP_REST_Request $request The incoming request.
	 *
	 * @return void
	 */
	public function process( \WP_REST_Request $request ): void;

	/**
	 * Whether this handler accepts GET requests in addition to POST.
	 *
	 * @return bool
	 */
	public function accepts_get(): bool;
}
