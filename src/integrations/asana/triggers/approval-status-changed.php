<?php

namespace Uncanny_Automator\Integrations\Asana;

/**
 * Trigger: A status of {{an approval task}} changes to {{a status}}
 *
 * @package Uncanny_Automator\Integrations\Asana
 *
 * @property Asana_App_Helpers $helpers
 * @property Asana_Api_Caller $api
 * @property Asana_Webhooks $webhooks
 */
class APPROVAL_STATUS_CHANGED extends \Uncanny_Automator\Recipe\App_Trigger {

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
	 * Status meta key.
	 *
	 * @var string
	 */
	private $status_meta_key = 'ASANA_APPROVAL_STATUS';

	/**
	 * Setup the trigger
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->workspace_meta_key = $this->helpers->get_const( 'ACTION_WORKSPACE_META_KEY' );
		$this->project_meta_key   = $this->helpers->get_const( 'ACTION_PROJECT_META_KEY' );

		$this->set_integration( 'ASANA' );
		$this->set_trigger_code( 'APPROVAL_STATUS_CHANGED' );
		$this->set_trigger_meta( 'APPROVAL_STATUS_CHANGED_META' );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: Task name, %2$s: Status
				esc_attr_x( '{{An approval task:%1$s}} is set to {{a status:%2$s}}', 'Asana', 'uncanny-automator' ),
				$this->get_trigger_meta(),
				$this->status_meta_key . ':' . $this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( '{{An approval task}} is set to {{a status}}', 'Asana', 'uncanny-automator' ) );
		$this->add_action( 'automator_asana_task_status_changed' );
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
				esc_html_x( 'Approval status changed', 'Asana', 'uncanny-automator' )
			),
			$this->helpers->get_webhook_task_option_config(
				$this->get_trigger_meta(),
				$this->project_meta_key
			),
			array(
				'input_type'      => 'select',
				'option_code'     => $this->status_meta_key,
				'label'           => esc_html_x( 'Status', 'Asana', 'uncanny-automator' ),
				'required'        => true,
				'options'         => $this->get_status_options(),
				'options_show_id' => false,
				'relevant_tokens' => array(),
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
			Asana_Tokens::get_trigger_extended_task_token_definitions(),
			Asana_Tokens::get_trigger_task_assignee_token_definitions()
		);

		// Define approval status specific tokens
		$approval_tokens = array(
			array(
				'tokenId'   => 'ASANA_APPROVAL_STATUS',
				'tokenName' => esc_html_x( 'Approval status', 'Asana', 'uncanny-automator' ),
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

		return array_merge( $common_tokens, $approval_tokens, $tokens );
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

		// Get the selected task from the trigger
		$selected_task = $trigger['meta'][ $this->get_trigger_meta() ];
		$task_id       = $event_data['task_id'] ?? '';

		if ( empty( $task_id ) ) {
			return false;
		}

		// Check if specific task is selected and matches
		if ( ! empty( $selected_task ) && '-1' !== $selected_task && $selected_task !== $task_id ) {
			return false;
		}

		// Get the selected status from the trigger
		$selected_status = $trigger['meta'][ $this->status_meta_key ] ?? '';

		// If "Any status" is selected, allow all status changes
		if ( '-1' === $selected_status ) {
			return true;
		}

		// Since Asana doesn't send the actual status values in the webhook,
		// we need to fetch the current task to get the approval status.
		$task_data = $this->get_task_details( $task_id );
		$status    = $task_data['approval_status'] ?? '';

		return $status === $selected_status;
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
		$task_id    = $event_data['task_id'] ?? '';
		$task       = $this->get_task_details( $task_id );

		// Get the user who changed the approval status.
		$changed_by_user = $this->helpers->get_workspace_user_option( $event_data['workspace_id'], $event_data['user_id'] );

		// Use helper for common tokens
		$tokens = array_merge(
			Asana_Tokens::hydrate_workspace_tokens( $event_data ),
			Asana_Tokens::hydrate_project_tokens( $event_data, $project_id ),
			Asana_Tokens::hydrate_extended_task_tokens( $event_data, $task ),
			Asana_Tokens::hydrate_task_assignee_tokens( $task )
		);

		// Add approval status specific tokens
		$tokens['ASANA_APPROVAL_STATUS']  = $task['approval_status'] ?? '';
		$tokens['ASANA_CHANGED_BY_ID']    = $changed_by_user['value'] ?? '';
		$tokens['ASANA_CHANGED_BY_NAME']  = $changed_by_user['text'] ?? '';
		$tokens['ASANA_CHANGED_BY_EMAIL'] = $changed_by_user['email'] ?? '';
		$tokens['ASANA_CHANGED_AT']       = $task['modified_at'] ?? '';

		return $tokens;
	}

	/**
	 * Get status options.
	 *
	 * @return array
	 */
	private function get_status_options() {
		return array(
			array(
				'text'  => esc_html_x( 'Any status', 'Asana', 'uncanny-automator' ),
				'value' => '-1',
			),
			array(
				'text'  => esc_html_x( 'Pending', 'Asana', 'uncanny-automator' ),
				'value' => 'pending',
			),
			array(
				'text'  => esc_html_x( 'Approved', 'Asana', 'uncanny-automator' ),
				'value' => 'approved',
			),
			array(
				'text'  => esc_html_x( 'Changes requested', 'Asana', 'uncanny-automator' ),
				'value' => 'changes_requested',
			),
			array(
				'text'  => esc_html_x( 'Rejected', 'Asana', 'uncanny-automator' ),
				'value' => 'rejected',
			),
		);
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
