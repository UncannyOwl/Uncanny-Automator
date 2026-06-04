<?php

namespace Uncanny_Automator\Integrations\Asana;

use Exception;

/**
 * Trigger: A comment is added to {{a task}} in {{a specific project}}
 *
 * @package Uncanny_Automator\Integrations\Asana
 *
 * @property Asana_App_Helpers $helpers
 * @property Asana_Api_Caller $api
 * @property Asana_Webhooks $webhooks
 */
class COMMENT_ADDED_TO_TASK extends \Uncanny_Automator\Recipe\App_Trigger {

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
		$this->set_trigger_code( 'COMMENT_ADDED_TO_TASK' );
		$this->set_trigger_meta( 'COMMENT_ADDED_TO_TASK_META' );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: Task name, %2$s: Project name
				esc_attr_x( 'A comment is added to {{a task:%1$s}} in {{a specific project:%2$s}}', 'Asana', 'uncanny-automator' ),
				$this->get_trigger_meta(),
				$this->project_meta_key . ':' . $this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'A comment is added to {{a task}} in {{a specific project}}', 'Asana', 'uncanny-automator' ) );
		$this->add_action( 'automator_asana_story_added' ); // Asana references comments as stories.
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
				esc_html_x( 'Comment added', 'Asana', 'uncanny-automator' )
			),
			$this->helpers->get_webhook_task_option_config(
				$this->get_trigger_meta(),
				$this->project_meta_key
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
		// Get common tokens from helper.
		$common_tokens = array_merge(
			Asana_Tokens::get_trigger_workspace_token_definitions(),
			Asana_Tokens::get_trigger_project_token_definitions(),
			Asana_Tokens::get_trigger_extended_task_token_definitions(),
			Asana_Tokens::get_trigger_task_assignee_token_definitions()
		);

		// Define comment-specific tokens.
		$comment_tokens = array(
			array(
				'tokenId'   => 'ASANA_COMMENT_ID',
				'tokenName' => esc_html_x( 'Comment ID', 'Asana', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ASANA_COMMENT_TEXT',
				'tokenName' => esc_html_x( 'Comment text', 'Asana', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ASANA_COMMENT_TEXT_HTML',
				'tokenName' => esc_html_x( 'Comment text (HTML)', 'Asana', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ASANA_COMMENTED_BY_ID',
				'tokenName' => esc_html_x( 'Commented by member ID', 'Asana', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ASANA_COMMENTED_BY_NAME',
				'tokenName' => esc_html_x( 'Commented by member name', 'Asana', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ASANA_COMMENTED_BY_EMAIL',
				'tokenName' => esc_html_x( 'Commented by member email', 'Asana', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'ASANA_COMMENTED_AT',
				'tokenName' => esc_html_x( 'Commented at', 'Asana', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);

		return array_merge( $common_tokens, $comment_tokens, $tokens );
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

		// Get the selected project and task from the trigger.
		$selected_project_id = $trigger['meta'][ $this->project_meta_key ];
		$selected_task_id    = $trigger['meta'][ $this->get_trigger_meta() ];
		$is_any_task         = '-1' === $selected_task_id;

		// Check if the comment was added to a task in the selected project.
		if ( (string) $project_id !== (string) $selected_project_id ) {
			return false;
		}

		// If not any task, check if the comment was added to the selected task.
		if ( ! $is_any_task && (string) $selected_task_id !== (string) $event_data['task_id'] ) {
			return false;
		}

		// Ensure we have the required comment data.
		if ( empty( $event_data['comment_id'] ) || empty( $event_data['task_id'] ) ) {
			return false;
		}

		return true;
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

		// Get task details for additional token data.
		$task_details = null;
		$task_id      = $event_data['task_id'] ?? null;
		if ( ! empty( $task_id ) ) {
			$task_details = $this->api->get_task( $task_id );
		}

		// Get comment details for comment-specific tokens.
		$comment_details = null;
		$comment_id      = $event_data['comment_id'] ?? null;
		if ( ! empty( $comment_id ) ) {
			$comment_details = $this->get_comment_details( $comment_id );
		}

		// Use helper for common tokens.
		$tokens = array_merge(
			Asana_Tokens::hydrate_workspace_tokens( $event_data ),
			Asana_Tokens::hydrate_project_tokens( $event_data, $project_id ),
			Asana_Tokens::hydrate_extended_task_tokens( $event_data, $task_details ),
			Asana_Tokens::hydrate_task_assignee_tokens( $task_details )
		);

		// Add comment-specific tokens.
		if ( $comment_details ) {
			$tokens['ASANA_COMMENT_ID']         = $event_data['comment_id'] ?? '';
			$tokens['ASANA_COMMENT_TEXT']       = $comment_details['text'] ?? '';
			$tokens['ASANA_COMMENT_TEXT_HTML']  = $comment_details['html_text'] ?? '';
			$tokens['ASANA_COMMENTED_BY_ID']    = $comment_details['created_by']['gid'] ?? '';
			$tokens['ASANA_COMMENTED_BY_NAME']  = $comment_details['created_by']['name'] ?? '';
			$tokens['ASANA_COMMENTED_BY_EMAIL'] = $comment_details['created_by']['email'] ?? '';
			$tokens['ASANA_COMMENTED_AT']       = $comment_details['created_at'] ?? $event_data['created_at'] ?? '';
		}

		return $tokens;
	}



	/**
	 * Get comment details.
	 *
	 * @param string $comment_id
	 *
	 * @return array
	 */
	private function get_comment_details( $comment_id ) {
		try {
			$response = $this->api->api_request(
				array(
					'action'   => 'get_story',
					'story_id' => $comment_id,
				)
			);
		} catch ( Exception $e ) {
			return array();
		}

		return $response['data']['data'] ?? array();
	}
}
