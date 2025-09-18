<?php

namespace Uncanny_Automator\Integrations\Asana;

use Exception;

/**
 * Action: Create {{a task}} in {{a specific project}}
 *
 * @package Uncanny_Automator\Integrations\Asana
 *
 * @property Asana_App_Helpers $helpers
 * @property Asana_Api_Caller $api
 * @property Asana_Custom_Fields_Helper $custom_fields_helper
 */
class ASANA_CREATE_TASK extends \Uncanny_Automator\Recipe\App_Action {

	use Asana_Task_Data;

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
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'ASANA' );
		$this->set_action_code( 'ASANA_CREATE_TASK_CODE' );
		$this->set_action_meta( 'ASANA_CREATE_TASK_META' );
		$this->set_requires_user( false );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/asana-integration/' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the project name, %2$s is the task name
				esc_attr_x( 'Create a task in {{a specific project:%1$s}}', 'Asana', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Create a task in {{a specific project}}', 'Asana', 'uncanny-automator' ) );

		$this->set_action_tokens(
			Asana_Tokens::get_full_task_operation_tokens(),
			$this->get_action_code()
		);

		$this->workspace_meta_key = $this->helpers->get_const( 'ACTION_WORKSPACE_META_KEY' );
		$this->project_meta_key   = $this->get_action_meta();
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return $this->get_task_options();
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
		$project_id   = $action_data['meta'][ $this->action_meta ] ?? '';
		$task_name    = $parsed['TASK_NAME'] ?? '';

		if ( empty( $task_name ) ) {
			throw new Exception( esc_html_x( 'Task name is required.', 'Asana', 'uncanny-automator' ) );
		}

		if ( empty( $project_id ) ) {
			throw new Exception( esc_html_x( 'Project is required.', 'Asana', 'uncanny-automator' ) );
		}

		// Build task data.
		$task_data = $this->build_task_data( $parsed, $workspace_id, $project_id );

		// Prepare the request body and make request.
		$body     = array(
			'action' => 'create_task',
			'task'   => wp_json_encode( $task_data ),
		);
		$response = $this->api->api_request( $body, $action_data );
		if ( 201 !== $response['statusCode'] ) {
			throw new Exception( esc_html_x( 'Failed to create task.', 'Asana', 'uncanny-automator' ) );
		}

		// Check if we have any custom field error messages.
		if ( $this->custom_fields_helper->has_errors() ) {
			$this->set_complete_with_notice( true );
			$this->add_log_error( $this->custom_fields_helper->get_error_message() );
		}

		// Hydrate tokens.
		$this->hydrate_task_tokens(
			$parsed,
			$response['data']['data']['gid'] ?? '',
			$response['data']['data']['permalink_url'] ?? '',
			$workspace_id,
			$project_id
		);

		return true;
	}
}
