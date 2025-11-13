<?php

namespace Uncanny_Automator\Integrations\Active_Campaign;

use Uncanny_Automator\App_Integrations\Api_Caller;
use Uncanny_Automator\Utilities\Automator_Http_Response_Code;
use Uncanny_Automator\Api_Server;

use WP_Error;
use Exception;

/**
 * Class Active_Campaign_Api_Caller
 *
 * @package Uncanny_Automator
 *
 * @property Active_Campaign_App_Helpers $helpers
 */
class Active_Campaign_Api_Caller extends Api_Caller {

	////////////////////////////////////////////////////////////
	// Abstract override methods
	////////////////////////////////////////////////////////////

	/**
	 * Check for errors.
	 *
	 * @param array $response The response.
	 * @param array $args     The arguments.
	 *
	 * @return void
	 * @throws Exception
	 */
	public function check_for_errors( $response, $args = array() ) {
		$status_code = absint( $response['statusCode'] ?? 0 );
		// Bail if valid code.
		if ( in_array( $status_code, array( 201, 200 ), true ) ) {
			return;
		}

		// Get errors.
		$errors = $response['data']['errors'] ?? array();
		$errors = ! empty( $errors )
			// Extract titles.
			? wp_list_pluck( $errors, 'title' )
			// Fallback message.
			: array( esc_html_x( 'Try reconnecting your ActiveCampaign account and try again', 'ActiveCampaign', 'uncanny-automator' ) );

		// Format message.
		$message = sprintf(
			// translators: 1: Status code, 2: Message, 3: Status code text
			esc_html_x( 'ActiveCampaign has responded with status code: %1$d (%3$s) &mdash; %2$s', 'ActiveCampaign', 'uncanny-automator' ),
			$status_code,
			implode( ', ', $errors ),
			Automator_Http_Response_Code::text( $status_code )
		);

		throw new Exception( esc_html( $message ) );
	}

	////////////////////////////////////////////////////////////
	// Integration specific methods
	////////////////////////////////////////////////////////////

	/**
	 * Make a request to the ActiveCampaign API Proxy.
	 * This is the standard method for new development and future API calls.
	 *
	 * @todo This wrapper method may be removed once we store credentials in the vault
	 *       and use existing get_api_request_credentials() method.
	 *
	 * @param array|string $body The request body.
	 * @param string|null $action The action to perform.
	 * @return array The response from the API.
	 * @throws Exception
	 */
	public function active_campaign_request( $body, $action = null ) {

		// If $body is a string, convert it to an array with 'action' key.
		$body = is_string( $body ) ? array( 'action' => $body ) : $body;

		// Get credentials from helpers (this will throw an exception if invalid)
		$credentials = $this->helpers->get_credentials();

		// Add credentials to the request.
		$body['url']   = $credentials['url'];
		$body['token'] = $credentials['token'];

		// Exclude default credentials until moved to vault.
		$args = array(
			'exclude_credentials' => true,
			'include_timeout'     => 60, // Sync can be slow sometimes.
		);

		return $this->api_request( $body, $action, $args );
	}

	/**
	 * Get tag id.
	 *
	 * @param mixed $contact_id The ID.
	 * @param mixed $tag_id The ID.
	 *
	 * @return int
	 * @throws Exception
	 */
	public function get_tag_id( $contact_id, $tag_id ) {

		$contact_tag_id = 0;

		$body = array(
			'action'    => 'get_contact_tags',
			'contactId' => $contact_id,
		);

		$response = $this->active_campaign_request( $body );

		if ( empty( $response['data']['contactTags'] ) ) {
			throw new Exception(
				esc_html_x( 'The contact has no tags.', 'ActiveCampaign', 'uncanny-automator' ),
				absint( $response['statusCode'] )
			);
		}

		// Check if $tag_id is not numeric.
		if ( ! is_numeric( $tag_id ) ) {
			$tag_id = $this->helpers->get_tag_id_by_name( $tag_id );
		}

		foreach ( $response['data']['contactTags'] as $contact_tag ) {
			if ( (string) $tag_id === (string) $contact_tag['tag'] ) {
				$contact_tag_id = $contact_tag['id'];
				break;
			}
		}

		if ( 0 === $contact_tag_id ) {
			throw new Exception( esc_html_x( "The contact doesn't have the given tag.", 'ActiveCampaign', 'uncanny-automator' ) );
		}

		return $contact_tag_id;
	}

