<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Services;

/**
 * Request-scoped detach state, per run.
 *
 * An action asks for a detach through `automator_request_run_detach()`; the walker takes the request after the
 * node returns, records the remainder of the walk as blocked nodes, and the walk entry dispatches the
 * continuation. State lives for the request only: the durable state is the run meta the walker writes.
 *
 * @since 8.0.0
 */
final class Run_Detach_Request {

	/**
	 * Runs with an un-taken request.
	 *
	 * @var array<int, true>
	 */
	private static array $requested = array();

	/**
	 * Runs whose walk detached, keyed to the anchor action id.
	 *
	 * @var array<int, int>
	 */
	private static array $anchors = array();

	/**
	 * How many nodes each detached run recorded for its continuation.
	 *
	 * @var array<int, int>
	 */
	private static array $recorded = array();

	/**
	 * Register a detach request for a run.
	 *
	 * @param int $recipe_log_id The run.
	 *
	 * @return void
	 */
	public static function request( int $recipe_log_id ): void {
		if ( $recipe_log_id > 0 ) {
			self::$requested[ $recipe_log_id ] = true;
		}
	}

	/**
	 * Consume the run's request, if any.
	 *
	 * @param int $recipe_log_id The run.
	 *
	 * @return bool True exactly once per request() call.
	 */
	public static function take( int $recipe_log_id ): bool {
		if ( ! isset( self::$requested[ $recipe_log_id ] ) ) {
			return false;
		}
		unset( self::$requested[ $recipe_log_id ] );
		return true;
	}

	/**
	 * Record that the run's walk detached after an action.
	 *
	 * @param int $recipe_log_id    The run.
	 * @param int $anchor_action_id The action after which the walk stopped.
	 *
	 * @return void
	 */
	public static function mark_detached( int $recipe_log_id, int $anchor_action_id ): void {
		self::$anchors[ $recipe_log_id ] = $anchor_action_id;
	}

	/**
	 * Whether the run's walk detached.
	 *
	 * @param int $recipe_log_id The run.
	 *
	 * @return bool
	 */
	public static function is_detached( int $recipe_log_id ): bool {
		return isset( self::$anchors[ $recipe_log_id ] );
	}

	/**
	 * The anchor action of a detached run.
	 *
	 * @param int $recipe_log_id The run.
	 *
	 * @return int|null Null when the run did not detach.
	 */
	public static function anchor( int $recipe_log_id ): ?int {
		return self::$anchors[ $recipe_log_id ] ?? null;
	}

	/**
	 * Add to the count of nodes recorded for the run's continuation.
	 *
	 * @param int $recipe_log_id The run.
	 * @param int $count         Nodes recorded by one level of the walk.
	 *
	 * @return void
	 */
	public static function note_recorded( int $recipe_log_id, int $count ): void {
		self::$recorded[ $recipe_log_id ] = ( self::$recorded[ $recipe_log_id ] ?? 0 ) + $count;
	}

	/**
	 * Nodes recorded for the run's continuation so far.
	 *
	 * @param int $recipe_log_id The run.
	 *
	 * @return int
	 */
	public static function recorded( int $recipe_log_id ): int {
		return self::$recorded[ $recipe_log_id ] ?? 0;
	}

	/**
	 * Forget a run's state once the walk entry has dispatched or decided not to.
	 *
	 * @param int $recipe_log_id The run.
	 *
	 * @return void
	 */
	public static function clear( int $recipe_log_id ): void {
		unset( self::$requested[ $recipe_log_id ], self::$anchors[ $recipe_log_id ], self::$recorded[ $recipe_log_id ] );
	}

	/**
	 * Forget every run. Tests only; public because the test harness calls it between cases.
	 *
	 * @internal
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$requested = array();
		self::$anchors   = array();
		self::$recorded  = array();
	}
}
