<?php

namespace Uncanny_Automator\Integrations\ClickUp;

use Exception;

/**
 * Action: Delete a task.
 *
 * @package Uncanny_Automator\Integrations\ClickUp
 *
 * @property ClickUp_App_Helpers $helpers
 * @property ClickUp_Api_Caller $api
 */
class Space_List_Task_Delete extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'CLICKUP' );
		$this->set_action_code( 'CLICKUP_SPACE_LIST_TASK_DELETE' );
		$this->set_action_meta( 'CLICKUP_SPACE_LIST_TASK_DELETE_META' );
		$this->set_requires_user( false );
		$this->set_is_pro( false );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/clickup/' ) );
		$this->set_readable_sentence( esc_attr_x( 'Delete {{a task}}', 'ClickUp', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: Task name
				esc_attr_x( 'Delete {{a task:%1$s}}', 'ClickUp', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => esc_attr_x( 'Task ID', 'ClickUp', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action data.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        Additional arguments.
	 * @param array $parsed      Parsed action data.
	 *
	 * @return bool
	 * @throws Exception If the action fails.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$task_id = sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' );

		if ( empty( absint( str_replace( '#', '', $task_id ) ) ) ) {
			throw new Exception(
				esc_html_x( 'Client error: Task ID is empty. Check token value if using token for the Task ID field.', 'ClickUp', 'uncanny-automator' )
			);
		}

		$this->api->api_request(
			array(
				'action'  => 'task_delete',
				'task_id' => $task_id,
			),
			$action_data
		);

		return true;
	}
}
