<?php

namespace Uncanny_Automator\Integrations\Asana;

use Exception;

/**
 * Action: Get {details} from {a task} in {a specific project}
 *
 * @package Uncanny_Automator\Integrations\Asana
 *
 * @property Asana_App_Helpers $helpers
 * @property Asana_Api_Caller $api
 */
class ASANA_GET_TASK_DETAILS extends \Uncanny_Automator\Recipe\App_Action {

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
	 * Action code.
	 *
	 * @var string
	 */
	const ACTION_CODE = 'ASANA_TASK_DETAILS_CODE';

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
		$this->set_action_code( self::ACTION_CODE );
		$this->set_action_meta( 'ASANA_TASK_DETAILS_META' );
		$this->set_requires_user( false );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/asana-integration/' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s are the details to retrieve, %2$s is the task name, %3$s is the project name
				esc_attr_x( 'Get {{details:%1$s}} from {{a task:%2$s}} in {{a specific project:%3$s}}', 'Asana', 'uncanny-automator' ),
				$this->get_action_meta(),
				$this->task_meta_key . ':' . $this->get_action_meta(),
				$this->project_meta_key . ':' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Get {{details}} from {{a task}} in {{a specific project}}', 'Asana', 'uncanny-automator' ) );

		$this->set_action_tokens(
			Asana_Tokens::get_basic_task_details_tokens(),
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
			array(
				'input_type'               => 'select',
				'option_code'              => $this->get_action_meta(),
				'label'                    => esc_html_x( 'Fields to retrieve', 'Asana', 'uncanny-automator' ),
				'required'                 => true,
				'options'                  => array(),
				'options_show_id'          => false,
				'relevant_tokens'          => array(),
				'supports_multiple_values' => true,
				'ajax'                     => array(
					'endpoint'      => 'automator_asana_get_field_options',
					'event'         => 'parent_fields_change',
					'listen_fields' => array( $this->project_meta_key ),
				),
			),
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
		$fields       = $this->get_parsed_meta_value( $this->get_action_meta() );
		$fields       = is_string( $fields ) ? json_decode( $fields, true ) : $fields;

		// Validate field selection.
		if ( empty( $fields ) || ! is_array( $fields ) ) {
			throw new Exception( esc_html_x( 'Fields to retrieve are required.', 'Asana', 'uncanny-automator' ) );
		}

		// Get task details from API.
		$body = array(
			'action'  => 'get_task_details',
			'task_id' => $task_id,
		);

		$response = $this->api->api_request( $body, $action_data );

		if ( 200 !== $response['statusCode'] ) {
			throw new Exception( esc_html_x( 'Failed to retrieve task details.', 'Asana', 'uncanny-automator' ) );
		}

		$task = $response['data']['data'] ?? array();
		if ( empty( $task ) ) {
			throw new Exception( esc_html_x( 'Failed to retrieve task details.', 'Asana', 'uncanny-automator' ) );
		}

		// Set the default tokens.
		$tokens = array(
			'WORKSPACE_NAME' => $parsed[ $this->workspace_meta_key . '_readable' ] ?? '',
			'WORKSPACE_ID'   => $workspace_id,
			'PROJECT_NAME'   => $parsed[ $this->project_meta_key . '_readable' ] ?? '',
			'PROJECT_ID'     => $project_id,
			'TASK_NAME'      => $parsed[ $this->task_meta_key . '_readable' ] ?? '',
			'TASK_ID'        => $task_id,
		);

		// Process each selected field and hydrate tokens.
		foreach ( $fields as $index => $field_name ) {
			$field_name  = sanitize_text_field( $field_name );
			$field_value = $this->extract_field_value( $task, $field_name );
			// Add the field value to the tokens array.
			$tokens[ 'FIELD_' . ( $index + 1 ) . '_VALUE' ] = $field_value;
		}

		// Hydrate tokens with essential information.
		$this->hydrate_tokens( $tokens );

		return true;
	}

	/**
	 * Extract field value from task data.
	 *
	 * @param array $task The task data from API
	 * @param string $field_name The field name to extract (could be standard field or custom field GID)
	 * @return string The field value
	 */
	private function extract_field_value( $task, $field_name ) {
		// Get standard fields from helpers
		$standard_fields = $this->helpers->get_standard_task_fields();

		// Check if it's a standard field
		foreach ( $standard_fields as $field ) {
			if ( $field['value'] === $field_name ) {
				return $this->extract_standard_field_value( $task, $field_name );
			}
		}

		// Check if it's a custom field GID
		return $this->extract_custom_field_value( $task, $field_name );
	}

	/**
	 * Extract standard field value from task data.
	 *
	 * @param array $task The task data from API
	 * @param string $field_name The standard field name
	 *
	 * @return string The field value
	 */
	private function extract_standard_field_value( $task, $field_name ) {
		// Handle dot notation for nested properties.
		if ( false !== strpos( $field_name, '.' ) ) {
			return $this->extract_nested_field_value( $task, $field_name );
		}

		if ( 'completed' === $field_name ) {
			return ! empty( $task['completed'] )
				? esc_html_x( 'Yes', 'Asana', 'uncanny-automator' )
				: esc_html_x( 'No', 'Asana', 'uncanny-automator' );
		}

		return $task[ $field_name ] ?? '';
	}

