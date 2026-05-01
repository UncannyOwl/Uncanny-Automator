<?php
/**
 * Keap Field Helpers Trait
 *
 * Provides shared field configuration and validation methods
 * for both contact and company actions.
 *
 * @package Uncanny_Automator\Integrations\Keap
 * @since 7.0
 */

namespace Uncanny_Automator\Integrations\Keap;

/**
 * Trait Keap_Field_Helpers
 *
 * @property Keap_App_Helpers $helpers
 * @property Keap_Api_Caller $api
 */
trait Keap_Field_Helpers {

	/**
	 * Get email field configuration.
	 *
	 * @param string $code     Field option code.
	 * @param bool   $required Whether field is required.
	 *
	 * @return array Field configuration.
	 */
	protected function get_email_field_config( $code, $required = true ) {
		return array(
			'input_type'      => 'text',
			'option_code'     => $code,
			'label'           => esc_html_x( 'Email', 'Keap', 'uncanny-automator' ),
			'supports_tokens' => true,
			'required'        => $required,
		);
	}

	/**
	 * Get update existing option configuration.
	 *
	 * @param string $type Entity type ('contact' or 'company').
	 *
	 * @return array Field configuration.
	 */
	protected function get_update_existing_option_config( $type = 'contact' ) {
		return array(
			'option_code' => 'UPDATE_EXISTING_' . strtoupper( $type ),
			'input_type'  => 'checkbox',
			'is_toggle'   => true,
			'label'       => sprintf(
				/* translators: %s: entity type (contact or company) */
				esc_html_x( 'Update existing %s', 'Keap', 'uncanny-automator' ),
				$type
			),
			'description' => sprintf(
				/* translators: %1$s: delete key placeholder */
				esc_html_x( 'To exclude fields from being updated, leave them empty. To delete a value from a field, set its value to %1$s, including the square brackets.', 'Keap', 'uncanny-automator' ),
				$this->get_delete_key()
			),
		);
	}

	/**
	 * Get email from parsed data.
	 *
	 * @param array  $parsed   Parsed action data.
	 * @param string $meta_key The meta key for the email field.
	 *
	 * @return string Valid email address.
	 * @throws \Exception When email is missing or invalid.
	 */
	protected function get_email_from_parsed( $parsed, $meta_key ) {

		if ( ! isset( $parsed[ $meta_key ] ) ) {
			throw new \Exception( esc_html_x( 'Missing email', 'Keap', 'uncanny-automator' ) );
		}

		$email = $this->get_valid_email( $parsed[ $meta_key ] );

		if ( ! $email ) {
			throw new \Exception( esc_html_x( 'Invalid email', 'Keap', 'uncanny-automator' ) );
		}

		return $email;
	}

	/**
	 * Validate and sanitize email.
	 *
	 * @param string $email Email address to validate.
	 *
	 * @return string|false Sanitized email or false if invalid.
	 */
	protected function get_valid_email( $email ) {
		if ( empty( $email ) || ! is_string( $email ) ) {
			return false;
		}
		$email    = sanitize_text_field( $email );
		$is_valid = $email && filter_var( $email, FILTER_VALIDATE_EMAIL );
		return $is_valid ? $email : false;
	}

	/**
	 * Validate and sanitize phone number.
	 *
	 * @param string $phone Phone number to validate.
	 *
	 * @return string|false Sanitized phone number or false if invalid.
	 */
	protected function get_valid_phone_number( $phone ) {
		if ( empty( $phone ) || ! is_string( $phone ) ) {
			return false;
		}
		// Allow the plus sign and remove all other non-numeric characters.
		$phone = preg_replace( '/(?!^\+)[^0-9]/', '', $phone );
		return $phone;
	}

	/**
	 * Validate and sanitize URL.
	 *
	 * @param string $url URL to validate.
	 *
	 * @return string|false Sanitized URL or false if invalid.
	 */
	protected function get_valid_url( $url ) {
		if ( empty( $url ) || ! is_string( $url ) ) {
			return false;
		}
		$url = esc_url( $url );
		return $url ? $url : false;
	}

	/**
	 * Get formatted date.
	 *
	 * @param string $input  Date input (timestamp or date string).
	 * @param string $format Output date format.
	 *
	 * @return string|\WP_Error Formatted date or WP_Error on failure.
	 */
	protected function get_formatted_date( $input, $format = 'Y-m-d' ) {

		try {
			// Get the date object.
			$date = is_numeric( $input ) ? date_create_from_format( 'U', $input ) : date_create( $input );
			if ( ! $date ) {
				throw new \Exception( esc_html_x( 'Invalid date', 'Keap', 'uncanny-automator' ) );
			}
			// Return the date in the requested format.
			$formatted = date_format( $date, $format );
			if ( ! $formatted ) {
				throw new \Exception( esc_html_x( 'Invalid format', 'Keap', 'uncanny-automator' ) );
			}
			return $formatted;
		} catch ( \Exception $e ) {
			// Return WP_Error on exception.
			return new \WP_Error(
				'invalid_date',
				$e->getMessage()
			);
		}
	}

	/**
	 * Get boolean value from parsed data.
	 *
	 * @param array  $parsed         Parsed action data.
	 * @param string $meta_key       The meta key.
	 * @param bool   $default_value  Default value if not set.
	 *
	 * @return bool
	 */
	protected function get_bool_value_from_parsed( $parsed, $meta_key, $default_value = false ) {
		if ( ! isset( $parsed[ $meta_key ] ) ) {
			return $default_value;
		}
		return filter_var( strtolower( $parsed[ $meta_key ] ), FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Get the delete key constant.
	 *
	 * @return string
	 */
	protected function get_delete_key() {
		return Keap_App_Helpers::DELETE_KEY;
	}

	/**
	 * Check if value is the delete key.
	 *
	 * @param string $value Value to check.
	 *
	 * @return bool
	 */
	protected function is_delete_value( $value ) {
		return is_string( $value ) && Keap_App_Helpers::DELETE_KEY === trim( $value );
	}

	/**
	 * Maybe remove delete value and optionally sanitize.
	 *
	 * @param string $value    Value to process.
	 * @param bool   $sanitize Whether to sanitize the value.
	 *
	 * @return string Empty string if delete value, otherwise processed value.
	 */
	protected function maybe_remove_delete_value( $value, $sanitize = true ) {
		return $this->is_delete_value( $value )
			? ''
			: ( $sanitize ? sanitize_text_field( $value ) : $value );
	}
}
