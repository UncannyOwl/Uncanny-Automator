<?php

namespace Uncanny_Automator\Integrations\ClickUp;

use Uncanny_Automator\Integrations\ClickUp\Time_Utility;

/**
 * Action: Create a task.
 *
 * @package Uncanny_Automator\Integrations\ClickUp
 *
 * @property ClickUp_App_Helpers $helpers
 * @property ClickUp_Api_Caller $api
 */
class Space_List_Task_Create extends \Uncanny_Automator\Recipe\App_Action {

	use ClickUp_Hierarchy_Options;

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'CLICKUP' );
		$this->set_action_code( 'CLICKUP_SPACE_LIST_TASK_CREATE' );
		$this->set_action_meta( 'CLICKUP_SPACE_LIST_TASK_CREATE_META' );
		$this->set_requires_user( false );
		$this->set_is_pro( false );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/clickup/' ) );
		$this->set_readable_sentence( esc_html_x( 'Create a {{task}}', 'ClickUp', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: Task name
				esc_html_x( 'Create a {{task:%1$s}}', 'ClickUp', 'uncanny-automator' ),
				'NAME:' . $this->get_action_meta()
			)
		);

		$this->set_action_tokens(
			array(
				'TASK_ID' => array(
					'name' => esc_attr_x( 'Task ID', 'ClickUp', 'uncanny-automator' ),
					'type' => 'int',
				),
			),
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
			$this->get_team_option_config(),
			$this->get_space_option_config(),
			$this->get_folder_option_config(),
			$this->helpers->get_list_option_config( $this->get_action_meta() ),
			$this->helpers->get_name_option_config(),
			array(
				'option_code' => 'DESCRIPTION',
				'label'       => esc_html_x( 'Description', 'ClickUp', 'uncanny-automator' ),
				'input_type'  => 'textarea',
				'required'    => true,
			),
			$this->helpers->get_assignee_option_config( $this->helpers->get_const( 'META_ASSIGNEE' ), $this->get_action_meta() ),
			array(
				'option_code' => 'TAGS',
				'label'       => esc_html_x( 'Tag', 'ClickUp', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
			$this->helpers->get_status_option_config(),
			$this->helpers->get_priority_option_config(),
			array(
				'option_code'           => 'DATE_DUE',
				'label'                 => esc_html_x( 'Date due', 'ClickUp', 'uncanny-automator' ),
				'input_type'            => 'date',
				'supports_custom_value' => true,
				'supports_tokens'       => true,
				'required'              => false,
			),
			array(
				'option_code'           => 'DATE_DUE_TIME',
				'label'                 => esc_html_x( 'Date due time', 'ClickUp', 'uncanny-automator' ),
				'description'           => esc_html_x( 'Date due time is ignored if Date due is empty.', 'ClickUp', 'uncanny-automator' ),
				'input_type'            => 'time',
				'supports_custom_value' => true,
				'supports_tokens'       => true,
				'required'              => false,
			),
			array(
				'option_code'           => 'TIME_ESTIMATE',
				'label'                 => esc_html_x( 'Time estimate', 'ClickUp', 'uncanny-automator' ),
				'description'           => esc_html_x( 'Provide the time estimate in hours.', 'ClickUp', 'uncanny-automator' ),
				'input_type'            => 'int',
				'supports_custom_value' => true,
				'supports_tokens'       => true,
				'required'              => false,
			),
			array(
				'option_code'           => 'START_DATE',
				'label'                 => esc_html_x( 'Start date', 'ClickUp', 'uncanny-automator' ),
				'input_type'            => 'date',
				'supports_custom_value' => true,
				'supports_tokens'       => true,
				'required'              => false,
			),
			array(
				'option_code'           => 'START_TIME',
				'label'                 => esc_html_x( 'Start time', 'ClickUp', 'uncanny-automator' ),
				'description'           => esc_html_x( 'Start time is ignored if Start date is empty.', 'ClickUp', 'uncanny-automator' ),
				'input_type'            => 'time',
				'supports_custom_value' => true,
				'supports_tokens'       => true,
				'required'              => false,
			),
			array(
				'option_code' => 'NOTIFY_ALL',
				'label'       => esc_html_x( 'Notify all', 'ClickUp', 'uncanny-automator' ),
				'input_type'  => 'checkbox',
				'required'    => false,
			),
			array(
				'option_code' => 'PARENT',
				'label'       => esc_html_x( 'Parent', 'ClickUp', 'uncanny-automator' ),
				'description' => esc_html_x( 'Enter an existing Task ID. Task must be in the same List.', 'ClickUp', 'uncanny-automator' ),
				'input_type'  => 'text',
			),
			array(
				'option_code' => 'LINKS_TO',
				'label'       => esc_html_x( 'Links to', 'ClickUp', 'uncanny-automator' ),
				'description' => esc_html_x( 'Enter an existing Task ID. Task must be in the same List.', 'ClickUp', 'uncanny-automator' ),
				'input_type'  => 'text',
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
	 * @throws \Exception If the action fails.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$time_utility = new Time_Utility(
			Automator()->get_date_format(),
			Automator()->get_time_format(),
			Automator()->get_timezone_string()
		);

		$start_date = sanitize_text_field( $parsed['START_DATE'] ?? '' );
		$start_time = sanitize_text_field( $parsed['START_TIME'] ?? '' );
		$due_date   = sanitize_text_field( $parsed['DATE_DUE'] ?? '' );
		$due_time   = sanitize_text_field( $parsed['DATE_DUE_TIME'] ?? '' );

		$body = array(
			'action'               => 'create_task',
			'start_date_timestamp' => $time_utility->to_timestamp( $start_date, $start_time ),
			'due_date_timestamp'   => $time_utility->to_timestamp( $due_date, $due_time ),
			'name'                 => sanitize_text_field( $parsed['NAME'] ?? '' ),
			'description'          => sanitize_textarea_field( $parsed['DESCRIPTION'] ?? '' ),
			'time_estimate'        => absint( $parsed['TIME_ESTIMATE'] ?? 0 ),
			'status'               => sanitize_text_field( $parsed['STATUS'] ?? '' ),
			'tags'                 => sanitize_text_field( $parsed['TAGS'] ?? '' ),
			'priority'             => sanitize_text_field( $parsed['PRIORITY'] ?? '' ),
			'assignees'            => sanitize_text_field( $parsed['ASSIGNEE'] ?? '' ),
			'list_id'              => sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' ),
			'notify_all'           => sanitize_text_field( $parsed['NOTIFY_ALL'] ?? '' ),
			'parent'               => sanitize_text_field( $parsed['PARENT'] ?? '' ),
			'links_to'             => sanitize_text_field( $parsed['LINKS_TO'] ?? '' ),
		);

		$response = $this->api->api_request( $body, $action_data );

		$this->hydrate_tokens(
			array(
				'TASK_ID' => $response['data']['id'] ?? null,
			)
		);

		return true;
	}
}
