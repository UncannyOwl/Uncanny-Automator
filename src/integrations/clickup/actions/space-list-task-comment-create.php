<?php

namespace Uncanny_Automator\Integrations\ClickUp;

use Exception;

/**
 * Action: Add a comment to a task.
 *
 * @package Uncanny_Automator\Integrations\ClickUp
 *
 * @property ClickUp_App_Helpers $helpers
 * @property ClickUp_Api_Caller $api
 */
class Space_List_Task_Comment_Create extends \Uncanny_Automator\Recipe\App_Action {

	use ClickUp_Hierarchy_Options;

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'CLICKUP' );
		$this->set_action_code( 'CLICKUP_SPACE_LIST_TASK_COMMENT_CREATE' );
		$this->set_action_meta( 'CLICKUP_SPACE_LIST_TASK_COMMENT_CREATE_META' );
		$this->set_requires_user( false );
		$this->set_is_pro( false );
		$this->set_background_processing( true );
		$this->set_wpautop( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/clickup/' ) );
		$this->set_readable_sentence( esc_attr_x( 'Add {{a comment}} to a task', 'ClickUp', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: Comment text
				esc_attr_x( 'Add {{a comment:%1$s}} to a task', 'ClickUp', 'uncanny-automator' ),
				'COMMENT_TEXT:' . $this->get_action_meta()
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
			$this->get_team_option_config(),
			$this->get_space_option_config(),
			$this->get_folder_option_config(),
			$this->helpers->get_list_option_config(),
			$this->helpers->get_assignee_option_config(),
			$this->helpers->get_task_option_config( $this->get_action_meta(), $this->helpers->get_const( 'META_LIST' ) ),
			array(
				'option_code' => 'COMMENT_TEXT',
				'label'       => esc_attr_x( 'Comment text', 'ClickUp', 'uncanny-automator' ),
				'input_type'  => 'textarea',
				'required'    => true,
			),
			array(
				'option_code' => 'NOTIFY_ALL',
				'label'       => esc_attr_x( 'Notify all', 'ClickUp', 'uncanny-automator' ),
				'input_type'  => 'checkbox',
				'required'    => false,
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
		$body = array(
			'action'       => 'task_add_comment',
			'task_id'      => sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' ),
			'comment_text' => sanitize_textarea_field( $parsed['COMMENT_TEXT'] ?? '' ),
			'notify_all'   => sanitize_text_field( $parsed['NOTIFY_ALL'] ?? 'false' ),
			'assignee'     => sanitize_text_field( $parsed['ASSIGNEE'] ?? '' ),
		);

		$this->api->api_request( $body, $action_data );

		return true;
	}
}