	/**
	 * Get the contact by email.
	 *
	 * @param string $email The email of the contact.
	 *
	 * @return array The contact data.
	 * @throws Exception
	 */
	public function get_contact_by_email( $email = '' ) {

		$this->validate_email( $email );

		$body = array(
			'action' => 'get_contact_by_email',
			'email'  => $email,
		);

		$response = $this->active_campaign_request( $body );
		$contacts = $response['data']['contacts'] ?? array();
		$contact  = ! empty( $contacts ) ? array_shift( $contacts ) : array();

		if ( empty( $contact ) ) {
			$message = sprintf(
				// translators: %s - Contact email
				esc_html_x( 'The contact %s does not exist in ActiveCampaign', 'ActiveCampaign', 'uncanny-automator' ),
				esc_html( $email )
			);

			throw new Exception( esc_html( $message ), 404 );
		}

		return $contact;
	}

	/**
	 * Add a tag to a contact by email.
	 *
	 * @param string $email The email of the contact.
	 * @param int $tag_id The ID of the tag.
	 * @param array $action_data The action data.
	 *
	 * @return array The response from the API.
	 * @throws Exception
	 */
	public function add_tag( $email, $tag_id, $action_data ) {
		$this->validate_action_context();

		// Validate we have an email and tag ID.
		$this->validate_email( $email );
		$this->validate_tag_id( $tag_id );

		$contact  = $this->get_contact_by_email( $email );
		$body     = array(
			'action'    => 'add_tag',
			'tagId'     => $tag_id,
			'contactId' => $contact['id'],
		);
		$response = $this->active_campaign_request( $body, $action_data );
		return $response;
	}

	/**
	 * Remove a tag from a contact by email.
	 *
	 * @param string $email The email of the contact.
	 * @param int $tag_id The ID of the tag.
	 * @param array $action_data The action data.
	 *
	 * @return array The response from the API.
	 * @throws Exception
	 */
	public function remove_tag( $email, $tag_id, $action_data ) {
		$this->validate_action_context();

		// Validate we have an email and tag ID.
		$this->validate_email( $email );
		$this->validate_tag_id( $tag_id );

		$contact = $this->get_contact_by_email( $email );
		$tag_id  = $this->get_tag_id( $contact['id'], $tag_id );

		// Delete the tag.
		$body = array(
			'action'       => 'delete_contact_tag',
			'contactTagId' => $tag_id,
		);

		$response = $this->active_campaign_request( $body, $action_data );
		$message  = $response['data']['message'] ?? '';

		// If there is a message, throw an exception.
		if ( ! empty( $message ) ) {
			throw new Exception( esc_html( $message ), absint( $response['statusCode'] ) );
		}

		return $response;
	}

	/**
	 * Add a contact to a list.
	 *
	 * @param string $email The email of the contact.
	 * @param int $list_id The ID of the list.
	 * @param array $action_data The action data.
	 *
	 * @return array The response from the API.
	 * @throws Exception
	 */
	public function add_contact_to_list( $email, $list_id, $action_data ) {
		return $this->update_contact_list_status( $email, $list_id, 1, $action_data );
	}

	/**
	 * Remove a contact from a list.
	 *
	 * @param string $email The email of the contact.
	 * @param int $list_id The ID of the list.
	 * @param array $action_data The action data.
	 *
	 * @return array The response from the API.
	 * @throws Exception
	 */
	public function remove_contact_from_list( $email, $list_id, $action_data ) {
		return $this->update_contact_list_status( $email, $list_id, 2, $action_data );
	}

	/**
	 * Update the status of a contact in a list.
	 *
	 * @param string $email The email of the contact.
	 * @param int $list_id The ID of the list.
	 * @param int $status The status to set.
	 * @param array $action_data The action data.
	 *
	 * @return array The response from the API.
	 * @throws Exception
	 */
	private function update_contact_list_status( $email, $list_id, $status, $action_data ) {
		$this->validate_action_context();

		// Validate we have an email and list ID.
		$this->validate_email( $email );
		$this->validate_list_id( $list_id );

		$contact = $this->get_contact_by_email( $email );

		$body = array(
			'action'    => 'list_update_contact',
			'listId'    => $list_id,
			'contactId' => $contact['id'],
			'status'    => $status,
		);

		return $this->active_campaign_request( $body, $action_data );
	}

	/**
	 * Delete a contact by email.
	 *
	 * @param string $email The email of the contact to delete.
	 * @param array $action_data The action data.
	 *
	 * @return array The response from the API.
	 * @throws Exception If email is not valid or if there's an API error.
	 */
	public function delete_contact( $email, $action_data ) {
		$this->validate_action_context();

		// Validate email.
		$this->validate_email( $email );

		$body = array(
			'action' => 'delete_contact_with_email',
			'email'  => $email,
		);

		return $this->active_campaign_request( $body, $action_data );
	}

	////////////////////////////////////////////////////////////
	// Legacy methods (for direct ActiveCampaign API calls)
	////////////////////////////////////////////////////////////

