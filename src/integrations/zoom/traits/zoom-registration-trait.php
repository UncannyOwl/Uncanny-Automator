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
			'remote_data'           => $this->helpers->remote_data_parent_config( 'meetings', array( $listen_field ) ),
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
			'remote_data'              => $this->helpers->remote_data_parent_config( 'meeting_occurrences', array( $listen_field ) ),
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
			'remote_data'     => $this->helpers->remote_data_with_mapping_column(
				$this->helpers->remote_data_parent_config( 'meeting_questions', array( $listen_field ) ),
				'QUESTION_NAME'
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

	/**
	 * Get the registration action tokens.
	 *
	 * Shared by the meeting register actions so the registrant's unique join link
	 * and ID returned by Zoom are exposed to later actions in the recipe. The
	 * unregister actions deliberately do not surface these.
	 *
	 * @return array
	 */
	protected function get_registration_action_tokens() {
		return array(
			'JOIN_URL'              => array(
				'name' => esc_html_x( 'Registration join link', 'Zoom', 'uncanny-automator' ),
				'type' => 'url',
			),
			'REGISTRANT_ID'         => array(
				'name' => esc_html_x( 'Registrant ID', 'Zoom', 'uncanny-automator' ),
				'type' => 'text',
			),
			'REGISTRANT_TOPIC'      => array(
				'name' => esc_html_x( 'Meeting topic', 'Zoom', 'uncanny-automator' ),
				'type' => 'text',
			),
			'REGISTRANT_START_TIME' => array(
				'name' => esc_html_x( 'Start time', 'Zoom', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Hydrate the registration action tokens from the Zoom register response.
	 *
	 * Reads the documented registrant fields null-safely so an unexpected payload
	 * never fatals the action. The per-registrant join_url Zoom returns carries the
	 * passcode as its `tk` query param and is surfaced verbatim — never synthesized.
	 *
	 * @param array $data The decoded `data` payload from the register API response.
	 *
	 * @return void
	 */
	protected function hydrate_registration_tokens( $data ) {
		$this->hydrate_tokens(
			array(
				'JOIN_URL'              => $data['join_url'] ?? '',
				'REGISTRANT_ID'         => $data['registrant_id'] ?? '',
				'REGISTRANT_TOPIC'      => $data['topic'] ?? '',
				'REGISTRANT_START_TIME' => $data['start_time'] ?? '',
			)
		);
	}
}
