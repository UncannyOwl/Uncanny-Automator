<?php

namespace Uncanny_Automator\Integrations\Fluent_Boards;

use FluentBoards\App\Models\Board;
use FluentBoards\App\Models\Label;
use FluentBoards\App\Models\Stage;
use FluentBoards\App\Models\Task;
use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Fluent_Boards_Helpers
 *
 * Shared option-data (remote_data REST handlers) and token resolvers for the
 * Fluent Boards integration.
 *
 * @package Uncanny_Automator\Integrations\Fluent_Boards
 */
class Fluent_Boards_Helpers extends Abstract_Helpers {

	/**
	 * "Any" sentinel value used by trigger dropdowns.
	 */
	const ANY = '-1';

	/**
	 * Shared option_code for the board selector. The board-dependent stage,
	 * label and task dropdowns listen on this exact code, and their handlers
	 * read the selected board via $request->get_field_value( self::BOARD ).
	 */
	const BOARD = 'FLUENT_BOARDS_BOARD';

	/**
	 * Internal action the completed/reopened trigger listens on, emitted by
	 * Fluent_Boards_Integration::load_shared_hooks(). Fluent Boards has no
	 * single native hook covering both the status-property path and the
	 * drag-into-a-closed-stage path, so the integration normalises both into
	 * this one, with the arg shape ( $task, 'closed'|'open' ).
	 */
	const TASK_STATUS_CHANGED = 'automator_fluent_boards_task_status_changed';

	/* -------------------------------------------------------------------------
	 * Remote data handlers — boards.
	 * ---------------------------------------------------------------------- */

	/**
	 * Board list for triggers (includes "Any board").
	 *
	 * @param Remote_Data_Request $request
	 *
	 * @return array
	 */
	protected function remote_data_get_boards( $request ): array {
		unset( $request );
		return $this->remote_data_success( $this->board_options( true ) );
	}

	/**
	 * Board list for actions (no "Any board").
	 *
	 * @param Remote_Data_Request $request
	 *
	 * @return array
	 */
	protected function remote_data_get_boards_strict( $request ): array {
		unset( $request );
		return $this->remote_data_success( $this->board_options( false ) );
	}

	/* -------------------------------------------------------------------------
	 * Remote data handlers — stages (dependent on board).
	 * ---------------------------------------------------------------------- */

	/**
	 * Stage list for triggers, scoped to the selected board (includes "Any stage").
	 *
	 * @param Remote_Data_Request $request
	 *
	 * @return array
	 */
	protected function remote_data_get_stages( $request ): array {
		return $this->remote_data_success( $this->stage_options( $request->get_field_value( self::BOARD ), true ) );
	}

	/**
	 * Stage list for actions, scoped to the selected board (no "Any stage").
	 *
	 * @param Remote_Data_Request $request
	 *
	 * @return array
	 */
	protected function remote_data_get_stages_strict( $request ): array {
		return $this->remote_data_success( $this->stage_options( $request->get_field_value( self::BOARD ), false ) );
	}

	/* -------------------------------------------------------------------------
	 * Remote data handlers — labels (dependent on board).
	 * ---------------------------------------------------------------------- */

	/**
	 * Label list for triggers, scoped to the selected board (includes "Any label").
	 *
	 * @param Remote_Data_Request $request
	 *
	 * @return array
	 */
	protected function remote_data_get_labels( $request ): array {
		return $this->remote_data_success( $this->label_options( $request->get_field_value( self::BOARD ), true ) );
	}

	/**
	 * Label list for actions, scoped to the selected board (no "Any label").
	 *
	 * @param Remote_Data_Request $request
	 *
	 * @return array
	 */
	protected function remote_data_get_labels_strict( $request ): array {
		return $this->remote_data_success( $this->label_options( $request->get_field_value( self::BOARD ), false ) );
	}

	/* -------------------------------------------------------------------------
	 * Remote data handlers — tasks (dependent on board, actions only).
	 * ---------------------------------------------------------------------- */

