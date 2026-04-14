<?php

namespace Uncanny_Automator\Integrations\ClickUp;

/**
 * Trait ClickUp_Hierarchy_Options
 *
 * Provides the cascading Team → Space → Folder option configs
 * used across most ClickUp actions.
 *
 * @package Uncanny_Automator\Integrations\ClickUp
 */
trait ClickUp_Hierarchy_Options {

	/**
	 * Get team (workspace) option config.
	 *
	 * @return array
	 */
	public function get_team_option_config() {
		return array(
			'input_type'            => 'select',
			'option_code'           => ClickUp_App_Helpers::META_TEAM,
			'label'                 => esc_html_x( 'Team', 'ClickUp', 'uncanny-automator' ),
			'description'           => esc_html_x( 'Team (Workspace)', 'ClickUp', 'uncanny-automator' ),
			'placeholder'           => esc_html_x( 'Select a team', 'ClickUp', 'uncanny-automator' ),
			'required'              => true,
			'supports_custom_value' => false,
			'options'               => array(),
			'options_show_id'       => false,
			'relevant_tokens'       => array(),
			'ajax'                  => array(
				'endpoint' => 'automator_clickup_fetch_teams',
				'event'    => 'on_load',
			),
		);
	}

	/**
	 * Get space option config.
	 *
	 * @return array
	 */
	public function get_space_option_config() {
		return array(
			'input_type'            => 'select',
			'option_code'           => ClickUp_App_Helpers::META_SPACE,
			'label'                 => esc_html_x( 'Space', 'ClickUp', 'uncanny-automator' ),
			'placeholder'           => esc_html_x( 'Select a space', 'ClickUp', 'uncanny-automator' ),
			'required'              => true,
			'supports_custom_value' => false,
			'options'               => array(),
			'options_show_id'       => false,
			'relevant_tokens'       => array(),
			'ajax'                  => array(
				'endpoint'      => 'automator_clickup_fetch_spaces',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( ClickUp_App_Helpers::META_TEAM ),
			),
		);
	}

	/**
	 * Get folder option config.
	 *
	 * @return array
	 */
	public function get_folder_option_config() {
		return array(
			'input_type'            => 'select',
			'option_code'           => ClickUp_App_Helpers::META_FOLDER,
			'label'                 => esc_html_x( 'Folder', 'ClickUp', 'uncanny-automator' ),
			'placeholder'           => esc_html_x( 'Select a folder', 'ClickUp', 'uncanny-automator' ),
			'required'              => true,
			'supports_custom_value' => false,
			'options'               => array(),
			'options_show_id'       => false,
			'relevant_tokens'       => array(),
			'ajax'                  => array(
				'endpoint'      => 'automator_clickup_fetch_folders',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( ClickUp_App_Helpers::META_SPACE ),
			),
		);
	}
}
