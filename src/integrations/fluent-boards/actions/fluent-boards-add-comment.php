<?php

namespace Uncanny_Automator\Integrations\Fluent_Boards;

use FluentBoards\App\Models\Task;
use FluentBoards\App\Services\CommentService;
use Uncanny_Automator\Recipe\Action;

/**
 * Class Fluent_Boards_Add_Comment
 *
 * @package Uncanny_Automator\Integrations\Fluent_Boards
 *
 * @property Fluent_Boards_Helpers $item_helpers
 */
class Fluent_Boards_Add_Comment extends Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'FLUENT_BOARDS' );
		$this->set_action_code( 'FLUENT_BOARDS_ADD_COMMENT' );
		$this->set_action_meta( Fluent_Boards_Helpers::BOARD );
		$this->set_is_pro( false );
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s - task */
				esc_html_x( 'Add a comment to {{a task:%1$s}}', 'Fluent Boards', 'uncanny-automator' ),
				'FLUENT_BOARDS_TASK:' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Add a comment to {{a task}}', 'Fluent Boards', 'uncanny-automator' ) );
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
				'option_code'     => 'FLUENT_BOARDS_COMMENT',
				'label'           => esc_html_x( 'Comment', 'Fluent Boards', 'uncanny-automator' ),
				'input_type'      => 'textarea',
				'required'        => true,
				'supports_tokens' => true,
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
			'FB_COMMENT_ID'   => array(
				'name' => esc_html_x( 'Comment ID', 'Fluent Boards', 'uncanny-automator' ),
				'type' => 'int',
			),
			'FB_COMMENT_TEXT' => array(
				'name' => esc_html_x( 'Comment text', 'Fluent Boards', 'uncanny-automator' ),
				'type' => 'text',
			),
			'FB_TASK_TITLE'   => array(
				'name' => esc_html_x( 'Task title', 'Fluent Boards', 'uncanny-automator' ),
				'type' => 'text',
			),
			'FB_BOARD_TITLE'  => array(
				'name' => esc_html_x( 'Board title', 'Fluent Boards', 'uncanny-automator' ),
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

		if ( ! class_exists( '\FluentBoards\App\Services\CommentService' ) || ! class_exists( '\FluentBoards\App\Models\Task' ) ) {
			$this->add_log_error( esc_html_x( 'Fluent Boards is not active.', 'Fluent Boards', 'uncanny-automator' ) );
			return false;
		}

		$board_id = absint( $parsed[ $this->get_action_meta() ] ?? 0 );
		$task_id  = absint( $parsed['FLUENT_BOARDS_TASK'] ?? 0 );
		$text     = trim( (string) ( $parsed['FLUENT_BOARDS_COMMENT'] ?? '' ) );

		if ( 0 === $task_id || '' === $text ) {
			$this->add_log_error( esc_html_x( 'A valid task and comment text are required.', 'Fluent Boards', 'uncanny-automator' ) );
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

		if ( 0 === $board_id ) {
			$board_id = absint( $task->board_id );
		}

		$comment_data = array(
			'task_id'     => $task_id,
			'board_id'    => $board_id,
			'description' => wp_kses_post( $text ),
		);
		if ( absint( $user_id ) > 0 ) {
			$comment_data['created_by'] = absint( $user_id );
		}

		try {
			$comment = ( new CommentService() )->create( $comment_data, $task_id, $board_id );
		} catch ( \Exception $e ) {
			$this->add_log_error(
				sprintf(
					/* translators: %s - the error message */
					esc_html_x( 'The comment could not be added: %s', 'Fluent Boards', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
			return false;
		}

		if ( ! is_object( $comment ) || empty( $comment->id ) ) {
			$this->add_log_error( esc_html_x( 'The comment could not be added.', 'Fluent Boards', 'uncanny-automator' ) );
			return false;
		}

		$this->hydrate_tokens(
			array(
				'FB_COMMENT_ID'   => $comment->id,
				'FB_COMMENT_TEXT' => wp_strip_all_tags( $text ),
				'FB_TASK_TITLE'   => isset( $task->title ) ? $task->title : '',
				'FB_BOARD_TITLE'  => $this->item_helpers->board_title( $board_id ),
			)
		);

		return true;
	}
}
