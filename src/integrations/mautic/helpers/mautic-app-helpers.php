<?php

namespace Uncanny_Automator\Integrations\Mautic;

use Uncanny_Automator\App_Integrations\App_Helpers;
use Exception;

/**
 * Provides helper methods for the Mautic integration, including credential
 * validation and AJAX handlers for fetching segments and contact fields.
 *
 * @package Uncanny_Automator\Integrations\Mautic
 *
 * @property Mautic_Api_Caller $api
 */
class Mautic_App_Helpers extends App_Helpers {

	/**
	 * Set the properties for the Mautic integration.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Preserve legacy account option name for backward compatibility.
		$this->set_account_option_name( 'automator_mautic_resource_owner' );
	}

	/**
	 * Validate that stored credentials are a non-empty JSON string.
	 *
	 * Credentials are stored and used as a JSON-encoded string throughout
	 * the integration, matching the format expected by the API proxy.
	 *
	 * @param mixed $credentials The raw credentials from the database.
	 * @param array $args        Optional arguments for validation context.
	 *
	 * @throws Exception If credentials are empty or not a string.
	 *
	 * @return string The validated JSON credentials string.
	 */
	public function validate_credentials( $credentials, $args = array() ) {

		if ( ! is_string( $credentials ) || empty( $credentials ) ) {
			throw new Exception( esc_html_x( 'Mautic is not connected', 'Mautic', 'uncanny-automator' ) );
		}

		return $credentials;
	}

	/**
	 * Return the standard email option field configuration for action options.
	 *
	 * @param string $option_code The option code for the field. Defaults to 'EMAIL'.
	 *
	 * @return array The email option field definition.
	 */
	public function get_email_option_config( $option_code = 'EMAIL' ) {
		return array(
			'option_code'     => $option_code,
			'label'           => esc_html_x( 'Email', 'Mautic', 'uncanny-automator' ),
			'input_type'      => 'email',
			'required'        => true,
			'supports_tokens' => true,
		);
	}

	/**
	 * Sanitize and validate an email address.
	 *
	 * @param string $email The email address to validate.
	 *
	 * @return string The sanitized email address.
	 *
	 * @throws Exception If the email is empty or invalid.
	 */
	public function validate_email( $email ) {

		if ( empty( $email ) ) {
			throw new Exception( esc_html_x( 'Email is required', 'Mautic', 'uncanny-automator' ) );
		}

		if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new Exception( esc_html_x( 'Invalid email address', 'Mautic', 'uncanny-automator' ) );
		}

