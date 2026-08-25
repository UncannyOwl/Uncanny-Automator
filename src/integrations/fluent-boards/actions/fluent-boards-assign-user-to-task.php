<?php

namespace Uncanny_Automator\Integrations\Fluent_Boards;

use FluentBoards\App\Models\Task;
use Uncanny_Automator\Recipe\Action;

/**
 * Class Fluent_Boards_Assign_User_To_Task
 *
 * @package Uncanny_Automator\Integrations\Fluent_Boards
 *
 * @property Fluent_Boards_Helpers $item_helpers
 */
class Fluent_Boards_Assign_User_To_Task extends Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'FLUENT_BOARDS' );
		$this->set_action_code( 'FLUENT_BOARDS_ASSIGN_USER_TO_TASK' );
		$this->set_action_meta( Fluent_Boards_Helpers::BOARD );
		$this->set_is_pro( false );
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s - user, %2$s - task */
				esc_html_x( 'Assign {{a user:%1$s}} to {{a task:%2$s}}', 'Fluent Boards', 'uncanny-automator' ),
				'FLUENT_BOARDS_USER:' . $this->get_action_meta(),
				'FLUENT_BOARDS_TASK:' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Assign {{a user}} to {{a task}}', 'Fluent Boards', 'uncanny-automator' ) );
	}

	/**
	 * Options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_action_meta(),
				'label'           => esc_html_x( 'Board', 'Fluent Boards', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'boards_strict' ),
			),
			array(
				'option_code'     => 'FLUENT_BOARDS_USER',
				'label'           => esc_html_x( 'User', 'Fluent Boards', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_search_config( 'users_strict' ),
			),
			array(
				'option_code'     => 'FLUENT_BOARDS_TASK',
				'label'           => esc_html_x( 'Task', 'Fluent Boards', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_parent_config( 'tasks_strict', array( Fluent_Boards_Helpers::BOARD ) ),
			),
		);
	}

	/**
	 * Define tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'FB_TASK_TITLE'        => array(
				'name' => esc_html_x( 'Task title', 'Fluent Boards', 'uncanny-automator' ),
				'type' => 'text',
			),
			'FB_USER_DISPLAY_NAME' => array(
				'name' => esc_html_x( 'User display name', 'Fluent Boards', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Process action.
	 *
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		if ( ! class_exists( '\FluentBoards\App\Models\Task' ) ) {
			$this->add_log_error( esc_html_x( 'Fluent Boards is not active.', 'Fluent Boards', 'uncanny-automator' ) );
			return false;
		}

		$board_id    = absint( $parsed[ $this->get_action_meta() ] ?? 0 );
		$assignee_id = absint( $parsed['FLUENT_BOARDS_USER'] ?? 0 );
		$task_id     = absint( $parsed['FLUENT_BOARDS_TASK'] ?? 0 );

		if ( 0 === $task_id || 0 === $assignee_id ) {
			$this->add_log_error( esc_html_x( 'A valid task and user are required.', 'Fluent Boards', 'uncanny-automator' ) );
			return false;
		}

		if ( ! get_userdata( $assignee_id ) ) {
			$this->add_log_error(
				sprintf(
					/* translators: %d - the user ID */
					esc_html_x( 'A valid user is required. Got user ID %d.', 'Fluent Boards', 'uncanny-automator' ),
					$assignee_id
				)
			);
			return false;
		}

		$task = Task::find( $task_id );
		if ( ! is_object( $task ) || empty( $task->id ) ) {
			$this->add_log_error(
				sprintf(
					/* translators: %d - the task ID */
					esc_html_x( 'Task %d was not found.', 'Fluent Boards', 'uncanny-automator' ),
					$task_id
				)
			);
			return false;
		}

		if ( 0 !== $board_id && absint( $task->board_id ) !== $board_id ) {
			$this->add_log_error(
				sprintf(
					/* translators: 1: Task ID, 2: Board ID */
					esc_html_x( 'Task %1$d does not belong to board %2$d.', 'Fluent Boards', 'uncanny-automator' ),
					$task_id,
					$board_id
				)
			);
			return false;
		}

		// addOrRemoveAssignee() is a toggle. Only call it when the user is not
		// already assigned so this action never accidentally un-assigns them.
		$assigned_ids = $task->assignees->pluck( 'ID' )->toArray();
		if ( ! in_array( $assignee_id, array_map( 'absint', $assigned_ids ), true ) ) {
			$task->addOrRemoveAssignee( $assignee_id );
		}

		$member = get_userdata( $assignee_id );

		$this->hydrate_tokens(
			array(
				'FB_TASK_TITLE'        => isset( $task->title ) ? $task->title : '',
				'FB_USER_DISPLAY_NAME' => $member ? $member->display_name : '',
			)
		);

		return true;
	}
}
