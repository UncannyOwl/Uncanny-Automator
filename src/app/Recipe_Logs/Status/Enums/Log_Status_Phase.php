<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Status\Enums;

/**
 * Log_Status_Phase Enum.
 *
 * The five lifecycle phases the recipe-log wire ships on every status
 * object. CLOSED SET — mirrors the frontend Zod enum
 * (`src/assets/src/domains/recipe/types/models/recipe-log/status.ts`) and
 * the runtime architecture design (2026-05-08, Section 2) verbatim. The
 * frontend buckets every behavioral decision on this value; adding a member
 * is a frontend-coordinated contract change, never a backend-only edit.
 */
final class Log_Status_Phase {

	/**
	 * Node not yet dispatched (blocked on deps, queued, scheduled, delayed).
	 *
	 * @var string
	 */
	const WAITING = 'waiting';

	/**
	 * Dispatched and running, or awaiting a callback.
	 *
	 * @var string
	 */
	const ACTIVE = 'active';

	/**
	 * Held by user / admin; resume restores the prior state.
	 *
	 * @var string
	 */
	const PAUSED = 'paused';

	/**
	 * Ran cleanly (possibly with notice, sub-errors, or did-nothing).
	 *
	 * @var string
	 */
	const TERMINAL_SUCCESS = 'terminal_success';

	/**
	 * Failed, skipped, or cancelled.
	 *
	 * @var string
	 */
	const TERMINAL_FAILURE = 'terminal_failure';

	/**
	 * All phases, in contract order.
	 *
	 * @return array<int, string>
	 */
	public static function all(): array {
		return array(
			self::WAITING,
			self::ACTIVE,
			self::PAUSED,
			self::TERMINAL_SUCCESS,
			self::TERMINAL_FAILURE,
		);
	}

	/**
	 * Whether the given phase is a member of the closed set.
	 *
	 * @param string $phase Candidate phase.
	 *
	 * @return bool
	 */
	public static function is_valid( string $phase ): bool {
		return in_array( $phase, self::all(), true );
	}
}
