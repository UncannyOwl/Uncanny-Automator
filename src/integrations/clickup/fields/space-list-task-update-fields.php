<?php
/**
 * This file renders the fields for `Add and remove a tag to a specific task` action.
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
		'ajax'                  => array(
			'endpoint'      => 'automator_clickup_fetch_spaces',
			'event'         => 'parent_fields_change',
			'listen_fields' => array( 'TEAM' ),
		),
		'options_show_id'       => false,
	),
	array(
		'option_code'           => 'FOLDER',
		'label'                 => esc_html__( 'Folder', 'uncanny-automator' ),
		'input_type'            => 'select',
		'supports_custom_value' => false,
		'required'              => true,
		'options'               => array(),
		'ajax'                  => array(
			'endpoint'      => 'automator_clickup_fetch_folders',
			'event'         => 'parent_fields_change',
			'listen_fields' => array( 'SPACE' ),
		),
		'options_show_id'       => false,
	),
	array(
		'option_code'           => 'LIST',
		'label'                 => esc_html__( 'List', 'uncanny-automator' ),
		'input_type'            => 'select',
		'supports_custom_value' => false,
		'required'              => true,
		'options'               => array(),
		'ajax'                  => array(
			'endpoint'      => 'automator_clickup_fetch_lists',
			'event'         => 'parent_fields_change',
			'listen_fields' => array( 'FOLDER' ),
		),
		'options_show_id'       => false,
	),
	array(
		'option_code'              => $action->get_action_meta(),
		'label'                    => esc_attr__( 'Task', 'uncanny-automator' ),
		'input_type'               => 'select',
		'supports_custom_value'    => true,
		'custom_value_description' => esc_attr__( 'Task ID', 'uncanny-automator' ),
		'required'                 => true,
		'ajax'                     => array(
			'endpoint'      => 'automator_clickup_fetch_tasks',
			'event'         => 'parent_fields_change',
			'listen_fields' => array( 'LIST' ),
		),
	),
	array(
		'option_code'              => 'ASSIGNEES_ADD',
		'label'                    => esc_attr__( 'Add assignees', 'uncanny-automator' ),
		'input_type'               => 'select',
		'placeholder'              => esc_attr__( 'Click to choose from list of assignees', 'uncanny-automator' ),
		'supports_multiple_values' => true,
		'options'                  => array(),
		'supports_custom_value'    => false,
		'required'                 => false,
		'ajax'                     => array(
			'endpoint'      => 'automator_clickup_fetch_assignees_list',
			'event'         => 'parent_fields_change',
			'listen_fields' => array( 'LIST' ),
		),
	),
	array(
		'option_code'              => 'ASSIGNEES_REMOVE',
		'label'                    => esc_attr__( 'Remove assignees', 'uncanny-automator' ),
		'input_type'               => 'select',
		'placeholder'              => esc_attr__( 'Click to choose from list of assignees', 'uncanny-automator' ),
		'options'                  => array(),
		'supports_multiple_values' => true,
		'supports_custom_value'    => false,
		'required'                 => false,
		'ajax'                     => array(
			'endpoint'      => 'automator_clickup_fetch_assignees_list',
			'event'         => 'parent_fields_change',
			'listen_fields' => array( 'LIST' ),
		),
		'options_show_id'          => false,
	),
	array(
		'option_code'           => 'STATUS',
		'label'                 => esc_attr__( 'Status', 'uncanny-automator' ),
		'input_type'            => 'select',
		'options'               => array(),
		'supports_custom_value' => false,
		'required'              => false,
		'default_value'         => 'Select status',
		'ajax'                  => array(
			'endpoint'      => 'automator_clickup_fetch_statuses',
			'event'         => 'parent_fields_change',
			'listen_fields' => array( 'SPACE' ),
		),
		'options_show_id'       => false,
	),
	array(
		'option_code'           => 'PRIORITY',
		'label'                 => esc_attr__( 'Priority', 'uncanny-automator' ),
		'input_type'            => 'select',
		'options'               => array(
			'__NO_UPDATE__' => esc_html__( 'Leave unchanged in ClickUp', 'uncanny-automator' ),
			'__REMOVE__'    => esc_html__( 'Remove priority', 'uncanny-automator' ),
			'0'             => esc_html__( 'No priority', 'uncanny-automator' ),
			'4'             => esc_html__( 'Low', 'uncanny-automator' ),
			'3'             => esc_html__( 'Normal', 'uncanny-automator' ),
			'2'             => esc_html__( 'High', 'uncanny-automator' ),
			'1'             => esc_html__( 'Urgent', 'uncanny-automator' ),
		),
		'supports_custom_value' => false,
		'required'              => false,
		'options_show_id'       => false,
	),
	array(
		'option_code'  => 'NAME_REPEATER',
		'label'        => esc_attr__( 'Name', 'uncanny-automator' ),
		'hide_actions' => true,
		'input_type'   => 'repeater',
		'required'     => true,
		'fields'       => array(
			array(
				'option_code' => 'NAME',
				'label'       => esc_attr__( 'Value', 'uncanny-automator' ),
				'input_type'  => 'text',
			),
			array(
				'option_code' => 'NAME_UPDATE',
				'label'       => esc_attr__( 'Update?', 'uncanny-automator' ),
				'input_type'  => 'checkbox',
				'is_toggle'   => true,
			),
		),
	),
	array(
		'option_code'  => 'DESCRIPTION_REPEATER',
		'label'        => esc_attr__( 'Description', 'uncanny-automator' ),
		'hide_actions' => true,
		'input_type'   => 'repeater',
		'required'     => true,
		'fields'       => array(
			array(
				'option_code'   => 'DESCRIPTION',
				'label'         => esc_attr__( 'Value', 'uncanny-automator' ),
				'input_type'    => 'textarea',
				'default_value' => '',
			),
			array(
				'option_code' => 'DESCRIPTION_UPDATE',
				'label'       => esc_attr__( 'Update?', 'uncanny-automator' ),
				'input_type'  => 'checkbox',
				'is_toggle'   => true,
			),
		),
	),
	array(
		'option_code'  => 'START_DATE_REPEATER',
		'label'        => esc_attr__( 'Start date', 'uncanny-automator' ),
		'hide_actions' => true,
		'input_type'   => 'repeater',
		'required'     => true,
		'fields'       => array(
			array(
				'option_code'   => 'START_DATE',
				'label'         => esc_attr__( 'Value', 'uncanny-automator' ),
				'input_type'    => 'date',
				'default_value' => '',
			),
			array(
				'option_code' => 'START_DATE_UPDATE',
				'label'       => esc_attr__( 'Update?', 'uncanny-automator' ),
				'input_type'  => 'checkbox',
				'is_toggle'   => true,
			),
		),
	),
	array(
		'option_code'  => 'START_TIME_REPEATER',
		'label'        => esc_attr__( 'Start time', 'uncanny-automator' ),
		'hide_actions' => true,
		'input_type'   => 'repeater',
		'required'     => true,
		'fields'       => array(
			array(
				'option_code'   => 'START_TIME',
				'label'         => esc_attr__( 'Value', 'uncanny-automator' ),
				'input_type'    => 'time',
				'default_value' => '',
			),
			array(
				'option_code' => 'START_TIME_UPDATE',
				'label'       => esc_attr__( 'Update?', 'uncanny-automator' ),
				'input_type'  => 'checkbox',
				'is_toggle'   => true,
			),
		),
	),
	array(
		'option_code'  => 'DUE_DATE_REPEATER',
		'label'        => esc_attr__( 'Due date', 'uncanny-automator' ),
		'hide_actions' => true,
		'input_type'   => 'repeater',
		'required'     => true,
		'fields'       => array(
			array(
				'option_code'   => 'DUE_DATE',
				'label'         => esc_attr__( 'Value', 'uncanny-automator' ),
				'input_type'    => 'date',
				'default_value' => '',
			),
			array(
				'option_code' => 'DUE_DATE_UPDATE',
				'label'       => esc_attr__( 'Update?', 'uncanny-automator' ),
				'input_type'  => 'checkbox',
				'is_toggle'   => true,
			),
		),
	),
	array(
		'option_code'  => 'DUE_TIME_REPEATER',
		'label'        => esc_attr__( 'Due date time', 'uncanny-automator' ),
		'hide_actions' => true,
		'input_type'   => 'repeater',
		'required'     => true,
		'fields'       => array(
			array(
				'option_code'   => 'DUE_TIME',
				'label'         => esc_attr__( 'Start time', 'uncanny-automator' ),
				'input_type'    => 'time',
				'default_value' => '',
			),
			array(
				'option_code' => 'DUE_TIME_UPDATE',
				'label'       => esc_attr__( 'Update?', 'uncanny-automator' ),
				'input_type'  => 'checkbox',
				'is_toggle'   => true,
			),
		),
	),
);