	/**
	 * Task list for actions, scoped to the selected board.
	 *
	 * @param Remote_Data_Request $request
	 *
	 * @return array
	 */
	protected function remote_data_get_tasks_strict( $request ): array {
		return $this->remote_data_success( $this->task_options( $request->get_field_value( self::BOARD ) ) );
	}

	/* -------------------------------------------------------------------------
	 * Remote data handlers — users (actions only, searchable).
	 * ---------------------------------------------------------------------- */

	/**
	 * WordPress user list for actions.
	 *
	 * @param Remote_Data_Request $request
	 *
	 * @return array
	 */
	protected function remote_data_get_users_strict( $request ): array {
		$search = is_object( $request ) && method_exists( $request, 'get_search_query' ) ? $request->get_search_query() : '';
		return $this->remote_data_success( $this->user_options( $search ) );
	}

	/* -------------------------------------------------------------------------
	 * Remote data handlers — enums.
	 * ---------------------------------------------------------------------- */

	/**
	 * Priority list for triggers (includes "Any priority").
	 *
	 * @param Remote_Data_Request $request
	 *
	 * @return array
	 */
	protected function remote_data_get_priorities( $request ): array {
		unset( $request );
		return $this->remote_data_success( $this->priority_options( true ) );
	}

	/**
	 * Priority list for actions (no "Any priority").
	 *
	 * @param Remote_Data_Request $request
	 *
	 * @return array
	 */
	protected function remote_data_get_priorities_strict( $request ): array {
		unset( $request );
		return $this->remote_data_success( $this->priority_options( false ) );
	}

	/**
	 * Status list for actions (no "Any status").
	 *
	 * @param Remote_Data_Request $request
	 *
	 * @return array
	 */
	protected function remote_data_get_statuses_strict( $request ): array {
		unset( $request );
		return $this->remote_data_success( $this->status_options() );
	}

	/**
	 * Completed / reopened direction list for the completed-or-reopened trigger.
	 *
	 * @param Remote_Data_Request $request
	 *
	 * @return array
	 */
	protected function remote_data_get_status_directions( $request ): array {
		unset( $request );
		return $this->remote_data_success(
			array(
				array(
					'text'  => esc_html_x( 'completed', 'Fluent Boards', 'uncanny-automator' ),
					'value' => 'closed',
				),
				array(
					'text'  => esc_html_x( 'reopened', 'Fluent Boards', 'uncanny-automator' ),
					'value' => 'open',
				),
			)
		);
	}

	/**
	 * Label action list (added / removed) for the label trigger (includes "Any").
	 *
	 * @param Remote_Data_Request $request
	 *
	 * @return array
	 */
	protected function remote_data_get_label_actions( $request ): array {
		unset( $request );
		return $this->remote_data_success(
			array(
				array(
					'text'  => esc_html_x( 'added to or removed from', 'Fluent Boards', 'uncanny-automator' ),
					'value' => self::ANY,
				),
				array(
					'text'  => esc_html_x( 'added to', 'Fluent Boards', 'uncanny-automator' ),
					'value' => 'added',
				),
				array(
					'text'  => esc_html_x( 'removed from', 'Fluent Boards', 'uncanny-automator' ),
					'value' => 'removed',
				),
			)
		);
	}

	/**
	 * Board member role list for the add-user-to-board action.
	 *
	 * @param Remote_Data_Request $request
	 *
	 * @return array
	 */
	protected function remote_data_get_board_roles_strict( $request ): array {
		unset( $request );
		return $this->remote_data_success(
			array(
				array(
					'text'  => esc_html_x( 'Member', 'Fluent Boards', 'uncanny-automator' ),
					'value' => 'member',
				),
				array(
					'text'  => esc_html_x( 'Viewer', 'Fluent Boards', 'uncanny-automator' ),
					'value' => 'viewer',
				),
			)
		);
	}

