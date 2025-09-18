<?php

namespace Uncanny_Automator\Integrations\Asana;

/**
 * Asana Tokens Class
 *
 * Provides common token definitions for Asana actions.
 *
 * @package Uncanny_Automator\Integrations\Asana\Tokens
 */
class Asana_Tokens {

	/**
	 * Get workspace token definitions.
	 *
	 * @return array
	 */
	public static function get_workspace_token_definitions() {
		return array(
			'WORKSPACE_NAME' => array(
				'name' => esc_html_x( 'Workspace name', 'Asana', 'uncanny-automator' ),
				'type' => 'text',
			),
			'WORKSPACE_ID'   => array(
				'name' => esc_html_x( 'Workspace ID', 'Asana', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Get project token definitions.
	 *
	 * @return array
	 */
	public static function get_project_token_definitions() {
		return array(
			'PROJECT_NAME' => array(
				'name' => esc_html_x( 'Project name', 'Asana', 'uncanny-automator' ),
				'type' => 'text',
			),
			'PROJECT_ID'   => array(
				'name' => esc_html_x( 'Project ID', 'Asana', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Get basic task token definitions (ID and name only).
	 *
	 * @return array
	 */
	public static function get_basic_task_token_definitions() {
		return array(
			'TASK_ID'   => array(
				'name' => esc_html_x( 'Task ID', 'Asana', 'uncanny-automator' ),
				'type' => 'text',
			),
			'TASK_NAME' => array(
				'name' => esc_html_x( 'Task name', 'Asana', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Get extended task token definitions (basic + additional fields).
	 *
	 * @return array
	 */
	public static function get_extended_task_token_definitions() {
		return array_merge(
			self::get_basic_task_token_definitions(),
			array(
				'TASK_URL'        => array(
					'name' => esc_html_x( 'Task URL', 'Asana', 'uncanny-automator' ),
					'type' => 'text',
				),
				'TASK_START_ON'   => array(
					'name' => esc_html_x( 'Task start date', 'Asana', 'uncanny-automator' ),
					'type' => 'text',
				),
				'TASK_DUE_ON'     => array(
					'name' => esc_html_x( 'Task due date', 'Asana', 'uncanny-automator' ),
					'type' => 'text',
				),
				'TASK_NOTES'      => array(
					'name' => esc_html_x( 'Task description', 'Asana', 'uncanny-automator' ),
					'type' => 'text',
				),
				'TASK_TYPE'       => array(
					'name' => esc_html_x( 'Task type', 'Asana', 'uncanny-automator' ),
					'type' => 'text',
				),
				'APPROVAL_STATUS' => array(
					'name' => esc_html_x( 'Approval status', 'Asana', 'uncanny-automator' ),
					'type' => 'text',
				),
			)
		);
	}

	/**
	 * Get tag token definitions.
	 *
	 * @return array
	 */
	public static function get_tag_token_definitions() {
		return array(
			'TAG_ID'   => array(
				'name' => esc_html_x( 'Tag ID', 'Asana', 'uncanny-automator' ),
				'type' => 'text',
			),
			'TAG_NAME' => array(
				'name' => esc_html_x( 'Tag name', 'Asana', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Get comment token definitions.
	 *
	 * @return array
	 */
	public static function get_comment_token_definitions() {
		return array(
			'COMMENT_ID'   => array(
				'name' => esc_html_x( 'Comment ID', 'Asana', 'uncanny-automator' ),
				'type' => 'text',
			),
			'COMMENT_TEXT' => array(
				'name' => esc_html_x( 'Comment text', 'Asana', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Get tokens for tag operations (workspace + project + basic task + tag).
	 *
	 * @return array
	 */
	public static function get_tag_operation_tokens() {
		return array_merge(
			self::get_workspace_token_definitions(),
			self::get_project_token_definitions(),
			self::get_basic_task_token_definitions(),
			self::get_tag_token_definitions()
		);
	}

	/**
	 * Get tokens for comment operations (workspace + project + basic task + comment).
	 *
	 * @return array
	 */
	public static function get_comment_operation_tokens() {
		return array_merge(
			self::get_workspace_token_definitions(),
			self::get_project_token_definitions(),
			self::get_basic_task_token_definitions(),
			self::get_comment_token_definitions()
		);
	}

	/**
	 * Get tokens for full task operations (workspace + project + extended task).
	 *
	 * @return array
	 */
	public static function get_full_task_operation_tokens() {
		return array_merge(
			self::get_workspace_token_definitions(),
			self::get_project_token_definitions(),
			self::get_extended_task_token_definitions()
		);
	}

	/**
	 * Get tokens for basic task details (workspace + project + basic task).
	 *
	 * @return array
	 */
	public static function get_basic_task_details_tokens() {
		return array_merge(
			self::get_workspace_token_definitions(),
			self::get_project_token_definitions(),
			self::get_basic_task_token_definitions()
		);
	}
}
