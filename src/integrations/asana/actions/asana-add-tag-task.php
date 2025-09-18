<?php

namespace Uncanny_Automator\Integrations\Asana;

use Exception;

/**
 * Action: Add {{a tag}} to {{a task}} in {{a specific project}}
 *
 * @package Uncanny_Automator\Integrations\Asana
 *
 * @property Asana_App_Helpers $helpers
 * @property Asana_Api_Caller $api
 */
class ASANA_ADD_TAG_TASK extends \Uncanny_Automator\Recipe\App_Action {

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
	 * Task meta key.
	 *
	 * @var string
	 */
	private $task_meta_key;

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->workspace_meta_key = $this->helpers->get_const( 'ACTION_WORKSPACE_META_KEY' );
		$this->project_meta_key   = $this->helpers->get_const( 'ACTION_PROJECT_META_KEY' );
		$this->task_meta_key      = $this->helpers->get_const( 'ACTION_TASK_META_KEY' );

		$this->set_integration( 'ASANA' );
		$this->set_action_code( 'ASANA_ADD_TAG_TASK_CODE' );
		$this->set_action_meta( 'ASANA_ADD_TAG_TASK_META' );
		$this->set_requires_user( false );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/asana-integration/' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the tag name, %2$s is the task name, %3$s is the project name
				esc_attr_x( 'Add {{a tag:%1$s}} to {{a task:%2$s}} in {{a specific project:%3$s}}', 'Asana', 'uncanny-automator' ),
				$this->get_action_meta(),
				$this->task_meta_key . ':' . $this->get_action_meta(),
				$this->project_meta_key . ':' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Add {{a tag}} to {{a task}} in {{a specific project}}', 'Asana', 'uncanny-automator' ) );

		$this->set_action_tokens(
			Asana_Tokens::get_tag_operation_tokens(),
			$this->get_action_code()
		);
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_workspace_option_config( $this->workspace_meta_key ),
			$this->helpers->get_project_option_config( $this->project_meta_key ),
			$this->helpers->get_task_option_config( $this->task_meta_key ),
			$this->helpers->get_tag_option_config( $this->action_meta ),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		// Validate the required fields.
		$workspace_id = $this->helpers->get_workspace_from_parsed( $parsed, $this->workspace_meta_key );
		$project_id   = $this->helpers->get_project_from_parsed( $parsed, $this->project_meta_key );
		$task_id      = $this->helpers->get_task_from_parsed( $parsed, $this->task_meta_key );
		$tag_id       = $this->helpers->get_tag_from_parsed( $parsed, $this->action_meta );
		$tag_name     = $parsed[ $this->action_meta . '_readable' ];
		$is_custom    = $this->helpers->is_token_custom_value_text( $tag_name );

		// Handle custom value tags - check if tag already exists first.
		if ( $is_custom ) {
			$existing_tag = $this->get_existing_custom_tag( $tag_id, $workspace_id );
			if ( $existing_tag ) {
				// Tag already exists, use its ID and name instead of creating new one.
				$tag_id    = $existing_tag['value'];
				$tag_name  = $existing_tag['text'];
				$is_custom = false;
			}
		}

		$body = array(
			'action'       => 'add_tag_to_task',
			'workspace_id' => $workspace_id,
			'project_id'   => $project_id,
			'task_id'      => $task_id,
			'tag_id'       => $tag_id,
		);

		$response = $this->api->api_request( $body, $action_data );

		if ( 200 !== $response['statusCode'] ) {
			// Check for API error format first (ActionException format)
			$error = $response['error']['description'] ?? '';
			if ( ! empty( $error ) ) {
				throw new Exception( esc_html( $error ) );
			}
			throw new Exception( esc_html_x( 'Failed to add tag to task.', 'Asana', 'uncanny-automator' ) );
		}

		// Handle custom value tags that weren't found in existing options.
		if ( $is_custom ) {
			$tag      = $this->get_custom_value_tag_option( $tag_id, $workspace_id );
			$tag_name = $tag['text'];
			$tag_id   = $tag['value'];
		}

		// Hydrate tokens.
		$this->hydrate_tokens(
			array(
				'TAG_ID'         => $tag_id,
				'TAG_NAME'       => $tag_name,
				'TASK_ID'        => $task_id,
				'TASK_NAME'      => $parsed[ $this->task_meta_key . '_readable' ],
				'PROJECT_ID'     => $project_id,
				'PROJECT_NAME'   => $parsed[ $this->project_meta_key . '_readable' ],
				'WORKSPACE_ID'   => $workspace_id,
				'WORKSPACE_NAME' => $parsed[ $this->workspace_meta_key . '_readable' ],
			)
		);

		return true;
	}

	/**
	 * Check if a custom tag already exists in the workspace.
	 *
	 * @param string $tag_id
	 * @param string $workspace_id
	 *
	 * @return array|false Returns tag data if found, false otherwise
	 */
	private function get_existing_custom_tag( $tag_id, $workspace_id ) {
		// Check if custom value tag is a valid GID first.
		if ( $this->helpers->is_valid_gid( $tag_id ) ) {
			return $this->get_tag_from_options( 'value', $tag_id, $workspace_id );
		}

		// Check if tag exists by name (case-insensitive).
		return $this->get_tag_from_options( 'text', $tag_id, $workspace_id );
	}

	/**
	 * Get the custom value tag option.
	 *
	 * @param string $tag_id
	 * @param string $workspace_id
	 *
	 * @return array
	 */
	private function get_custom_value_tag_option( $tag_id, $workspace_id ) {

		// Check if custom value tag is a valid GID.
		if ( $this->helpers->is_valid_gid( $tag_id ) ) {
			$option = $this->get_tag_from_options( 'value', $tag_id, $workspace_id );
			if ( ! $option ) {
				$option = array(
					'value' => $tag_id,
					'text'  => '-',
				);
			}
			return $option;
		}

		// Else a tag was created with a custom value.
		$option = $this->get_tag_from_options( 'text', $tag_id, $workspace_id );
		if ( ! $option ) {
			$option = array(
				'value' => '-',
				'text'  => $tag_id,
			);
		}

		return $option;
	}

	/**
	 * Get the tag from the options.
	 *
	 * @param string $key
	 * @param string $value
	 * @param string $workspace_id
	 * @param bool $refresh
	 *
	 * @return array|false
	 */
	private function get_tag_from_options( $key, $value, $workspace_id, $refresh = false ) {
		$tags  = $this->api->get_workspace_tags( $workspace_id, $refresh );
		$value = strtolower( trim( $value ) );
		foreach ( $tags as $tag ) {
			if ( strtolower( trim( $tag[ $key ] ) ) === $value ) {
				return $tag;
			}
		}

		// Try once with refresh.
		if ( ! $refresh ) {
			return $this->get_tag_from_options( $key, $value, $workspace_id, true );
		}

		return false;
	}
}
