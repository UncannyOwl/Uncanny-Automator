<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Webhooks;

/**
 * Marks a webhook handler whose `process()` MUST complete before the router
 * returns 2xx.
 *
 * {@see Webhook_Router} defaults to answering 200 immediately and running
 * `process()` on `shutdown`. That is correct for third-party vendors (Slack,
 * Discord, Meta) which enforce 3–5 second timeouts and de-register endpoints
 * that answer slowly — there, a fast ack is the contract.
 *
 * It is wrong for the Automator Platform. The platform treats 2xx as proof of
 * delivery and flushes its outbox row; it will not retry. Acknowledging before
 * the durable write means a PHP fatal, an OOM, or a failed INSERT after the
 * response loses the event permanently, with both sides believing it was
 * handled.
 *
 * Implementing this interface inverts that for one handler: validation and the
 * durable write run inside the request, and the 2xx becomes an honest claim.
 * Handlers that do so are responsible for keeping the synchronous section
 * short — land the row, then defer the actual processing (draining, recipe
 * execution) to `shutdown` themselves, so response latency is unchanged.
 *
 * Deliberately a SEPARATE interface rather than a method on
 * {@see Webhook_Handler_Interface}: that interface is implemented by
 * third-party code, where adding a required method is a breaking change. An
 * `instanceof` check costs nothing and is opt-in.
 *
 * @since 8.0.0
 */
interface Durable_Webhook_Handler extends Webhook_Handler_Interface {

}
