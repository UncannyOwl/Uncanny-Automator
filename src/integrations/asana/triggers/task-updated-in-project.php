<?php

namespace Uncanny_Automator\Integrations\Asana;

/**
 * Trigger: {{A task}} is updated in {{a specific project}}
 *
 * @package Uncanny_Automator\Integrations\Asana
 *
 * @property Asana_App_Helpers $helpers
 * @property Asana_Api_Caller $api
 * @property Asana_Webhooks $webhooks
 */
class TASK_UPDATED_IN_PROJECT extends \Uncanny_Automator\Recipe\App_Trigger {

	/**
	 * Workspace meta key.
	 *
	 * @var string
	 */
	private $workspace_meta_key;

	/**
	 * Project meta key.
	 *
	 * @var string
	 */
	private $project_meta_key;

	/**
	 * Setup the trigger
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->workspace_meta_key = $this->helpers->get_const( 'ACTION_WORKSPACE_META_KEY' );
		$this->project_meta_key   = $this->helpers->get_const( 'ACTION_PROJECT_META_KEY' );

		$this->set_integration( 'ASANA' );
		$this->set_trigger_code( 'TASK_UPDATED_IN_PROJECT' );
		$this->set_trigger_meta( 'TASK_UPDATED_IN_PROJECT_META' );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: Task name, %2$s: Project name
				esc_attr_x( '{{A task:%1$s}} is updated in {{a specific project:%2$s}}', 'Asana', 'uncanny-automator' ),
				$this->get_trigger_meta(),
				$this->project_meta_key . ':' . $this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( '{{A task}} is updated in {{a specific project}}', 'Asana', 'uncanny-automator' ) );
		$this->add_action( 'automator_asana_task_changed' );
		$this->set_action_args_count( 3 );
		$this->set_trigger_type( 'anonymous' );
		$this->set_is_login_required( false );
		$this->set_uses_api( true );
	}

	/**
	 * Define trigger options
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_webhook_workspace_option_config( $this->workspace_meta_key ),
			$this->helpers->get_webhook_project_option_config(
				$this->project_meta_key,
				$this->workspace_meta_key,
				esc_html_x( 'Task updated', 'Asana', 'uncanny-automator' )
			),
			$this->helpers->get_webhook_task_option_config(
				$this->get_trigger_meta(),
				$this->project_meta_key
			),
			array(
				'option_code'              => 'FIELDS_TO_MONITOR',
				'label'                    => esc_attr_x( 'Fields to monitor', 'Asana', 'uncanny-automator' ),
				'input_type'               => 'select',
				'required'                 => false,
				'supports_multiple_values' => true,
				'options'                  => array(),
				'relevant_tokens'          => array(),
				'description'              => esc_attr_x( 'Leave empty to listen to all field changes.', 'Asana', 'uncanny-automator' ),
				'remote_data'              => $this->helpers->remote_data_parent_config(
					'task_fields',
					array( $this->project_meta_key )
				),
			),
		);
	}

	/**
	 * @param $trigger
	 * @param $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		// Get common tokens from helper
		$common_tokens = array_merge(
			Asana_Tokens::get_trigger_workspace_token_definitions(),
			Asana_Tokens::get_trigger_project_token_definitions(),
			Asana_Tokens::get_trigger_extended_task_token_definitions()
		);

		// Define task updated specific tokens.
		$task_updated_tokens = array(
			array(
				'tokenId'   => 'ASANA_TASK_DUE_DATE',
				'tokenName' => esc_html_x( 'Task due date', 'Asana', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ASANA_UPDATED_BY_NAME',
				'tokenName' => esc_html_x( 'Updated by name', 'Asana', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ASANA_UPDATED_BY_EMAIL',
				'tokenName' => esc_html_x( 'Updated by email', 'Asana', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'ASANA_UPDATED_AT',
				'tokenName' => esc_html_x( 'Updated at', 'Asana', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ASANA_CHANGED_FIELD',
				'tokenName' => esc_html_x( 'Changed field', 'Asana', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ASANA_CHANGED_FIELD_IDS',
				'tokenName' => esc_html_x( 'Changed field IDs', 'Asana', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ASANA_CHANGED_FIELD_NAMES',
				'tokenName' => esc_html_x( 'Changed field names', 'Asana', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);

		return array_merge( $common_tokens, $task_updated_tokens, $tokens );
	}

	/**
	 * Validate trigger.
	 *
	 * @param $trigger
	 * @param $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		if ( ! $trigger || ! isset( $trigger['meta'][ $this->get_trigger_meta() ], $hook_args[0], $hook_args[1] ) ) {
			return false;
		}

		$project_id = $hook_args[0];
		$event_data = $hook_args[1];

		// Get the selected project and task from the trigger.
		$selected_project_id = $trigger['meta'][ $this->project_meta_key ];
		$selected_task_id    = $trigger['meta'][ $this->get_trigger_meta() ];
		$is_any_task         = '-1' === $selected_task_id;

		// Check if the task was updated in the selected project.
		if ( (string) $project_id !== (string) $selected_project_id ) {
			return false;
		}

		// If not any task, check if the proper task was updated.
		if ( ! $is_any_task && (string) $selected_task_id !== (string) $event_data['task_id'] ) {
			return false;
		}

		$changed_fields = $event_data['changed_field'] ?? array();

		// Ensure changed_fields is always an array.
		if ( ! is_array( $changed_fields ) ) {
			$changed_fields = array( $changed_fields );
		}

		// Get field monitoring configuration.
		$fields_to_monitor = $trigger['meta']['FIELDS_TO_MONITOR'] ?? false;

		// If no field monitoring is configured, allow all changes.
		if ( ! $fields_to_monitor ) {
			return true;
		}

		// Prepare fields to monitor.
		$monitored_fields = $this->get_monitored_fields( $trigger );
		if ( empty( $monitored_fields ) ) {
			return false;
		}

		// Check if any selected standard fields were changed.
		foreach ( $changed_fields as $field ) {
			if ( in_array( (string) $field, $monitored_fields, true ) ) {
				return true;
			}
		}

		// Check if custom fields were changed.
		if ( ! in_array( 'custom_fields', $changed_fields, true ) ) {
			return false;
		}

		$custom_fields     = $event_data['custom_fields'] ?? array();
		$custom_field_gids = $this->helpers->get_custom_field_gids_from_webhook( $custom_fields );

		// If any changed custom field is being monitored, allow trigger
		foreach ( $custom_field_gids as $gid ) {
			if ( in_array( (string) $gid, $monitored_fields, true ) ) {
				return true;
			}
		}

		// Field change doesn't match any configured monitoring.
		return false;
	}

	/**
	 * Hydrate tokens.
	 *
	 * @param $completed_trigger
	 * @param $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $completed_trigger, $hook_args ) {
		$project_id = $hook_args[0];
		$event_data = $hook_args[1];

		// Get current task details from API.
		$task_id      = $event_data['task_id'] ?? null;
		$task_details = null;
		if ( ! empty( $task_id ) ) {
			$task_details = $this->api->get_task( $task_id );
		}

		// Use helper for common tokens.
		$tokens = array_merge(
			Asana_Tokens::hydrate_workspace_tokens( $event_data ),
			Asana_Tokens::hydrate_project_tokens( $event_data, $project_id ),
			Asana_Tokens::hydrate_extended_task_tokens( $event_data, $task_details )
		);

		// Add task updated specific tokens.
		if ( $task_details ) {
			$tokens['ASANA_TASK_DUE_DATE'] = $task_details['due_on'] ?? '';

			// Get our user info from options.
			$user_info                        = $this->webhooks->get_user_token_data( $event_data );
			$tokens['ASANA_UPDATED_BY_NAME']  = $user_info['text'];
			$tokens['ASANA_UPDATED_BY_EMAIL'] = $user_info['email'];

			// Use task modification time if available.
			if ( ! empty( $task_details['modified_at'] ) ) {
				$tokens['ASANA_UPDATED_AT'] = $task_details['modified_at'];
			}
		}

		// Add field change tokens.
		$tokens['ASANA_CHANGED_FIELD']       = $this->format_changed_fields_for_tokens( $event_data['changed_field'] ?? array() );
		$tokens['ASANA_CHANGED_FIELD_IDS']   = '';
		$tokens['ASANA_CHANGED_FIELD_NAMES'] = '';

		// Populate field change tokens if we have task details.
		if ( $task_details ) {
			$tokens = $this->populate_field_change_tokens( $tokens, $completed_trigger, $event_data, $task_details );
		}

		return $tokens;
	}

	/**
	 * Populate field change tokens.
	 *
	 * @param array $tokens
	 * @param array $completed_trigger
	 * @param array $event_data
	 * @param array $task_details
	 * @return array
	 */
	private function populate_field_change_tokens( $tokens, $completed_trigger, $event_data, $task_details ) {
		$changed_fields = $event_data['changed_field'] ?? array();
		if ( ! is_array( $changed_fields ) ) {
			$changed_fields = array( $changed_fields );
		}

		// Get monitored fields from trigger configuration.
		$monitored_fields = $this->get_monitored_fields( $completed_trigger );
		if ( empty( $monitored_fields ) ) {
			return $tokens;
		}

		// Process changed fields to populate unified tokens.
		$changed_field_ids   = array();
		$changed_field_names = array();

		// Process standard fields that were changed.
		$changed_field_ids   = array_merge( $changed_field_ids, $this->get_changed_standard_fields( $changed_fields, $monitored_fields ) );
		$changed_field_names = array_merge( $changed_field_names, $this->get_changed_standard_field_names( $changed_fields, $monitored_fields ) );

		// Process custom fields that were changed.
		if ( in_array( 'custom_fields', $changed_fields, true ) ) {
			$custom_field_data   = $this->get_changed_custom_fields( $event_data, $task_details, $monitored_fields );
			$changed_field_ids   = array_merge( $changed_field_ids, $custom_field_data['ids'] );
			$changed_field_names = array_merge( $changed_field_names, $custom_field_data['names'] );
		}

		// Populate the unified tokens.
		if ( ! empty( $changed_field_ids ) ) {
			$tokens['ASANA_CHANGED_FIELD_IDS'] = implode( ', ', $changed_field_ids );
		}

		if ( ! empty( $changed_field_names ) ) {
			$tokens['ASANA_CHANGED_FIELD_NAMES'] = implode( ', ', $changed_field_names );
		}

		return $tokens;
	}

