<?php
/**
 * This file renders the fields for `Create tasks` action.
 *
 * @global $action The current action class where this field is defined.
 * @since 4.9
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	// Dependency categories.
	array(
		'option_code'           => 'TEAM',
		'label'                 => esc_html__( 'Team', 'uncanny-automator' ),
		'input_type'            => 'select',
		'description'           => esc_html__( 'Team (Workspace)', 'uncanny-automator' ),
		'supports_custom_value' => false,
		'required'              => true,
		'options'               => $action->get_helpers()->get_team_workspaces(),
		'options_show_id'       => false,
	),
	array(
		'option_code'           => 'SPACE',
		'label'                 => esc_html__( 'Space', 'uncanny-automator' ),
		'input_type'            => 'select',
		'supports_custom_value' => false,
		'required'              => true,
		'options'               => array(),
		'options_show_id'       => false,
		'ajax'                  => array(
			'endpoint'      => 'automator_clickup_fetch_spaces',
			'event'         => 'parent_fields_change',
			'listen_fields' => array( 'TEAM' ),
		),
	),
	array(
		'option_code'           => 'FOLDER',
		'label'                 => esc_html__( 'Folder', 'uncanny-automator' ),
		'input_type'            => 'select',
		'supports_custom_value' => false,
		'required'              => true,
		'options'               => array(),
		'options_show_id'       => false,
		'ajax'                  => array(
			'endpoint'      => 'automator_clickup_fetch_folders',
			'event'         => 'parent_fields_change',
			'listen_fields' => array( 'SPACE' ),
		),
	),
	array(
		'option_code'           => $action->get_action_meta(),
		'label'                 => esc_html__( 'List', 'uncanny-automator' ),
		'input_type'            => 'select',
		'supports_custom_value' => false,
		'required'              => true,
		'options'               => array(),
		'options_show_id'       => false,
		'ajax'                  => array(
			'endpoint'      => 'automator_clickup_fetch_lists',
			'event'         => 'parent_fields_change',
			'listen_fields' => array( 'FOLDER' ),
		),
	),
	// Start of the actual fields.
	array(
		'option_code'           => 'NAME',
		'label'                 => esc_html__( 'Name', 'uncanny-automator' ),
		'input_type'            => 'text',
		'supports_custom_value' => true,
		'required'              => true,
	),
	array(
		'option_code'           => 'DESCRIPTION',
		'label'                 => esc_html__( 'Description', 'uncanny-automator' ),
		'input_type'            => 'textarea',
		'supports_custom_value' => false,
		'required'              => true,
	),
	array(
		'option_code'           => 'ASSIGNEE',
		'label'                 => esc_html__( 'Assignee', 'uncanny-automator' ),
		'input_type'            => 'select',
		'supports_custom_value' => true,
		'required'              => false,
		'options'               => array(),
		'options_show_id'       => false,
		'ajax'                  => array(
			'endpoint'      => 'automator_clickup_fetch_assignees_list',
			'event'         => 'parent_fields_change',
			'listen_fields' => array( $action->get_action_meta() ),
		),
	),
	array(
		'option_code'           => 'TAGS',
		'label'                 => esc_html__( 'Tag', 'uncanny-automator' ),
		'input_type'            => 'text',
		'supports_custom_value' => false,
		'required'              => false,
	),
	array(
		'option_code'           => 'STATUS',
		'label'                 => esc_html__( 'Status', 'uncanny-automator' ),
		'input_type'            => 'select',
		'options'               => array(),
		'supports_custom_value' => false,
		'required'              => false,
		'options_show_id'       => false,
		'ajax'                  => array(
			'endpoint'      => 'automator_clickup_fetch_statuses',
			'event'         => 'parent_fields_change',
			'listen_fields' => array( 'SPACE' ),
		),
	),
	array(
		'option_code'           => 'PRIORITY',
		'label'                 => esc_html__( 'Priority', 'uncanny-automator' ),
		'input_type'            => 'select',
		'supports_custom_value' => true,
		'required'              => false,
		'options'               => array(
			'0' => esc_html__( 'No priority', 'uncanny-automator' ),
			'4' => esc_html__( 'Low', 'uncanny-automator' ),
			'3' => esc_html__( 'Normal', 'uncanny-automator' ),
			'2' => esc_html__( 'High', 'uncanny-automator' ),
			'1' => esc_html__( 'Urgent', 'uncanny-automator' ),
		),
		'options_show_id'       => false,
	),
	array(
		'option_code'           => 'DATE_DUE',
		'label'                 => esc_html__( 'Date due', 'uncanny-automator' ),
		'input_type'            => 'date',
		'supports_custom_value' => true,
		'supports_tokens'       => true,
		'required'              => false,
	),
	array(
		'option_code'           => 'DATE_DUE_TIME',
		'label'                 => esc_html__( 'Date due time', 'uncanny-automator' ),
		'description'           => esc_html__( 'Date due time is ignored if Date due is empty.', 'uncanny-automator' ),
		'input_type'            => 'time',
		'supports_custom_value' => true,
		'supports_tokens'       => true,
		'required'              => false,
	),
	array(
		'option_code'           => 'TIME_ESTIMATE',
		'label'                 => esc_html__( 'Time estimate', 'uncanny-automator' ),
		'description'           => esc_html__( 'Provide the time estimate in hours.', 'uncanny-automator' ),
		'input_type'            => 'int',
		'supports_custom_value' => true,
		'supports_tokens'       => true,
		'required'              => false,
	),
	array(
		'option_code'           => 'START_DATE',
		'label'                 => esc_html__( 'Start date', 'uncanny-automator' ),
		'input_type'            => 'date',
		'supports_custom_value' => true,
		'supports_tokens'       => true,
		'required'              => false,
	),
	array(
		'option_code'           => 'START_TIME',
		'label'                 => esc_html__( 'Start time', 'uncanny-automator' ),
		'description'           => esc_html__( 'Start time is ignored if Start date is empty.', 'uncanny-automator' ),
		'input_type'            => 'time',
		'supports_custom_value' => true,
		'supports_tokens'       => true,
		'required'              => false,
	),
	array(
		'option_code'           => 'NOTIFY_ALL',
		'label'                 => esc_html__( 'Notify all', 'uncanny-automator' ),
		'input_type'            => 'checkbox',
		'supports_custom_value' => true,
		'required'              => false,
	),
	array(
		'option_code' => 'PARENT',
		'label'       => esc_html__( 'Parent', 'uncanny-automator' ),
		'description' => esc_html__( 'Enter an existing Task ID. Task must be in the same List.', 'uncanny-automator' ),
		'input_type'  => 'text',
	),
	array(
		'option_code' => 'LINKS_TO',
		'label'       => esc_html__( 'Links to', 'uncanny-automator' ),
		'description' => esc_html__( 'Enter an existing Task ID. Task must be in the same List.', 'uncanny-automator' ),
		'input_type'  => 'text',
	),
);
