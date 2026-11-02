<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Services;

use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Action_Error_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Execution_Log_Store;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Violation_Service;
use Uncanny_Automator\Automator_Status;
use Uncanny_Automator\App\Infrastructure\Database\Database;

/**
 * Computes the final recipe status from all action statuses in one pass.
 *
 * Status is derived from action statuses (integers in uap_action_log.completed),
 * actionable run/block errors and failed loop entries,
 * NOT from error message content.
 *
 * Used by finalize_recipe() (Stage 4) and the severity guard in persist_recipe_log().
 * Replaces the old determine_recipe_status() + maybe_escalate_action_errors() pattern.
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Services
 * @since   7.3
 */
class Recipe_Status_Resolver {

	/**
	 * @var Execution_Log_Store
	 */
	private $log_store;

	/**
	 * @var Action_Error_Store
	 */
	private $error_store;

	/**
	 * @param Execution_Log_Store|null $log_store   Optional log store instance.
	 * @param Action_Error_Store|null  $error_store Optional error store instance; reads the run's system and block errors.
	 */
	public function __construct( ?Execution_Log_Store $log_store = null, ?Action_Error_Store $error_store = null ) {
		$this->log_store   = $log_store ?? Database::get_execution_log_store();
		$this->error_store = $error_store ?? Database::get_action_error_store();
	}

	/**
	 * Severity ranking for recipe statuses.
	 *
	 * Higher rank = more severe. The severity guard uses this to prevent
	 * status downgrades — once a recipe reaches COMPLETED_WITH_ERRORS (rank 5),
	 * a later COMPLETED (rank 1) cannot overwrite it.
	 *
	 * @var array<int,int>
	 */
	private const SEVERITY = array(
		Automator_Status::DID_NOTHING            => 0,
		Automator_Status::COMPLETED              => 1,
		Automator_Status::COMPLETED_WITH_NOTICE  => 2,
		Automator_Status::SKIPPED                => 2,
		Automator_Status::CANCELLED              => 2,
		Automator_Status::IN_PROGRESS            => 3,
		Automator_Status::COMPLETED_AWAITING     => 3,
		Automator_Status::QUEUED                 => 3,
		Automator_Status::BLOCKED_ON_DEPENDENCY  => 3,
		Automator_Status::IN_PROGRESS_WITH_ERROR => 4,
		Automator_Status::COMPLETED_WITH_ERRORS  => 5,
		Automator_Status::FAILED                 => 6,
	);

	/**
	 * Action statuses that always count as "actionable errors" for recipe escalation.
	 *
	 * NOT_COMPLETED is intentionally NOT here. A NOT_COMPLETED(0) row is the
	 * transient initial state every action passes through: create_action() writes
	 * the row at 0 *before* the action runs, and mark_action_complete() flips it
	 * to a terminal status afterwards. In the live finalize path a 0 therefore
	 * means "this action is mid-flight," NOT "this action errored" — treating it
	 * as an error produced false COMPLETED_WITH_ERRORS runs (locked permanently by
	 * the no-downgrade guard) whenever finalize observed an action mid-completion.
	 * This restores the pre-7.3 (7.2.x) contract, which escalated on real errors.
	 *
	 * A genuinely stuck 0 (PHP fatal / OOM mid-Stage 3) is surfaced by passing
	 * $treat_incomplete_as_error = true — used exclusively by Stuck_Recipe_Recovery,
	 * which only runs against recipes stuck at NOT_COMPLETED for 1h+. See resolve().
	 *
	 * @var array<int,bool>
	 */
	private const ACTIONABLE_ERROR_STATUSES = array(
		Automator_Status::COMPLETED_WITH_ERRORS  => true,
		Automator_Status::FAILED                 => true,
		Automator_Status::IN_PROGRESS_WITH_ERROR => true,
	);

