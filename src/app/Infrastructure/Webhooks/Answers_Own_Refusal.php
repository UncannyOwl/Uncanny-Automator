<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Webhooks;

use WP_REST_Request;
use WP_REST_Response;

/**
 * Marks a webhook handler that answers its own refusal, instead of the
 * {@see Webhook_Router}'s `403 webhook_validation_failed`.
 *
 * The router's refusal is a WordPress permission refusal: the route's
 * `permission_callback` returns a `WP_Error`, which WordPress always renders
 * as `{code, message, data}`. That serves third-party vendors, and it stays
 * exactly as it is for them. A handler whose sender reads a refusal of its own
 * shape — the Automator Platform's callback receiver answers
 * `401 {"error":{"type":"SIGNATURE_REJECTED","reason":…}}` (callback delivery
 * contract §4) — cannot be answered that way, so it owns its refusal:
 *
 *  - the router registers its route with a permission callback that lets the
 *    request through, because the refusal is not WordPress's to give;
 *  - the route callback asks {@see refusal_for()} first, and answers a
 *    non-null response exactly as the handler built it. The request is never
 *    processed then. Null means it verified: processing goes on as for any
 *    handler (synchronously for a {@see Durable_Webhook_Handler}).
 *
 * The handler still implements `validate()` (the interface requires it), but
 * the router does not call it for such a handler: `refusal_for()` is its
 * verdict and its answer in one call, with whatever a refusal starts.
 *
 * Deliberately a SEPARATE interface rather than a method on
 * {@see Durable_Webhook_Handler}: the two are orthogonal. Durability is about
 * when `process()` runs relative to the 2xx; owning a refusal is about who
 * answers a request that fails verification. A handler may be either without
 * the other, and neither is a breaking addition to
 * {@see Webhook_Handler_Interface}, which third-party code implements.
 *
 * @since 8.0.0
 */
interface Answers_Own_Refusal extends Webhook_Handler_Interface {

	/**
	 * Verify the request.
	 *
	 * @param WP_REST_Request $request The incoming request.
	 *
	 * @return WP_REST_Response|null The handler's refusal, answered as-is; null
	 *                               when the request verified and may be processed.
	 */
	public function refusal_for( WP_REST_Request $request ): ?WP_REST_Response;
}