	/**
	 * Updatable task-property list for the update-task action.
	 *
	 * @param Remote_Data_Request $request
	 *
	 * @return array
	 */
	protected function remote_data_get_task_properties_strict( $request ): array {
		unset( $request );
		return $this->remote_data_success(
			array(
				array(
					'text'  => esc_html_x( 'Title', 'Fluent Boards', 'uncanny-automator' ),
					'value' => 'title',
				),
				array(
					'text'  => esc_html_x( 'Description', 'Fluent Boards', 'uncanny-automator' ),
					'value' => 'description',
				),
				array(
					'text'  => esc_html_x( 'Priority', 'Fluent Boards', 'uncanny-automator' ),
					'value' => 'priority',
				),
				array(
					'text'  => esc_html_x( 'Status', 'Fluent Boards', 'uncanny-automator' ),
					'value' => 'status',
				),
				array(
					'text'  => esc_html_x( 'Due date', 'Fluent Boards', 'uncanny-automator' ),
					'value' => 'due_at',
				),
			)
		);
	}

	/* -------------------------------------------------------------------------
	 * Option builders.
	 * ---------------------------------------------------------------------- */

	/**
	 * Build the board options.
	 *
	 * @param bool $include_any
	 *
	 * @return array
	 */
	private function board_options( $include_any ) {
		$options = array();
		if ( true === $include_any ) {
			$options[] = array(
				'text'  => esc_html_x( 'Any board', 'Fluent Boards', 'uncanny-automator' ),
				'value' => self::ANY,
			);
		}
		if ( ! class_exists( '\FluentBoards\App\Models\Board' ) ) {
			return $options;
		}
		foreach ( Board::orderBy( 'title' )->get() as $board ) {
			$options[] = array(
				'text'  => esc_html( $board->title ),
				'value' => (string) $board->id,
			);
		}
		return $options;
	}

	/**
	 * Build the stage options for a board.
	 *
	 * @param string $board_id
	 * @param bool   $include_any
	 *
	 * @return array
	 */
	private function stage_options( $board_id, $include_any ) {
		$options = array();
		if ( true === $include_any ) {
			$options[] = array(
				'text'  => esc_html_x( 'Any stage', 'Fluent Boards', 'uncanny-automator' ),
				'value' => self::ANY,
			);
		}
		// Cast without absint(): absint('-1') === 1 would leak the "Any board" sentinel into a board_id=1 query.
		$board_id = (int) $board_id;
		if ( $board_id <= 0 || ! class_exists( '\FluentBoards\App\Models\Stage' ) ) {
			return $options;
		}
		$stages = Stage::where( 'board_id', $board_id )
			->whereNull( 'archived_at' )
			->orderBy( 'position', 'asc' )
			->get();
		foreach ( $stages as $stage ) {
			$options[] = array(
				'text'  => esc_html( $stage->title ),
				'value' => (string) $stage->id,
			);
		}
		return $options;
	}

	/**
	 * Build the label options for a board.
	 *
	 * @param string $board_id
	 * @param bool   $include_any
	 *
	 * @return array
	 */
	private function label_options( $board_id, $include_any ) {
		$options = array();
		if ( true === $include_any ) {
			$options[] = array(
				'text'  => esc_html_x( 'Any label', 'Fluent Boards', 'uncanny-automator' ),
				'value' => self::ANY,
			);
		}
		// Cast without absint(): absint('-1') === 1 would leak the "Any board" sentinel into a board_id=1 query.
		$board_id = (int) $board_id;
		if ( $board_id <= 0 || ! class_exists( '\FluentBoards\App\Models\Label' ) ) {
			return $options;
		}
		$labels = Label::where( 'board_id', $board_id )
			->whereNull( 'archived_at' )
			->orderBy( 'title', 'asc' )
			->get();
		foreach ( $labels as $label ) {
			$options[] = array(
				'text'  => esc_html( $label->title ),
				'value' => (string) $label->id,
			);
		}
		return $options;
	}

	/**
	 * Build the task options for a board (top-level, non-archived).
	 *
	 * @param string $board_id
	 *
	 * @return array
	 */
	private function task_options( $board_id ) {
		$options = array();
		// Cast without absint(): absint('-1') === 1 would leak the "Any board" sentinel into a board_id=1 query.
		$board_id = (int) $board_id;
		if ( $board_id <= 0 || ! class_exists( '\FluentBoards\App\Models\Task' ) ) {
			return $options;
		}
		$tasks = Task::where( 'board_id', $board_id )
			->whereNull( 'parent_id' )
			->whereNull( 'archived_at' )
			->orderBy( 'title', 'asc' )
			->get( array( 'id', 'title' ) );
		foreach ( $tasks as $task ) {
			$options[] = array(
				'text'  => esc_html( $task->title ),
				'value' => (string) $task->id,
			);
		}
		return $options;
	}

