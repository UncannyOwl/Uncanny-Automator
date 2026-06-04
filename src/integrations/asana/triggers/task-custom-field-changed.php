<?php

namespace Uncanny_Automator\Integrations\Asana;

/**
 * Trigger: {{A custom field}} value of {{a task}} changes to {{a specific value}}
 *
 * @package Uncanny_Automator\Integrations\Asana
 *
 * @property Asana_App_Helpers $helpers
 * @property Asana_Api_Caller $api
 * @property Asana_Webhooks $webhooks
 */
class TASK_CUSTOM_FIELD_CHANGED extends \Uncanny_Automator\Recipe\App_Trigger {

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
	 * Custom field meta key.
	 *
	 * @var string
	 */
	private $custom_field_meta_key = 'ASANA_CUSTOM_FIELD';

	/**
	 * Custom field value meta key.
	 *
	 * @var string
	 */
	private $custom_field_value_meta_key = 'ASANA_CUSTOM_FIELD_VALUE';

	/**
	 * Setup the trigger
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->workspace_meta_key = $this->helpers->get_const( 'ACTION_WORKSPACE_META_KEY' );
		$this->project_meta_key   = $this->helpers->get_const( 'ACTION_PROJECT_META_KEY' );

		$this->set_integration( 'ASANA' );
		$this->set_trigger_code( 'TASK_CUSTOM_FIELD_CHANGED' );
		$this->set_trigger_meta( 'TASK_CUSTOM_FIELD_CHANGED_META' );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: Custom field, %2$s: Task, %3$s: Value
				esc_attr_x( "A tasks's {{custom field:%1\$s}} is set to {{a specific value:%2\$s}}", 'Asana', 'uncanny-automator' ),
				$this->custom_field_meta_key . ':' . $this->get_trigger_meta(),
				$this->custom_field_value_meta_key . ':' . $this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( "A tasks's {{custom field}} is set to {{a specific value}}", 'Asana', 'uncanny-automator' ) );
		$this->add_action( 'automator_asana_task_custom_field_changed' );
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
				'input_type'      => 'select',
				'option_code'     => $this->custom_field_meta_key,
				'label'           => esc_html_x( 'Custom field', 'Asana', 'uncanny-automator' ),
				'required'        => true,
				'relevant_tokens' => array(),
				'remote_data'     => $this->helpers->remote_data_load_config( 'webhook_project_custom_fields' ),
			),
			array(
				'input_type'      => 'text',
				'option_code'     => $this->custom_field_value_meta_key,
				'label'           => esc_html_x( 'Value', 'Asana', 'uncanny-automator' ),
				'description'     => esc_html_x( 'Enter the value to match. For dropdown fields, use the exact option name. Leave empty to trigger on any value change.', 'Asana', 'uncanny-automator' ),
				'required'        => false,
				'relevant_tokens' => array(),
			),
		);
	}

	/**
	 * Define trigger tokens.
	 *
	 * @param array $trigger
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		// Get common tokens from helper
		$common_tokens = array_merge(
			Asana_Tokens::get_trigger_workspace_token_definitions(),
			Asana_Tokens::get_trigger_project_token_definitions(),
			Asana_Tokens::get_trigger_extended_task_token_definitions(),
			Asana_Tokens::get_trigger_task_assignee_token_definitions()
		);

		// Define custom field specific tokens
		$custom_field_tokens = array(
			array(
				'tokenId'   => 'ASANA_CUSTOM_FIELD_ID',
				'tokenName' => esc_html_x( 'Custom field ID', 'Asana', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ASANA_CUSTOM_FIELD_NAME',
				'tokenName' => esc_html_x( 'Custom field name', 'Asana', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ASANA_CUSTOM_FIELD_VALUE',
				'tokenName' => esc_html_x( 'Custom field value', 'Asana', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ASANA_CHANGED_BY_ID',
				'tokenName' => esc_html_x( 'Changed by member ID', 'Asana', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ASANA_CHANGED_BY_NAME',
				'tokenName' => esc_html_x( 'Changed by member name', 'Asana', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ASANA_CHANGED_BY_EMAIL',
				'tokenName' => esc_html_x( 'Changed by member email', 'Asana', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'ASANA_CHANGED_AT',
				'tokenName' => esc_html_x( 'Changed at', 'Asana', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);

		return array_merge( $common_tokens, $custom_field_tokens, $tokens );
	}

	/**
	 * Validate trigger.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		if ( ! $trigger || ! isset( $trigger['meta'][ $this->get_trigger_meta() ], $hook_args[0], $hook_args[1] ) ) {
			return false;
		}

		$project_id = $hook_args[0];
		$event_data = $hook_args[1];

		// Get the selected task, custom field, and value from the trigger.
		$selected_task         = $trigger['meta'][ $this->get_trigger_meta() ];
		$selected_custom_field = $trigger['meta'][ $this->custom_field_meta_key ] ?? '';
		$selected_value        = $trigger['meta'][ $this->custom_field_value_meta_key ] ?? '';
		$task_id               = $event_data['task_id'] ?? '';

		if ( empty( $task_id ) ) {
			return false;
		}

		// Check if specific task is selected and matches.
		if ( ! empty( $selected_task ) && '-1' !== $selected_task && $selected_task !== $task_id ) {
			return false;
		}

		// Check if this is a custom field change for the selected field.
		$custom_fields     = $event_data['custom_fields'] ?? array();
		$custom_field_gids = $this->helpers->get_custom_field_gids_from_webhook( $custom_fields );
		$custom_field_gids = array_map( 'strval', $custom_field_gids );
		if ( ! in_array( (string) $selected_custom_field, $custom_field_gids, true ) ) {
			return false;
		}

		// If no specific value is set, trigger on any change to this custom field.
		if ( empty( $selected_value ) ) {
			return true;
		}

		// Validate the custom field value.
		// @todo: We could attempt to limit API calls by validating against our cached
		// custom fields values first before fetching the task.
		// Would only save us when the trigger is skipped as we fetch task for token hydration.
		// Downside is that it increases complexity.
		$task          = $this->get_task_details( $task_id );
		$current_value = $this->get_custom_field_value( $task, $selected_custom_field );
		return $this->values_match( $current_value, $selected_value, $selected_custom_field, $task );
	}

	/**
	 * Hydrate tokens.
	 *
	 * @param array $completed_trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $completed_trigger, $hook_args ) {
		$project_id = $hook_args[0];
		$event_data = $hook_args[1];
		$task_id    = $event_data['task_id'] ?? '';
		$task       = $this->get_task_details( $task_id );

		// Get the user who changed the custom field
		$changed_by_user = $this->helpers->get_workspace_user_option( $event_data['workspace_id'], $event_data['user_id'] );

		// Get custom field details
		$selected_custom_field = $completed_trigger['meta'][ $this->custom_field_meta_key ] ?? '';
		$custom_field_name     = '';
		$custom_field_value    = '';

		// Find the custom field that was changed.
		if ( ! empty( $task['custom_fields'] ) && ! empty( $selected_custom_field ) ) {
			foreach ( $task['custom_fields'] as $field ) {
				if ( $field['gid'] === $selected_custom_field ) {
					$custom_field_name  = $field['name'] ?? '';
					$custom_field_value = $this->get_custom_field_value( $task, $selected_custom_field );
					break;
				}
			}
		}

		// Use helper for common tokens
		$tokens = array_merge(
			Asana_Tokens::hydrate_workspace_tokens( $event_data ),
			Asana_Tokens::hydrate_project_tokens( $event_data, $project_id ),
			Asana_Tokens::hydrate_extended_task_tokens( $event_data, $task ),
			Asana_Tokens::hydrate_task_assignee_tokens( $task )
		);

		// Add custom field specific tokens
		$tokens['ASANA_CUSTOM_FIELD_ID']    = $selected_custom_field;
		$tokens['ASANA_CUSTOM_FIELD_NAME']  = $custom_field_name;
		$tokens['ASANA_CUSTOM_FIELD_VALUE'] = $custom_field_value;
		$tokens['ASANA_CHANGED_BY_ID']      = $changed_by_user['value'] ?? '';
		$tokens['ASANA_CHANGED_BY_NAME']    = $changed_by_user['text'] ?? '';
		$tokens['ASANA_CHANGED_BY_EMAIL']   = $changed_by_user['email'] ?? '';
		$tokens['ASANA_CHANGED_AT']         = $task['modified_at'] ?? '';

		return $tokens;
	}

	/**
	 * Get the human-readable value for a custom field.
	 *
	 * @param array $task The task data
	 * @param string $field_gid The custom field GID
	 *
	 * @return string
	 */
	private function get_custom_field_value( $task, $field_gid ) {
		$custom_fields = $task['custom_fields'] ?? array();
		return $this->helpers->get_custom_field_value_from_task( $custom_fields, $field_gid );
	}

