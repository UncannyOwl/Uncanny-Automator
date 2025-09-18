<?php

namespace Uncanny_Automator\Integrations\Asana;

/**
 * Trait for common Asana task Create / Update data functionality.
 *
 * @package Uncanny_Automator\Integrations\Asana
 *
 * @property Asana_App_Helpers $helpers
 * @property string $workspace_meta_key
 * @property string $project_meta_key
 */
trait Asana_Task_Data {

	/**
	 * Whether this is an update action.
	 *
	 * @var bool
	 */
	private $is_update_action;

	/**
	 * Custom fields helper instance.
	 *
	 * @var Asana_Custom_Fields_Helper
	 */
	private $custom_fields_helper;

	/**
	 * Get task options configuration.
	 *
	 * @return array
	 */
	protected function get_task_options() {
		$this->is_update_action = $this->is_update_action();

		// Add Workspace and Project selectors.
		$options = array(
			$this->helpers->get_workspace_option_config( $this->workspace_meta_key ),
			$this->helpers->get_project_option_config( $this->project_meta_key ),
		);

		// Add Task selector for update actions.
		if ( $this->is_update_action ) {
			$options[] = $this->helpers->get_task_option_config( $this->get_action_meta() );
		}

		// Add remaining common options for the task.
		$options[] = $this->get_task_name_option();
		$options[] = $this->get_task_type_option();
		$options[] = $this->get_approval_status_option();
		$options[] = $this->get_user_assignment_option();
		$options[] = $this->get_start_date_option();
		$options[] = $this->get_due_date_option();
		$options[] = $this->get_task_notes_option();
		$options[] = $this->get_custom_fields_option();

		return $options;
	}

	/**
	 * Get task name option configuration.
	 *
	 * @return array
	 */
	private function get_task_name_option() {
		return array(
			'input_type'      => 'text',
			'option_code'     => 'TASK_NAME',
			'label'           => esc_html_x( 'Task name', 'Asana', 'uncanny-automator' ),
			'required'        => ! $this->is_update_action,
			'relevant_tokens' => array(),
			'description'     => $this->is_update_action
				? esc_html_x( 'Leave empty to keep current name', 'Asana', 'uncanny-automator' )
				: '',
		);
	}

	/**
	 * Get task type option configuration.
	 *
	 * @return array
	 */
	private function get_task_type_option() {
		return array(
			'input_type'      => 'select',
			'option_code'     => 'TASK_TYPE',
			'label'           => esc_html_x( 'Task type', 'Asana', 'uncanny-automator' ),
			'placeholder'     => esc_html_x( 'Select a task type', 'Asana', 'uncanny-automator' ),
			'required'        => false,
			'options'         => $this->helpers->prepend_empty_option( $this->get_task_type_options() ),
			'options_show_id' => false,
			'relevant_tokens' => array(),
			'description'     => $this->is_update_action
				? esc_html_x( "Don't select anything to keep current type", 'Asana', 'uncanny-automator' )
				: '',
		);
	}

