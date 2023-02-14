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
		'option_code'           => 'TASK',
		'label'                 => esc_html__( 'Task', 'uncanny-automator' ),
		'input_type'            => 'select',
		'supports_custom_value' => true,
		'required'              => true,
		'ajax'                  => array(
			'endpoint'      => 'automator_clickup_fetch_tasks',
			'event'         => 'parent_fields_change',
			'listen_fields' => array( 'LIST' ),
		),
	),
	array(
		'option_code'              => $action->get_action_meta(),
		'label'                    => esc_html__( 'Tag', 'uncanny-automator' ),
		'input_type'               => 'text',
		'required'                 => true,
		'custom_value_description' => esc_html__( 'Tag ID', 'uncanny-automator' ),
	),
);
