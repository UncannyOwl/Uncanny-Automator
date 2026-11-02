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
	 * Used as the REST route permission_callback. Return false to reject:
	 * the router answers `403 webhook_validation_failed`. Set a validation
	 * error via get_validation_error() for detailed REST API error responses.
	 * A handler that answers its own refusal is not refused here — see
	 * {@see Answers_Own_Refusal}.
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

	/**
	 * OPTIONAL — deliberately NOT declared on this interface.
	 *
	 * `public function get_rest_namespace(): string` — the REST namespace
	 * a handler registers under. Webhook_Router `method_exists()`-checks
	 * for it and falls back to `uap/v2` (the legacy / plugin-scoped
	 * default) when absent, so pre-7.4 implementers of this interface —
	 * including third-party ones — keep loading without change. Platform
	 * integration controllers that receive callbacks from
	 * `api.automatorplugin.com` implement it (or pull in
	 * {@see Namespace_Default_Trait}) and return `automator/platform/v1`
	 * so their routes sit on a distinct REST namespace separate from the
	 * plugin's own webhook receivers.
	 *
	 * Declaring it abstract here would fatal every existing implementer
	 * at class-load time — an interface addition is a breaking change
	 * for implementers, unlike the router-side sniff.
	 *
	 * @since 8.0.0
	 */
}
