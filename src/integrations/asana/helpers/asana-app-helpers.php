<?php

namespace Uncanny_Automator\Integrations\Asana;

use Exception;

/**
 * Class Asana_App_Helpers
 *
 * @package Uncanny_Automator
 *
 * @property Asana_API $api
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
			'ajax'            => array(
				'endpoint' => 'automator_asana_get_workspace_options',
				'event'    => 'on_load',
			),
		);
	}

	/**
	 * Get workspace options AJAX.
	 *
	 * @return void
	 */
	public function get_workspace_options_ajax() {
		Automator()->utilities->verify_nonce();
		$workspaces = $this->api->get_user_workspaces( $this->is_ajax_refresh() );

		wp_send_json(
			array(
				'success' => true,
				'options' => $workspaces,
			)
		);
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
			'ajax'            => array(
				'endpoint'      => 'automator_asana_get_project_options',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( self::ACTION_WORKSPACE_META_KEY ),
			),
		);
	}

	/**
	 * Get project options AJAX.
	 *
	 * @return void
	 */
	public function get_project_options_ajax() {
		Automator()->utilities->verify_nonce();
		$workspace_id = $this->get_workspace_from_ajax();
		$projects     = $this->api->get_workspace_projects( $workspace_id, $this->is_ajax_refresh() );

		wp_send_json(
			array(
				'success' => true,
				'options' => $projects,
			)
		);
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
			'ajax'                     => array(
				'endpoint'      => 'automator_asana_get_task_options',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( self::ACTION_PROJECT_META_KEY ),
			),
		);
	}

	/**
	 * Get task options AJAX.
	 *
	 * @return void
	 */
	public function get_task_options_ajax() {
		Automator()->utilities->verify_nonce();
		$project_id = $this->get_project_from_ajax();
		$tasks      = $this->api->get_project_tasks( $project_id, $this->is_ajax_refresh() );

		wp_send_json(
			array(
				'success' => true,
				'options' => $tasks,
			)
		);
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
			'ajax'                     => array(
				'endpoint'      => 'automator_asana_get_tag_options',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( self::ACTION_WORKSPACE_META_KEY ),
			),
			'description'              => $is_update
				? esc_html_x( 'To create a new tag enter it as a custom value', 'Asana', 'uncanny-automator' )
				: esc_html_x( 'Select an existing tag to remove', 'Asana', 'uncanny-automator' ),
			'custom_value_description' => $is_update
				? esc_html_x( 'ID of existing tag or new tag name', 'Asana', 'uncanny-automator' )
				: esc_html_x( 'ID of existing tag', 'Asana', 'uncanny-automator' ),
		);
	}

	/**
	 * Get tag options AJAX.
	 *
	 * @return void
	 */
	public function get_tag_options_ajax() {
		Automator()->utilities->verify_nonce();
		$workspace_id = $this->get_workspace_from_ajax();
		$tags         = $this->api->get_workspace_tags( $workspace_id, $this->is_ajax_refresh() );

		wp_send_json(
			array(
				'success' => true,
				'options' => $tags,
			)
		);
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
	 * Get user options AJAX.
	 *
	 * @return void
	 */
	public function get_user_options_ajax() {
		Automator()->utilities->verify_nonce();
		$workspace_id = $this->get_workspace_from_ajax();
		$users        = $this->api->get_workspace_users( $workspace_id, $this->is_ajax_refresh() );
		$users        = $this->prepend_empty_option( $users );

		// Append the [DELETE] option to update task meta.
		if ( 'ASANA_UPDATE_TASK_META' === automator_filter_input( 'group_id', INPUT_POST ) ) {
			$users = $this->append_delete_option( $users );
		}

		wp_send_json(
			array(
				'success' => true,
				'options' => $users,
			)
		);
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
	 * AJAX handler for field options.
	 *
	 * @return void
	 */
	public function get_field_options_ajax() {
		Automator()->utilities->verify_nonce();
		$project_id = $this->get_project_from_ajax();

		// Return empty if no project selected
		if ( empty( $project_id ) ) {
			wp_send_json(
				array(
					'success' => true,
					'options' => array(),
				)
			);
		}

		// Get standard fields
		$options = $this->get_standard_task_fields();

		// Add custom fields if project is selected
		$custom_fields = $this->api->get_project_custom_fields( $project_id, $this->is_ajax_refresh() );

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

		wp_send_json(
			array(
				'success' => true,
				'options' => $options,
			)
		);
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
	 * Get custom fields repeater for a project via AJAX.
	 *
	 * @return void
	 */
	public function get_custom_fields_repeater_ajax() {
		Automator()->utilities->verify_nonce();

		$group_id    = automator_filter_input( 'group_id', INPUT_POST );
		$option_code = 'ASANA_UPDATE_TASK_META' === $group_id
			? self::ACTION_PROJECT_META_KEY
			: $group_id;
		$project_id  = $this->get_project_from_ajax( $option_code );

		// Return empty if no project selected.
		if ( empty( $project_id ) ) {
			wp_send_json(
				array(
					'success'          => false,
					'error'            => esc_html_x( 'Please select a project.', 'Asana', 'uncanny-automator' ),
					'field_properties' => array(
						'fields' => array(),
					),
				)
			);
		}

		// Get custom fields for the project.
		$custom_fields = $this->api->get_project_custom_fields( $project_id, $this->is_ajax_refresh() );

		// Generate people options if necessary.
		$people_options = Asana_Custom_Fields_Helper::has_people_fields( $custom_fields )
			? $this->api->get_workspace_users( $this->get_workspace_from_ajax(), false )
			: array();

		// Generate repeater rows.
		$is_update_task = 'ASANA_UPDATE_TASK_META' === $group_id;
		$fields         = Asana_Custom_Fields_Helper::generate_repeater_fields( $custom_fields, $people_options, $is_update_task, $this );

		// Return rows.
		wp_send_json(
			array(
				'success'          => true,
				'field_properties' => array(
					'fields' => $fields,
				),
			)
		);
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
}
