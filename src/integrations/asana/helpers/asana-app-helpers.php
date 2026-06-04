<?php

namespace Uncanny_Automator\Integrations\Asana;

use Exception;

/**
 * Class Asana_App_Helpers
 *
 * @package Uncanny_Automator
 *
 * @property Asana_API $api
 * @property Asana_Webhooks $webhooks
 */
class Asana_App_Helpers extends \Uncanny_Automator\App_Integrations\App_Helpers {

	/**
	 * The option name for the workspaces.
	 *
	 * @var string
	 */
	const WORKSPACE_OPTION = 'automator_asana_workspaces';

	/**
	 * The option name for the projects.
	 *
	 * @var string
	 */
	const PROJECT_OPTION = 'automator_asana_projects';

	/**
	 * The option name for the tasks.
	 *
	 * @var string
	 */
	const TASK_OPTION = 'automator_asana_tasks';

	/**
	 * The option name for the tags.
	 *
	 * @var string
	 */
	const TAG_OPTION = 'automator_asana_tags';

	/**
	 * Workspace field action meta key.
	 *
	 * @var string
	 */
	const ACTION_WORKSPACE_META_KEY = 'ASANA_WORKSPACE';

	/**
	 * Project field action meta key.
	 *
	 * @var string
	 */
	const ACTION_PROJECT_META_KEY = 'ASANA_PROJECT';

	/**
	 * Task field action meta key.
	 *
	 * @var string
	 */
	const ACTION_TASK_META_KEY = 'ASANA_TASK';

	/**
	 * The delete value.
	 *
	 * @var string
	 */
	const DELETE_VALUE = '[DELETE]';

	////////////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////////////

	/**
	 * Get account info.
	 *
	 * @return array
	 */
	public function get_account_info() {
		$credentials = $this->get_credentials();
		return $credentials['user'] ?? array();
	}

	/**
	 * Prepare credentials for storage.
	 *
	 * @param  array $credentials
	 * @return array
	 */
	public function prepare_credentials_for_storage( $credentials ) {
		// Extract workspaces from credentials before storage.
		$workspaces = $credentials['workspaces'] ?? array();

		// Save workspaces to their own option.
		if ( ! empty( $workspaces ) ) {
			automator_update_option( self::WORKSPACE_OPTION, $workspaces );
		}

		// Remove workspaces from credentials before storage.
		unset( $credentials['workspaces'] );

		return $credentials;
	}

	////////////////////////////////////////////////////////////
	// Recipe UI helper methods
	////////////////////////////////////////////////////////////

	/**
	 * Get workspace option config.
	 *
	 * @param string $option_code
	 *
	 * @return array
	 */
	public function get_workspace_option_config( $option_code ) {
		return array(
			'input_type'      => 'select',
			'option_code'     => $option_code,
			'label'           => esc_html_x( 'Workspace', 'Asana', 'uncanny-automator' ),
			'placeholder'     => esc_html_x( 'Select a workspace', 'Asana', 'uncanny-automator' ),
			'required'        => true,
			'options'         => array(),
			'options_show_id' => false,
			'relevant_tokens' => array(),
			'remote_data'     => $this->remote_data_load_config( 'workspaces' ),
		);
	}

	/**
	 * Fetch user workspaces.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_workspaces( $request ): array {
		$workspaces = $this->api->get_user_workspaces( $request->is_refresh() );
		return $this->remote_data_success( $workspaces );
	}

	/**
	 * Get project option config.
	 *
	 * @param string $option_code
	 *
	 * @return array
	 */
	public function get_project_option_config( $option_code ) {
		return array(
			'input_type'      => 'select',
			'option_code'     => $option_code,
			'label'           => esc_html_x( 'Project', 'Asana', 'uncanny-automator' ),
			'placeholder'     => esc_html_x( 'Select a project', 'Asana', 'uncanny-automator' ),
			'required'        => true,
			'options'         => array(),
			'options_show_id' => false,
			'relevant_tokens' => array(),
			'remote_data'     => $this->remote_data_parent_config( 'projects', array( self::ACTION_WORKSPACE_META_KEY ) ),
		);
	}