	/**
	 * Extract nested field value from task data.
	 *
	 * @param array $task The task data from API
	 * @param string $field_name The nested field name
	 *
	 * @return string The field value
	 */
	private function extract_nested_field_value( $task, $field_name ) {
		$parts = explode( '.', $field_name );
		$value = $task;

		foreach ( $parts as $part ) {
			if ( ! isset( $value[ $part ] ) ) {
				return '';
			}
			$value = $value[ $part ];
		}

		return $value;
	}

	/**
	 * Extract custom field value from task data.
	 *
	 * @param array $task The task data from API
	 * @param string $field_gid The custom field GID
	 * @return string The custom field value
	 */
	private function extract_custom_field_value( $task, $field_gid ) {
		if ( empty( $task['custom_fields'] ) ) {
			return '';
		}

		foreach ( $task['custom_fields'] as $field ) {
			$gid = $field['gid'] ?? '';
			if ( $gid !== $field_gid ) {
				continue;
			}

			// Get the human-readable value based on field type
			if ( isset( $field['enum_value']['name'] ) ) {
				return $field['enum_value']['name'];
			}

			if ( isset( $field['text_value'] ) ) {
				return $field['text_value'];
			}

			if ( isset( $field['number_value'] ) ) {
				return $this->extract_number_value( $field );
			}

			if ( isset( $field['date_value'] ) ) {
				return $this->extract_date_value( $field );
			}

			// Handle multi-select enum values
			if ( isset( $field['multi_enum_values'] ) ) {
				return $this->extract_multi_enum_value( $field );
			}

			// Handle people values
			if ( isset( $field['people_value'] ) ) {
				return $this->extract_people_value( $field );
			}
		}

		return '';
	}

	/**
	 * Extract number value from field data.
	 *
	 * @param array $field The field data from API
	 *
	 * @return string The number value
	 */
	private function extract_number_value( $field ) {
		$value     = (float) ( $field['number_value'] ?? 0 );
		$format    = $field['number_format'] ?? '';
		$precision = (int) $field['number_precision'] ?? 0;

		// Handle percentage format
		if ( 'percentage' === $format ) {
			$value = $value * 100;
		}

		return number_format( $value, $precision );
	}

	/**
	 * Extract date value from field data.
	 *
	 * @param array $field The field data from API
	 *
	 * @return string The date value
	 */
	private function extract_date_value( $field ) {
		$value = $field['date_value'] ?? array();
		if ( ! is_array( $value ) ) {
			return $value;
		}

		$date_time = $value['date_time'] ?? '';
		$date      = $value['date'] ?? '';

		return empty( $date_time ) ? $date : $date_time;
	}

	/**
	 * Extract multi-enum value from field data.
	 *
	 * @param array $field The field data from API
	 *
	 * @return string The multi-enum value
	 */
	private function extract_multi_enum_value( $field ) {
		$enum_values = $field['multi_enum_values'] ?? array();
		$values      = array();
		foreach ( $enum_values as $enum_value ) {
			$values[] = $enum_value['name'] ?? '';
		}
		return ! empty( $values )
			? implode( ', ', array_filter( $values ) )
			: '';
	}

	/**
	 * Extract people value from field data.
	 *
	 * @param array $field The field data from API
	 *
	 * @return string The people value
	 */
	private function extract_people_value( $field ) {
		$people = $field['people_value'] ?? array();
		$values = array();
		foreach ( $people as $person ) {
			$values[] = $person['name'] ?? $person['email'] ?? '';
		}
		return ! empty( $values )
			? implode( ', ', array_filter( $values, 'trim' ) )
			: '';
	}

	/**
	 * Dynamically add field tokens based on the action's field selection.
	 *
	 * @param array $registered_tokens The currently registered tokens
	 * @param mixed $action_id The action ID
	 * @param mixed $recipe_id The recipe ID
	 * @return array The enhanced tokens array
	 */
	public static function add_dynamic_field_tokens( $registered_tokens, $action_id, $recipe_id ) {
		// Get the action data to see what fields are selected
		$details_meta = get_post_meta( $action_id, 'ASANA_TASK_DETAILS_META_readable', true );

		if ( empty( $details_meta ) ) {
			return $registered_tokens;
		}

		// Explode CSV string for individual tokens.
		$selected_fields = explode( ',', $details_meta );

		// Add dynamic field tokens for each selected field
		foreach ( $selected_fields as $index => $field_name ) {
			$field_number = $index + 1;
			$field_name   = trim( $field_name ); // Clean up any whitespace

			// Create token array directly
			$registered_tokens[] = array(
				'tokenId'     => 'FIELD_' . $field_number . '_VALUE',
				'tokenParent' => self::ACTION_CODE,
				'tokenName'   => $field_name,
				'tokenType'   => 'text',
			);
		}

		return $registered_tokens;
	}
}
