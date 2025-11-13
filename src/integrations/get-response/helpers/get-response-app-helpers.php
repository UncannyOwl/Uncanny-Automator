<?php
namespace Uncanny_Automator\Integrations\Get_Response;

use Exception;

/**
 * Class Get_Response_App_Helpers
 *
 * @package Uncanny_Automator
 *
 * @property Get_Response_Api_Caller $api
 */
class Get_Response_App_Helpers extends \Uncanny_Automator\App_Integrations\App_Helpers {

	/**
	 * The uap_options table key for the API key.
	 *
	 * @var string
	 */
	const API_KEY_OPTION = 'automator_getresponse_api_key';

	/**
	 * Set properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Set a custom credentials option name for API key.
		$this->set_credentials_option_name( self::API_KEY_OPTION );
	}

	/**
	 * Get custom contact fields.
	 *
	 * @param bool $refresh
	 * @param array|null $args
	 *
	 * @return array
	 */
	public function get_contact_fields( $refresh = false, $args = null ) {

		// Get cached fields.
		$transient = 'automator_getresponse_contact/fields';
		$fields    = $refresh ? array() : get_transient( $transient );

		if ( ! empty( $fields ) ) {
			return $fields;
		}

		$fields = array();

		// Make request to get custom fields.
		try {
			$response = $this->api->api_request( 'get_contact_fields', null, $args );
			$data     = $response['data']['fields'] ?? array();

		} catch ( Exception $e ) {
			throw $e;
		}

		if ( empty( $data ) ) {
			return $fields;
		}

		// Mapping arrays.
		$multiple_types  = array( 'multi_select', 'checkbox' );
		$select_types    = array( 'single_select', 'multi_select', 'radio', 'checkbox' );
		$convert_formats = array( 'date', 'datetime', 'number', 'phone', 'url' );

		foreach ( $data as $field ) {
			// Validate field data structure
			if ( ! is_array( $field ) || ! isset( $field['name'], $field['format'], $field['type'], $field['customFieldId'] ) ) {
				continue;
			}

			$name        = $field['name'];
			$format      = $field['format']; //text, textarea, single_select, multi_select, radio, checkbox
			$field_type  = $field['type']; // text, textarea, date, datetime, country, currency, checkbox, multi_select, number, phone, url, gender
			$options     = false;
			$multiple    = false;
			$is_datetime = false;

			// Map all option type fields to select.
			$input_type = in_array( $format, $select_types, true ) ? 'select' : $format;

			// Adjust text fields to supported types.
			if ( 'text' === $input_type ) {
				$input_type = in_array( $field_type, $convert_formats, true ) ? $field_type : 'text';
				$input_type = 'number' === $field_type ? 'int' : $input_type;
			}

			// Set options for select fields.
			$fields[ $field['customFieldId'] ] = array(
				'name'          => $name,
				'type'          => $input_type,
				'original_type' => $field_type,
				'options'       => 'select' === $input_type && ! empty( $field['values'] ) ? $field['values'] : false,
				'multiple'      => in_array( $format, $multiple_types, true ),
			);
		}

		uasort(
			$fields,
			function ( $a, $b ) {
				return strcmp( $a['name'], $b['name'] );
			}
		);

		set_transient( $transient, $fields, DAY_IN_SECONDS );

		return $fields;
	}

	/**
	 * Get lists.
	 *
	 * @param bool $refresh
	 * @param array|null $args
	 *
	 * @return array
	 */
	public function get_lists( $refresh = false, $args = null ) {

		$transient = 'automator_getresponse_contact/lists';
		$lists     = $refresh ? array() : get_transient( $transient );

		if ( ! empty( $lists ) ) {
			return $lists;
		}

		$lists = array();

		// Get lists.
		try {
			$response = $this->api->api_request( 'get_lists', null, $args );
			$data     = $response['data']['lists'] ?? array();
		} catch ( Exception $e ) {
			throw $e;
		}

		if ( empty( $data ) ) {
			return $lists;
		}

		// Build select options.
		foreach ( $data as $list ) {
			// Validate list data structure
			if ( ! is_array( $list ) || ! isset( $list['campaignId'], $list['name'] ) ) {
				continue;
			}

			$lists[] = array(
				'value' => $list['campaignId'],
				'text'  => $list['name'],
			);
		}

		set_transient( $transient, $lists, DAY_IN_SECONDS );

		return $lists;
	}

	/**
	 * AJAX - Get lists for UI select options.
	 *
	 * @return void
	 */
	public function ajax_get_list_options() {
		Automator()->utilities->ajax_auth_check();

		try {
			$lists = $this->get_lists( $this->is_ajax_refresh(), array( 'context' => 'select' ) );
			wp_send_json(
				array(
					'success' => true,
					'options' => $lists,
				)
			);

		} catch ( Exception $e ) {
			wp_send_json(
				array(
					'success' => false,
					'error'   => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Get email from parsed.
	 *
	 * @param  array $parsed
	 * @param  string $meta_key
	 *
	 * @return string
	 * @throws Exception
	 */
	public function get_email_from_parsed( $parsed, $meta_key ) {

		if ( ! isset( $parsed[ $meta_key ] ) ) {
			throw new Exception( esc_html_x( 'Missing email', 'GetResponse', 'uncanny-automator' ) );
		}

		$email = sanitize_text_field( $parsed[ $meta_key ] );

		if ( ! $email || ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new Exception( esc_html_x( 'Invalid email', 'GetResponse', 'uncanny-automator' ) );
		}
		return $email;
	}
}
