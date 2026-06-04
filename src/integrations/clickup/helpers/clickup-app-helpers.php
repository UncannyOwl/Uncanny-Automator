<?php

namespace Uncanny_Automator\Integrations\ClickUp;

/**
 * Class ClickUp_App_Helpers
 *
 * @package Uncanny_Automator\Integrations\ClickUp
 *
 * @property ClickUp_Api_Caller $api
 */
class ClickUp_App_Helpers extends \Uncanny_Automator\App_Integrations\App_Helpers {

	/**
	 * Field meta key constants.
	 */
	const META_TEAM     = 'TEAM';
	const META_SPACE    = 'SPACE';
	const META_FOLDER   = 'FOLDER';
	const META_LIST     = 'LIST';
	const META_TASK     = 'TASK';
	const META_ASSIGNEE = 'ASSIGNEE';
	const META_STATUS   = 'STATUS';
	const META_PRIORITY = 'PRIORITY';

	/**
	 * Set custom properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Preserve the existing option name from the legacy integration.
		$this->set_credentials_option_name( 'automator_clickup_client' );
	}

	////////////////////////////////////////////////////////////
	// Recipe UI option config methods
	////////////////////////////////////////////////////////////

	/**
	 * Get list option config.
	 *
	 * @param string $option_code
	 *
	 * @return array
	 */
	public function get_list_option_config( $option_code = self::META_LIST ) {
		return array(
			'input_type'            => 'select',
			'option_code'           => $option_code,
			'label'                 => esc_html_x( 'List', 'ClickUp', 'uncanny-automator' ),
			'placeholder'           => esc_html_x( 'Select a list', 'ClickUp', 'uncanny-automator' ),
			'required'              => true,
			'supports_custom_value' => false,
			'options'               => array(),
			'options_show_id'       => false,
			'relevant_tokens'       => array(),
			'remote_data'           => $this->remote_data_parent_config( 'lists', array( self::META_FOLDER ) ),
		);
	}

	/**
	 * Get task option config.
	 *
	 * @param string $option_code  The option meta key (default TASK).
	 * @param string $listen_field The field to listen to (default LIST).
	 *
	 * @return array
	 */
	public function get_task_option_config( $option_code = self::META_TASK, $listen_field = self::META_LIST ) {
		return array(
			'input_type'               => 'select',
			'option_code'              => $option_code,
			'label'                    => esc_html_x( 'Task', 'ClickUp', 'uncanny-automator' ),
			'placeholder'              => esc_html_x( 'Select a task', 'ClickUp', 'uncanny-automator' ),
			'required'                 => true,
			'options'                  => array(),
			'options_show_id'          => false,
			'relevant_tokens'          => array(),
			'supports_custom_value'    => true,
			'custom_value_description' => esc_html_x( 'Task ID', 'ClickUp', 'uncanny-automator' ),
			'remote_data'              => $this->remote_data_parent_config( 'tasks', array( $listen_field ) ),
		);
	}

	/**
	 * Get assignee option config.
	 *
	 * @param string $option_code  The option meta key.
	 * @param string $listen_field The field to listen to.
	 *
	 * @return array
	 */
	public function get_assignee_option_config( $option_code = self::META_ASSIGNEE, $listen_field = self::META_LIST ) {
		return array(
			'input_type'               => 'select',
			'option_code'              => $option_code,
			'label'                    => esc_html_x( 'Assignee', 'ClickUp', 'uncanny-automator' ),
			'placeholder'              => esc_html_x( 'No assignee', 'ClickUp', 'uncanny-automator' ),
			'required'                 => false,
			'options'                  => array(),
			'options_show_id'          => false,
			'supports_custom_value'    => true,
			'custom_value_description' => esc_html_x( 'ClickUp User ID', 'ClickUp', 'uncanny-automator' ),
			'remote_data'              => $this->remote_data_parent_config( 'assignees_list', array( $listen_field ) ),
		);
	}

	/**
	 * Get status option config.
	 *
	 * @param string $option_code The option meta key.
	 *
	 * @return array
	 */
	public function get_status_option_config( $option_code = self::META_STATUS ) {
		return array(
			'input_type'            => 'select',
			'option_code'           => $option_code,
			'label'                 => esc_html_x( 'Status', 'ClickUp', 'uncanny-automator' ),
			'placeholder'           => esc_html_x( 'Select a status', 'ClickUp', 'uncanny-automator' ),
			'required'              => false,
			'supports_custom_value' => false,
			'options'               => array(),
			'options_show_id'       => false,
			'relevant_tokens'       => array(),
			'remote_data'           => $this->remote_data_parent_config( 'statuses', array( self::META_SPACE ) ),
		);
	}

