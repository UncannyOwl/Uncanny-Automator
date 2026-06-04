<?php

namespace Uncanny_Automator\Integrations\Asana;

/**
 * Trigger: A task is created in {{a specific project}}
 *
 * @package Uncanny_Automator\Integrations\Asana
 *
 * @property Asana_App_Helpers $helpers
 * @property Asana_Api_Caller $api
 * @property Asana_Webhooks $webhooks
 */
class TASK_CREATED_IN_PROJECT extends \Uncanny_Automator\Recipe\App_Trigger {

	/**
	 * Workspace meta key.
	 *
	 * @var string
	 */
	private $workspace_meta_key;

	/**
	 * Setup the trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->workspace_meta_key = $this->helpers->get_const( 'ACTION_WORKSPACE_META_KEY' );

		$this->set_integration( 'ASANA' );
		$this->set_trigger_code( 'TASK_CREATED_IN_PROJECT' );
		$this->set_trigger_meta( 'TASK_CREATED_IN_PROJECT_META' );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: Project name
				esc_attr_x( 'A task is created in {{a specific project:%1$s}}', 'Asana', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'A task is created in {{a specific project}}', 'Asana', 'uncanny-automator' ) );
		$this->add_action( 'automator_asana_task_added' );
		$this->set_action_args_count( 3 );
		$this->set_trigger_type( 'anonymous' );
		$this->set_is_login_required( false );
		$this->set_uses_api( true );
	}

	/**
	 * Define trigger options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_webhook_workspace_option_config( $this->workspace_meta_key ),
			$this->helpers->get_webhook_project_option_config(
				$this->get_trigger_meta(),
				$this->workspace_meta_key,
				esc_html_x( 'Task created', 'Asana', 'uncanny-automator' )
			),
		);
	}

	/**
	 * Define trigger tokens.
	 *
	 * @param $trigger
	 * @param $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		// Get common tokens from helper.
		$common_tokens = array_merge(
			Asana_Tokens::get_trigger_workspace_token_definitions(),
			Asana_Tokens::get_trigger_project_token_definitions(),
			Asana_Tokens::get_trigger_basic_task_token_definitions()
		);

		// Define task created specific tokens
		$task_created_tokens = array(
			array(
				'tokenId'   => 'ASANA_CREATED_BY_ID',
				'tokenName' => esc_html_x( 'Created by member ID', 'Asana', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ASANA_CREATED_BY_NAME',
				'tokenName' => esc_html_x( 'Created by member name', 'Asana', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ASANA_CREATED_BY_EMAIL',
				'tokenName' => esc_html_x( 'Created by member email', 'Asana', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'ASANA_CREATED_AT',
				'tokenName' => esc_html_x( 'Created at', 'Asana', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);

		return array_merge( $common_tokens, $task_created_tokens, $tokens );
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

		// Get the selected project from the trigger.
		$selected_project_id = $trigger['meta'][ $this->get_trigger_meta() ];

		// Check if the task was created in the selected project.
		if ( (string) $project_id !== (string) $selected_project_id ) {
			return false;
		}

		return true;
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
		$project_id   = $hook_args[0];
		$event_data   = $hook_args[1];
		$task_details = null;
		$task_id      = $event_data['task_id'] ?? null;
		if ( ! empty( $task_id ) ) {
			// Add small delay to allow Asana to save task title.
			sleep( 5 );
			$task_details = $this->api->get_task( $task_id );
		}

		// Use helper for common tokens.
		$tokens = array_merge(
			Asana_Tokens::hydrate_workspace_tokens( $event_data ),
			Asana_Tokens::hydrate_project_tokens( $event_data, $project_id ),
			Asana_Tokens::hydrate_basic_task_tokens( $event_data, $task_details )
		);

		// Add task created specific tokens
		if ( $task_details ) {
			// Get our user info from options.
			$user_info                        = $this->webhooks->get_user_token_data( $event_data );
			$tokens['ASANA_CREATED_BY_ID']    = $user_info['value'];
			$tokens['ASANA_CREATED_BY_NAME']  = $user_info['text'];
			$tokens['ASANA_CREATED_BY_EMAIL'] = $user_info['email'];
			$tokens['ASANA_CREATED_AT']       = $task_details['created_at'] ?? '';
		}

		return $tokens;
	}
}
