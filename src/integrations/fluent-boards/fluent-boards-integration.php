<?php

namespace Uncanny_Automator\Integrations\Fluent_Boards;

use FluentBoards\App\Models\Stage;

/**
 * Class Fluent_Boards_Integration
 *
 * @package Uncanny_Automator\Integrations\Fluent_Boards
 */
class Fluent_Boards_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Seconds a task's last_completed_at may lag the current request and still
	 * count as "completed on this move". Generous because Fluent Boards runs
	 * default-assignee handling between Task::close() and the stage hook.
	 */
	const COMPLETED_WINDOW = 60;

	/**
	 * Task ids already re-dispatched this request, keyed id:direction.
	 *
	 * @var array<string, bool>
	 */
	private $dispatched = array();

	/**
	 * Set up integration.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->helpers = new Fluent_Boards_Helpers();

		$this->set_integration( 'FLUENT_BOARDS' );
		$this->set_name( 'FluentBoards' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/fluent-boards-icon.svg' );
	}

	/**
	 * Load triggers and actions.
	 *
	 * @return void
	 */
	public function load() {

		// Native Fluent Boards -> Automator hook bridges must register even in
		// targeted mode, so they live in load_shared_hooks(). The parent calls
		// that method in targeted mode only, never alongside load().
		$this->load_shared_hooks();

		// Triggers.
		new Fluent_Boards_Task_Created( $this->helpers );
		new Fluent_Boards_Task_Completed_Reopened( $this->helpers );
		new Fluent_Boards_Task_Assignee_Added( $this->helpers );
		new Fluent_Boards_Task_Assignee_Removed( $this->helpers );
		new Fluent_Boards_Comment_Added( $this->helpers );
		new Fluent_Boards_Task_Due_Date_Changed( $this->helpers );
		new Fluent_Boards_Task_Label_Changed( $this->helpers );
		new Fluent_Boards_Board_Created( $this->helpers );

		// Actions.
		new Fluent_Boards_Create_Task( $this->helpers );
		new Fluent_Boards_Create_Board( $this->helpers );
		new Fluent_Boards_Add_User_To_Board( $this->helpers );
		new Fluent_Boards_Assign_User_To_Task( $this->helpers );
		new Fluent_Boards_Add_Comment( $this->helpers );
		new Fluent_Boards_Update_Task( $this->helpers );
	}

	/**
	 * Shared hooks required for Fluent Boards execution.
	 *
	 * Fluent Boards has no single hook meaning "this task was completed or
	 * reopened". `task_completed_activity` covers only the status-property
	 * path (the status circle on a card or in the task detail); completing a
	 * task by dragging it into a stage whose default status is closed fires
	 * `task_stage_updated` instead. Both are normalised here into the single
	 * internal action the trigger listens on, so the trigger sees one hook
	 * with one arg shape: ( $task, 'closed'|'open' ).
	 *
	 * These must run whenever the integration is needed — including targeted
	 * (runtime) mode, where the base does NOT call load().
	 *
	 * @return void
	 */
	protected function load_shared_hooks() {

		add_action( 'fluent_boards/task_completed_activity', array( $this, 'bridge_status_change' ), PHP_INT_MAX, 2 );
		add_action( 'fluent_boards/task_stage_updated', array( $this, 'bridge_stage_move' ), PHP_INT_MAX, 2 );
	}

	/**
	 * Status-property path — `TaskService::updateStatus()`.
	 *
	 * Fluent Boards passes the raw client value, which is 'closed' for a
	 * completion and anything else (in practice 'open') for a reopen.
	 *
	 * @param object $task   Fluent Boards task model.
	 * @param string $status Raw status value.
	 *
	 * @return void
	 */
	public function bridge_status_change( $task, $status ) {

		$this->dispatch( $task, 'closed' === $status ? 'closed' : 'open' );
	}

	/**
	 * Drag-and-drop path — `TaskController::moveTask()` and the MCP move tool.
	 *
	 * Both close the task in-line when the destination stage defaults to
	 * closed, then fire only `task_stage_updated`. The hook carries no status
	 * delta, so the completion is inferred from the two stages and the
	 * freshness of last_completed_at.
	 *
	 * Note this path only ever completes: `moveTask()` has no matching reopen
	 * branch, and a reopen leaves last_completed_at null, which is
	 * indistinguishable from a task that was already open.
	 *
	 * @param object $task         Fluent Boards task model, already moved.
	 * @param int    $old_stage_id Stage the task came from.
	 *
	 * @return void
	 */
	public function bridge_stage_move( $task, $old_stage_id ) {

		if ( ! is_object( $task ) || 'closed' !== $this->prop( $task, 'status' ) ) {
			return;
		}

		// Fluent Boards closes the task only when the destination stage
		// defaults to closed. Moving between two closed-default stages is a
		// re-file, not a completion.
		if ( 'closed' !== $this->stage_default_status( $this->prop( $task, 'stage_id' ) ) ) {
			return;
		}

		if ( 'closed' === $this->stage_default_status( $old_stage_id ) ) {
			return;
		}

		// Task::close() early-returns when the task was already completed, so
		// a stale last_completed_at means this move changed nothing.
		if ( ! $this->completed_this_request( $task ) ) {
			return;
		}

		$this->dispatch( $task, 'closed' );
	}

	/**
	 * Re-dispatch a normalised status change to the trigger's hook.
	 *
	 * De-duplicated per request: a single request should produce at most one
	 * event per task per direction, whichever bridge observes it first.
	 *
	 * @param object $task      Fluent Boards task model.
	 * @param string $direction 'closed' or 'open'.
	 *
	 * @return void
	 */
	private function dispatch( $task, $direction ) {

		$task_id = absint( $this->prop( $task, 'id' ) );

		if ( ! is_object( $task ) || 0 === $task_id ) {
			return;
		}

		$key = $task_id . ':' . $direction;

		if ( isset( $this->dispatched[ $key ] ) ) {
			return;
		}

		$this->dispatched[ $key ] = true;

		do_action( Fluent_Boards_Helpers::TASK_STATUS_CHANGED, $task, $direction );
	}

	/**
	 * Default task status of a stage ('open' or 'closed'), '' when unknown.
	 *
	 * @param int $stage_id
	 *
	 * @return string
	 */
	private function stage_default_status( $stage_id ) {

		$stage_id = absint( $stage_id );

		if ( 0 === $stage_id || ! class_exists( '\FluentBoards\App\Models\Stage' ) ) {
			return '';
		}

		$stage = Stage::find( $stage_id );

		if ( ! is_object( $stage ) || ! method_exists( $stage, 'defaultTaskStatus' ) ) {
			return '';
		}

		return (string) $stage->defaultTaskStatus();
	}

	/**
	 * Whether the task's completion timestamp belongs to the current request.
	 *
	 * @param object $task
	 *
	 * @return bool
	 */
	private function completed_this_request( $task ) {

		$completed_at = $this->prop( $task, 'last_completed_at' );

		if ( empty( $completed_at ) ) {
			return false;
		}

		$completed = strtotime( $completed_at );

		if ( false === $completed ) {
			return false;
		}

		return ( strtotime( current_time( 'mysql' ) ) - $completed ) <= self::COMPLETED_WINDOW;
	}

	/**
	 * Read a property off a Fluent Boards model without tripping its magic
	 * attribute accessors on absent columns.
	 *
	 * @param object $task
	 * @param string $key
	 *
	 * @return mixed
	 */
	private function prop( $task, $key ) {

		return is_object( $task ) && isset( $task->{$key} ) ? $task->{$key} : null;
	}

	/**
	 * Only load this integration when Fluent Boards is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'FLUENT_BOARDS' );
	}
}