	/**
	 * Fetch projects for the selected workspace.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_projects( $request ): array {
		$workspace_id = $request->get_field_value( self::ACTION_WORKSPACE_META_KEY );
		$projects     = $this->api->get_workspace_projects( $workspace_id, $request->is_refresh() );
		return $this->remote_data_success( $projects );
	}

	/**
	 * Get workspace ID from $_POST.
	 *
	 * @param string $meta_key
	 *
	 * @return string
	 */
	public function get_workspace_from_ajax( $meta_key = self::ACTION_WORKSPACE_META_KEY ) {
		$values = automator_filter_has_var( 'values', INPUT_POST )
			? automator_filter_input_array( 'values', INPUT_POST )
			: array();

		return isset( $values[ $meta_key ] )
			? sanitize_text_field( wp_unslash( $values[ $meta_key ] ) )
			: '';
	}

	/**
	 * Get workspace info from parsed.
	 *
	 * @param array $parsed
	 * @param string $meta_key
	 *
	 * @return string
	 * @throws Exception
	 */
	public function get_workspace_from_parsed( $parsed, $meta_key = self::ACTION_WORKSPACE_META_KEY ) {
		if ( ! isset( $parsed[ $meta_key ] ) ) {
			throw new Exception( esc_html_x( 'Workspace is required.', 'Asana', 'uncanny-automator' ) );
		}

		return sanitize_text_field( $parsed[ $meta_key ] );
	}

	/**
	 * Get task option config.
	 *
	 * @param string $option_code
	 *
	 * @return array
	 */
	public function get_task_option_config( $option_code ) {
		return array_merge(
			$this->get_task_option_config_base( $option_code ),
			array(
				'remote_data' => $this->remote_data_parent_config(
					'tasks',
					array( self::ACTION_PROJECT_META_KEY )
				),
			)
		);
	}

	/**
	 * Shared field config for the Task select — every key except `remote_data`.
	 * Used by both the action-side `get_task_option_config()` and the trigger-side
	 * `get_webhook_task_option_config()` so the visible field shape stays in lockstep
	 * while each call site supplies its own routed `remote_data` block.
	 *
	 * @param string $option_code The field's option code.
	 *
	 * @return array
	 */
	private function get_task_option_config_base( $option_code ) {
		return array(
			'input_type'               => 'select',
			'option_code'              => $option_code,
			'label'                    => esc_html_x( 'Task', 'Asana', 'uncanny-automator' ),
			'placeholder'              => esc_html_x( 'Select a task', 'Asana', 'uncanny-automator' ),
			'required'                 => true,
			'options'                  => array(),
			'options_show_id'          => false,
			'relevant_tokens'          => array(),
			'custom_value_description' => esc_html_x( 'ID of existing task', 'Asana', 'uncanny-automator' ),
		);
	}

	/**
	 * Fetch tasks for the selected project.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_tasks( $request ): array {
		$project_id = $request->get_field_value( self::ACTION_PROJECT_META_KEY );
		$tasks      = $this->api->get_project_tasks( $project_id, $request->is_refresh() );
		return $this->remote_data_success( $tasks );
	}

	/**
	 * Get project ID from $_POST.
	 *
	 * @param string $meta_key
	 *
	 * @return string
	 */
	public function get_project_from_ajax( $meta_key = self::ACTION_PROJECT_META_KEY ) {
		$values = automator_filter_has_var( 'values', INPUT_POST )
			? automator_filter_input_array( 'values', INPUT_POST )
			: array();

		return isset( $values[ $meta_key ] )
			? sanitize_text_field( wp_unslash( $values[ $meta_key ] ) )
			: '';
	}

	/**
	 * Get project info from parsed.
	 *
	 * @param array $parsed
	 * @param string $meta_key
	 *
	 * @return string
	 * @throws Exception
	 */
	public function get_project_from_parsed( $parsed, $meta_key = self::ACTION_PROJECT_META_KEY ) {
		if ( ! isset( $parsed[ $meta_key ] ) ) {
			throw new Exception( esc_html_x( 'Project is required.', 'Asana', 'uncanny-automator' ) );
		}

		return sanitize_text_field( $parsed[ $meta_key ] );
	}

	/**
	 * Get task info from parsed.
	 *
	 * @param array $parsed
	 * @param string $meta_key
	 *
	 * @return string
	 * @throws Exception
	 */
	public function get_task_from_parsed( $parsed, $meta_key = self::ACTION_TASK_META_KEY ) {
		if ( ! isset( $parsed[ $meta_key ] ) ) {
			throw new Exception( esc_html_x( 'Task is required.', 'Asana', 'uncanny-automator' ) );
		}

		return sanitize_text_field( $parsed[ $meta_key ] );
	}

