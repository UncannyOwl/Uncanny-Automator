<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Webhooks;

/**
 * Thrown when a durable webhook write fails to land — the inbox INSERT returned
 * a hard failure (missing table from a skipped schema upgrade, a dropped DB
 * connection, disk pressure). The event is NOT safely stored, so the request
 * MUST NOT answer 2xx: the platform treats 2xx as proof of delivery and flushes
 * its outbox row without retrying.
 *
 * This is a RETRYABLE, server-side error. The router answers 500 so the platform
 * retries the whole batch; already-stored rows dedup on the inbox UNIQUE key and
 * the previously-failed row lands on the retry.
 *
 * @since 8.0.0
 */
class Durable_Storage_Exception extends \Exception implements Provides_Http_Status {

	/**
	 * @return int
	 */
	public function get_http_status(): int {

		return 500;
	}
}