	/**
	 * Compute final recipe status from all action results in one pass.
	 *
	 * Live path note: with $treat_incomplete_as_error = false, a run whose action
	 * rows are all still NOT_COMPLETED(0) (Stage 4 observed Stage 3 mid-flight)
	 * resolves to COMPLETED, not an error — see the ACTIONABLE_ERROR_STATUSES
	 * docblock for why a transient 0 is not a failure. The status converges once
	 * the rows flip to their terminal values; a genuinely stuck run is escalated
	 * later by Stuck_Recipe_Recovery (which passes $treat_incomplete_as_error = true).
	 *
	 * @param int   $recipe_log_id             The recipe log ID.
	 * @param array $args                      Recipe args (may contain do-nothing, complete_with_notice flags).
	 * @param bool  $treat_incomplete_as_error When true, a NOT_COMPLETED(0) action row is
	 *                                         treated as an actionable error. ONLY the recovery
	 *                                         cron (Stuck_Recipe_Recovery, 1h+ stuck) sets this —
	 *                                         a 0 there is a genuinely stuck/partial run. The live
	 *                                         finalize path leaves it false so a transient 0 (an
	 *                                         action mid-completion) does not falsely escalate.
	 *
	 * @return int Automator_Status constant.
	 */
	public function resolve( int $recipe_log_id, array $args = array(), bool $treat_incomplete_as_error = false ): int {

		$action_statuses = $this->log_store->get_all_action_statuses( $recipe_log_id );
		$has_run_error   = $this->error_store->has_system_errors( $recipe_log_id ) || $this->has_failed_loop( $recipe_log_id );

		// No actions at all — fall through to flag-based resolution below.
		if ( ! empty( $action_statuses ) ) {

			// Any pending/async action means the recipe isn't done yet.
			// IN_PROGRESS: background actions, Action Scheduler delayed.
			// COMPLETED_AWAITING: waiting for external confirmation (WhatsApp delivery, Instagram publish).
			// QUEUED: loop iterations queued but not started.
			// BLOCKED_ON_DEPENDENCY: waiting on a background app action.
			$has_scheduled = $this->has_status( $action_statuses, Automator_Status::IN_PROGRESS )
				|| $this->has_status( $action_statuses, Automator_Status::COMPLETED_AWAITING )
				|| $this->has_status( $action_statuses, Automator_Status::QUEUED )
				|| $this->has_status( $action_statuses, Automator_Status::BLOCKED_ON_DEPENDENCY );
			$has_errors    = $has_run_error || $this->has_actionable_error_status( $action_statuses, $treat_incomplete_as_error )
				|| $this->has_not_run_block( $recipe_log_id );

			if ( $has_scheduled && $has_errors ) {
				return Automator_Status::IN_PROGRESS_WITH_ERROR;
			}

			if ( $has_scheduled ) {
				return Automator_Status::IN_PROGRESS;
			}

			if ( $has_errors ) {
				return Automator_Status::COMPLETED_WITH_ERRORS;
			}

			// A run whose delayed actions were cancelled before anything executed is
			// CANCELLED, not COMPLETED. Once any action has run the run keeps its
			// completed status and the cancel is visible on the row that carries it.
			// SKIPPED and NOT_COMPLETED rows never ran, so they do not count.
			if ( $this->has_status( $action_statuses, Automator_Status::CANCELLED ) ) {

				$executed = $this->has_status( $action_statuses, Automator_Status::COMPLETED )
					|| $this->has_status( $action_statuses, Automator_Status::COMPLETED_WITH_NOTICE )
					|| $this->has_status( $action_statuses, Automator_Status::DID_NOTHING );

				if ( ! $executed ) {
					return Automator_Status::CANCELLED;
				}
			}

			if ( $this->all_match( $action_statuses, Automator_Status::DID_NOTHING ) ) {
				return Automator_Status::DID_NOTHING;
			}
		}

		// An actionable run or block error fails even a run with no action rows.
		if ( empty( $action_statuses ) && ( $has_run_error || $this->has_not_run_block( $recipe_log_id ) ) ) {
			return Automator_Status::COMPLETED_WITH_ERRORS;
		}

		// Recovery context only: a run with NO action rows that was stuck at
		// NOT_COMPLETED long enough for Stuck_Recipe_Recovery to claim it is a
		// partial/failed run — it never produced a single completed action — so
		// surface it as COMPLETED_WITH_ERRORS to match the RECIPE_STUCK entry
		// recovery writes. The live path (flag false) leaves an action-less run
		// as COMPLETED via the fallbacks below.
		if ( $treat_incomplete_as_error && empty( $action_statuses ) ) {
			return Automator_Status::COMPLETED_WITH_ERRORS;
		}

		// Flag-based fallbacks — used when action statuses alone aren't conclusive.
		if ( array_key_exists( 'do-nothing', $args ) || array_key_exists( 'do_nothing', $args ) ) {
			return Automator_Status::DID_NOTHING;
		}

		// The user selector failed (for example, the user to create had no
		// email), so every action was skipped. That is an error the owner has
		// to fix, not "did nothing": an owner-chosen "do nothing" fallback
		// already arrives as the do-nothing flag above. Run counts leave out
		// both statuses, so only the label differs.
		if ( ! empty( $args['complete_with_errors'] ) ) {
			return Automator_Status::COMPLETED_WITH_ERRORS;
		}

		if ( ! empty( $args['complete_with_notice'] ) ) {
			return Automator_Status::COMPLETED_WITH_NOTICE;
		}

		// A run whose rows are all SKIPPED with no flag, such as a filter whose
		// conditions failed with nothing on its other branch, stays COMPLETED on
		// purpose. COMPLETED counts toward per-user and total run limits and
		// DID_NOTHING does not, so switching would let that user trigger the
		// recipe again.
		return Automator_Status::COMPLETED;
	}

