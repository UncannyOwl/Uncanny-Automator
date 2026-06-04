<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Services;

use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Execution_Log_Store;
use Uncanny_Automator\Automator_Status;
use Uncanny_Automator\App\Infrastructure\Database\Database;

/**
 * Computes the final recipe status from all action statuses in one pass.
 *
 * Status is derived from action statuses (integers in uap_action_log.completed),
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
	 * @param Execution_Log_Store|null $log_store Optional log store instance.
	 */
	public function __construct( ?Execution_Log_Store $log_store = null ) {
		$this->log_store = $log_store ?? Database::get_execution_log_store();
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
		Automator_Status::IN_PROGRESS            => 3,
		Automator_Status::COMPLETED_AWAITING     => 3,
		Automator_Status::QUEUED                 => 3,
		Automator_Status::IN_PROGRESS_WITH_ERROR => 4,
		Automator_Status::COMPLETED_WITH_ERRORS  => 5,
		Automator_Status::FAILED                 => 6,
	);

	/**
	 * Action statuses that count as "actionable errors" for recipe escalation.
	 *
	 * @var array<int,bool>
	 */
	private const ACTIONABLE_ERROR_STATUSES = array(
		Automator_Status::COMPLETED_WITH_ERRORS  => true,
		Automator_Status::FAILED                 => true,
		Automator_Status::IN_PROGRESS_WITH_ERROR => true,
		// A NOT_COMPLETED row at resolve time means create_action() wrote the
		// row but complete_action() never ran — PHP fatal / OOM mid-Stage 3,
		// or an action threw between create + complete. Once async / in-flight
		// statuses are filtered (has_scheduled above), NOT_COMPLETED can only
		// indicate an action that was started and never finished. Treating it
		// as an error makes Stuck_Recipe_Recovery surface the partial run as
		// COMPLETED_WITH_ERRORS instead of silently upgrading to COMPLETED.
		Automator_Status::NOT_COMPLETED          => true,
	);

	/**
	 * Compute final recipe status from all action results in one pass.
	 *
	 * @param int   $recipe_log_id The recipe log ID.
	 * @param array $args          Recipe args (may contain do-nothing, complete_with_notice flags).
	 *
	 * @return int Automator_Status constant.
	 */
	public function resolve( int $recipe_log_id, array $args = array() ): int {

		$action_statuses = $this->log_store->get_all_action_statuses( $recipe_log_id );

		// No actions at all — fall through to flag-based resolution below.
		if ( ! empty( $action_statuses ) ) {

			// Any pending/async action means the recipe isn't done yet.
			// IN_PROGRESS: background actions, Action Scheduler delayed.
			// COMPLETED_AWAITING: waiting for external confirmation (WhatsApp delivery, Instagram publish).
			// QUEUED: loop iterations queued but not started.
			$has_scheduled = $this->has_status( $action_statuses, Automator_Status::IN_PROGRESS )
				|| $this->has_status( $action_statuses, Automator_Status::COMPLETED_AWAITING )
				|| $this->has_status( $action_statuses, Automator_Status::QUEUED );
			$has_errors    = $this->has_actionable_error_status( $action_statuses );

			if ( $has_scheduled && $has_errors ) {
				return Automator_Status::IN_PROGRESS_WITH_ERROR;
			}

			if ( $has_scheduled ) {
				return Automator_Status::IN_PROGRESS;
			}

			if ( $has_errors ) {
				return Automator_Status::COMPLETED_WITH_ERRORS;
			}

			if ( $this->all_match( $action_statuses, Automator_Status::DID_NOTHING ) ) {
				return Automator_Status::DID_NOTHING;
			}
		}

		// Flag-based fallbacks — used when action statuses alone aren't conclusive.
		if ( array_key_exists( 'do-nothing', $args ) || array_key_exists( 'do_nothing', $args ) ) {
			return Automator_Status::DID_NOTHING;
		}

		if ( ! empty( $args['complete_with_notice'] ) ) {
			return Automator_Status::COMPLETED_WITH_NOTICE;
		}

		return Automator_Status::COMPLETED;
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
	 * @param int[] $statuses Action status integers.
	 *
	 * @return bool
	 */
	private function has_actionable_error_status( array $statuses ): bool {

		foreach ( $statuses as $status ) {
			if ( isset( self::ACTIONABLE_ERROR_STATUSES[ $status ] ) ) {
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
