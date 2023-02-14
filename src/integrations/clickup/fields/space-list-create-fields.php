<?php
/**
 * This file renders the fields for `Create list` action.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
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
		'option_code' => $action->get_action_meta(),
		'label'       => esc_html__( 'Name', 'uncanny-automator' ),
		'input_type'  => 'text',
		'required'    => true,
	),
);
