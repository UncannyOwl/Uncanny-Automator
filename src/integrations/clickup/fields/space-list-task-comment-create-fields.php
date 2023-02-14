<?php
/**
 * This file returns the fields for `Add a comment to a Task` action.
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
		'option_code'           => 'LIST',
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
	array(
		'option_code'              => 'ASSIGNEE',
		'label'                    => esc_html__( 'Assignee', 'uncanny-automator' ),
		'input_type'               => 'select',
		'supports_custom_value'    => true,
		'custom_value_description' => esc_html__( 'ClickUp User ID', 'uncanny-automator' ),
		'required'                 => false,
		'ajax'                     => array(
			'endpoint'      => 'automator_clickup_fetch_assignees_list',
			'event'         => 'parent_fields_change',
			'listen_fields' => array( 'LIST' ),
		),
		'options_show_id'          => false,
	),
	array(
		'option_code'              => $action->get_action_meta(),
		'label'                    => esc_attr__( 'Task', 'uncanny-automator' ),
		'input_type'               => 'select',
		'required'                 => true,
		'supports_custom_value'    => true,
		'custom_value_description' => esc_attr__( 'Task ID', 'uncanny-automator' ),
		'ajax'                     => array(
			'endpoint'      => 'automator_clickup_fetch_tasks',
			'event'         => 'parent_fields_change',
			'listen_fields' => array( 'LIST' ),
		),
		'options_show_id'          => false,
	),
	array(
		'option_code' => 'COMMENT_TEXT',
		'label'       => esc_attr__( 'Comment text', 'uncanny-automator' ),
		'input_type'  => 'textarea',
		'required'    => true,
	),
	array(
		'option_code' => 'NOTIFY_ALL',
		'label'       => esc_attr__( 'Notify all', 'uncanny-automator' ),
		'input_type'  => 'checkbox',
		'required'    => false,
	),
);