	/**
	 * Get task type options array.
	 *
	 * @return array
	 */
	private function get_task_type_options() {
		return array(
			array(
				'value' => 'default_task',
				'text'  => esc_html_x( 'Task', 'Asana', 'uncanny-automator' ),
			),
			array(
				'value' => 'approval',
				'text'  => esc_html_x( 'Approval', 'Asana', 'uncanny-automator' ),
			),
			array(
				'value' => 'milestone',
				'text'  => esc_html_x( 'Milestone', 'Asana', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Get approval status option configuration.
	 *
	 * @return array
	 */
	private function get_approval_status_option() {
		$config = array(
			'input_type'      => 'select',
			'option_code'     => 'APPROVAL_STATUS',
			'label'           => esc_html_x( 'Approval status', 'Asana', 'uncanny-automator' ),
			'placeholder'     => esc_html_x( 'Select approval status', 'Asana', 'uncanny-automator' ),
			'required'        => false,
			'options'         => $this->helpers->prepend_empty_option( $this->get_approval_status_options() ),
			'options_show_id' => false,
			'relevant_tokens' => array(),
			'description'     => $this->is_update_action
				? esc_html_x( "Don't select anything to keep current status", 'Asana', 'uncanny-automator' )
				: '',
		);

		// Only add visibility rules for create actions.
		if ( ! $this->is_update_action ) {
			$config['dynamic_visibility'] = $this->get_approval_status_visibility_rules();
		}

		return $config;
	}

	/**
	 * Get approval status options array.
	 *
	 * @return array
	 */
	private function get_approval_status_options() {
		return array(
			array(
				'value' => 'pending',
				'text'  => esc_html_x( 'Pending', 'Asana', 'uncanny-automator' ),
			),
			array(
				'value' => 'approved',
				'text'  => esc_html_x( 'Approved', 'Asana', 'uncanny-automator' ),
			),
			array(
				'value' => 'rejected',
				'text'  => esc_html_x( 'Rejected', 'Asana', 'uncanny-automator' ),
			),
			array(
				'value' => 'changes_requested',
				'text'  => esc_html_x( 'Changes requested', 'Asana', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Get approval status visibility rules.
	 *
	 * @return array
	 */
	private function get_approval_status_visibility_rules() {
		return array(
			'default_state'    => 'hidden',
			'visibility_rules' => array(
				array(
					'operator'             => 'AND',
					'rule_conditions'      => array(
						array(
							'option_code' => 'TASK_TYPE',
							'compare'     => '==',
							'value'       => 'approval',
						),
					),
					'resulting_visibility' => 'show',
				),
			),
		);
	}

	/**
	 * Get user assignment option configuration.
	 *
	 * @return array
	 */
	private function get_user_assignment_option() {
		return array(
			'input_type'               => 'select',
			'option_code'              => 'ASANA_USER',
			'label'                    => esc_html_x( 'Assign to user', 'Asana', 'uncanny-automator' ),
			'placeholder'              => esc_html_x( 'Select a user', 'Asana', 'uncanny-automator' ),
			'required'                 => false,
			'options'                  => array(),
			'options_show_id'          => false,
			'relevant_tokens'          => array(),
			'custom_value_description' => esc_html_x( 'Asana user ID or email', 'Asana', 'uncanny-automator' ),
			'ajax'                     => array(
				'endpoint'      => 'automator_asana_get_user_options',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( $this->workspace_meta_key ),
			),
			'description'              => $this->is_update_action
				? esc_html_x( "Don't select anything to keep current assignee", 'Asana', 'uncanny-automator' )
				: '',
		);
	}

	/**
	 * Get start date option configuration.
	 *
	 * @return array
	 */
	private function get_start_date_option() {
		$config = array(
			'input_type'      => 'date',
			'option_code'     => 'TASK_START_ON',
			'label'           => esc_html_x( 'Start date', 'Asana', 'uncanny-automator' ),
			'required'        => false,
			'relevant_tokens' => array(),
			'supports_tokens' => true,
			'description'     => $this->is_update_action
				? esc_html_x( 'Leave empty to keep current start date', 'Asana', 'uncanny-automator' )
				: '',
		);

		// Only add visibility rules for create actions.
		if ( ! $this->is_update_action ) {
			$config['dynamic_visibility'] = $this->get_start_date_visibility_rules();
		}

		return $config;
	}

	/**
	 * Get start date visibility rules.
	 *
	 * @return array
	 */
	private function get_start_date_visibility_rules() {
		return array(
			'default_state'    => 'visible',
			'visibility_rules' => array(
				array(
					'operator'             => 'AND',
					'rule_conditions'      => array(
						array(
							'option_code' => 'TASK_TYPE',
							'compare'     => '==',
							'value'       => 'milestone',
						),
					),
					'resulting_visibility' => 'hide',
				),
			),
		);
	}

	/**
	 * Get due date option configuration.
	 *
	 * @return array
	 */
	private function get_due_date_option() {
		return array(
			'input_type'      => 'date',
			'option_code'     => 'TASK_DUE_ON',
			'label'           => esc_html_x( 'Due date', 'Asana', 'uncanny-automator' ),
			'required'        => false,
			'relevant_tokens' => array(),
			'supports_tokens' => true,
			'description'     => $this->is_update_action
				? esc_html_x( 'Leave empty to keep current due date', 'Asana', 'uncanny-automator' )
				: esc_html_x( 'Required for milestone tasks or if start date is set.', 'Asana', 'uncanny-automator' ),
		);
	}

	/**
	 * Get task notes option configuration.
	 *
	 * @return array
	 */
	private function get_task_notes_option() {
		return array(
			'input_type'       => 'textarea',
			'option_code'      => 'TASK_NOTES',
			'label'            => esc_html_x( 'Task description', 'Asana', 'uncanny-automator' ),
			'required'         => false,
			'supports_tinymce' => false,
			'relevant_tokens'  => array(),
			'description'      => $this->is_update_action
				? esc_html_x( 'Leave empty to keep current description', 'Asana', 'uncanny-automator' )
				: '',
		);
	}

	/**
	 * Get custom fields option configuration.
	 *
	 * @return array
	 */
	private function get_custom_fields_option() {
		return array(
			'option_code'     => 'CUSTOM_FIELDS',
			'input_type'      => 'repeater',
			'hide_actions'    => true,
			'hide_header'     => true,
			'relevant_tokens' => array(),
			'label'           => esc_html_x( 'Custom fields', 'Asana', 'uncanny-automator' ),
			'required'        => true,
			'layout'          => 'transposed',
			'fields'          => array(),
			'ajax'            => array(
				'event'         => 'parent_fields_change',
				'listen_fields' => array( $this->project_meta_key ),
				'endpoint'      => 'automator_asana_get_custom_fields_repeater',
			),
			'description'     => $this->is_update_action
				? esc_html_x( 'Leave empty to keep current custom field values or use [DELETE] to remove', 'Asana', 'uncanny-automator' )
				: esc_html_x( 'Leave empty to skip adding custom field values', 'Asana', 'uncanny-automator' ),
		);
	}

	/**
	 * Build task data array from parsed data.
	 *
	 * @param array $parsed The parsed action data.
	 * @param string $workspace_id The workspace ID.
	 * @param string $project_id The project ID.
	 *
	 * @return array
	 */
	protected function build_task_data( $parsed, $workspace_id, $project_id ) {
		$this->is_update_action = $this->is_update_action();
		$data                   = array();

		// Add task name if provided.
		if ( ! empty( $parsed['TASK_NAME'] ) ) {
			$data['name'] = sanitize_text_field( $parsed['TASK_NAME'] );
		}

		// Add projects for create action.
		if ( ! $this->is_update_action ) {
			$data['projects'] = array( $project_id );
		}

		// Add user assignment if provided.
		$assigned_to = $this->get_assigned_user( $parsed, $workspace_id );
		if ( ! empty( $assigned_to ) ) {
			$data['assignee'] = $this->helpers->is_delete_value( $assigned_to )
				? null
				: $assigned_to;
		}

		// Add task type if provided.
		$task_type = '';
		if ( ! empty( $parsed['TASK_TYPE'] ) ) {
			$task_type                = sanitize_text_field( $parsed['TASK_TYPE'] );
			$data['resource_subtype'] = $this->helpers->is_delete_value( $task_type )
				? 'default_task' // Set to default task you can't delete this.
				: $task_type;
		}

		// Add approval status only if task type is approval.
		if ( 'approval' === $task_type && ! empty( $parsed['APPROVAL_STATUS'] ) ) {
			$data['approval_status'] = $this->helpers->is_delete_value( $parsed['APPROVAL_STATUS'] )
				? 'pending' // Set to pending you can't delete this.
				: $parsed['APPROVAL_STATUS'];
		}

		// Handle start date and due date based on task type.
		$start_date_provided = ! empty( $parsed['TASK_START_ON'] );
		$due_date_provided   = ! empty( $parsed['TASK_DUE_ON'] );

		// For milestones, never include start date.
		if ( 'milestone' !== $task_type && $start_date_provided ) {
			$start_date = $this->helpers->validate_and_format_date( $parsed['TASK_START_ON'] );
			if ( $start_date ) {
				$data['start_on'] = $start_date;
			}

			// If start date is set, due date is required for create action.
			if ( ! $this->is_update_action && ! $due_date_provided ) {
				throw new \Exception( esc_html_x( 'Due date is required when setting a start date.', 'Asana', 'uncanny-automator' ) );
			}
		}

		// Add due date if provided.
		if ( $due_date_provided ) {
			$due_date = $this->helpers->validate_and_format_date( $parsed['TASK_DUE_ON'] );
			if ( $due_date ) {
				$data['due_on'] = $due_date;
			}
		}

		// Add notes if provided.
		if ( ! empty( $parsed['TASK_NOTES'] ) ) {
			$data['notes'] = $this->helpers->is_delete_value( $parsed['TASK_NOTES'] )
				? ''
				: sanitize_textarea_field( $parsed['TASK_NOTES'] );
		}

		// Set custom fields helper instance.
		$this->custom_fields_helper = new Asana_Custom_Fields_Helper( $this->helpers, $this->api, $workspace_id, $project_id );

		// Add custom fields if provided.
		$custom_fields = ! empty( $parsed['CUSTOM_FIELDS'] ) ? json_decode( $parsed['CUSTOM_FIELDS'], true ) : array();
		$custom_fields = $this->custom_fields_helper->process_repeater_fields( $custom_fields );
		if ( ! empty( $custom_fields ) ) {
			$data['custom_fields'] = $custom_fields;
		}

		// For update actions, ensure we have at least one field to update
		if ( $this->is_update_action && empty( $data ) ) {
			throw new \Exception( esc_html_x( 'No fields provided to update.', 'Asana', 'uncanny-automator' ) );
		}

		return $data;
	}

	/**
	 * Get assigned user from parsed data.
	 *
	 * @param array $parsed The parsed action data.
	 * @param string $workspace_id The workspace ID.
	 *
	 * @return string
	 */
	protected function get_assigned_user( $parsed, $workspace_id ) {
		$user_input = '';

		if ( isset( $parsed['ASANA_USER'] ) ) {
			$user_input = sanitize_text_field( $parsed['ASANA_USER'] );
		}

		if ( empty( $user_input ) ) {
			return '';
		}

		// Handle [DELETE] value.
		if ( $this->helpers->is_delete_value( $user_input ) ) {
			return $user_input;
		}

		// If it's already a GID (numeric), return as is
		if ( $this->helpers->is_valid_gid( $user_input ) ) {
			return $user_input;
		}

		// Get workspace users to map email to ID if needed
		$users = $this->api->get_workspace_users( $workspace_id );

		// Check if it's an email and find the corresponding user ID
		if ( is_array( $users ) ) {
			foreach ( $users as $user ) {
				if ( isset( $user['email'] ) && $user['email'] === $user_input ) {
					return $user['value'];
				}
			}
		}

		return $user_input;
	}

	/**
	 * Hydrate task tokens.
	 *
	 * @param array $parsed The parsed action data.
	 * @param string $task_id The task ID.
	 * @param string $task_url The task URL.
	 * @param string $workspace_id The workspace ID.
	 * @param string $project_id The project ID.
	 */
	protected function hydrate_task_tokens( $parsed, $task_id, $task_url, $workspace_id, $project_id ) {
		// Determine the actual task name being used (either provided or fallback to readable name).
		$task_name = $parsed['TASK_NAME'] ?? '';
		if ( empty( $task_name ) && $this->is_update_action() ) {
			$task_name = $parsed[ $this->get_action_meta() . '_readable' ];
		}

		$this->hydrate_tokens(
			array(
				'TASK_ID'         => $task_id,
				'TASK_NAME'       => $task_name,
				'TASK_URL'        => $task_url,
				'TASK_START_ON'   => $parsed['TASK_START_ON'] ?? '',
				'TASK_DUE_ON'     => $parsed['TASK_DUE_ON'] ?? '',
				'TASK_NOTES'      => $parsed['TASK_NOTES'] ?? '',
				'PROJECT_NAME'    => $parsed[ $this->project_meta_key . '_readable' ] ?? '',
				'PROJECT_ID'      => $project_id,
				'WORKSPACE_ID'    => $workspace_id,
				'WORKSPACE_NAME'  => $parsed[ $this->workspace_meta_key . '_readable' ] ?? '',
				'TASK_TYPE'       => $parsed['TASK_TYPE'] ?? '',
				'APPROVAL_STATUS' => $parsed['APPROVAL_STATUS'] ?? '',
			)
		);
	}

	/**
	 * Check if this is an update action.
	 *
	 * @return bool
	 */
	private function is_update_action() {
		return 'ASANA_UPDATE_TASK_CODE' === $this->get_action_code();
	}
}