	/**
	 * Get monitored fields from trigger configuration.
	 *
	 * @param array $trigger_data
	 * @return array
	 */
	private function get_monitored_fields( $trigger_data ) {
		// Ensure we have valid trigger data structure.
		if ( ! is_array( $trigger_data ) || ! isset( $trigger_data['meta'] ) ) {
			return array();
		}

		$fields_to_monitor = $trigger_data['meta']['FIELDS_TO_MONITOR'] ?? false;
		if ( ! $fields_to_monitor ) {
			return array();
		}

		$monitored_fields = json_decode( $fields_to_monitor, true );
		if ( ! is_array( $monitored_fields ) ) {
			return array();
		}

		return array_map( 'strval', $monitored_fields );
	}

	/**
	 * Get changed standard field IDs.
	 *
	 * @param array $changed_fields
	 * @param array $monitored_fields
	 * @return array
	 */
	private function get_changed_standard_fields( $changed_fields, $monitored_fields ) {
		$field_ids = array();

		foreach ( $changed_fields as $field ) {
			$field_str = (string) $field;
			if ( in_array( $field_str, $monitored_fields, true ) ) {
				$field_ids[] = $field_str;
			}
		}

		return $field_ids;
	}

	/**
	 * Get changed standard field names.
	 *
	 * @param array $changed_fields
	 * @param array $monitored_fields
	 * @return array
	 */
	private function get_changed_standard_field_names( $changed_fields, $monitored_fields ) {
		$field_names = array();

		foreach ( $changed_fields as $field ) {
			$field_str = (string) $field;
			if ( in_array( $field_str, $monitored_fields, true ) ) {
				$field_names[] = $this->get_field_display_name( $field_str );
			}
		}

		return $field_names;
	}

