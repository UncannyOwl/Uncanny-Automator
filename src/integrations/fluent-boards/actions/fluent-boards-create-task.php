<?php

namespace Uncanny_Automator\Integrations\Fluent_Boards;

use Uncanny_Automator\Recipe\Action;

/**
 * Class Fluent_Boards_Create_Task
 *
 * @package Uncanny_Automator\Integrations\Fluent_Boards
 *
 * @property Fluent_Boards_Helpers $item_helpers
 */
class Fluent_Boards_Create_Task extends Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'FLUENT_BOARDS' );
		$this->set_action_code( 'FLUENT_BOARDS_CREATE_TASK' );
		$this->set_action_meta( Fluent_Boards_Helpers::BOARD );
		$this->set_is_pro( false );
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s - board */
				esc_html_x( 'Create a task on {{a board:%1$s}}', 'Fluent Boards', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Create a task on {{a board}}', 'Fluent Boards', 'uncanny-automator' ) );
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
				'option_code'     => 'FLUENT_BOARDS_STAGE',
				'label'           => esc_html_x( 'Stage', 'Fluent Boards', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_parent_config( 'stages_strict', array( Fluent_Boards_Helpers::BOARD ) ),
			),
			array(
				'option_code' => 'FLUENT_BOARDS_TITLE',
				'label'       => esc_html_x( 'Title', 'Fluent Boards', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
			),
			array(
				'option_code' => 'FLUENT_BOARDS_DESCRIPTION',
				'label'       => esc_html_x( 'Description', 'Fluent Boards', 'uncanny-automator' ),
				'input_type'  => 'textarea',
				'required'    => false,
			),
			array(
				'option_code'     => 'FLUENT_BOARDS_PRIORITY',
				'label'           => esc_html_x( 'Priority', 'Fluent Boards', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => false,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'priorities_strict' ),
			),
			array(
				'option_code'     => 'FLUENT_BOARDS_STATUS',
				'label'           => esc_html_x( 'Status', 'Fluent Boards', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => false,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'statuses_strict' ),
			),
			array(
				'option_code'     => 'FLUENT_BOARDS_DUE_DATE',
				'label'           => esc_html_x( 'Due date', 'Fluent Boards', 'uncanny-automator' ),
				'input_type'      => 'text',
				'required'        => false,
				'supports_tokens' => true,
				'description'     => esc_html_x( 'Accepts a date (Y-m-d H:i:s) or a token.', 'Fluent Boards', 'uncanny-automator' ),
			),
			array(
				'option_code'              => 'FLUENT_BOARDS_ASSIGNEES',
				'label'                    => esc_html_x( 'Assignees', 'Fluent Boards', 'uncanny-automator' ),
				'input_type'               => 'select',
				'required'                 => false,
				'supports_multiple_values' => true,
				'options'                  => array(),
				'relevant_tokens'          => array(),
				'remote_data'              => $this->item_helpers->remote_data_search_config( 'users_strict' ),
			),
			array(
				'option_code'              => 'FLUENT_BOARDS_LABELS',
				'label'                    => esc_html_x( 'Labels', 'Fluent Boards', 'uncanny-automator' ),
				'input_type'               => 'select',
				'required'                 => false,
				'supports_multiple_values' => true,
				'options'                  => array(),
				'relevant_tokens'          => array(),
				'remote_data'              => $this->item_helpers->remote_data_parent_config( 'labels_strict', array( Fluent_Boards_Helpers::BOARD ) ),
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
			'FB_TASK_ID'     => array(
				'name' => esc_html_x( 'Task ID', 'Fluent Boards', 'uncanny-automator' ),
				'type' => 'int',
			),
			'FB_TASK_TITLE'  => array(
				'name' => esc_html_x( 'Task title', 'Fluent Boards', 'uncanny-automator' ),
				'type' => 'text',
			),
			'FB_BOARD_TITLE' => array(
				'name' => esc_html_x( 'Board title', 'Fluent Boards', 'uncanny-automator' ),
				'type' => 'text',
			),
			'FB_STAGE_TITLE' => array(
				'name' => esc_html_x( 'Stage title', 'Fluent Boards', 'uncanny-automator' ),
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

		if ( ! function_exists( 'FluentBoardsApi' ) ) {
			$this->add_log_error( esc_html_x( 'Fluent Boards is not active.', 'Fluent Boards', 'uncanny-automator' ) );
			return false;
		}

		$board_id = absint( $parsed[ $this->get_action_meta() ] ?? 0 );
		$stage_id = absint( $parsed['FLUENT_BOARDS_STAGE'] ?? 0 );
		$title    = sanitize_text_field( $parsed['FLUENT_BOARDS_TITLE'] ?? '' );

		if ( 0 === $board_id || 0 === $stage_id || '' === $title ) {
			$this->add_log_error( esc_html_x( 'Board, stage and title are required.', 'Fluent Boards', 'uncanny-automator' ) );
			return false;
		}

		// Assignees and labels keys must always be present (the API reads them unconditionally).
		$data = array(
			'title'     => $title,
			'board_id'  => $board_id,
			'stage_id'  => $stage_id,
			'assignees' => $this->to_id_array( $parsed['FLUENT_BOARDS_ASSIGNEES'] ?? '' ),
			'labels'    => $this->to_id_array( $parsed['FLUENT_BOARDS_LABELS'] ?? '' ),
		);

		$description = $parsed['FLUENT_BOARDS_DESCRIPTION'] ?? '';
		if ( '' !== $description ) {
			$data['description'] = wp_kses_post( $description );
		}
		$priority = sanitize_text_field( $parsed['FLUENT_BOARDS_PRIORITY'] ?? '' );
		if ( '' !== $priority ) {
			$data['priority'] = $priority;
		}
		$status = sanitize_text_field( $parsed['FLUENT_BOARDS_STATUS'] ?? '' );
		if ( '' !== $status ) {
			$data['status'] = $status;
		}
		$due_date = sanitize_text_field( $parsed['FLUENT_BOARDS_DUE_DATE'] ?? '' );
		if ( '' !== $due_date ) {
			$data['due_at'] = $due_date;
		}

		$task = FluentBoardsApi( 'tasks' )->create( $data );

		if ( ! is_object( $task ) || empty( $task->id ) ) {
			$this->add_log_error( esc_html_x( 'The task could not be created.', 'Fluent Boards', 'uncanny-automator' ) );
			return false;
		}

		$this->hydrate_tokens(
			array(
				'FB_TASK_ID'     => $task->id,
				'FB_TASK_TITLE'  => $task->title,
				'FB_BOARD_TITLE' => $this->item_helpers->board_title( $board_id ),
				'FB_STAGE_TITLE' => $this->item_helpers->stage_title( $stage_id ),
			)
		);

		return true;
	}

	/**
	 * Normalize a comma-separated, JSON, or array field value into a list of ints.
	 *
	 * @param mixed $value
	 *
	 * @return array
	 */
	private function to_id_array( $value ) {
		if ( is_array( $value ) ) {
			$ids = $value;
		} else {
			$decoded = json_decode( (string) $value, true );
			$ids     = is_array( $decoded ) ? $decoded : explode( ',', (string) $value );
		}
		return array_values( array_filter( array_map( 'absint', $ids ) ) );
	}
}