	/**
	 * Get the main account user info from ActiveCampaign API.
	 * Used for credential validation during account connection.
	 *
	 * @todo This method uses direct API calls (legacy approach). Future development should use
	 *       active_campaign_request() which goes through the API proxy server.
	 *
	 * @return array The account user data.
	 *
	 * @throws Exception If credentials are invalid or API error occurs.
	 */
	public function get_account_user() {
		$response = $this->make_direct_api_request( 'GET', '/api/3/users' );

		if ( is_wp_error( $response ) ) {
			// Check if it's a credential error
			if ( in_array( $response->get_error_code(), array( '401', '403' ), true ) ) {
				throw new Exception( esc_html_x( 'Error validating the credentials', 'Active Campaign', 'uncanny-automator' ) );
			}
			throw new Exception( esc_html( $response->get_error_message() ) );
		}

		if ( empty( $response['users'] ) ) {
			throw new Exception( esc_html_x( 'User was not found', 'Active Campaign', 'uncanny-automator' ) );
		}

		return $response['users'];
	}

	/**
	 * Fetch tags from ActiveCampaign API.
	 *
	 * @return array|WP_Error Array of tag data, or WP_Error on failure.
	 */
	public function fetch_tags() {
		return $this->fetch_paginated_data( '/api/3/tags', 'tags' );
	}

	/**
	 * Fetch lists from ActiveCampaign API.
	 *
	 * @return array|WP_Error Array of list data, or WP_Error on failure.
	 */
	public function fetch_lists() {
		return $this->fetch_paginated_data( '/api/3/lists', 'lists' );
	}

	/**
	 * Fetch contact fields from ActiveCampaign API.
	 *
	 * @return array|WP_Error Array of field data, or WP_Error on failure.
	 */
	public function fetch_contact_fields() {
		$offset            = 0;
		$limit             = 100;
		$has_items         = true;
		$all_field_options = array();
		$all_fields        = array();

		while ( $has_items ) {
			$result = $this->make_single_page_direct_api_request( '/api/3/fields', $limit, $offset );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$field_options = $result['fieldOptions'] ?? array();
			$fields        = $result['fields'] ?? array();

			if ( ! empty( $field_options ) ) {
				$all_field_options = array_merge( $all_field_options, $field_options );
			}

			if ( ! empty( $fields ) ) {
				$all_fields = array_merge( $all_fields, $fields );
			}

			// If there are no more fields, stop the loop.
			$has_items = ! empty( $fields ) && count( $fields ) >= $limit;

			// Increment the offset.
			$offset += $limit;
		}

		return array(
			'fieldOptions' => $all_field_options,
			'fields'       => $all_fields,
		);
	}

	/**
	 * Make a direct API request to ActiveCampaign (bypassing API proxy server).
	 *
	 * @todo This method is for direct API calls (legacy approach). Future development should use
	 *       active_campaign_request() which goes through the API proxy server.
	 *
	 * @param string $method      The HTTP method (GET, POST, DELETE, etc.).
	 * @param string $endpoint    The API endpoint path.
	 * @param array  $query_params Optional query parameters.
	 * @param array  $args        Additional request arguments.
	 *
	 * @return array|WP_Error The response data or WP_Error on failure.
	 * @throws Exception If credentials are invalid.
	 */
	private function make_direct_api_request( $method, $endpoint, $query_params = array(), $args = array() ) {
		$credentials = $this->helpers->get_credentials();

		// Build the complete API URL
		$base_url = untrailingslashit( $credentials['url'] );
		$url      = $base_url . $endpoint;
		if ( ! empty( $query_params ) ) {
			$url = add_query_arg( $query_params, $url );
		}

		$params = array(
			'method'  => $method,
			'url'     => $url,
			'headers' => array(
				'Api-token' => $credentials['token'],
				'Accept'    => 'application/json',
			),
		);

		if ( ! empty( $args ) ) {
			$params = array_merge( $params, $args );
		}

		try {
			$response    = Api_Server::call( $params );
			$status_code = wp_remote_retrieve_response_code( $response );
			$body        = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( 200 !== $status_code ) {
				return $this->handle_direct_api_error( $status_code, sprintf( 'accessing %s', $endpoint ), $body );
			}

			return $body;

		} catch ( Exception $e ) {
			return new WP_Error( 'api_error', $e->getMessage() );
		}
	}