	/**
	 * Get tag option config.
	 *
	 * @param string $option_code
	 * @param bool $is_update
	 *
	 * @return array
	 */
	public function get_tag_option_config( $option_code, $is_update = true ) {
		return array(
			'input_type'               => 'select',
			'option_code'              => $option_code,
			'label'                    => esc_html_x( 'Tag', 'Asana', 'uncanny-automator' ),
			'required'                 => true,
			'options'                  => array(),
			'options_show_id'          => true,
			'relevant_tokens'          => array(),
			'remote_data'              => $this->remote_data_parent_config( 'tags', array( self::ACTION_WORKSPACE_META_KEY ) ),
			'description'              => $is_update
				? esc_html_x( 'To create a new tag enter it as a custom value', 'Asana', 'uncanny-automator' )
				: esc_html_x( 'Select an existing tag to remove', 'Asana', 'uncanny-automator' ),
			'custom_value_description' => $is_update
				? esc_html_x( 'ID of existing tag or new tag name', 'Asana', 'uncanny-automator' )
				: esc_html_x( 'ID of existing tag', 'Asana', 'uncanny-automator' ),
		);
	}

	/**
	 * Fetch tags for the selected workspace.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_tags( $request ): array {
		$workspace_id = $request->get_field_value( self::ACTION_WORKSPACE_META_KEY );
		$tags         = $this->api->get_workspace_tags( $workspace_id, $request->is_refresh() );
		return $this->remote_data_success( $tags );
	}

	/**
	 * Get tag info from parsed.
	 *
	 * @param array $parsed
	 * @param string $meta_key
	 *
	 * @return string
	 * @throws Exception
	 */
	public function get_tag_from_parsed( $parsed, $meta_key ) {
		if ( ! isset( $parsed[ $meta_key ] ) ) {
			throw new Exception( esc_html_x( 'Tag is required.', 'Asana', 'uncanny-automator' ) );
		}

		return sanitize_text_field( $parsed[ $meta_key ] );
	}

	/**
	 * Fetch users for the selected workspace, with the empty placeholder + the
	 * [DELETE] sentinel appended when the request comes from `ASANA_UPDATE_TASK_META`.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_users( $request ): array {
		$workspace_id = $request->get_field_value( self::ACTION_WORKSPACE_META_KEY );
		$users        = $this->api->get_workspace_users( $workspace_id, $request->is_refresh() );
		$users        = $this->prepend_empty_option( $users );

		if ( 'ASANA_UPDATE_TASK_META' === $request->get_group_id() ) {
			$users = $this->append_delete_option( $users );
		}

		return $this->remote_data_success( $users );
	}

	/**
	 * Find a user by ID in workspace users with retry logic.
	 *
	 * Tries cached data first, then refreshes if task not found.
	 *
	 * @param string $workspace_id The workspace ID.
	 * @param string $identifier The user ID or email to find.
	 * @param bool $refresh Whether this is already a refresh attempt.
	 *
	 * @return array User data array or empty array if not found.
	 */
	public function get_workspace_user_option( $workspace_id, $identifier, $refresh = false ) {
		if ( empty( $workspace_id ) || empty( $identifier ) || $this->is_delete_value( $identifier ) ) {
			return array();
		}

		// Get users from the workspace (cached or fresh based on $refresh)
		$users = $this->api->get_workspace_users( $workspace_id, $refresh );
		// Determine the key to use for the identifier.
		$key = $this->is_valid_gid( $identifier ) ? 'value' : 'email';

		// Look for the user in the results
		foreach ( $users as $user ) {
			if ( (string) $user[ $key ] === (string) $identifier ) {
				// Clean up the text field to remove email if it was appended for dropdown display
				if ( ! empty( $user['email'] ) && false !== strpos( $user['text'], '(' . $user['email'] . ')' ) ) {
					$user['text'] = str_replace( ' (' . $user['email'] . ')', '', $user['text'] );
				}
				return $user; // Found it
			}
		}

		// User not found - if this was cached data, try refreshing once to update the cache.
		if ( ! $refresh ) {
			return $this->get_workspace_user_option( $workspace_id, $identifier, true );
		}

		return array(); // User not found even after refresh
	}