	/**
	 * Whether an actionable block failure occurred. Its rows read SKIPPED, so only the stored block row,
	 * which every leg of the run can read, says the run failed.
	 *
	 * @since 8.0.0
	 *
	 * @param int $recipe_log_id The recipe log ID.
	 *
	 * @return bool
	 */
	private function has_not_run_block( int $recipe_log_id ): bool {
		foreach ( $this->error_store->get_block_errors( $recipe_log_id ) as $row ) {
			if ( Error_Code::BLOCK_NOT_RUN === $row->error_code && 1 === (int) $row->is_actionable ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a loop of the run ended Completed with errors (D67). A loop the walker refused only because it has
	 * no filter does not count: its entry carries the refusal as a note, and the run closes by its own steps. Any
	 * other refusal, and a loop that could not start, counts.
	 *
	 * @since 8.0.0
	 *
	 * @param int $recipe_log_id The recipe log ID.
	 *
	 * @return bool
	 */
	private function has_failed_loop( int $recipe_log_id ): bool {
		if ( ! $this->log_store->has_failed_loop_entries( $recipe_log_id ) ) {
			return false;
		}

		$noted = $this->loops_refused_for_a_missing_filter( $recipe_log_id );

		return array() === $noted || $this->log_store->has_failed_loop_entries( $recipe_log_id, $noted );
	}

	/**
	 * The loops the walker refused for having no filter and for nothing else: the run's refusal record
	 * (`Execution_Log_Store::META_BLOCK_REFUSALS`) holds that violation and no other for them.
	 *
	 * @since 8.0.0
	 *
	 * @param int $recipe_log_id The recipe log ID.
	 *
	 * @return int[] Loop block ids.
	 */
	private function loops_refused_for_a_missing_filter( int $recipe_log_id ): array {
		$refusals = json_decode( (string) $this->log_store->get_recipe_log_meta( $recipe_log_id, Execution_Log_Store::META_BLOCK_REFUSALS ), true );
		$loop_ids = array();

		foreach ( is_array( $refusals ) ? $refusals : array() as $block_id => $refusal ) {
			$violations = is_array( $refusal ) ? (array) ( $refusal['violations'] ?? array() ) : array();
			$codes      = array_map(
				static function ( $violation ): string {
					return is_array( $violation ) ? (string) ( $violation['code'] ?? '' ) : '';
				},
				$violations
			);

			if ( array( Recipe_Violation_Service::BLOCK_MODIFIER_MISSING ) === array_values( array_unique( $codes ) ) ) {
				$loop_ids[] = (int) $block_id;
			}
		}

		return $loop_ids;
	}

	/**
	 * Transient statuses — always overwritable by any status.
	 *
	 * These represent "work is ongoing." When the work finishes, any
	 * terminal status replaces them. Only terminal statuses are protected
	 * from downgrades.
	 *
	 * @var array<int,bool>
	 */
	private const TRANSIENT_STATUSES = array(
		Automator_Status::NOT_COMPLETED          => true,
		Automator_Status::IN_PROGRESS            => true,
		Automator_Status::IN_PROGRESS_WITH_ERROR => true,
		Automator_Status::QUEUED                 => true,
		Automator_Status::COMPLETED_AWAITING     => true,
		Automator_Status::BLOCKED_ON_DEPENDENCY  => true,
	);

	/**
	 * Check if transitioning from $current_status to $new_status would be a severity downgrade.
	 *
	 * @param int $current_status Current recipe status.
	 * @param int $new_status     Proposed new status.
	 *
	 * @return bool True if the new status is less severe than the current.
	 */
	public function is_downgrade( int $current_status, int $new_status ): bool {

		// Transient statuses are always overwritable — not a downgrade.
		if ( isset( self::TRANSIENT_STATUSES[ $current_status ] ) ) {
			return false;
		}

		$current_severity = self::SEVERITY[ $current_status ] ?? 0;
		$new_severity     = self::SEVERITY[ $new_status ] ?? 0;

		return $new_severity < $current_severity;
	}

	/**
	 * Get the severity rank for a status.
	 *
	 * @param int $status Automator_Status constant.
	 *
	 * @return int Severity rank (0 = lowest).
	 */
	public function get_severity( int $status ): int {
		return self::SEVERITY[ $status ] ?? 0;
	}

	/**
	 * Check if any action has an actionable error status.
	 *
	 * @param int[] $statuses                  Action status integers.
	 * @param bool  $treat_incomplete_as_error When true, NOT_COMPLETED(0) also counts
	 *                                         as an error (recovery context only).
	 *
	 * @return bool
	 */
	private function has_actionable_error_status( array $statuses, bool $treat_incomplete_as_error = false ): bool {

		foreach ( $statuses as $status ) {
			if ( isset( self::ACTIONABLE_ERROR_STATUSES[ $status ] ) ) {
				return true;
			}

			if ( $treat_incomplete_as_error && Automator_Status::NOT_COMPLETED === $status ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if any action has a specific status.
	 *
	 * @param int[] $statuses Action status integers.
	 * @param int   $target   Status to look for.
	 *
	 * @return bool
	 */
	private function has_status( array $statuses, int $target ): bool {
		return in_array( $target, $statuses, true );
	}

	/**
	 * Check if all actions have the same status.
	 *
	 * @param int[] $statuses Action status integers.
	 * @param int   $target   Status to check against.
	 *
	 * @return bool
	 */
	private function all_match( array $statuses, int $target ): bool {

		foreach ( $statuses as $status ) {
			if ( $target !== $status ) {
				return false;
			}
		}

		return true;
	}
}
