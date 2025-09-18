<?php

namespace Uncanny_Automator\Integrations\Zoom_Webinar;

use Exception;

/**
 * Trait Zoom_Webinar_Registration_Trait
 *
 * Provides registration-related functionality for Zoom Webinar actions
 *
 * @package Uncanny_Automator\Integrations\Zoom_Webinar
 *
 * @property Zoom_Webinar_App_Helpers $helpers
 */
trait Zoom_Webinar_Registration_Trait {

	/**
	 * Get account user field.
	 *
	 * @param string $option_code The option code for the field
	 * @param bool $required Whether the field is required
	 * @param array $ajax_config Additional AJAX configuration
	 *
	 * @return array
	 */
	protected function get_account_user_field( $option_code = 'ZOOMUSER', $required = false ) {
		return array(
			'option_code'           => $option_code,
			'label'                 => esc_html_x( 'Account user', 'Zoom Webinar', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => $required,
			'options'               => array(),
			'relevant_tokens'       => array(),
			'supports_custom_value' => false,
			'ajax'                  => array(
				'endpoint' => 'uap_zoom_webinar_api_get_account_users',
				'event'    => 'on_load',
			),
		);
	}

	/**
	 * Get webinar selection field.
	 *
	 * @param string $option The action meta key
	 * @param bool $supports_tokens Whether the field supports tokens
	 * @param string $listen_field The field to listen to for AJAX
	 *
	 * @return array
	 */
	protected function get_webinar_selection_field( $action_meta, $supports_tokens = true, $listen_field = 'ZOOMUSER' ) {
		return array(
			'option_code'           => $action_meta,
			'label'                 => esc_html_x( 'Webinar', 'Zoom Webinar', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'options'               => array(),
			'supports_tokens'       => $supports_tokens,
			'supports_custom_value' => $supports_tokens,
			'ajax'                  => array(
				'endpoint'      => 'uap_zoom_webinar_api_get_webinars',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( $listen_field ),
			),
		);
	}

	/**
	 * Get webinar occurrences field.
	 *
	 * @param string $listen_field The field to listen to for AJAX
	 *
	 * @return array
	 */
	protected function get_webinar_occurrences_field( $listen_field ) {
		return array(
			'option_code'              => 'OCCURRENCES',
			'label'                    => esc_html_x( 'Occurrences', 'Zoom Webinar', 'uncanny-automator' ),
			'input_type'               => 'select',
			'required'                 => false,
			'options'                  => array(),
			'supports_tokens'          => true,
			'supports_custom_value'    => true,
			'supports_multiple_values' => true,
			'ajax'                     => array(
				'endpoint'      => 'uap_zoom_webinar_api_get_webinar_occurrences',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( $listen_field ),
			),
		);
	}

	/**
	 * Get email field.
	 *
	 * @param string $option_code The option code for the field
	 * @param bool $required Whether the field is required
	 *
	 * @return array
	 */
	protected function get_email_field( $option_code = 'EMAIL', $required = true ) {
		return array(
			'option_code' => $option_code,
			'input_type'  => 'text',
			'label'       => esc_attr_x( 'Email address', 'Zoom Webinar', 'uncanny-automator' ),
			'placeholder' => '',
			'description' => '',
			'required'    => $required,
			'tokens'      => true,
			'default'     => '',
		);
	}

	/**
	 * Get webinar questions repeater.
	 *
	 * @param string $listen_field The field to listen to for AJAX
	 *
	 * @return array
	 */
	protected function get_webinar_questions_repeater( $listen_field ) {
		return array(
			'option_code'     => 'WEBINARQUESTIONS',
			'input_type'      => 'repeater',
			'relevant_tokens' => array(),
			'label'           => esc_html_x( 'Webinar questions', 'Zoom Webinar', 'uncanny-automator' ),
			'hide_actions'    => true,
			'required'        => false,
			'fields'          => array(
				array(
					'option_code' => 'QUESTION_NAME',
					'label'       => esc_html_x( 'Question', 'Zoom Webinar', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => false,
					'read_only'   => true,
					'options'     => array(),
				),
				array(
					'option_code' => 'QUESTION_VALUE',
					'label'       => esc_html_x( 'Value', 'Zoom Webinar', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => false,
				),
			),
			'ajax'            => array(
				'event'          => 'parent_fields_change',
				'listen_fields'  => array( $listen_field ),
				'endpoint'       => 'uap_zoom_webinar_api_get_webinar_questions',
				'mapping_column' => 'QUESTION_NAME',
			),
		);
	}

	/**
	 * Parse webinar key by removing the object key suffix.
	 *
	 * @param string $webinar_key The webinar key to parse
	 *
	 * @return string
	 */
	protected function parse_webinar_key( $webinar_key ) {
		return str_replace( '-objectkey', '', $webinar_key );
	}

	/**
	 * Parse user data from WordPress user object.
	 *
	 * @param int $user_id The WordPress user ID
	 *
	 * @return array
	 * @throws Exception
	 */
	protected function parse_user_data_from_wp_user( $user_id ) {
		if ( empty( $user_id ) ) {
			throw new Exception( esc_html_x( 'User was not found.', 'Zoom Webinar', 'uncanny-automator' ) );
		}

		$user = get_userdata( $user_id );

		if ( is_wp_error( $user ) ) {
			throw new Exception( esc_html_x( 'User not found.', 'Zoom Webinar', 'uncanny-automator' ) );
		}

		$webinar_user = array(
			'email'      => $user->user_email,
			'first_name' => $user->first_name,
			'last_name'  => $user->last_name,
		);

		// Use email prefix as first name if first name is empty.
		$email_parts                = explode( '@', $webinar_user['email'] );
		$webinar_user['first_name'] = empty( $webinar_user['first_name'] ) ? $email_parts[0] : $webinar_user['first_name'];

		return $webinar_user;
	}

	/**
	 * Parse user data from form fields.
	 *
	 * @param array $action_data The action data containing form values
	 *
	 * @return array
	 * @throws Exception
	 */
	protected function parse_user_data_from_fields( $action_data ) {
		$webinar_user = array();

		$email = $this->get_parsed_meta_value( 'EMAIL' );
		if ( empty( $email ) ) {
			throw new Exception( esc_html_x( 'Email address is missing.', 'Zoom Webinar', 'uncanny-automator' ) );
		}

		if ( false === is_email( $email ) ) {
			throw new Exception( esc_html_x( 'Invalid email address.', 'Zoom Webinar', 'uncanny-automator' ) );
		}

		$webinar_user['email']      = $email;
		$webinar_user['first_name'] = $this->get_parsed_meta_value( 'FIRSTNAME' );
		$webinar_user['last_name']  = $this->get_parsed_meta_value( 'LASTNAME' );

		// Use email prefix as first name if first name is empty.
		$email_parts                = explode( '@', $webinar_user['email'] );
		$webinar_user['first_name'] = empty( $webinar_user['first_name'] ) ? $email_parts[0] : $webinar_user['first_name'];

		return $webinar_user;
	}

	/**
	 * Parse webinar occurrences from JSON.
	 *
	 * @param string $occurrences The occurrences JSON string
	 *
	 * @return array
	 */
	protected function parse_webinar_occurrences( $occurrences ) {
		if ( empty( $occurrences ) ) {
			return array();
		}

		$parsed = json_decode( $occurrences );
		return is_array( $parsed ) ? $parsed : array();
	}

	/**
	 * Parse webinar questions.
	 *
	 * @param array $user
	 * @param string $questions
	 * @param int $recipe_id
	 * @param int $user_id
	 * @param array $args
	 *
	 * @return array
	 */
	public function parse_webinar_questions( $user, $questions, $recipe_id, $user_id, $args ) {
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

			// If the question is a default question, add it to the custom questions array.
			if ( $this->is_default_question( $name ) ) {
				$user[ $name ] = $value;
				continue;
			}

			// If the question is not a default question, add it to the user array.
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