	/**
	 * Build WordPress user options.
	 *
	 * @param string $search
	 *
	 * @return array
	 */
	private function user_options( $search = '' ) {
		$args = array(
			'number'  => 100,
			'orderby' => 'display_name',
			'order'   => 'ASC',
			'fields'  => array( 'ID', 'display_name', 'user_email' ),
		);
		if ( '' !== $search ) {
			$args['search']         = '*' . $search . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}
		$options = array();
		foreach ( get_users( $args ) as $user ) {
			$options[] = array(
				'text'  => esc_html( $user->display_name . ' (' . $user->user_email . ')' ),
				'value' => (string) $user->ID,
			);
		}
		return $options;
	}

	/**
	 * Build the priority options.
	 *
	 * @param bool $include_any
	 *
	 * @return array
	 */
	private function priority_options( $include_any ) {
		$options = array();
		if ( true === $include_any ) {
			$options[] = array(
				'text'  => esc_html_x( 'Any priority', 'Fluent Boards', 'uncanny-automator' ),
				'value' => self::ANY,
			);
		}
		$options[] = array(
			'text'  => esc_html_x( 'Low', 'Fluent Boards', 'uncanny-automator' ),
			'value' => 'low',
		);
		$options[] = array(
			'text'  => esc_html_x( 'Medium', 'Fluent Boards', 'uncanny-automator' ),
			'value' => 'medium',
		);
		$options[] = array(
			'text'  => esc_html_x( 'High', 'Fluent Boards', 'uncanny-automator' ),
			'value' => 'high',
		);
		return $options;
	}

	/**
	 * Build the status options (open / closed).
	 *
	 * @return array
	 */
	private function status_options() {
		return array(
			array(
				'text'  => esc_html_x( 'Open', 'Fluent Boards', 'uncanny-automator' ),
				'value' => 'open',
			),
			array(
				'text'  => esc_html_x( 'Closed', 'Fluent Boards', 'uncanny-automator' ),
				'value' => 'closed',
			),
		);
	}

	/* -------------------------------------------------------------------------
	 * Trigger-config readers.
	 * ---------------------------------------------------------------------- */

	/**
	 * Read a *required* field's saved value out of a trigger's configuration.
	 *
	 * Returns null when the key is missing or empty, so callers can fail closed.
	 * A required field has no meaningful default: defaulting an absent value to
	 * the "Any" sentinel silently widens the trigger to fire for every board,
	 * and for a filter, firing too much is the damaging direction — the recipe
	 * runs for boards the user never scoped it to, with nothing to show why.
	 *
	 * An explicitly saved '-1' is a real user choice ("Any board") and is
	 * returned as-is; only a genuinely absent value yields null. Optional fields
	 * (FLUENT_BOARDS_STAGE, FLUENT_BOARDS_LABEL_ACTION) must NOT use this — for
	 * them "absent" really does mean "no filter", so the sentinel is correct.
	 *
	 * @param array  $trigger     The trigger configuration.
	 * @param string $option_code The field's option code.
	 *
	 * @return string|null Null when unset — caller should refuse to fire.
	 */
	public function required_meta_value( $trigger, $option_code ) {

		if ( ! isset( $trigger['meta'][ $option_code ] ) ) {
			return null;
		}

		$value = (string) $trigger['meta'][ $option_code ];

		return '' === $value ? null : $value;
	}

	/* ----------------------------------------------------------------------
	 * Title resolvers.
	 * ---------------------------------------------------------------------- */

	/**
	 * Resolve a board title from its id.
	 *
	 * @param int $board_id
	 *
	 * @return string
	 */
	public function board_title( $board_id ) {
		$board_id = absint( $board_id );
		if ( 0 === $board_id || ! class_exists( '\FluentBoards\App\Models\Board' ) ) {
			return '';
		}
		$board = Board::find( $board_id );
		return $board ? (string) $board->title : '';
	}

