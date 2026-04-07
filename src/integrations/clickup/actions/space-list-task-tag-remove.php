<?php

namespace Uncanny_Automator\Integrations\ClickUp;

use Exception;

/**
 * Action: Remove a tag from a task.
 *
 * @package Uncanny_Automator\Integrations\ClickUp
 *
 * @property ClickUp_App_Helpers $helpers
 * @property ClickUp_Api_Caller $api
 */
class Space_List_Task_Tag_Remove extends \Uncanny_Automator\Recipe\App_Action {

	use ClickUp_Hierarchy_Options;

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'CLICKUP' );
		$this->set_action_code( 'CLICKUP_SPACE_LIST_TASK_TAG_REMOVE' );
		$this->set_action_meta( 'CLICKUP_SPACE_LIST_TASK_TAG_REMOVE_META' );
		$this->set_requires_user( false );
		$this->set_is_pro( false );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/clickup/' ) );
		$this->set_readable_sentence( esc_attr_x( 'Remove {{a tag}} from a task', 'ClickUp', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: Tag name
				esc_attr_x( 'Remove {{a tag:%1$s}} from a task', 'ClickUp', 'uncanny-automator' ),
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
			$this->get_team_option_config(),
			$this->get_space_option_config(),
			$this->get_folder_option_config(),
			$this->helpers->get_list_option_config(),
			$this->helpers->get_task_option_config(),
			$this->helpers->get_tag_option_config( $this->get_action_meta() ),
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
			'action'   => 'task_remove_tag',
			'task_id'  => sanitize_text_field( $parsed[ $this->helpers->get_const( 'META_TASK' ) ] ?? '' ),
			'tag_name' => sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' ),
		);

		$this->api->api_request( $body, $action_data );

		return true;
	}
}