	/**
	 * Check if the current custom field value matches the selected value.
	 * For enum fields, also check if the selected value matches the enum option GID.
	 *
	 * @param string $current_value The current field value
	 * @param string $value         The user-entered value to match
	 * @param string $field_gid     The custom field GID
	 * @param array  $task          The task data
	 * @return bool True if values match
	 */
	private function values_match( $current_value, $value, $field_gid, $task ) {
		// Direct match (handles text, number, and enum name matches)
		if ( $current_value === $value ) {
			return true;
		}

		// For enum fields, also check if the selected value is the enum option GID
		if ( ! empty( $task['custom_fields'] ) ) {
			foreach ( $task['custom_fields'] as $field ) {
				if ( $field['gid'] === $field_gid && isset( $field['enum_value']['gid'] ) ) {
					return $field['enum_value']['gid'] === $value;
				}
			}
		}

		return false;
	}

	/**
	 * Get task details with caching.
	 * Used to validate and hydrate tokens.
	 *
	 * @param string $task_id
	 *
	 * @return array
	 */
	private function get_task_details( $task_id ) {
		static $tasks = array();

		if ( empty( $task_id ) ) {
			return array();
		}

		if ( isset( $tasks[ $task_id ] ) ) {
			return $tasks[ $task_id ];
		}

		$tasks[ $task_id ] = $this->api->get_task( $task_id );

		return $tasks[ $task_id ];
	}
}