		return $email;
	}

	/**
	 * Return the standard segment select option field configuration for action options.
	 *
	 * @param string $option_code The option code for the field. Defaults to 'SEGMENT'.
	 *
	 * @return array The segment option field definition.
	 */
	public function get_segment_option_config( $option_code = 'SEGMENT' ) {
		return array(
			'option_code' => $option_code,
			'input_type'  => 'select',
			'label'       => esc_html_x( 'Segment', 'Mautic', 'uncanny-automator' ),
			'token_name'  => esc_html_x( 'Segment ID', 'Mautic', 'uncanny-automator' ),
			'required'    => true,
			'ajax'        => array(
				'endpoint' => 'automator_mautic_segment_fetch',
				'event'    => 'on_load',
			),
		);
	}

	/**
	 * Validate and sanitize a segment ID from parsed action values.
	 *
	 * @param mixed $segment_id The raw segment ID value.
	 *
	 * @return int The validated segment ID.
	 *
	 * @throws Exception If the segment ID is empty or invalid.
	 */
	public function validate_segment( $segment_id ) {

		$segment_id = absint( $segment_id );

		if ( empty( $segment_id ) ) {
			throw new Exception( esc_html_x( 'A segment is required', 'Mautic', 'uncanny-automator' ) );
		}

		return $segment_id;
	}

	/**
	 * Return the standard tag select option field configuration for action options.
	 *
	 * @param string $option_code The option code for the field. Defaults to 'TAG'.
	 *
	 * @return array The tag option field definition.
	 */
	public function get_tag_option_config( $option_code = 'TAG' ) {
		return array(
			'option_code'              => $option_code,
			'input_type'               => 'select',
			'label'                    => esc_html_x( 'Tags', 'Mautic', 'uncanny-automator' ),
			'required'                 => true,
			'multiple'                 => true,
			'supports_multiple_values' => true,
			'supports_custom_value'    => true,
			'options_show_id'          => false,
			'ajax'                     => array(
				'endpoint' => 'automator_mautic_tags_fetch',
				'event'    => 'on_load',
			),
		);
	}

	/**
	 * Resolve the selected tag values into an array of tag names.
	 *
	 * Handles JSON-encoded arrays, comma-separated strings, and single tag names.
	 *
	 * @param mixed  $raw_value The raw parsed tag value (JSON string, CSV, or plain text).
	 * @param string $readable  The readable label from action meta (fallback for single values).
	 *
	 * @return string[] Array of tag name strings.
	 *
	 * @throws Exception If no tags are provided.
	 */
	public function resolve_tag_names( $raw_value, $readable = '' ) {

		if ( empty( $raw_value ) ) {
			throw new Exception( esc_html_x( 'At least one tag is required', 'Mautic', 'uncanny-automator' ) );
		}

		// Try JSON first (standard multi-select format).
		$decoded = json_decode( $raw_value, true );

		if ( is_array( $decoded ) && ! empty( $decoded ) ) {
			return array_filter( array_map( 'sanitize_text_field', $decoded ) );
		}

		// Comma-separated string (custom values or token output).
		if ( false !== strpos( $raw_value, ',' ) ) {
			return array_filter( array_map( 'sanitize_text_field', explode( ',', $raw_value ) ) );
		}

		// Single value — prefer the readable label when available.
		$name = ! empty( $readable ) ? $readable : $raw_value;

		return array( sanitize_text_field( $name ) );
	}

	/**
	 * AJAX handler that fetches all Mautic tags and returns them as
	 * select options for the recipe editor dropdown.
	 *
	 * Sends a JSON response with 'success' and 'options' keys on success,
	 * or 'success' => false and 'error' on failure. Terminates execution.
	 *
	 * @return void Outputs JSON and dies.
	 */
	public function tags_fetch() {

		$option_key = $this->get_option_key( 'tags' );
		$cached     = $this->get_app_option( $option_key );

		// Return cached data if available and not explicitly refreshed.
		if ( ! empty( $cached['data'] ) && ! $cached['refresh'] && ! $this->is_ajax_refresh() ) {
			$this->ajax_success( $cached['data'] );
		}

		$tags = array();

		try {

			$response      = (array) $this->api->api_request( 'tag_list' );
			$response_data = (array) ( $response['data'] ?? array() );

			if ( ! isset( $response_data['tags'] ) ) {
				throw new Exception( 'Invalid response format', 421 );
			}

			$tag_list = (array) $response_data['tags'];

			foreach ( $tag_list as $tag ) {
				if ( ! is_array( $tag ) || ! isset( $tag['tag'] ) ) {
					continue;
				}
				$tags[] = array(
					'value' => $tag['tag'],
					'text'  => $tag['tag'],
				);
			}

			$this->save_app_option( $option_key, $tags );

		} catch ( Exception $e ) {
			$this->ajax_error( $e->getMessage() );
		}

		$this->ajax_success( $tags );
	}

	/**
	 * AJAX handler that fetches all Mautic segments and returns them as
	 * select options for the recipe editor dropdown.
	 *
	 * Sends a JSON response with 'success' and 'options' keys on success,
	 * or 'success' => false and 'error' on failure. Terminates execution.
	 *
	 * @return void Outputs JSON and dies.
	 */
	public function segments_fetch() {

		$option_key = $this->get_option_key( 'segments' );
		$cached     = $this->get_app_option( $option_key );

		// Return cached data if available and not explicitly refreshed.
		if ( ! empty( $cached['data'] ) && ! $cached['refresh'] && ! $this->is_ajax_refresh() ) {
			$this->ajax_success( $cached['data'] );
		}

		$segments = array();

		try {

			$response      = (array) $this->api->api_request( 'segment_list' );
			$response_data = (array) ( $response['data'] ?? array() );

			if ( ! isset( $response_data['lists'] ) ) {
				throw new Exception( 'Invalid response format', 421 );
			}

			$lists = (array) $response_data['lists'];

			foreach ( $lists as $list ) {
				if ( ! is_array( $list ) || ! isset( $list['id'] ) || ! isset( $list['name'] ) ) {
					continue;
				}
				$segments[] = array(
					'value' => $list['id'],
					'text'  => $list['name'],
				);
			}

			$this->save_app_option( $option_key, $segments );

		} catch ( Exception $e ) {
			$this->ajax_error( $e->getMessage() );
		}

		$this->ajax_success( $segments );
	}

	/**
	 * AJAX handler that fetches all Mautic contact fields and returns them
	 * as repeater rows for the recipe editor. The email field is excluded
	 * since it is handled as a separate dedicated input.
	 *
	 * Sends a JSON response with 'success' and 'rows' keys on success,
	 * or 'success' => false and 'error' on failure. Terminates execution.
	 *
	 * @return void Outputs JSON and dies.
	 */
	public function render_contact_fields() {

		$option_key = $this->get_option_key( 'contact_fields' );
		$cached     = $this->get_app_option( $option_key );

		// Return cached data if available and not explicitly refreshed.
		if ( ! empty( $cached['data'] ) && ! $cached['refresh'] && ! $this->is_ajax_refresh() ) {
			$this->ajax_success( $cached['data'], 'rows' );
		}

		$rows = array();

		try {

			$response      = (array) $this->api->api_request( 'contact_fields' );
			$response_data = (array) ( $response['data'] ?? array() );

			if ( ! isset( $response_data['fields'] ) ) {
				throw new Exception( 'Invalid response format', 421 );
			}

			$fields = (array) $response_data['fields'];

			foreach ( $fields as $field ) {
				$field = (array) $field;
				if ( 'email' === $field['alias'] ) {
					continue; // Skip email.
				}
				$rows[] = array(
					'ALIAS' => $field['alias'],
					'VALUE' => $field['defaultValue'],
				);
			}

			$this->save_app_option( $option_key, $rows );
		} catch ( Exception $e ) {
			$this->ajax_error( $e->getMessage(), 'rows' );
		}

		$this->ajax_success( $rows, 'rows' );
	}
}
