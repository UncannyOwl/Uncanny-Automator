<?php

namespace Uncanny_Automator\Integrations\Asana;

use Exception;

/**
 * Action: Remove {{a tag}} from {{a task}} in {{a specific project}}
 *
 * @package Uncanny_Automator\Integrations\Asana
 *
 * @property Asana_App_Helpers $helpers
 * @property Asana_Api_Caller $api
 */
class ASANA_REMOVE_TAG_TASK extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Workspace meta key.
	 *
	 * @var string
	 */
	private $workspace_meta_key;

	/**
	 * Project meta key.
	 *
	 * @var string
	 */
	private $project_meta_key;

	/**
	 * Task meta key.
	 *
	 * @var string
	 */
	private $task_meta_key;

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->workspace_meta_key = $this->helpers->get_const( 'ACTION_WORKSPACE_META_KEY' );
		$this->project_meta_key   = $this->helpers->get_const( 'ACTION_PROJECT_META_KEY' );
		$this->task_meta_key      = $this->helpers->get_const( 'ACTION_TASK_META_KEY' );

		$this->set_integration( 'ASANA' );
		$this->set_action_code( 'ASANA_REMOVE_TAG_TASK_CODE' );
		$this->set_action_meta( 'ASANA_REMOVE_TAG_TASK_META' );
		$this->set_requires_user( false );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/asana-integration/' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the tag name, %2$s is the task name, %3$s is the project name
				esc_attr_x( 'Remove {{a tag:%1$s}} from {{a task:%2$s}} in {{a specific project:%3$s}}', 'Asana', 'uncanny-automator' ),
				$this->get_action_meta(),
				$this->task_meta_key . ':' . $this->get_action_meta(),
				$this->project_meta_key . ':' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Remove {{a tag}} from {{a task}} in {{a specific project}}', 'Asana', 'uncanny-automator' ) );

		$this->set_action_tokens(
			Asana_Tokens::get_tag_operation_tokens(),
			$this->get_action_code()
		);
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_workspace_option_config( $this->workspace_meta_key ),
			$this->helpers->get_project_option_config( $this->project_meta_key ),
			$this->helpers->get_task_option_config( $this->task_meta_key ),
			$this->helpers->get_tag_option_config( $this->action_meta, false ),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		// Validate the required fields.
		$workspace_id = $this->helpers->get_workspace_from_parsed( $parsed, $this->workspace_meta_key );
		$project_id   = $this->helpers->get_project_from_parsed( $parsed, $this->project_meta_key );
		$task_id      = $this->helpers->get_task_from_parsed( $parsed, $this->task_meta_key );
		$tag_id       = $this->helpers->get_tag_from_parsed( $parsed, $this->action_meta );

		$body = array(
			'action'       => 'remove_tag_from_task',
			'workspace_id' => $workspace_id,
			'project_id'   => $project_id,
			'task_id'      => $task_id,
			'tag_id'       => $tag_id,
		);

		$response = $this->api->api_request( $body, $action_data );

		if ( 200 !== $response['statusCode'] ) {
			throw new Exception( esc_html_x( 'Failed to remove tag from task.', 'Asana', 'uncanny-automator' ) );
		}

		// Hydrate tokens.
		$this->hydrate_tokens(
			array(
				'TAG_ID'         => $tag_id,
				'TAG_NAME'       => $parsed[ $this->action_meta . '_readable' ],
				'TASK_ID'        => $task_id,
				'TASK_NAME'      => $parsed[ $this->task_meta_key . '_readable' ],
				'PROJECT_ID'     => $project_id,
				'PROJECT_NAME'   => $parsed[ $this->project_meta_key . '_readable' ],
				'WORKSPACE_ID'   => $workspace_id,
				'WORKSPACE_NAME' => $parsed[ $this->workspace_meta_key . '_readable' ],
			)
		);

		return true;
	}
}
