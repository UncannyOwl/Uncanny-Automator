<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Services;

use Uncanny_Automator\App\Events\Dispatcher;

/**
 * Transient-based deduplication guard for recipe trigger events.
 *
 * Prevents duplicate recipe runs caused by webhook retries and replay attacks.
 * Uses WordPress transients for automatic expiry and compatibility with
 * persistent object caches (Redis, Memcached).
 *
 * Only active when the caller provides an explicit event_hash (e.g., webhook
 * X-Request-Id, form submission nonce, payload hash). Without a hash, every
 * trigger fire is treated as unique — no silent blocking.
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Services
 * @since   7.2
 */
class Idempotency_Guard {

	/**
	 * Check whether this event has already been processed within the dedup window.
	 *
	 * Atomic check-and-set: if the transient exists, it is a duplicate.
	 * Otherwise the transient is created and the event is considered new.
	 *
	 * @param int    $recipe_id  The recipe ID.
	 * @param int    $user_id    The user ID.
	 * @param int    $trigger_id The trigger ID.
	 * @param string $event_hash Optional caller-provided event hash (webhook ID, form nonce, etc.).
	 *
	 * @return bool True if duplicate (already processed), false if new.
	 */
	public function is_duplicate( int $recipe_id, int $user_id, int $trigger_id, string $event_hash = '' ): bool {

		// TODO: Re-enable idempotency guard for a future release.
		//
		// Short-circuited because:
		// 1. The get_transient() + set_transient() check-and-set is NOT atomic —
		//    under high concurrency (persistent object cache + webhook bursts),
		//    two requests can both pass get_transient() before either writes.
		//    Proper fix: use wp_cache_add() (returns false if key exists) or
		//    a MySQL INSERT IGNORE / GET_LOCK pattern for true atomicity.
		// 2. Needs real-world testing with webhook-heavy integrations before
		//    going live to ensure no false-positive blocking of legitimate runs.
		//
		// To close the circuit: remove this early return and address the race
		// condition in the check-and-set block below.
		return false;

		// @codeCoverageIgnoreStart — dead code while short-circuited.

		// Only dedup when the caller provides an explicit event hash.
		// No hash = no dedup. Every trigger fire without a hash is treated as unique.
		// Webhooks should pass X-Request-Id or payload hash via $args['event_hash'].
		if ( '' === $event_hash ) {
			return false;
		}

		$window = (int) Dispatcher::filter( 'automator_idempotency_window_seconds', 60 );

		// Kill switch — window of 0 disables dedup entirely.
		if ( 0 >= $window ) {
			return false;
		}

		$key = $this->build_key( $recipe_id, $user_id, $trigger_id, $event_hash );

		// NOT atomic — see TODO above. Two concurrent requests can both
		// see false from get_transient() and both proceed.
		if ( false !== get_transient( $key ) ) {
			return true;
		}

		set_transient( $key, time(), $window );

		return false;

		// @codeCoverageIgnoreEnd
	}

	/**
	 * Build a deduplication key from composite parts.
	 *
	 * @param int    $recipe_id  The recipe ID.
	 * @param int    $user_id    The user ID.
	 * @param int    $trigger_id The trigger ID.
	 * @param string $event_hash Caller-provided event hash.
	 *
	 * @return string The transient key.
	 */
	private function build_key( int $recipe_id, int $user_id, int $trigger_id, string $event_hash ): string {
		return sprintf( 'uap_idem_%s', md5( "{$recipe_id}:{$user_id}:{$trigger_id}:{$event_hash}" ) );
	}
}
