<?php

namespace Uncanny_Automator\Integrations\Notion;

use Uncanny_Automator\App_Integrations\Api_Caller;
use Uncanny_Automator\Integrations\Notion\Fields\Mapper;
use Exception;

/**
 * Class Notion_Api
 *
 * @package Uncanny_Automator
 *
 * @property Notion_App_Helpers $helpers
 */
class Notion_Api_Caller extends Api_Caller {

	////////////////////////////////////////////////////////////
	// Abstract override methods
	////////////////////////////////////////////////////////////

	/**
	 * Set class properties.
	 *
	 * @return void
	 * @throws Exception
	 */
	public function set_properties() {
		$this->set_credential_request_key( 'access_token' );
	}

	/**
	 * Prepare credentials for use in API requests.
	 *
	 * @param array $credentials The credentials to prepare.
	 * @param array $args        Additional arguments that may be needed for preparation.
	 *
	 * @return string - Access token if it exists, otherwise an empty string.
	 */
	public function prepare_request_credentials( $credentials, $args ) {
		return $credentials[ $this->get_credential_request_key() ] ?? '';
	}

	/**
	 * Check for errors.
	 *
	 * @param array $response The response.
	 * @param array $args     The arguments.
	 *
	 * @return void
	 * @throws Exception If an error occurs
	 */
	public function check_for_errors( $response, $args = array() ) {
		if ( 200 !== $response['statusCode'] ) {

			// Check for error message in multiple possible locations
			$error_message = $response['data']['message'] ?? $response['error'] ?? 'Unknown error';

			throw new Exception( esc_html( $error_message ), absint( $response['statusCode'] ?? 500 ) );
		}
	}

	////////////////////////////////////////////////////////////
	// Integration specific methods
	////////////////////////////////////////////////////////////

	/**
	 * List users from Notion API.
	 *
	 * @return array List of users with their details
	 * @throws Exception If API request fails
	 */
	public function list_users() {
		$body = array(
			'action' => 'list_users',
		);

		$response = $this->api_request( $body );
		$results  = $response['data']['results'] ?? array();

		$users = array();
		foreach ( $results as $person ) {
			if ( 'person' === $person['type'] ) {
				$users[] = array(
					'text'  => sprintf( '%1$s (%2$s)', $person['name'], $person['person']['email'] ),
					'value' => $person['id'],
				);
			}
		}

		return $users;
	}

	/**
	 * List pages from Notion API.
	 *
	 * @return array List of pages with their details
	 * @throws Exception If API request fails
	 */
	public function list_pages() {
		$body = array(
			'action' => 'list_pages',
		);

		$response = $this->api_request( $body );
		$results  = (array) $response['data']['results'] ?? array();

		$options = array();
		foreach ( $results as $result ) {
			// Get the title content.
			$title = $result['properties']['title']['title'][0]['text']['content'] ?? '';
			// Only add to options if we have both an ID and a title
			if ( ! empty( $result['id'] ) && ! empty( $title ) ) {
				$options[] = array(
					'value' => $result['id'],
					'text'  => $title,
				);
			}
		}

		return $options;
	}

	/**
	 * List databases from Notion API.
	 *
	 * @return array List of databases with their details
	 * @throws Exception If API request fails
	 */
	public function list_databases() {
		$body = array(
			'action' => 'list_databases',
		);

		$response = $this->api_request( $body );
		$results  = isset( $response['data']['results'] ) ? (array) $response['data']['results'] : array();

		$options = array();
		foreach ( $results as $result ) {
			$options[] = array(
				'value' => $result['id'],
				'text'  => $result['title'][0]['text']['content'] ?? '',
			);
		}

		return $options;
	}

	/**
	 * Get database properties from Notion API.
	 *
	 * @param string $database_id The database ID
	 *
	 * @return array The database properties
	 * @throws Exception If API request fails or database ID is empty
	 */
	public function get_database_properties( $database_id ) {
		if ( empty( $database_id ) ) {
			throw new Exception( esc_html_x( 'Error: The database ID field is mandatory and cannot be left blank.', 'Notion', 'uncanny-automator' ) );
		}

		$body = array(
			'action' => 'get_database',
			'db_id'  => $database_id,
		);

		$response = $this->api_request( $body );
		return (array) $response['data']['properties'] ?? array();
	}

	/**
	 * Get database columns from Notion API.
	 *
	 * @param string $database_id The database ID
	 * @return array List of database columns with their details
	 * @throws Exception If API request fails or database ID is empty
	 */
	public function get_database_columns( $database_id ) {
		$properties         = $this->get_database_properties( $database_id );
		$unsupported_fields = $this->helpers->get_unsupported_field_types();
		$options            = array();

		foreach ( $properties as $property ) {
			// Skip unsupported field types
			if ( in_array( $property['type'], $unsupported_fields, true ) ) {
				continue;
			}

			// Skip if required properties are missing
			if ( empty( $property['id'] ) || empty( $property['type'] ) || empty( $property['name'] ) ) {
				continue;
			}

			$field_string = Notion_App_Helpers::get_field_string();

			$option_value = strtr(
				$field_string,
				array(
					'{{NAME}}' => $property['name'],
					'{{ID}}'   => $property['id'],
					'{{TYPE}}' => $property['type'],
				)
			);

			$options[] = array(
				'text'  => $property['name'],
				'value' => $option_value,
			);
		}

		return $options;
	}

	/**
	 * Get database fields from Notion API.
	 *
	 * @param string $database_id The database ID
	 * @return array List of database fields
	 * @throws Exception If API request fails or database ID is empty
	 */
	public function get_database_fields( $database_id ) {
		$properties = $this->get_database_properties( $database_id );
		$fields     = array();

		foreach ( $properties as $property ) {
			$fields_mapper = new Mapper();
			$fields_mapper->set_properties( $property );
			$field = $fields_mapper->get_corresponding_field();

			if ( ! empty( $field ) ) {
				$fields[] = $field;
			}
		}

		return $fields;
	}
}
