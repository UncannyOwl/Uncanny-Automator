<?php

namespace Uncanny_Automator\Integrations\Zoom;

use Exception;

/**
 * Trait Zoom_Common_Trait
 *
 * Provides common functionality used across all Zoom actions.
 *
 * @package Uncanny_Automator\Integrations\Zoom
 */
trait Zoom_Common_Trait {

	/**
	 * Get account users field.
	 *
	 * @return array
	 */
	protected function get_account_users_field() {
		return array(
			'option_code'           => 'ZOOMUSER',
			'label'                 => esc_html_x( 'Account user', 'Zoom', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => false,
			'options'               => array(),
			'relevant_tokens'       => array(),
			'supports_custom_value' => false,
			'ajax'                  => array(
				'endpoint' => 'uap_zoom_meetings_api_get_account_users',
				'event'    => 'on_load',
			),
		);
	}

	/**
	 * Get email field.
	 *
	 * @return array
	 */
	protected function get_email_field() {
		return array(
			'option_code' => 'EMAIL',
			'input_type'  => 'text',
			'label'       => esc_attr_x( 'Email address', 'Zoom', 'uncanny-automator' ),
			'placeholder' => '',
			'description' => '',
			'required'    => true,
			'tokens'      => true,
			'default'     => '',
		);
	}

	/**
	 * Get first name field.
	 *
	 * @return array
	 */
	protected function get_first_name_field() {
		return array(
			'option_code' => 'FIRSTNAME',
			'input_type'  => 'text',
			'label'       => esc_attr_x( 'First name', 'Zoom', 'uncanny-automator' ),
			'placeholder' => '',
			'description' => '',
			'required'    => false,
			'tokens'      => true,
			'default'     => '',
		);
	}

	/**
	 * Get last name field.
	 *
	 * @return array
	 */
	protected function get_last_name_field() {
		return array(
			'option_code' => 'LASTNAME',
			'input_type'  => 'text',
			'label'       => esc_attr_x( 'Last name', 'Zoom', 'uncanny-automator' ),
			'placeholder' => '',
			'description' => '',
			'required'    => false,
			'tokens'      => true,
			'default'     => '',
		);
	}

	/**
	 * Parse email with validation.
	 *
	 * @return string
	 * @throws Exception
	 */
	protected function parse_email() {
		$email = $this->get_parsed_meta_value( 'EMAIL' );

		if ( empty( $email ) ) {
			throw new Exception( esc_html_x( 'Email address is missing.', 'Zoom', 'uncanny-automator' ) );
		}

		if ( false === is_email( $email ) ) {
			throw new Exception( esc_html_x( 'Invalid email address.', 'Zoom', 'uncanny-automator' ) );
		}

		return $email;
	}

	/**
	 * Parse user ID with validation.
	 *
	 * @param int $user_id
	 *
	 * @return int
	 * @throws Exception
	 */
	protected function parse_user_id( $user_id ) {
		if ( empty( $user_id ) ) {
			throw new Exception( esc_html_x( 'User was not found.', 'Zoom', 'uncanny-automator' ) );
		}

		return $user_id;
	}

	/**
	 * Parse meeting key with cleanup.
	 *
	 * @param string $action_meta The action meta key.
	 *
	 * @return string
	 * @throws Exception
	 */
	protected function parse_meeting_key( $action_meta ) {
		$meeting_key = $this->get_parsed_meta_value( $action_meta );

		if ( empty( $meeting_key ) ) {
			throw new Exception( esc_html_x( 'Meeting was not found.', 'Zoom', 'uncanny-automator' ) );
		}

		return str_replace( '-objectkey', '', $meeting_key );
	}

	/**
	 * Build user data array for registration.
	 *
	 * @param int $user_id
	 *
	 * @return array
	 * @throws Exception
	 */
	protected function build_user_data( $user_id ) {
		$user = get_userdata( $user_id );

		if ( is_wp_error( $user ) ) {
			throw new Exception( esc_html_x( 'User was not found.', 'Zoom', 'uncanny-automator' ) );
		}

		$meeting_user               = array();
		$meeting_user['email']      = $user->user_email;
		$meeting_user['first_name'] = $user->first_name;
		$meeting_user['last_name']  = $user->last_name;

		$email_parts                = explode( '@', $meeting_user['email'] );
		$meeting_user['first_name'] = empty( $meeting_user['first_name'] ) ? $email_parts[0] : $meeting_user['first_name'];

		return $meeting_user;
	}

	/**
	 * Build userless data array for registration.
	 *
	 * @return array
	 * @throws Exception
	 */
	protected function build_userless_data() {
		$meeting_user = array();

		$meeting_user['email']      = $this->parse_email();
		$meeting_user['first_name'] = $this->get_parsed_meta_value( 'FIRSTNAME' );
		$meeting_user['last_name']  = $this->get_parsed_meta_value( 'LASTNAME' );

		$email_parts                = explode( '@', $meeting_user['email'] );
		$meeting_user['first_name'] = empty( $meeting_user['first_name'] ) ? $email_parts[0] : $meeting_user['first_name'];

		return $meeting_user;
	}
}