	/**
	 * Resolve a stage title from its id.
	 *
	 * @param int $stage_id
	 *
	 * @return string
	 */
	public function stage_title( $stage_id ) {
		$stage_id = absint( $stage_id );
		if ( 0 === $stage_id || ! class_exists( '\FluentBoards\App\Models\Stage' ) ) {
			return '';
		}
		$stage = Stage::find( $stage_id );
		return $stage ? (string) $stage->title : '';
	}

	/* -------------------------------------------------------------------------
	 * Token definitions and hydration.
	 * ---------------------------------------------------------------------- */

	/**
	 * Common task token definitions (trigger format).
	 *
	 * @return array
	 */
	public function task_token_definitions() {
		return array(
			$this->token_def( 'FB_TASK_ID', esc_html_x( 'Task ID', 'Fluent Boards', 'uncanny-automator' ), 'int' ),
			$this->token_def( 'FB_TASK_TITLE', esc_html_x( 'Task title', 'Fluent Boards', 'uncanny-automator' ), 'text' ),
			$this->token_def( 'FB_TASK_DESCRIPTION', esc_html_x( 'Task description', 'Fluent Boards', 'uncanny-automator' ), 'text' ),
			$this->token_def( 'FB_TASK_STATUS', esc_html_x( 'Task status', 'Fluent Boards', 'uncanny-automator' ), 'text' ),
			$this->token_def( 'FB_TASK_PRIORITY', esc_html_x( 'Task priority', 'Fluent Boards', 'uncanny-automator' ), 'text' ),
			$this->token_def( 'FB_TASK_DUE_DATE', esc_html_x( 'Task due date', 'Fluent Boards', 'uncanny-automator' ), 'text' ),
			$this->token_def( 'FB_BOARD_ID', esc_html_x( 'Board ID', 'Fluent Boards', 'uncanny-automator' ), 'int' ),
			$this->token_def( 'FB_BOARD_TITLE', esc_html_x( 'Board title', 'Fluent Boards', 'uncanny-automator' ), 'text' ),
			$this->token_def( 'FB_STAGE_TITLE', esc_html_x( 'Stage title', 'Fluent Boards', 'uncanny-automator' ), 'text' ),
			$this->token_def( 'FB_TASK_CREATED_BY', esc_html_x( 'Task created by (user ID)', 'Fluent Boards', 'uncanny-automator' ), 'int' ),
		);
	}

	/**
	 * Hydrate the common task tokens from a task model.
	 *
	 * @param object $task
	 *
	 * @return array
	 */
	public function hydrate_task_tokens( $task ) {
		if ( ! is_object( $task ) ) {
			return $this->empty_keyset( $this->task_token_definitions() );
		}
		$board_id = isset( $task->board_id ) ? $task->board_id : 0;
		$stage_id = isset( $task->stage_id ) ? $task->stage_id : 0;
		return array(
			'FB_TASK_ID'          => isset( $task->id ) ? $task->id : '',
			'FB_TASK_TITLE'       => isset( $task->title ) ? $task->title : '',
			'FB_TASK_DESCRIPTION' => isset( $task->description ) ? wp_strip_all_tags( (string) $task->description ) : '',
			'FB_TASK_STATUS'      => isset( $task->status ) ? $task->status : '',
			'FB_TASK_PRIORITY'    => isset( $task->priority ) ? $task->priority : '',
			'FB_TASK_DUE_DATE'    => isset( $task->due_at ) ? $task->due_at : '',
			'FB_BOARD_ID'         => $board_id,
			'FB_BOARD_TITLE'      => $this->board_title( $board_id ),
			'FB_STAGE_TITLE'      => $this->stage_title( $stage_id ),
			'FB_TASK_CREATED_BY'  => isset( $task->created_by ) ? $task->created_by : '',
		);
	}

