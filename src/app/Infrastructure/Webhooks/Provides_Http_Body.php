<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Webhooks;

/**
 * Implemented by exceptions that carry the response body the {@see Webhook_Router}
 * must answer with when they escape a {@see Durable_Webhook_Handler}'s
 * `process()` — the companion of {@see Provides_Http_Status}.
 *
 * The router's default body (`{"success":false,"message":…}`) stays for every
 * other failure. An exception whose recipient reads a body of a set shape
 * names it here: {@see Malformed_Webhook_Batch_Exception} answers the
 * Automator Platform's `{"error":{"type":"PAYLOAD_REJECTED",…}}` (callback
 * delivery contract §4). Separate from {@see Provides_Http_Status} so an
 * exception that only names a status — {@see Durable_Storage_Exception}'s
 * 500 — keeps the router's body without restating it.
 *
 * @since 8.0.0
 */
interface Provides_Http_Body {

	/**
	 * The response body the router should answer with for this failure.
	 *
	 * @return array<string, mixed>
	 */
	public function get_http_body(): array;
}
