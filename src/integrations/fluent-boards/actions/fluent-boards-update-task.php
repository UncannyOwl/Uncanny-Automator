<?php

namespace Uncanny_Automator\Integrations\Fluent_Boards;

use FluentBoards\App\Models\Task;
use FluentBoards\App\Services\TaskService;
use Uncanny_Automator\Recipe\Action;

/**
 * Class Fluent_Boards_Update_Task
 *
 * @package Uncanny_Automator\Integrations\Fluent_Boards
 *
 * @property Fluent_Boards_Helpers $item_helpers
 */
class Fluent_Boards_Update_Task extends Action {

	/**
	 * Whitelisted, updatable task columns.
	 *
	 * @var array
	 */
	private $allowed_properties = array( 'title', 'description', 'priority', 'status', 'due_at' );

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'FLUENT_BOARDS' );
		$this->set_action_code( 'FLUENT_BOARDS_UPDATE_TASK' );
		$this->set_action_meta( Fluent_Boards_Helpers::BOARD );
		$this->set_is_pro( false );
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s - task */
				esc_html_x( "Update {{a task's:%1\$s}} properties", 'Fluent Boards', 'uncanny-automator' ),
				'FLUENT_BOARDS_TASK:' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( "Update {{a task's}} properties", 'Fluent Boards', 'uncanny-automator' ) );
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
				'option_code'     => 'FLUENT_BOARDS_TASK',
				'label'           => esc_html_x( 'Task', 'Fluent Boards', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_parent_config( 'tasks_strict', array( Fluent_Boards_Helpers::BOARD ) ),
			),
			array(
				'option_code'     => 'FLUENT_BOARDS_PROPERTY',
				'label'           => esc_html_x( 'Property to update', 'Fluent Boards', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'task_properties_strict' ),
			),
			array(
				'option_code'     => 'FLUENT_BOARDS_VALUE',
				'label'           => esc_html_x( 'New value', 'Fluent Boards', 'uncanny-automator' ),
				'input_type'      => 'text',
				'required'        => true,
				'supports_tokens' => true,
				'description'     => esc_html_x( 'Priority: low, medium or high. Status: open or closed. Due date: Y-m-d H:i:s.', 'Fluent Boards', 'uncanny-automator' ),
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
			'FB_TASK_ID'          => array(
				'name' => esc_html_x( 'Task ID', 'Fluent Boards', 'uncanny-automator' ),
				'type' => 'int',
			),
			'FB_TASK_TITLE'       => array(
				'name' => esc_html_x( 'Task title', 'Fluent Boards', 'uncanny-automator' ),
				'type' => 'text',
			),
			'FB_UPDATED_PROPERTY' => array(
				'name' => esc_html_x( 'Updated property', 'Fluent Boards', 'uncanny-automator' ),
				'type' => 'text',
			),
			'FB_OLD_VALUE'        => array(
				'name' => esc_html_x( 'Old value', 'Fluent Boards', 'uncanny-automator' ),
				'type' => 'text',
			),
			'FB_NEW_VALUE'        => array(
				'name' => esc_html_x( 'New value', 'Fluent Boards', 'uncanny-automator' ),
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

		if ( ! class_exists( '\FluentBoards\App\Services\TaskService' ) || ! class_exists( '\FluentBoards\App\Models\Task' ) ) {
			$this->add_log_error( esc_html_x( 'Fluent Boards is not active.', 'Fluent Boards', 'uncanny-automator' ) );
			return false;
		}

		$board_id = absint( $parsed[ $this->get_action_meta() ] ?? 0 );
		$task_id  = absint( $parsed['FLUENT_BOARDS_TASK'] ?? 0 );
		$property = sanitize_text_field( $parsed['FLUENT_BOARDS_PROPERTY'] ?? '' );
		$value    = (string) ( $parsed['FLUENT_BOARDS_VALUE'] ?? '' );

		if ( 0 === $task_id || '' === $property ) {
			$this->add_log_error( esc_html_x( 'A valid task and property are required.', 'Fluent Boards', 'uncanny-automator' ) );
			return false;
		}

		if ( ! in_array( $property, $this->allowed_properties, true ) ) {
			$this->add_log_error(
				sprintf(
					/* translators: %s - the unsupported property name */
					esc_html_x( 'Unsupported property: %s.', 'Fluent Boards', 'uncanny-automator' ),
					$property
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

		$old_value = isset( $task->{$property} ) ? (string) $task->{$property} : '';
		$value     = ( 'description' === $property ) ? wp_kses_post( $value ) : sanitize_text_field( $value );

		// TaskService::updateTaskProperty() signature is ( $col, $value, $task ).
		( new TaskService() )->updateTaskProperty( $property, $value, $task );

		$this->hydrate_tokens(
			array(
				'FB_TASK_ID'          => $task->id,
				'FB_TASK_TITLE'       => isset( $task->title ) ? $task->title : '',
				'FB_UPDATED_PROPERTY' => $property,
				'FB_OLD_VALUE'        => $old_value,
				'FB_NEW_VALUE'        => $value,
			)
		);

		return true;
	}
}