	/**
	 * Board token definitions (trigger format).
	 *
	 * @return array
	 */
	public function board_token_definitions() {
		return array(
			$this->token_def( 'FB_BOARD_ID', esc_html_x( 'Board ID', 'Fluent Boards', 'uncanny-automator' ), 'int' ),
			$this->token_def( 'FB_BOARD_TITLE', esc_html_x( 'Board title', 'Fluent Boards', 'uncanny-automator' ), 'text' ),
			$this->token_def( 'FB_BOARD_DESCRIPTION', esc_html_x( 'Board description', 'Fluent Boards', 'uncanny-automator' ), 'text' ),
			$this->token_def( 'FB_BOARD_TYPE', esc_html_x( 'Board type', 'Fluent Boards', 'uncanny-automator' ), 'text' ),
			$this->token_def( 'FB_BOARD_CREATED_BY', esc_html_x( 'Board created by (user ID)', 'Fluent Boards', 'uncanny-automator' ), 'int' ),
		);
	}

	/**
	 * Hydrate board tokens from a board model.
	 *
	 * @param object $board
	 *
	 * @return array
	 */
	public function hydrate_board_tokens( $board ) {
		if ( ! is_object( $board ) ) {
			return $this->empty_keyset( $this->board_token_definitions() );
		}
		return array(
			'FB_BOARD_ID'          => isset( $board->id ) ? $board->id : '',
			'FB_BOARD_TITLE'       => isset( $board->title ) ? $board->title : '',
			'FB_BOARD_DESCRIPTION' => isset( $board->description ) ? wp_strip_all_tags( (string) $board->description ) : '',
			'FB_BOARD_TYPE'        => isset( $board->type ) ? $board->type : '',
			'FB_BOARD_CREATED_BY'  => isset( $board->created_by ) ? $board->created_by : '',
		);
	}

	/**
	 * Assigned/removed user token definitions (trigger format).
	 *
	 * @return array
	 */
	public function user_token_definitions() {
		return array(
			$this->token_def( 'FB_USER_ID', esc_html_x( 'User ID', 'Fluent Boards', 'uncanny-automator' ), 'int' ),
			$this->token_def( 'FB_USER_DISPLAY_NAME', esc_html_x( 'User display name', 'Fluent Boards', 'uncanny-automator' ), 'text' ),
			$this->token_def( 'FB_USER_EMAIL', esc_html_x( 'User email', 'Fluent Boards', 'uncanny-automator' ), 'email' ),
		);
	}

	/**
	 * Hydrate user tokens from a WordPress user id.
	 *
	 * @param int $user_id
	 *
	 * @return array
	 */
	public function hydrate_user_tokens( $user_id ) {
		$user_id = absint( $user_id );
		$user    = $user_id ? get_userdata( $user_id ) : false;
		return array(
			'FB_USER_ID'           => $user_id ? $user_id : '',
			'FB_USER_DISPLAY_NAME' => $user ? $user->display_name : '',
			'FB_USER_EMAIL'        => $user ? $user->user_email : '',
		);
	}

	/**
	 * Comment token definitions (trigger format).
	 *
	 * @return array
	 */
	public function comment_token_definitions() {
		return array(
			$this->token_def( 'FB_COMMENT_ID', esc_html_x( 'Comment ID', 'Fluent Boards', 'uncanny-automator' ), 'int' ),
			$this->token_def( 'FB_COMMENT_TEXT', esc_html_x( 'Comment text', 'Fluent Boards', 'uncanny-automator' ), 'text' ),
			$this->token_def( 'FB_COMMENT_AUTHOR', esc_html_x( 'Comment author (user ID)', 'Fluent Boards', 'uncanny-automator' ), 'int' ),
			$this->token_def( 'FB_TASK_ID', esc_html_x( 'Task ID', 'Fluent Boards', 'uncanny-automator' ), 'int' ),
			$this->token_def( 'FB_BOARD_ID', esc_html_x( 'Board ID', 'Fluent Boards', 'uncanny-automator' ), 'int' ),
			$this->token_def( 'FB_BOARD_TITLE', esc_html_x( 'Board title', 'Fluent Boards', 'uncanny-automator' ), 'text' ),
		);
	}

