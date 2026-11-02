<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Status\Enums;

/**
 * Log_Status_Code Enum.
 *
 * The KNOWN recipe-log status codes: the frontend fixture's 21 codes
 * (`src/assets/src/domains/recipe/types/__fixtures__/recipe-log-status.ts`)
 * plus the backend-only `blocked-on-dependency`. OPEN SET — the wire
 * contract lets the backend introduce codes without a frontend change (the
 * frontend never branches on `code`, only on phase). This class enumerates
 * the known vocabulary so producers reference constants instead of magic
 * strings; unknown codes are still legal on the wire and take the registry's
 * fallback descriptor.
 */
final class Log_Status_Code {

	/**
	 * Ran cleanly.
	 *
	 * @var string
	 */
	const COMPLETED = 'completed';

	/**
	 * Completed but the error count is non-zero.
	 *
	 * @var string
	 */
	const COMPLETED_WITH_ERRORS = 'completed-with-errors';

	/**
	 * Action explicitly chose an early-return / no-op path.
	 *
	 * @var string
	 */
	const COMPLETED_DO_NOTHING = 'completed-do-nothing';

	/**
	 * Idempotent action found state already matching the desired outcome.
	 * No backend producer yet — carried for frontend-vocabulary parity.
	 *
	 * @var string
	 */
	const COMPLETED_DID_NOTHING = 'completed-did-nothing';

	/**
	 * Completed cleanly with a user-facing notice.
	 *
	 * @var string
	 */
	const COMPLETED_WITH_NOTICE = 'completed-with-notice';

	/**
	 * Synchronous work done; waiting on an external event.
	 *
	 * @var string
	 */
	const COMPLETED_AWAITING = 'completed-awaiting';

	/**
	 * Backend failure.
	 *
	 * @var string
	 */
	const FAILED = 'failed';

	/**
	 * Deliberately not executed (filter rejected, dependency failed).
	 *
	 * @var string
	 */
	const SKIPPED = 'skipped';

	/**
	 * Dispatched and running.
	 *
	 * @var string
	 */
	const IN_PROGRESS = 'in-progress';

	/**
	 * Running with a non-zero error count.
	 *
	 * @var string
	 */
	const IN_PROGRESS_WITH_ERRORS = 'in-progress-with-errors';

	/**
	 * Not started; sitting on the worker queue.
	 *
	 * @var string
	 */
	const QUEUED = 'queued';

	/**
	 * Parked on a future tick (cron, scheduled trigger).
	 *
	 * @var string
	 */
	const SCHEDULED = 'scheduled';

	/**
	 * Parked on a delay block's elapsed-time threshold.
	 *
	 * @var string
	 */
	const DELAYED = 'delayed';

	/**
	 * Terminated by the user before completion.
	 *
	 * @var string
	 */
	const CANCELLED = 'cancelled';

	/**
	 * Paused by the user mid-run.
	 *
	 * @var string
	 */
	const PAUSED = 'paused';

	/**
	 * Terminated naturally without completing every node.
	 *
	 * @var string
	 */
	const ENDED = 'ended';

	/**
	 * The runner never reached the node.
	 *
	 * @var string
	 */
	const NOT_COMPLETED = 'not-completed';

	/**
	 * A sibling short-circuited the path before evaluation.
	 *
	 * @var string
	 */
	const NOT_EVALUATED = 'not-evaluated';

	/**
	 * Condition evaluated true.
	 *
	 * @var string
	 */
	const CONDITION_MET = 'condition-met';

	/**
	 * Condition evaluated false.
	 *
	 * @var string
	 */
	const CONDITION_NOT_MET = 'condition-not-met';

	/**
	 * Condition short-circuited (logic already decided by a sibling).
	 *
	 * @var string
	 */
	const CONDITION_NOT_EVALUATED = 'condition-not-evaluated';

	/**
	 * In the execution graph but a dependency hasn't completed yet.
	 * Backend-only code (Automator_Status::BLOCKED_ON_DEPENDENCY); legal on
	 * the wire via the open-code rule.
	 *
	 * @var string
	 */
	const BLOCKED_ON_DEPENDENCY = 'blocked-on-dependency';

	/**
	 * All known codes, in contract-table order (spec §6).
	 *
	 * @return array<int, string>
	 */
	public static function all(): array {
		return array(
			self::COMPLETED,
			self::COMPLETED_WITH_ERRORS,
			self::COMPLETED_DO_NOTHING,
			self::COMPLETED_DID_NOTHING,
			self::COMPLETED_WITH_NOTICE,
			self::COMPLETED_AWAITING,
			self::FAILED,
			self::SKIPPED,
			self::IN_PROGRESS,
			self::IN_PROGRESS_WITH_ERRORS,
			self::QUEUED,
			self::SCHEDULED,
			self::DELAYED,
			self::CANCELLED,
			self::PAUSED,
			self::ENDED,
			self::NOT_COMPLETED,
			self::NOT_EVALUATED,
			self::CONDITION_MET,
			self::CONDITION_NOT_MET,
			self::CONDITION_NOT_EVALUATED,
			self::BLOCKED_ON_DEPENDENCY,
		);
	}
}