	/**
	 * Make a single paged request and return raw response data.
	 * Helper method for sync methods that need custom processing.
	 *
	 * @todo This method uses direct API calls (legacy approach). Future development should use
	 *       active_campaign_request() which goes through the API proxy server.
	 *
	 * @param string $endpoint The full endpoint path (e.g., '/api/3/fields').
	 * @param int    $limit    Number of items per page (default: 100).
	 * @param int    $offset   Offset for pagination (default: 0).
	 * @param array  $args     Additional arguments for the request.
	 *
	 * @return array|WP_Error Raw response data, or WP_Error on failure.
	 */
	private function make_single_page_direct_api_request( $endpoint, $limit = 100, $offset = 0, $args = array() ) {
		$query_params = array(
			'limit'  => $limit,
			'offset' => $offset,
		);

		return $this->make_direct_api_request( 'GET', $endpoint, $query_params, $args );
	}

	/**
	 * Fetch paginated data from ActiveCampaign API.
	 *
	 * @param string $endpoint The API endpoint (e.g., '/api/3/tags').
	 * @param string $data_key The key in the response containing the data (e.g., 'tags').
	 * @param int    $limit    Number of items per page (default: 100).
	 *
	 * @return array|WP_Error Array of data, or WP_Error on failure.
	 */
	private function fetch_paginated_data( $endpoint, $data_key, $limit = 100 ) {
		$offset    = 0;
		$has_items = true;
		$all_data  = array();

		while ( $has_items ) {
			$result = $this->make_single_page_direct_api_request( $endpoint, $limit, $offset );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$items = $result[ $data_key ] ?? array();

			if ( ! empty( $items ) ) {
				$all_data = array_merge( $all_data, $items );
			}

			if ( empty( $items ) || count( $items ) < $limit ) {
				$has_items = false;
			}

			$offset += $limit;
		}

		return $all_data;
	}

	/**
	 * Handle API errors with consistent messaging for direct API calls.
	 *
	 * @todo This method is for direct API calls (legacy approach). Future development should use
	 *       the abstract class error handling which goes through the API proxy server.
	 *
	 * @param int    $status_code The HTTP status code.
	 * @param string $operation   Description of the operation being performed.
	 * @param array  $body        Optional response body for additional context.
	 *
	 * @return WP_Error The formatted error.
	 */
	private function handle_direct_api_error( $status_code, $operation, $body = array() ) {
		$custom_message = sprintf(
			// translators: %s - Operation description
			esc_html_x( 'while %s', 'ActiveCampaign', 'uncanny-automator' ),
			$operation
		);

		if ( empty( $body ) ) {
			$custom_message .= ' — ' . esc_html_x( 'Try reconnecting your ActiveCampaign account and try again if the issue persists.', 'ActiveCampaign', 'uncanny-automator' );
		}

		$message = $this->format_activecampaign_error( $status_code, $custom_message );
		return new WP_Error( $status_code, $message );
	}

	/**
	 * Create a standardized ActiveCampaign error message with custom details.
	 *
	 * @param int    $status_code The HTTP status code.
	 * @param string $custom_message The specific error message to append.
	 *
	 * @return string The formatted error message.
	 */
	private function format_activecampaign_error( $status_code, $custom_message ) {
		return sprintf(
			// translators: 1: Status code, 2: Status code text, 3: Custom error message
			esc_html_x( 'ActiveCampaign has responded with status code: %1$d (%2$s) &mdash; %3$s', 'ActiveCampaign', 'uncanny-automator' ),
			$status_code,
			Automator_Http_Response_Code::text( $status_code ),
			$custom_message
		);
	}

	////////////////////////////////////////////////////////////
	// Validation helper methods
	////////////////////////////////////////////////////////////

	/**
	 * Validate the email.
	 *
	 * @param string $email The email to validate.
	 *
	 * @return void
	 * @throws Exception
	 */
	private function validate_email( $email ) {
		if ( empty( $email ) ) {
			throw new Exception( esc_html_x( 'Email is required', 'ActiveCampaign', 'uncanny-automator' ) );
		}

		if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new Exception( esc_html_x( 'Invalid email address', 'ActiveCampaign', 'uncanny-automator' ) );
		}
	}

	/**
	 * Validate the tag ID.
	 *
	 * @param int $tag_id The tag ID to validate.
	 *
	 * @return void
	 * @throws Exception
	 */
	private function validate_tag_id( $tag_id ) {
		if ( empty( $tag_id ) ) {
			throw new Exception( esc_html_x( 'Tag ID is required', 'ActiveCampaign', 'uncanny-automator' ) );
		}
	}

	/**
	 * Validate the list ID.
	 *
	 * @param int $list_id The list ID to validate.
	 *
	 * @return void
	 * @throws Exception
	 */
	private function validate_list_id( $list_id ) {
		if ( empty( $list_id ) ) {
			throw new Exception( esc_html_x( 'List ID is required', 'ActiveCampaign', 'uncanny-automator' ) );
		}
	}
}
