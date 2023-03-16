<?php
namespace Uncanny_Automator;

/**
 * Class Space_List_Task_Tag_Add
 *
 * @package Uncanny_Automator
 */
class Space_List_Task_Comment_Create {

	use Recipe\Actions;

	/**
	 * Method __construct
	 *
	 * @return void
	 */
	public function __construct() {

		$this->setup_action();

		$this->set_helpers( new ClickUp_Helpers() );

	}

	/**
	 * Setups the Action.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->set_integration( 'CLICKUP' );

		$this->set_action_code( 'CLICKUP_SPACE_LIST_TASK_COMMENT_CREATE' );

		$this->set_action_meta( 'CLICKUP_SPACE_LIST_TASK_COMMENT_CREATE_META' );

		$this->set_is_pro( false );

		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/clickup/' ) );

		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: Action sentence */
				esc_attr__( 'Add {{a comment:%1$s}} to a task', 'uncanny-automator' ),
				'COMMENT_TEXT:' . $this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_attr__( 'Add {{a comment}} to a task', 'uncanny-automator' ) );

		$this->set_options_callback( array( $this, 'load_options' ) );

		$this->set_background_processing( true );

		$this->set_wpautop( false );

		$this->register_action();

	}

	/**
	 * Loads options.
	 *
	 * @return void.
	 */
	public function load_options() {

		return Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => array(
					$this->get_action_meta() => $this->get_helpers()->get_action_fields( $this, 'space-list-task-comment-create-fields' ),
				),
			)
		);

	}

	/**
	 * Processes the action.
	 *
	 * @return void.
	 */
	public function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		try {

			$body = array(
				'action'       => 'task_add_comment',
				'task_id'      => isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : 0,
				'comment_text' => isset( $parsed['COMMENT_TEXT'] ) ? sanitize_textarea_field( $parsed['COMMENT_TEXT'] ) : '',
				'notify_all'   => isset( $parsed['NOTIFY_ALL'] ) ? sanitize_text_field( $parsed['NOTIFY_ALL'] ) : 'false',
				'assignee'     => isset( $parsed['ASSIGNEE'] ) ? sanitize_text_field( $parsed['ASSIGNEE'] ) : 0,
			);

			$response = $this->get_helpers()->api_request(
				$this->get_helpers()->get_client(),
				$body,
				$action_data
			);

			Automator()->complete->action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {

			$action_data['complete_with_errors'] = true;

			Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );

		}

	}

}
