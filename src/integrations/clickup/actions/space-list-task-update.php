<?php

namespace Uncanny_Automator\Integrations\ClickUp;

use Exception;

/**
 * Action: Update a task.
 *
 * @package Uncanny_Automator\Integrations\ClickUp
 *
 * @property ClickUp_App_Helpers $helpers
 * @property ClickUp_Api_Caller $api
 */
class Space_List_Task_Update extends \Uncanny_Automator\Recipe\App_Action {

	use ClickUp_Hierarchy_Options;

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'CLICKUP' );
		$this->set_action_code( 'CLICKUP_SPACE_LIST_TASK_TAG_UPDATE' );
		$this->set_action_meta( 'CLICKUP_SPACE_LIST_TASK_TAG_UPDATE_META' );
		$this->set_requires_user( false );
		$this->set_is_pro( false );
		$this->set_wpautop( false );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/clickup/' ) );
		$this->set_readable_sentence( esc_attr_x( 'Update {{a task}}', 'ClickUp', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: Task name
				esc_attr_x( 'Update {{a task:%1$s}}', 'ClickUp', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->get_team_option_config(),
			$this->get_space_option_config(),
			$this->get_folder_option_config(),
			$this->helpers->get_list_option_config(),
			$this->helpers->get_task_option_config( $this->get_action_meta() ),
			$this->get_multi_assignee_config( 'ASSIGNEES_ADD', esc_attr_x( 'Add assignees', 'ClickUp', 'uncanny-automator' ) ),
			$this->get_multi_assignee_config( 'ASSIGNEES_REMOVE', esc_attr_x( 'Remove assignees', 'ClickUp', 'uncanny-automator' ) ),
			$this->helpers->get_status_option_config(),
			$this->helpers->get_priority_option_config( true ),
			// Repeater fields for conditional updates.
			$this->get_repeater_field( 'NAME', esc_attr_x( 'Name', 'ClickUp', 'uncanny-automator' ), 'text' ),
			$this->get_repeater_field( 'DESCRIPTION', esc_attr_x( 'Description', 'ClickUp', 'uncanny-automator' ), 'textarea' ),
			$this->get_repeater_field( 'START_DATE', esc_attr_x( 'Start date', 'ClickUp', 'uncanny-automator' ), 'date' ),
			$this->get_repeater_field( 'START_TIME', esc_attr_x( 'Start time', 'ClickUp', 'uncanny-automator' ), 'time' ),
			$this->get_repeater_field( 'DUE_DATE', esc_attr_x( 'Due date', 'ClickUp', 'uncanny-automator' ), 'date' ),
			$this->get_repeater_field( 'DUE_TIME', esc_attr_x( 'Due date time', 'ClickUp', 'uncanny-automator' ), 'time' ),
		);
	}

	/**
	 * Build a repeater field config with value + update checkbox.
	 *
	 * @param string $key        The field key.
	 * @param string $label      The field label.
	 * @param string $input_type The input type for the value field.
	 *
	 * @return array
	 */
	private function get_repeater_field( $key, $label, $input_type ) {
		$value_label = esc_attr_x( 'Value', 'ClickUp', 'uncanny-automator' );

		return array(
			'option_code'     => $key . '_REPEATER',
			'label'           => $label,
			'hide_actions'    => true,
			'input_type'      => 'repeater',
			'relevant_tokens' => array(),
			'required'        => true,
			'fields'          => array(
				array(
					'option_code'   => $key,
					'label'         => $value_label,
					'input_type'    => $input_type,
					'default_value' => '',
				),
				array(
					'option_code' => $key . '_UPDATE',
					'label'       => esc_attr_x( 'Update?', 'ClickUp', 'uncanny-automator' ),
					'input_type'  => 'checkbox',
					'is_toggle'   => true,
				),
			),
		);
	}

	/**
	 * Extend the base assignee config for multi-select.
	 *
	 * @param string $option_code  The option meta key.
	 * @param string $label        The field label.
	 *
	 * @return array
	 */
	private function get_multi_assignee_config( $option_code, $label ) {
		return array_merge(
			$this->helpers->get_assignee_option_config( $option_code, $this->helpers->get_const( 'META_LIST' ) ),
			array(
				'label'                    => $label,
				'placeholder'              => esc_attr_x( 'Click to choose from list of assignees', 'ClickUp', 'uncanny-automator' ),
				'supports_multiple_values' => true,
				'supports_custom_value'    => false,
			)
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action data.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        Additional arguments.
	 * @param array $parsed      Parsed action data.
	 *
	 * @return bool
	 * @throws Exception If the action fails.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$name        = $this->read_from_repeater( $parsed, 'NAME' );
		$description = $this->read_from_repeater( $parsed, 'DESCRIPTION', 'sanitize_textarea_field' );
		$start_date  = $this->read_from_repeater( $parsed, 'START_DATE' );
		$start_time  = $this->read_from_repeater( $parsed, 'START_TIME' );
		$due_date    = $this->read_from_repeater( $parsed, 'DUE_DATE' );
		$due_time    = $this->read_from_repeater( $parsed, 'DUE_TIME' );

		$body = array(
			'action'               => 'task_update',
			'status'               => sanitize_text_field( $parsed['STATUS'] ?? '' ),
			'priority'             => sanitize_text_field( $parsed['PRIORITY'] ?? '' ),
			'assignees_add'        => sanitize_text_field( $parsed['ASSIGNEES_ADD'] ?? '' ),
			'assignees_remove'     => sanitize_text_field( $parsed['ASSIGNEES_REMOVE'] ?? '' ),
			'task_id'              => sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' ),
			'name'                 => $name,
			'description'          => $description,
			'date_start_timestamp' => $this->to_timestamp( $start_date, $start_time ),
			'date_due_timestamp'   => $this->to_timestamp( $due_date, $due_time ),
		);

		// Enable time flags if values are present.
		if ( ! empty( $start_time ) ) {
			$body['enable_start_time'] = 'yes';
		}

		if ( ! empty( $due_time ) ) {
			$body['enable_due_time'] = 'yes';
		}

		$this->api->api_request( $body, $action_data );

		return true;
	}

	/**
	 * Read a value from a repeater field.
	 *
	 * Only returns the value if the _UPDATE checkbox is enabled.
	 *
	 * @param array    $parsed The parsed action data.
	 * @param string   $key    The field key (uppercase).
	 * @param callable $cb     The sanitization callback.
	 *
	 * @return string|null Null when disabled.
	 */
	private function read_from_repeater( $parsed, $key, $cb = 'sanitize_text_field' ) {
		$repeater_key = $key . '_REPEATER';

		if ( ! isset( $parsed[ $repeater_key ] ) ) {
			return null;
		}

		// Convert br to nl.
		$field_value = str_replace( array( '<br>', '<br/>', '<br />' ), PHP_EOL, $parsed[ $repeater_key ] );

		$restore_slashes = addcslashes( $cb( $field_value ), PHP_EOL );
		$repeater_fields = (array) json_decode( $restore_slashes, true );
		$repeater_fields = end( $repeater_fields );

		if ( isset( $repeater_fields[ $key . '_UPDATE' ] ) && true === $repeater_fields[ $key . '_UPDATE' ] ) {
			return $repeater_fields[ $key ] ?? '';
		}

		return apply_filters( 'automator_clickup_update_action_read_from_repeater', null, $parsed, $key, '', $cb, $this );
	}

	/**
	 * Convert date and time to a 13-digit millisecond timestamp.
	 *
	 * @param string|null $date The date string.
	 * @param string|null $time The time string.
	 *
	 * @return int|null
	 */
	private function to_timestamp( $date, $time ) {
		if ( empty( $date ) ) {
			return null;
		}

		$date_time_string = trim( $date . ' ' . $time );

		$date_time_object = new \DateTime(
			$date_time_string,
			new \DateTimeZone( Automator()->get_timezone_string() )
		);

		return (int) $date_time_object->format( 'U' ) * 1000;
	}
}