	/**
	 * Hydrate comment tokens from a comment model.
	 *
	 * @param object $comment
	 *
	 * @return array
	 */
	public function hydrate_comment_tokens( $comment ) {
		if ( ! is_object( $comment ) ) {
			return $this->empty_keyset( $this->comment_token_definitions() );
		}
		$board_id = isset( $comment->board_id ) ? $comment->board_id : 0;
		return array(
			'FB_COMMENT_ID'     => isset( $comment->id ) ? $comment->id : '',
			'FB_COMMENT_TEXT'   => isset( $comment->description ) ? wp_strip_all_tags( (string) $comment->description ) : '',
			'FB_COMMENT_AUTHOR' => isset( $comment->created_by ) ? $comment->created_by : '',
			'FB_TASK_ID'        => isset( $comment->task_id ) ? $comment->task_id : '',
			'FB_BOARD_ID'       => $board_id,
			'FB_BOARD_TITLE'    => $this->board_title( $board_id ),
		);
	}

	/**
	 * Label token definitions (trigger format).
	 *
	 * @return array
	 */
	public function label_token_definitions() {
		return array(
			$this->token_def( 'FB_LABEL_ID', esc_html_x( 'Label ID', 'Fluent Boards', 'uncanny-automator' ), 'int' ),
			$this->token_def( 'FB_LABEL_TITLE', esc_html_x( 'Label title', 'Fluent Boards', 'uncanny-automator' ), 'text' ),
			$this->token_def( 'FB_LABEL_ACTION', esc_html_x( 'Action (added/removed)', 'Fluent Boards', 'uncanny-automator' ), 'text' ),
			$this->token_def( 'FB_TASK_ID', esc_html_x( 'Task ID', 'Fluent Boards', 'uncanny-automator' ), 'int' ),
			$this->token_def( 'FB_TASK_TITLE', esc_html_x( 'Task title', 'Fluent Boards', 'uncanny-automator' ), 'text' ),
			$this->token_def( 'FB_BOARD_ID', esc_html_x( 'Board ID', 'Fluent Boards', 'uncanny-automator' ), 'int' ),
			$this->token_def( 'FB_BOARD_TITLE', esc_html_x( 'Board title', 'Fluent Boards', 'uncanny-automator' ), 'text' ),
		);
	}

	/**
	 * Hydrate label tokens from task + label models and the action string.
	 *
	 * @param object $task
	 * @param object $label
	 * @param string $action
	 *
	 * @return array
	 */
	public function hydrate_label_tokens( $task, $label, $action ) {
		$board_id = is_object( $task ) && isset( $task->board_id ) ? $task->board_id : 0;
		return array(
			'FB_LABEL_ID'     => is_object( $label ) && isset( $label->id ) ? $label->id : '',
			'FB_LABEL_TITLE'  => is_object( $label ) && isset( $label->title ) ? $label->title : '',
			'FB_LABEL_ACTION' => (string) $action,
			'FB_TASK_ID'      => is_object( $task ) && isset( $task->id ) ? $task->id : '',
			'FB_TASK_TITLE'   => is_object( $task ) && isset( $task->title ) ? $task->title : '',
			'FB_BOARD_ID'     => $board_id,
			'FB_BOARD_TITLE'  => $this->board_title( $board_id ),
		);
	}

	/* -------------------------------------------------------------------------
	 * Token helpers.
	 * ---------------------------------------------------------------------- */

	/**
	 * Build a single trigger token definition.
	 *
	 * @param string $id
	 * @param string $name
	 * @param string $type
	 *
	 * @return array
	 */
	private function token_def( $id, $name, $type ) {
		return array(
			'tokenId'   => $id,
			'tokenName' => $name,
			'tokenType' => $type,
		);
	}

	/**
	 * Return every token id in a definition list mapped to an empty string.
	 *
	 * @param array $definitions
	 *
	 * @return array
	 */
	private function empty_keyset( $definitions ) {
		$keyset = array();
		foreach ( $definitions as $definition ) {
			$keyset[ $definition['tokenId'] ] = '';
		}
		return $keyset;
	}
}
