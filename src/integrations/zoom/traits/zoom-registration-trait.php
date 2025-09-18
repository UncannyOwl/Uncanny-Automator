<?php

namespace Uncanny_Automator\Integrations\Zoom;

/**
 * Trait Zoom_Registration_Trait
 *
 * Provides registration-related functionality for Zoom actions.
 *
 * @package Uncanny_Automator\Integrations\Zoom
 */
trait Zoom_Registration_Trait {

	/**
	 * Get user meetings field.
	 *
	 * @param string $action_meta The action meta key
	 * @param string $listen_field The field to listen to
	 *
	 * @return array
	 */
	protected function get_user_meetings_field( $action_meta, $listen_field = 'ZOOMUSER' ) {
		return array(
			'option_code'           => $action_meta,
			'label'                 => esc_html_x( 'Meeting', 'Zoom', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'options'               => array(),
			'supports_tokens'       => true,
			'supports_custom_value' => true,
			'ajax'                  => array(
				'endpoint'      => 'uap_zoom_meetings_api_get_meetings',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( $listen_field ),
			),
		);
	}

	/**
	 * Get meeting occurrences field.
	 *
	 * @param string $listen_field The field to listen to
	 *
	 * @return array
	 */
	protected function get_meeting_occurrences_field( $listen_field ) {
		return array(
			'option_code'              => 'OCCURRENCES',
			'label'                    => esc_html_x( 'Occurrences', 'Zoom', 'uncanny-automator' ),
			'input_type'               => 'select',
			'required'                 => false,
			'options'                  => array(),
			'supports_tokens'          => true,
			'supports_custom_value'    => true,
			'supports_multiple_values' => true,
			'ajax'                     => array(
				'endpoint'      => 'uap_zoom_meetings_api_get_meeting_occurrences',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( $listen_field ),
			),
		);
	}

	/**
	 * Get meeting questions repeater field.
	 *
	 * @param string $listen_field The field to listen to
	 *
	 * @return array
	 */
	protected function get_meeting_questions_repeater( $listen_field ) {
		return array(
			'option_code'     => 'MEETINGQUESTIONS',
			'input_type'      => 'repeater',
			'relevant_tokens' => array(),
			'label'           => esc_html_x( 'Meeting questions', 'Zoom', 'uncanny-automator' ),
			'hide_actions'    => true,
			'required'        => false,
			'fields'          => array(
				array(
					'option_code' => 'QUESTION_NAME',
					'label'       => esc_html_x( 'Question', 'Zoom', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => false,
					'read_only'   => true,
					'options'     => array(),
				),
				array(
					'option_code' => 'QUESTION_VALUE',
					'label'       => esc_html_x( 'Value', 'Zoom', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => false,
				),
			),
			'ajax'            => array(
				'event'          => 'parent_fields_change',
				'listen_fields'  => array( $listen_field ),
				'endpoint'       => 'uap_zoom_meetings_api_get_meeting_questions',
				'mapping_column' => 'QUESTION_NAME',
			),
		);
	}

	/**
	 * Parse meeting questions.
	 *
	 * @param array $user
	 * @param string $questions
	 * @param int $recipe_id
	 * @param int $user_id
	 * @param array $args
	 *
	 * @return array
	 */
	public function parse_meeting_questions( $user, $questions, $recipe_id, $user_id, $args ) {
		$questions = json_decode( $questions, true );

		if ( empty( $questions ) || ! is_array( $questions ) ) {
			return $user;
		}

		foreach ( $questions as $question ) {
			if ( empty( $question['QUESTION_VALUE'] ) ) {
				continue;
			}

			$name  = $question['QUESTION_NAME'];
			$value = Automator()->parse->text( $question['QUESTION_VALUE'], $recipe_id, $user_id, $args );

			// If the question is a default, add it to the user array.
			if ( $this->is_default_question( $name ) ) {
				$user[ $name ] = $value;
				continue;
			}

			// If the question is not a default, add it to the custom questions array.
			$user['custom_questions'][] = array(
				'title' => $name,
				'value' => $value,
			);
		}

		return $user;
	}

	/**
	 * Check if the question is a default question.
	 *
	 * @param string $question_name
	 *
	 * @return bool
	 */
	protected function is_default_question( $question_name ) {

		$default_questions = array(
			'address',
			'city',
			'state',
			'zip',
			'country',
			'phone',
			'comments',
			'industry',
			'job_title',
			'no_of_employees',
			'org',
			'purchasing_time_frame',
			'role_in_purchase_process',
		);

		return in_array( $question_name, $default_questions, true );
	}
}
