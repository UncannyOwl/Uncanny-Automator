<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Webhooks;

/**
 * Implemented by exceptions that carry the HTTP status the {@see Webhook_Router}
 * must answer with when they escape a {@see Durable_Webhook_Handler}'s
 * `process()`.
 *
 * The router's default for any other `Throwable` is 500 (retryable) — the safe
 * assumption for an unclassified failure is that the platform should retry.
 * A handler that wants a different, deliberate status (e.g. a 4xx that tells the
 * platform NOT to retry a batch it can never satisfy) throws an exception that
 * implements this interface, and the router honours `get_http_status()` instead.
 *
 * @since 8.0.0
 */
interface Provides_Http_Status {

	/**
	 * The HTTP status the router should return for this failure.
	 *
	 * @return int
	 */
	public function get_http_status(): int;
}