	/**
	 * Get name option config.
	 *
	 * @param string $option_code The option meta key.
	 *
	 * @return array
	 */
	public function get_name_option_config( $option_code = 'NAME' ) {
		return array(
			'option_code' => $option_code,
			'label'       => esc_html_x( 'Name', 'ClickUp', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => true,
		);
	}

	/**
	 * Get tag option config.
	 *
	 * @param string $option_code The option meta key.
	 *
	 * @return array
	 */
	public function get_tag_option_config( $option_code ) {
		return array(
			'option_code'              => $option_code,
			'label'                    => esc_html_x( 'Tag', 'ClickUp', 'uncanny-automator' ),
			'input_type'               => 'text',
			'required'                 => true,
			'custom_value_description' => esc_html_x( 'Tag ID', 'ClickUp', 'uncanny-automator' ),
		);
	}

	/**
	 * Get priority option config.
	 *
	 * @param bool $include_update_options Whether to include __NO_UPDATE__ and __REMOVE__ options.
	 *
	 * @return array
	 */
	public function get_priority_option_config( $include_update_options = false ) {
		return array(
			'input_type'            => 'select',
			'option_code'           => self::META_PRIORITY,
			'label'                 => esc_html_x( 'Priority', 'ClickUp', 'uncanny-automator' ),
			'required'              => false,
			'options'               => $this->get_priority_options( $include_update_options ),
			'options_show_id'       => false,
			'supports_custom_value' => true,
		);
	}

	/**
	 * Get priority options (static list).
	 *
	 * @param bool $include_update_options Whether to include __NO_UPDATE__ and __REMOVE__ options.
	 *
	 * @return array
	 */
	public function get_priority_options( $include_update_options = false ) {
		$options = array();

		if ( $include_update_options ) {
			$options[] = array(
				'value' => '__NO_UPDATE__',
				'text'  => esc_html_x( 'Leave unchanged in ClickUp', 'ClickUp', 'uncanny-automator' ),
			);
			$options[] = array(
				'value' => '__REMOVE__',
				'text'  => esc_html_x( 'Remove priority', 'ClickUp', 'uncanny-automator' ),
			);
		}

		$options[] = array(
			'value' => '0',
			'text'  => esc_html_x( 'No priority', 'ClickUp', 'uncanny-automator' ),
		);
		$options[] = array(
			'value' => '4',
			'text'  => esc_html_x( 'Low', 'ClickUp', 'uncanny-automator' ),
		);
		$options[] = array(
			'value' => '3',
			'text'  => esc_html_x( 'Normal', 'ClickUp', 'uncanny-automator' ),
		);
		$options[] = array(
			'value' => '2',
			'text'  => esc_html_x( 'High', 'ClickUp', 'uncanny-automator' ),
		);
		$options[] = array(
			'value' => '1',
			'text'  => esc_html_x( 'Urgent', 'ClickUp', 'uncanny-automator' ),
		);

		return $options;
	}

	////////////////////////////////////////////////////////////
	// Caching
	////////////////////////////////////////////////////////////

	/**
	 * Get cached option data if still valid.
	 *
	 * Returns the cached data array when valid and not expired,
	 * or null when a fresh API fetch is needed.
	 *
	 * @param string $suffix  The option key suffix (e.g. 'spaces_123').
	 * @param bool   $refresh Caller-supplied refresh flag (typically `$request->is_refresh()`).
	 *                        When true, bypasses the cache and forces a fetch.
	 *
	 * @return array|null Cached data or null if a fetch is needed.
	 */
	private function get_cached_option_data( $suffix, $refresh = false ) {
		if ( $refresh ) {
			return null;
		}

		$cached = $this->get_app_option( $this->get_option_key( $suffix ) );
		if ( ! empty( $cached['data'] ) && ! $cached['refresh'] ) {
			return $cached['data'];
		}

		return null;
	}

	/**
	 * Save option data from an API response to the cache.
	 *
	 * Only persists non-empty data to avoid caching failed or empty responses.
	 *
	 * @param string $suffix The option key suffix (e.g. 'spaces_123').
	 * @param array  $data   The formatted option data to cache.
	 *
	 * @return void
	 */
	private function save_option_data_to_cache( $suffix, $data ) {
		if ( ! empty( $data ) ) {
			$this->save_app_option( $this->get_option_key( $suffix ), $data );
		}
	}

	////////////////////////////////////////////////////////////
	// Remote-data REST handlers
	//
	// Reachable via POST /wp-json/uap/v2/remote-data/clickup/{data},
	// where {data} matches the suffix on the method name. Dispatched
	// through Abstract_Helpers::process_remote_data_request().
	////////////////////////////////////////////////////////////

	/**
	 * Fetch teams (workspaces).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_teams( $request ): array {
		$cached = $this->get_cached_option_data( 'workspaces', $request->is_refresh() );

		if ( null !== $cached ) {
			return $this->remote_data_success( $cached );
		}

		$teams = $this->api->get_team_workspaces();
		$this->save_option_data_to_cache( 'workspaces', $teams );

		return $this->remote_data_success( $teams );
	}

	/**
	 * Fetch spaces for the selected team.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_spaces( $request ): array {
		$team_id   = $request->get_field_value( self::META_TEAM );
		$cache_key = 'spaces_' . $team_id;
		$cached    = $this->get_cached_option_data( $cache_key, $request->is_refresh() );

		if ( null !== $cached ) {
			return $this->remote_data_success( $cached );
		}

		$spaces = $this->api->get_spaces( $team_id );
		$this->save_option_data_to_cache( $cache_key, $spaces );

		return $this->remote_data_success( $spaces );
	}

	/**
	 * Fetch folders for the selected space, with the
	 * "Folderless lists" pseudo-option prepended.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_folders( $request ): array {
		$space_id  = $request->get_field_value( self::META_SPACE );
		$cache_key = 'folders_' . $space_id;
		$cached    = $this->get_cached_option_data( $cache_key, $request->is_refresh() );

		if ( null !== $cached ) {
			return $this->remote_data_success( $cached );
		}

		$folders = $this->api->get_folders( $space_id );

		// Format folders with folderless lists option prepended.
		$options = array(
			array(
				'text'  => esc_html_x( 'Folderless lists', 'ClickUp', 'uncanny-automator' ),
				'value' => $space_id . '|SPACE_ID',
			),
		);

		foreach ( $folders as $folder ) {
			if ( empty( $folder['name'] ) ) {
				continue;
			}
			$options[] = array(
				'text'  => $folder['name'],
				'value' => (string) $folder['id'],
			);
		}

		$this->save_option_data_to_cache( $cache_key, $options );

		return $this->remote_data_success( $options );
	}

	/**
	 * Fetch lists for the selected folder.
	 *
	 * Not cached — lists are the terminal node before task selection
	 * and are more volatile than structural data (spaces, folders, statuses).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_lists( $request ): array {
		$folder_id = $request->get_field_value( self::META_FOLDER );
		$lists     = $this->api->get_lists( $folder_id );

		return $this->remote_data_success( $lists );
	}

	/**
	 * Fetch assignees for the selected list.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_assignees_list( $request ): array {
		$list_id = $request->get_field_value( self::META_LIST );

		// Also check for create-task action meta.
		if ( empty( $list_id ) ) {
			$list_id = $request->get_field_value( 'CLICKUP_SPACE_LIST_TASK_CREATE_META' );
		}

		$options = $this->api->get_list_members( $list_id );

		return $this->remote_data_success( $options );
	}

	/**
	 * Fetch statuses for the selected space.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_statuses( $request ): array {
		$space_id  = $request->get_field_value( self::META_SPACE );
		$cache_key = 'statuses_' . $space_id;
		$cached    = $this->get_cached_option_data( $cache_key, $request->is_refresh() );

		if ( null !== $cached ) {
			return $this->remote_data_success( $cached );
		}

		$statuses = $this->api->get_space_statuses( $space_id );
		$this->save_option_data_to_cache( $cache_key, $statuses );

		return $this->remote_data_success( $statuses );
	}

	/**
	 * Fetch tasks for the selected list.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_tasks( $request ): array {
		$list_id = $request->get_field_value( self::META_LIST );
		$tasks   = $this->api->get_list_tasks( $list_id );

		return $this->remote_data_success( $tasks );
	}
}
