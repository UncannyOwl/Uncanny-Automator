<?php
namespace Uncanny_Automator\Integrations\Mailchimp;

/**
 * Trait Mailchimp_Email_Fields
 *
 * Provides email field configuration and parsing/validation methods.
 *
 * @package Uncanny_Automator
 */
trait Mailchimp_Email_Fields {

	/**
	 * Get email input field configuration.
	 *
	 * @param string $option_code The option code for the field.
	 *
	 * @return array The field configuration.
	 */
	public function get_email_field_config( $option_code ) {
		return array(
			'option_code' => $option_code,
			'label'       => esc_html_x( 'Email', 'Mailchimp', 'uncanny-automator' ),
			'input_type'  => 'email',
			'required'    => true,
			'tokens'      => true,
		);
	}

	/**
	 * Get validated email from parsed action data.
	 *
	 * @param string $meta_key The meta key to retrieve the email from.
	 *
	 * @return string The sanitized and validated email.
	 * @throws \Exception If email is invalid.
	 */
	public function get_email_from_parsed( $meta_key ) {
		$email = sanitize_email( trim( $this->get_parsed_meta_value( $meta_key ) ) );

		if ( empty( $email ) || ! is_email( $email ) ) {
			throw new \Exception(
				esc_html_x( 'Valid email address is required.', 'Mailchimp', 'uncanny-automator' )
			);
		}

		return $email;
	}

	/**
	 * Get validated email from a WordPress user.
	 *
	 * @param int $user_id The WordPress user ID.
	 *
	 * @return string The sanitized and validated email.
	 * @throws \Exception If user not found or email is invalid.
	 */
	public function get_email_from_user( $user_id ) {
		$user = get_userdata( $user_id );

		if ( false === $user ) {
			throw new \Exception(
				esc_html_x( 'User not found.', 'Mailchimp', 'uncanny-automator' )
			);
		}

		$email = sanitize_email( trim( $user->user_email ) );

		if ( empty( $email ) || ! is_email( $email ) ) {
			throw new \Exception(
				esc_html_x( 'Valid user email is required.', 'Mailchimp', 'uncanny-automator' )
			);
		}

		return $email;
	}
}
