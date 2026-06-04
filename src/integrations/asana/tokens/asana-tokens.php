<?php

namespace Uncanny_Automator\Integrations\Asana;

/**
 * Asana Tokens
 *
 * Single source of truth for every Asana token label used by both action
 * recipes and trigger hydration. Tokens are returned in the trigger-format
 * shape (`tokenId` / `tokenName` / `tokenType`) — `set_action_tokens()`
 * auto-converts that shape on the action side via Action_Tokens trait.
 *
 * Action-side IDs stay unprefixed (`WORKSPACE_NAME`) and trigger-side IDs
 * stay `ASANA_` prefixed (`ASANA_WORKSPACE_NAME`) to keep saved recipes
 * working — only the label/type definitions are deduplicated.
 *
 * @package Uncanny_Automator\Integrations\Asana
 */
class Asana_Tokens {

	////////////////////////////////////////////////////////////
	// Internal label pool
	////////////////////////////////////////////////////////////

	/**
	 * Label + type definitions keyed by short token ID (no prefix).
	 *
	 * Trigger callers prepend `ASANA_` to the short ID at build time;
	 * action callers use the short ID as-is.
	 *
	 * @return array<string, array{name: string, type: string}>
	 */
	private static function token_labels() {
		return array(
			// Workspace
			'WORKSPACE_ID'          => array(
				'name' => esc_html_x( 'Workspace ID', 'Asana', 'uncanny-automator' ),
				'type' => 'text',
			),
			'WORKSPACE_NAME'        => array(
				'name' => esc_html_x( 'Workspace name', 'Asana', 'uncanny-automator' ),
				'type' => 'text',
			),
			// Project
			'PROJECT_ID'            => array(
				'name' => esc_html_x( 'Project ID', 'Asana', 'uncanny-automator' ),
				'type' => 'text',
			),
			'PROJECT_NAME'          => array(
				'name' => esc_html_x( 'Project name', 'Asana', 'uncanny-automator' ),
				'type' => 'text',
			),
			// Task — basic
			'TASK_ID'               => array(
				'name' => esc_html_x( 'Task ID', 'Asana', 'uncanny-automator' ),
				'type' => 'text',
			),
			'TASK_NAME'             => array(
				'name' => esc_html_x( 'Task name', 'Asana', 'uncanny-automator' ),
				'type' => 'text',
			),
			'TASK_URL'              => array(
				'name' => esc_html_x( 'Task URL', 'Asana', 'uncanny-automator' ),
				'type' => 'text',
			),
			'TASK_TYPE'             => array(
				'name' => esc_html_x( 'Task type', 'Asana', 'uncanny-automator' ),
				'type' => 'text',
			),
			// Task — action-side extras
			'TASK_START_ON'         => array(
				'name' => esc_html_x( 'Task start date', 'Asana', 'uncanny-automator' ),
				'type' => 'text',
			),
			'TASK_DUE_ON'           => array(
				'name' => esc_html_x( 'Task due date', 'Asana', 'uncanny-automator' ),
				'type' => 'text',
			),
			'TASK_NOTES'            => array(
				'name' => esc_html_x( 'Task description', 'Asana', 'uncanny-automator' ),
				'type' => 'text',
			),
			'APPROVAL_STATUS'       => array(
				'name' => esc_html_x( 'Approval status', 'Asana', 'uncanny-automator' ),
				'type' => 'text',
			),
			// Task — trigger-side extras
			'TASK_STATUS'           => array(
				'name' => esc_html_x( 'Task status', 'Asana', 'uncanny-automator' ),
				'type' => 'text',
			),
			'TASK_DESCRIPTION'      => array(
				'name' => esc_html_x( 'Task description', 'Asana', 'uncanny-automator' ),
				'type' => 'text',
			),
			'TASK_DESCRIPTION_HTML' => array(
				'name' => esc_html_x( 'Task description (HTML)', 'Asana', 'uncanny-automator' ),
				'type' => 'text',
			),
			// Task assignee (trigger-only)
			'TASK_ASSIGNEE_ID'      => array(
				'name' => esc_html_x( 'Task assignee member ID', 'Asana', 'uncanny-automator' ),
				'type' => 'text',
			),
			'TASK_ASSIGNEE_NAME'    => array(
				'name' => esc_html_x( 'Task assignee member name', 'Asana', 'uncanny-automator' ),
				'type' => 'text',
			),
			'TASK_ASSIGNEE_EMAIL'   => array(
				'name' => esc_html_x( 'Task assignee member email', 'Asana', 'uncanny-automator' ),
				'type' => 'email',
			),
			// Tag (action-only)
			'TAG_ID'                => array(
				'name' => esc_html_x( 'Tag ID', 'Asana', 'uncanny-automator' ),
				'type' => 'text',
			),
			'TAG_NAME'              => array(
				'name' => esc_html_x( 'Tag name', 'Asana', 'uncanny-automator' ),
				'type' => 'text',
			),
			// Comment (action-only)
			'COMMENT_ID'            => array(
				'name' => esc_html_x( 'Comment ID', 'Asana', 'uncanny-automator' ),
				'type' => 'text',
			),
			'COMMENT_TEXT'          => array(
				'name' => esc_html_x( 'Comment text', 'Asana', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Map a list of short IDs to trigger-format token entries.
	 *
	 * @param string[] $ids    Short IDs that exist in {@see self::token_labels()}.
	 * @param string   $prefix Optional prefix prepended to each `tokenId` (e.g. `ASANA_` for triggers).
	 *
	 * @return array
	 */
	private static function build_tokens( array $ids, $prefix = '' ) {
		$labels = self::token_labels();
		$out    = array();
		foreach ( $ids as $id ) {
			if ( ! isset( $labels[ $id ] ) ) {
				continue;
			}
			$out[] = array(
				'tokenId'   => $prefix . $id,
				'tokenName' => $labels[ $id ]['name'],
				'tokenType' => $labels[ $id ]['type'],
			);
		}
		return $out;
	}

	////////////////////////////////////////////////////////////
	// Action-side definitions (unprefixed IDs)
	////////////////////////////////////////////////////////////

	/**
	 * Workspace tokens for actions.
	 *
	 * @return array
	 */
	public static function get_workspace_token_definitions() {
		return self::build_tokens( array( 'WORKSPACE_NAME', 'WORKSPACE_ID' ) );
	}

	/**
	 * Project tokens for actions.
	 *
	 * @return array
	 */
	public static function get_project_token_definitions() {
		return self::build_tokens( array( 'PROJECT_NAME', 'PROJECT_ID' ) );
	}

	/**
	 * Basic task tokens for actions (ID + name only).
	 *
	 * @return array
	 */
	public static function get_basic_task_token_definitions() {
		return self::build_tokens( array( 'TASK_ID', 'TASK_NAME' ) );
	}

	/**
	 * Extended task tokens for actions (basic + scheduling/notes/approval).
	 *
	 * @return array
	 */
	public static function get_extended_task_token_definitions() {
		return self::build_tokens(
			array(
				'TASK_ID',
				'TASK_NAME',
				'TASK_URL',
				'TASK_START_ON',
				'TASK_DUE_ON',
				'TASK_NOTES',
				'TASK_TYPE',
				'APPROVAL_STATUS',
			)
		);
	}

	/**
	 * Tag tokens for actions.
	 *
	 * @return array
	 */
	public static function get_tag_token_definitions() {
		return self::build_tokens( array( 'TAG_ID', 'TAG_NAME' ) );
	}

	/**
	 * Comment tokens for actions.
	 *
	 * @return array
	 */
	public static function get_comment_token_definitions() {
		return self::build_tokens( array( 'COMMENT_ID', 'COMMENT_TEXT' ) );
	}

	/**
	 * Composite: workspace + project + basic task + tag (for tag operations).
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
	 * Composite: workspace + project + basic task + comment (for comment operations).
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
	 * Composite: workspace + project + extended task (for full task operations).
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
	 * Composite: workspace + project + basic task (for basic task details).
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

	////////////////////////////////////////////////////////////
	// Trigger-side definitions (ASANA_ prefixed IDs)
	////////////////////////////////////////////////////////////

	/**
	 * Workspace tokens for triggers.
	 *
	 * @return array
	 */
	public static function get_trigger_workspace_token_definitions() {
		return self::build_tokens( array( 'WORKSPACE_ID', 'WORKSPACE_NAME' ), 'ASANA_' );
	}

	/**
	 * Project tokens for triggers.
	 *
	 * @return array
	 */
	public static function get_trigger_project_token_definitions() {
		return self::build_tokens( array( 'PROJECT_ID', 'PROJECT_NAME' ), 'ASANA_' );
	}

	/**
	 * Basic task tokens for triggers (id, name, type, status, url).
	 *
	 * @return array
	 */
	public static function get_trigger_basic_task_token_definitions() {
		return self::build_tokens(
			array( 'TASK_ID', 'TASK_NAME', 'TASK_TYPE', 'TASK_STATUS', 'TASK_URL' ),
			'ASANA_'
		);
	}

	/**
	 * Extended task tokens for triggers (basic + descriptions).
	 *
	 * @return array
	 */
	public static function get_trigger_extended_task_token_definitions() {
		return array_merge(
			self::get_trigger_basic_task_token_definitions(),
			self::build_tokens( array( 'TASK_DESCRIPTION', 'TASK_DESCRIPTION_HTML' ), 'ASANA_' )
		);
	}

	/**
	 * Task assignee tokens for triggers.
	 *
	 * @return array
	 */
	public static function get_trigger_task_assignee_token_definitions() {
		return self::build_tokens(
			array( 'TASK_ASSIGNEE_ID', 'TASK_ASSIGNEE_NAME', 'TASK_ASSIGNEE_EMAIL' ),
			'ASANA_'
		);
	}

	////////////////////////////////////////////////////////////
	// Trigger-side hydration
	////////////////////////////////////////////////////////////

	/**
	 * Hydrate workspace token values for a trigger event.
	 *
	 * @param array $event_data Webhook event data.
	 *
	 * @return array
	 */
	public static function hydrate_workspace_tokens( $event_data ) {
		return array(
			'ASANA_WORKSPACE_ID'   => $event_data['workspace_id'] ?? '',
			'ASANA_WORKSPACE_NAME' => $event_data['workspace_name'] ?? '',
		);
	}

	/**
	 * Hydrate project token values for a trigger event.
	 *
	 * @param array  $event_data Webhook event data.
	 * @param string $project_id Project ID.
	 *
	 * @return array
	 */
	public static function hydrate_project_tokens( $event_data, $project_id ) {
		return array(
			'ASANA_PROJECT_ID'   => $project_id,
			'ASANA_PROJECT_NAME' => $event_data['project_name'] ?? '',
		);
	}

	/**
	 * Hydrate basic task token values for a trigger event.
	 *
	 * @param array      $event_data   Webhook event data.
	 * @param array|null $task_details Optional task details from API call.
	 *
	 * @return array
	 */
	public static function hydrate_basic_task_tokens( $event_data, $task_details = null ) {
		$task_details = is_array( $task_details ) ? $task_details : array();

		return array(
			'ASANA_TASK_ID'     => $event_data['task_id'] ?? '',
			'ASANA_TASK_NAME'   => $task_details['name'] ?? '',
			'ASANA_TASK_TYPE'   => $task_details['resource_subtype'] ?? '',
			'ASANA_TASK_STATUS' => ! empty( $task_details['completed'] ) ? 'completed' : 'incomplete',
			'ASANA_TASK_URL'    => $task_details['permalink_url'] ?? '',
		);
	}

	/**
	 * Hydrate extended task token values for a trigger event.
	 *
	 * @param array      $event_data   Webhook event data.
	 * @param array|null $task_details Optional task details from API call.
	 *
	 * @return array
	 */
	public static function hydrate_extended_task_tokens( $event_data, $task_details = null ) {
		$task_details = is_array( $task_details ) ? $task_details : array();

		$tokens = self::hydrate_basic_task_tokens( $event_data, $task_details );

		$tokens['ASANA_TASK_DESCRIPTION']      = $task_details['notes'] ?? '';
		$tokens['ASANA_TASK_DESCRIPTION_HTML'] = $task_details['html_notes'] ?? '';

		return $tokens;
	}

	/**
	 * Hydrate task assignee token values for a trigger event.
	 *
	 * @param array $task_details Task details from API call.
	 *
	 * @return array
	 */
	public static function hydrate_task_assignee_tokens( $task_details ) {
		$task_details = is_array( $task_details ) ? $task_details : array();
		$assignee     = $task_details['assignee'] ?? array();
		return array(
			'ASANA_TASK_ASSIGNEE_ID'    => $assignee['gid'] ?? '',
			'ASANA_TASK_ASSIGNEE_NAME'  => $assignee['name'] ?? '',
			'ASANA_TASK_ASSIGNEE_EMAIL' => $assignee['email'] ?? '',
		);
	}
}