	/**
	 * Fetch the field-picker options (standard fields + per-project custom fields)
	 * for the field-monitoring select used by triggers/actions that listen to specific fields.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_task_fields( $request ): array {
		$project_id = $request->get_field_value( self::ACTION_PROJECT_META_KEY );

		// No project selected — empty list (the consumer falls back to "all field changes").
		if ( empty( $project_id ) ) {
			return $this->remote_data_success( array() );
		}

		$options       = $this->get_standard_task_fields();
		$custom_fields = $this->api->get_project_custom_fields( $project_id, $request->is_refresh() );

		if ( ! empty( $custom_fields ) && is_array( $custom_fields ) ) {
			foreach ( $custom_fields as $field ) {
				$options[] = array(
					'value' => $field['value'],
					'text'  => sprintf(
						// translators: %s is the custom field name
						esc_html_x( 'Custom field - %s', 'Asana', 'uncanny-automator' ),
						$field['text']
					),
				);
			}
		}

		return $this->remote_data_success( $options );
	}

	/**
	 * Get standard task field definitions.
	 *
	 * @return array Array of standard field definitions with value and text keys
	 */
	public function get_standard_task_fields() {
		return array(
			array(
				'value' => 'notes',
				'text'  => esc_html_x( 'Task description', 'Asana', 'uncanny-automator' ),
			),
			array(
				'value' => 'html_notes',
				'text'  => esc_html_x( 'Task description (HTML)', 'Asana', 'uncanny-automator' ),
			),
			array(
				'value' => 'due_on',
				'text'  => esc_html_x( 'Due date', 'Asana', 'uncanny-automator' ),
			),
			array(
				'value' => 'start_on',
				'text'  => esc_html_x( 'Start date', 'Asana', 'uncanny-automator' ),
			),
			array(
				'value' => 'created_at',
				'text'  => esc_html_x( 'Created date', 'Asana', 'uncanny-automator' ),
			),
			array(
				'value' => 'modified_at',
				'text'  => esc_html_x( 'Modified date', 'Asana', 'uncanny-automator' ),
			),
			array(
				'value' => 'permalink_url',
				'text'  => esc_html_x( 'Task URL', 'Asana', 'uncanny-automator' ),
			),
			array(
				'value' => 'assignee.gid',
				'text'  => esc_html_x( 'Assignee ID', 'Asana', 'uncanny-automator' ),
			),
			array(
				'value' => 'assignee.name',
				'text'  => esc_html_x( 'Assignee name', 'Asana', 'uncanny-automator' ),
			),
			array(
				'value' => 'assignee.email',
				'text'  => esc_html_x( 'Assignee email', 'Asana', 'uncanny-automator' ),
			),
			array(
				'value' => 'completed',
				'text'  => esc_html_x( 'Completion status', 'Asana', 'uncanny-automator' ),
			),
			array(
				'value' => 'approval_status',
				'text'  => esc_html_x( 'Approval status', 'Asana', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Build the per-project custom-fields repeater (returns the dynamic-field
	 * `field_properties` envelope, not a flat options list).
	 *
	 * The cascade pivot key flips meaning by trigger/action: for `ASANA_UPDATE_TASK_META`
	 * the pivot is the action's project meta key, while every other consumer drives
	 * the repeater off whatever group_id the request carries.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_custom_fields( $request ): array {
		$group_id    = $request->get_group_id();
		$option_code = 'ASANA_UPDATE_TASK_META' === $group_id
			? self::ACTION_PROJECT_META_KEY
			: $group_id;
		$project_id  = $request->get_field_value( $option_code );

		if ( empty( $project_id ) ) {
			return $this->remote_data_error(
				esc_html_x( 'Please select a project.', 'Asana', 'uncanny-automator' ),
				'field_properties'
			);
		}

		$custom_fields  = $this->api->get_project_custom_fields( $project_id, $request->is_refresh() );
		$people_options = Asana_Custom_Fields_Helper::has_people_fields( $custom_fields )
			? $this->api->get_workspace_users( $request->get_field_value( self::ACTION_WORKSPACE_META_KEY ), false )
			: array();

		$is_update_task = 'ASANA_UPDATE_TASK_META' === $group_id;
		$fields         = Asana_Custom_Fields_Helper::generate_repeater_fields( $custom_fields, $people_options, $is_update_task, $this );

		return $this->remote_data_success( array( 'fields' => $fields ), 'field_properties' );
	}

	/**
	 * Validate if a value is a valid Asana GID.
	 *
	 * Asana GIDs are numeric strings that represent unique identifiers.
	 *
	 * @param mixed $value The value to validate
	 * @return bool True if the value is a valid GID, false otherwise
	 */
	public function is_valid_gid( $value ) {
		// Convert to string and check if it contains only digits (0-9)
		return 1 === preg_match( '/^\d+$/', (string) $value );
	}

	/**
	 * Validate and format date for Asana API.
	 *
	 * @param string $date The date string to validate and format
	 * @param string $format Optional output format (defaults to Y-m-d for Asana)
	 *
	 * @return string|false Returns formatted date or false if invalid
	 */
	public function validate_and_format_date( $date, $format = 'Y-m-d' ) {
		$date = sanitize_text_field( $date );

		// Try to parse the date string
		$timestamp = strtotime( $date );
		if ( $timestamp ) {
			return gmdate( $format, $timestamp );
		}

		return false;
	}

	/**
	 * Check if the value is the DELETE value.
	 *
	 * @param mixed $value The value to check
	 *
	 * @return bool True if the value is [DELETE], false otherwise
	 */
	public function is_delete_value( $value ) {
		if ( is_array( $value ) ) {
			return in_array( self::DELETE_VALUE, $value, true );
		}
		return self::DELETE_VALUE === $value;
	}

	/**
	 * Prepend an empty option to the options array.
	 *
	 * @param array $options The options array
	 *
	 * @return array The options with the empty option prepended
	 */
	public function prepend_empty_option( $options ) {
		return array_merge(
			array(
				array(
					'value' => '',
					'text'  => esc_html_x( 'Select option', 'Asana', 'uncanny-automator' ),
				),
			),
			$options
		);
	}

	/**
	 * Append the [DELETE] option to the options array.
	 *
	 * @param array $options The options array
	 *
	 * @return array The options with the [DELETE] option appended
	 */
	public function append_delete_option( $options ) {
		$options[] = array(
			'value' => self::DELETE_VALUE,
			'text'  => esc_html_x( 'Delete value', 'Asana', 'uncanny-automator' ),
		);
		return $options;
	}

	////////////////////////////////////////////////////////////
	// Webhook recipe UI helper methods
	////////////////////////////////////////////////////////////

	/**
	 * Get workspace option config for webhooks.
	 *
	 * @param string $option_code
	 *
	 * @return array
	 */
	public function get_webhook_workspace_option_config( $option_code ) {
		return array(
			'input_type'      => 'select',
			'option_code'     => $option_code,
			'label'           => esc_html_x( 'Workspace', 'Asana', 'uncanny-automator' ),
			'placeholder'     => esc_html_x( 'Select a workspace', 'Asana', 'uncanny-automator' ),
			'required'        => true,
			'options'         => array(),
			'options_show_id' => false,
			'relevant_tokens' => array(),
			'description'     => sprintf(
				// translators: %s is common text with a link to the Asana settings page.
				esc_html_x( 'Only workspaces with at least one connected project webhook will be shown. %s', 'Asana', 'uncanny-automator' ),
				$this->get_formatted_manage_webhooks_link()
			),
			'remote_data'     => $this->remote_data_load_config( 'webhook_workspaces' ),
		);
	}

	/**
	 * Fetch workspaces that have at least one project with a connected webhook
	 * subscription. Filtered down from the canonical webhook-manager config.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_webhook_workspaces( $request ): array {
		$webhook_config = $this->webhooks->get_webhook_manager_config();
		$options        = array();

		foreach ( $webhook_config as $project ) {
			if ( empty( $project['events'] ) || empty( $project['hook_id'] ) ) {
				continue;
			}

			$workspace_id   = $project['meta']['workspace_id'] ?? '';
			$workspace_name = $project['meta']['workspace_name'] ?? '';
			if ( '' === $workspace_id || isset( $options[ $workspace_id ] ) ) {
				continue;
			}

			$options[ $workspace_id ] = array(
				'text'  => $workspace_name,
				'value' => $workspace_id,
			);
		}

		return $this->remote_data_success( array_values( $options ) );
	}

	/**
	 * Get project option config for webhooks.
	 *
	 * @param string $option_code
	 * @param string $parent_code
	 * @param string $event
	 *
	 * @return array
	 */
	public function get_webhook_project_option_config( $option_code, $parent_code, $event ) {

		$description = 'all' !== $event
			? sprintf(
				// translators: %1$s is the event name, %2$s is common text with a link to the Asana settings page.
				esc_html_x( 'Only projects with a connected webhook for %1$s will be shown. %2$s', 'Asana', 'uncanny-automator' ),
				$event,
				$this->get_formatted_manage_webhooks_link()
			)
			: sprintf(
				// translators: %s is common text with a link to the Asana settings page.
				esc_html_x( 'Only projects with a connected webhook will be shown. %s', 'Asana', 'uncanny-automator' ),
				$this->get_formatted_manage_webhooks_link()
			);

		return array(
			'input_type'      => 'select',
			'option_code'     => $option_code,
			'label'           => esc_html_x( 'Project', 'Asana', 'uncanny-automator' ),
			'required'        => true,
			'options'         => array(),
			'options_show_id' => false,
			'relevant_tokens' => array(),
			'description'     => $description,
			'remote_data'     => $this->remote_data_parent_config( 'webhook_projects', array( $parent_code ) ),
		);
	}

	/**
	 * Fetch the projects in the selected workspace whose webhooks are subscribed
	 * to the event corresponding to the requesting trigger (group_id maps to the
	 * Asana event name via {@see self::get_event_filter()}).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_webhook_projects( $request ): array {
		$event     = $this->get_event_filter( $request->get_group_id() );
		$workspace = $request->get_field_value( self::ACTION_WORKSPACE_META_KEY );
		$options   = $this->get_webhook_project_options( $workspace, $event );
		return $this->remote_data_success( $options );
	}

	/**
	 * Get task option config for webhooks.
	 *
	 * @param string $option_code
	 * @param string $parent_code
	 * @param string $event
	 *
	 * @return array
	 */
	public function get_webhook_task_option_config( $option_code, $parent_code ) {
		return array_merge(
			$this->get_task_option_config_base( $option_code ),
			array(
				'remote_data' => $this->remote_data_parent_config(
					'webhook_tasks',
					array( $parent_code )
				),
			)
		);
	}

	/**
	 * Fetch tasks for the webhook-connected project, prepended with the "Any task"
	 * sentinel (`-1`) so triggers can listen for events on every task in the project.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_webhook_tasks( $request ): array {
		$project_id = $request->get_field_value( self::ACTION_PROJECT_META_KEY );
		$tasks      = $this->api->get_project_tasks( $project_id, $request->is_refresh() );

		$options = array(
			array(
				'text'  => esc_html_x( 'Any task', 'Asana', 'uncanny-automator' ),
				'value' => '-1',
			),
		);

		return $this->remote_data_success( array_merge( $options, $tasks ) );
	}

	/**
	 * Get event options.
	 *
	 * @return array
	 */
	public function get_event_options() {
		return array(
			array(
				'text'  => esc_html_x( 'Task added', 'Asana', 'uncanny-automator' ),
				'value' => 'task.added',
			),
			array(
				'text'  => esc_html_x( 'Task changed', 'Asana', 'uncanny-automator' ),
				'value' => 'task.changed',
			),
			array(
				'text'  => esc_html_x( 'Comment added', 'Asana', 'uncanny-automator' ),
				'value' => 'story.added',
			),
			array(
				'text'  => esc_html_x( 'Approval status changed', 'Asana', 'uncanny-automator' ),
				'value' => 'task.status_changed',
			),
		);
	}

	/**
	 * Get a formatted link to the Asana settings page.
	 *
	 * @return string
	 */
	public function get_formatted_manage_webhooks_link() {
		return sprintf(
			// translators: %s is a link to the Asana settings page.
			esc_html_x( 'Manage webhooks in your %s.', 'Asana', 'uncanny-automator' ),
			sprintf(
				// translators: %1$s is a link to the Asana settings page, %2$s is the text "Asana settings".
				'<a href="%1$s" target="_blank">%2$s</a>',
				esc_url( $this->get_settings_page_url() ),
				esc_html_x( 'Asana settings', 'Asana', 'uncanny-automator' )
			)
		);
	}

	/**
	 * Map a trigger meta code (the cascade pivot, `group_id` on the wire) to the
	 * Asana webhook-event name we should filter projects against.
	 * Returns `'all'` for unrecognized codes.
	 *
	 * @param string $option_code Trigger meta code (e.g. `COMMENT_ADDED_TO_TASK_META`).
	 *
	 * @return string
	 */
	private function get_event_filter( $option_code ) {
		$triggers = array(
			'COMMENT_ADDED_TO_TASK_META'     => 'story.added',
			'TASK_CREATED_IN_PROJECT_META'   => 'task.added',
			'TASK_UPDATED_IN_PROJECT_META'   => 'task.changed',
			'APPROVAL_STATUS_CHANGED_META'   => 'task.status_changed',
			'TASK_CUSTOM_FIELD_CHANGED_META' => 'task.changed',
		);

		return $triggers[ $option_code ] ?? 'all';
	}

	/**
	 * Get project options for webhooks.
	 *
	 * @param string $workspace_id
	 * @param string $event
	 *
	 * @return array
	 */
	public function get_webhook_project_options( $workspace_id, $event = 'all' ) {
		$webhook_config = $this->webhooks->get_webhook_manager_config();
		$options        = array();
		foreach ( $webhook_config as $project_id => $project ) {

			// Ensure the project is in the workspace.
			if ( ( $project['meta']['workspace_id'] ?? '' ) !== $workspace_id ) {
				continue;
			}

			// Ensure the webhook has been connected.
			if ( empty( $project['events'] ) || empty( $project['hook_id'] ) ) {
				continue;
			}

			// Ensure the event is in the webhook's events.
			if ( 'all' !== $event && ! in_array( $event, $project['events'], true ) ) {
				continue;
			}

			$options[] = array(
				'text'  => $project['name'],
				'value' => $project_id,
			);
		}

		return $options;
	}

	/**
	 * Fetch the custom-field options for the project picked in a webhook trigger
	 * (used by the `task-custom-field-changed` trigger's custom-field selector).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_webhook_project_custom_fields( $request ): array {
		$project_id    = $request->get_field_value( self::ACTION_PROJECT_META_KEY );
		$custom_fields = $this->api->get_project_custom_fields( $project_id, $request->is_refresh() );
		return $this->remote_data_success( $custom_fields );
	}

	////////////////////////////////////////////////////////////
	// Trigger custom field helper methods.
	////////////////////////////////////////////////////////////

	/**
	 * Extract custom field GIDs from webhook data.
	 *
	 * @param array $custom_fields Array of custom fields from webhook
	 * @return array Array of custom field GIDs
	 */
	public function get_custom_field_gids_from_webhook( $custom_fields ) {
		if ( empty( $custom_fields ) ) {
			return array();
		}

		return wp_list_pluck( $custom_fields, 'gid' );
	}

	/**
	 * Extract custom field value from task custom fields array.
	 *
	 * @param array  $custom_fields Array of custom fields from task details
	 * @param string $field_gid     GID of the custom field to extract
	 * @return string Custom field value or empty string
	 */
	public function get_custom_field_value_from_task( $custom_fields, $field_gid ) {
		if ( empty( $custom_fields ) || empty( $field_gid ) ) {
			return '';
		}

		foreach ( $custom_fields as $field ) {
			if ( $field['gid'] === $field_gid ) {
				// Handle enum fields (dropdown/multi-select)
				if ( ! empty( $field['enum_value']['name'] ) ) {
					return $field['enum_value']['name'];
				}
				// Handle text fields
				if ( ! empty( $field['text_value'] ) ) {
					return $field['text_value'];
				}
				// Handle number fields
				if ( ! empty( $field['number_value'] ) ) {
					return $field['number_value'];
				}
				// Field found but no value
				return '';
			}
		}

		return '';
	}

	/**
	 * Format custom field GIDs for token display.
	 *
	 * @param array $custom_fields Array of custom fields from webhook
	 * @return string Comma-separated list of GIDs
	 */
	public function format_custom_field_gids_for_tokens( $custom_fields ) {
		if ( empty( $custom_fields ) ) {
			return '';
		}

		$gids = $this->get_custom_field_gids_from_webhook( $custom_fields );
		return implode( ', ', $gids );
	}
}