	/**
	 * Get changed custom field data.
	 *
	 * @param array $event_data
	 * @param array $task_details
	 * @param array $monitored_fields
	 * @return array
	 */
	private function get_changed_custom_fields( $event_data, $task_details, $monitored_fields ) {
		$webhook_custom_fields = $event_data['custom_fields'] ?? array();
		$changed_field_gids    = $this->helpers->get_custom_field_gids_from_webhook( $webhook_custom_fields );
		$custom_fields         = $task_details['custom_fields'] ?? array();

		$field_ids   = array();
		$field_names = array();

		foreach ( $changed_field_gids as $gid ) {
			$gid_str = (string) $gid;
			if ( in_array( $gid_str, $monitored_fields, true ) ) {
				$field_ids[]   = $gid_str;
				$field_names[] = $this->get_custom_field_display_name( $custom_fields, $gid );
			}
		}

		return array(
			'ids'   => $field_ids,
			'names' => $field_names,
		);
	}

	/**
	 * Format changed fields for token display.
	 *
	 * @param array|string $changed_fields
	 * @return string
	 */
	private function format_changed_fields_for_tokens( $changed_fields ) {
		if ( is_array( $changed_fields ) ) {
			return implode( ', ', $changed_fields );
		}

		return (string) $changed_fields;
	}

	/**
	 * Get display name for standard fields.
	 *
	 * @param string $field_id
	 * @return string
	 */
	private function get_field_display_name( $field_id ) {
		// Inlined narrow set (formerly Pro's Asana_Pro_App_Helpers::get_standard_task_fields()): the Base helper exposes a 12-field set, but this trigger only needs labels for the standard fields the user can monitor — keeping the original 3 preserves byte-for-byte token output and avoids surfacing extra labels.
		$standard_fields = array(
			array(
				'value' => 'name',
				'text'  => esc_html_x( 'Task name', 'Asana', 'uncanny-automator' ),
			),
			array(
				'value' => 'notes',
				'text'  => esc_html_x( 'Task description', 'Asana', 'uncanny-automator' ),
			),
			array(
				'value' => 'due_on',
				'text'  => esc_html_x( 'Due date', 'Asana', 'uncanny-automator' ),
			),
		);

		foreach ( $standard_fields as $field ) {
			if ( $field['value'] === $field_id ) {
				return $field['text'];
			}
		}

		return $field_id;
	}

	/**
	 * Get display name for custom fields.
	 *
	 * @param array  $custom_fields
	 * @param string $gid
	 * @return string
	 */
	private function get_custom_field_display_name( $custom_fields, $gid ) {
		foreach ( $custom_fields as $field ) {
			if ( $field['gid'] === $gid ) {
				return $field['name'] ?? $gid;
			}
		}

		return $gid;
	}
}
