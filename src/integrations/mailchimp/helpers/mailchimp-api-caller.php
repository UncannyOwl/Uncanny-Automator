<?php
namespace Uncanny_Automator\Integrations\Mailchimp;

use Exception;
use Uncanny_Automator\App_Integrations\Api_Caller;

/**
 * Class Mailchimp_Api_Caller
 *
 * @package Uncanny_Automator
 */
class Mailchimp_Api_Caller extends Api_Caller {

	////////////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////////////

	/**
	 * Set properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Mailchimp proxy expects credentials under 'client' key.
		$this->set_credential_request_key( 'client' );
	}

	/**
	 * Prepare request credentials for Mailchimp API proxy.
	 *
	 * The proxy expects: { "client": { "access_token": "...", "dc": "us1" } }
	 *
	 * @param array $credentials The stored credentials.
	 * @param array $args Additional arguments.
	 *
	 * @return array The formatted credentials for the proxy.
	 * @throws Exception If credentials are invalid.
	 */
	public function prepare_request_credentials( $credentials, $args ) {
		if ( empty( $credentials['access_token'] ) ) {
			throw new Exception(
				esc_html_x( 'Mailchimp connection is invalid. Please reconnect your account.', 'Mailchimp', 'uncanny-automator' )
			);
		}

		return array(
			'access_token' => $credentials['access_token'],
			'dc'           => $credentials['dc'] ?? '',
		);
	}

	/**
	 * Check for errors in API response.
	 *
	 * @param array $response The API response.
	 * @param array $args Additional arguments.
	 *
	 * @return void
	 * @throws Exception If the response indicates an error.
	 */
	public function check_for_errors( $response, $args = array() ) {
		$status_code   = absint( $response['statusCode'] ?? 0 );
		$success_codes = array( 200, 204 );

		if ( in_array( $status_code, $success_codes, true ) ) {
			return;
		}

		// Build error message from response.
		$error_message = '';

		if ( ! empty( $response['data']['title'] ) ) {
			$error_message .= $response['data']['title'];
		}

		if ( ! empty( $response['data']['detail'] ) ) {
			$error_message .= ! empty( $error_message ) ? ': ' : '';
			$error_message .= $response['data']['detail'];
		}

		// Check for field-level errors.
		if ( ! empty( $response['data']['errors'] ) && is_array( $response['data']['errors'] ) ) {
			foreach ( $response['data']['errors'] as $error ) {
				$error_message .= ' ' . ( $error['field'] ?? '' );
				$error_message .= ' ' . ( $error['message'] ?? '' );
			}
		}

		if ( empty( $error_message ) ) {
			$error_message = esc_html_x( 'An unknown error occurred with Mailchimp.', 'Mailchimp', 'uncanny-automator' );
		}

		// Prepend status code if available.
		if ( $status_code > 0 ) {
			$error_message = '(' . $status_code . ') ' . $error_message;
		}

		throw new Exception( esc_html( $error_message ), absint( $status_code ) );
	}

	////////////////////////////////////////////////////////////
	// Integration specific methods
	////////////////////////////////////////////////////////////

	/**
	 * Extract data array from API response.
	 *
	 * @param mixed  $response The API response.
	 * @param string $key The key to extract from response data.
	 *
	 * @return array The extracted data or empty array if not set.
	 */
	private function extract_response_data( $response, $key ) {
		if ( ! is_array( $response ) || empty( $response['data'] ) || ! is_array( $response['data'] ) ) {
			return array();
		}

		return isset( $response['data'][ $key ] ) && is_array( $response['data'][ $key ] )
			? $response['data'][ $key ]
			: array();
	}

	/**
	 * Get all lists.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_lists() {
		$response = $this->api_request( 'get_lists' );
		return $this->extract_response_data( $response, 'lists' );
	}

	/**
	 * Create and send a campaign.
	 *
	 * @param array $campaign_data
	 *
	 * @return array
	 * @throws Exception
	 */
	public function create_and_send_campaign( $campaign_data ) {
		// First create a campaign.
		$campaign_schema = array(
			'type'       => 'regular',
			'recipients' => array(
				'list_id' => $campaign_data['list_id'],
			),
			'settings'   => array(
				'subject_line' => $campaign_data['subject_line'],
				'preview_text' => $campaign_data['preview_text'],
				'title'        => $campaign_data['title'],
				'from_name'    => $campaign_data['from_name'],
				'reply_to'     => $campaign_data['from_email_address'],
				'to_name'      => $campaign_data['to_name'],
			),
		);

		if ( ! empty( $campaign_data['segment_id'] ) && '-1' !== $campaign_data['segment_id'] ) {
			$campaign_schema['recipients']['segment_opts']['saved_segment_id'] = (int) $campaign_data['segment_id'];
		}

		if ( ! empty( $campaign_data['template_id'] ) && '-1' !== $campaign_data['template_id'] ) {
			$campaign_schema['settings']['template_id'] = (int) $campaign_data['template_id'];
			$campaign_schema['content_type']            = 'template';
		} else {
			$campaign_schema['content_type'] = 'multichannel';
		}

		$request_params = array(
			'action'          => 'add_campaign',
			'campaign_schema' => wp_json_encode( $campaign_schema ),
		);

		$add_campaign_response = $this->api_request( $request_params );
		$campaign_id           = $add_campaign_response['data']['id'];

		// Put content if template was not set.
		if ( empty( $campaign_data['template_id'] ) || '-1' === $campaign_data['template_id'] ) {
			$campaign_content = array(
				'html' => $campaign_data['email_content'],
			);

			$request_params = array(
				'action'           => 'update_campaign_content',
				'campaign_content' => wp_json_encode( $campaign_content ),
				'campaign_id'      => $campaign_id,
			);

			$this->api_request( $request_params );
		}

		// Send campaign.
		$request_params = array(
			'action'      => 'send_campaign',
			'campaign_id' => $campaign_id,
		);

		return $this->api_request( $request_params );
	}

	/**
	 * Add subscriber to list.
	 *
	 * @param array $subscriber_data
	 *
	 * @return array
	 * @throws Exception
	 */
	public function add_subscriber_to_list( $subscriber_data ) {
		$user_email = $subscriber_data['email'];
		$list_id    = $subscriber_data['list_id'];
		$user_hash  = $this->generate_user_email_hash( $user_email );

		// Check if user already exists.
		$existing_user = false;
		try {
			$existing_user = $this->get_list_user( $list_id, $user_hash );
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// User doesn't exist, which is fine.
		}

		// Merge fields should be pre-parsed by the action via trait.
		$merge_fields = ! empty( $subscriber_data['merge_fields'] ) && is_array( $subscriber_data['merge_fields'] )
			? $subscriber_data['merge_fields']
			: array();

		$user_interests = array();
		if ( false !== $existing_user ) {
			// If user exists and update_existing is no, throw error.
			if ( 'no' === $subscriber_data['update_existing'] ) {
				throw new Exception( esc_html_x( 'User already subscribed to the list.', 'Mailchimp', 'uncanny-automator' ) );
			}
			// Compile user interests based on change_groups setting.
			$user_interests = $this->compile_user_interests(
				$existing_user,
				$subscriber_data['change_groups'],
				$subscriber_data['groups_list']
			);
		}

		// Determine status based on double opt-in setting
		$status = 'yes' === $subscriber_data['double_optin'] ? 'pending' : 'subscribed';

		$user_data = array(
			'email_address' => $user_email,
			'status'        => $status,
			'merge_fields'  => $merge_fields,
			'interests'     => $user_interests,
		);

		if ( ! empty( $subscriber_data['lang_code'] ) ) {
			$user_data['language'] = $subscriber_data['lang_code'];
		}

		if ( 'yes' === $subscriber_data['update_existing'] ) {
			$user_data['status_if_new'] = $status;
		}

		if ( empty( $user_data['interests'] ) ) {
			unset( $user_data['interests'] );
		}

		$body = array(
			'action'    => 'add_subscriber',
			'list_id'   => $list_id,
			'user_hash' => $user_hash,
			'user_data' => wp_json_encode( $user_data ),
		);

		return $this->api_request( $body );
	}

	/**
	 * Compile user interests based on existing interests and change groups setting.
	 *
	 * @param array  $existing_user
	 * @param string $change_groups
	 * @param mixed  $groups_list
	 *
	 * @return array
	 */
	private function compile_user_interests( $existing_user, $change_groups, $groups_list ) {
		if ( empty( $groups_list ) ) {
			return array();
		}

		$groups_list = is_string( $groups_list )
			? json_decode( $groups_list, true )
			: $groups_list;

		if ( ! is_array( $groups_list ) ) {
			return array();
		}

		// Add only: Add selected groups to existing interests.
		if ( 'add-only' === $change_groups ) {
			$add_interests = array();
			foreach ( $groups_list as $group_id ) {
				$add_interests[ $group_id ] = true;
			}
			return $add_interests;
		}

		// Remove matching: Set selected groups to false.
		if ( 'replace-matching' === $change_groups ) {
			$remove_interests = array();
			foreach ( $groups_list as $group_id ) {
				$remove_interests[ $group_id ] = false;
			}
			return $remove_interests;
		}

		// Replace all: First set all existing interests to false, then add selected.
		if ( 'replace-all' === $change_groups ) {
			$new_interests = array();

			// First remove all existing interests.
			if ( is_array( $existing_user ) && ! empty( $existing_user['interests'] ) ) {
				foreach ( $existing_user['interests'] as $interest_id => $status ) {
					$new_interests[ $interest_id ] = false;
				}
			}

			// Then add the selected ones.
			foreach ( $groups_list as $group_id ) {
				$new_interests[ $group_id ] = true;
			}

			return $new_interests;
		}

		return array();
	}

	/**
	 * Get a specific user from a list.
	 *
	 * @param string $list_id
	 * @param string $user_hash
	 *
	 * @return array|false
	 * @throws Exception
	 */
	public function get_list_user( $list_id, $user_hash ) {
		$body = array(
			'action'    => 'get_subscriber',
			'list_id'   => $list_id,
			'user_hash' => $user_hash,
		);

		try {
			$response = $this->api_request( $body );
			return isset( $response['data'] ) ? $response['data'] : false;
		} catch ( Exception $e ) {
			// Return false if user doesn't exist.
			return false;
		}
	}

	/**
	 * Delete a subscriber from a list.
	 *
	 * @param string $list_id
	 * @param string $email
	 *
	 * @return array
	 * @throws Exception
	 */
	public function delete_subscriber( $list_id, $email ) {
		return $this->api_request(
			array(
				'action'    => 'delete_subscriber',
				'list_id'   => $list_id,
				'user_hash' => $this->generate_user_email_hash( $email ),
			)
		);
	}

	/**
	 * Unsubscribe a contact from a list.
	 *
	 * @param string $list_id
	 * @param string $email
	 *
	 * @return array
	 * @throws Exception
	 */
	public function unsubscribe_contact( $list_id, $email ) {

		$user_data = array(
			'status' => 'unsubscribed',
		);

		$body = array(
			'action'    => 'update_subscriber',
			'list_id'   => $list_id,
			'user_hash' => $this->generate_user_email_hash( $email ),
			'user_data' => wp_json_encode( $user_data ),
		);

		return $this->api_request( $body );
	}

	/**
	 * Get list categories (groups).
	 *
	 * @param string $list_id
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_list_categories( $list_id ) {
		$response = $this->api_request(
			array(
				'action'  => 'get_list_categories',
				'list_id' => $list_id,
			)
		);

		return $this->extract_response_data( $response, 'categories' );
	}

	/**
	 * Get interests for a category.
	 *
	 * @param string $list_id
	 * @param string $category_id
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_interests( $list_id, $category_id ) {
		$response = $this->api_request(
			array(
				'action'      => 'get_interests',
				'list_id'     => $list_id,
				'category_id' => $category_id,
			)
		);

		return $this->extract_response_data( $response, 'interests' );
	}

	/**
	 * Get segments for a list.
	 *
	 * Note: Tags in Mailchimp are static segments. Use get_segments($list_id, 'static') for tags.
	 *
	 * @param string $list_id
	 * @param string $type Optional segment type filter (e.g., 'static' for tags, 'saved' for saved segments).
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_segments( $list_id, $type = '' ) {
		$params = array(
			'action'  => 'get_segments',
			'list_id' => $list_id,
			'fields'  => 'segments.id,segments.name',
			'count'   => 1000,
		);

		if ( ! empty( $type ) ) {
			$params['type'] = $type;
		}

		$response = $this->api_request( $params );

		return $this->extract_response_data( $response, 'segments' );
	}

	/**
	 * Get merge fields for a list.
	 *
	 * @param string $list_id
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_merge_fields( $list_id ) {
		$response = $this->api_request(
			array(
				'action'  => 'get_list_fields',
				'list_id' => $list_id,
			)
		);

		return $this->extract_response_data( $response, 'merge_fields' );
	}

	/**
	 * Get email templates.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_templates() {
		$response = $this->api_request( 'get_email_templates' );

		return $this->extract_response_data( $response, 'templates' );
	}

	/**
	 * Add tag to contact.
	 *
	 * Uses update_subscriber_tags_add_list which also adds user to list if not exists.
	 *
	 * @param string $list_id
	 * @param string $email
	 * @param array  $tags Array of tag names.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function add_tag_to_contact( $list_id, $email, $tags ) {
		$response = $this->api_request(
			array(
				'action'     => 'update_subscriber_tags_add_list',
				'list_id'    => $list_id,
				'user_hash'  => $this->generate_user_email_hash( $email ),
				'user_email' => $email,
				'tags'       => wp_json_encode( $this->format_tags_for_api( $tags, 'active' ) ),
			)
		);

		return $response['data'] ?? array();
	}

	/**
	 * Remove tag from contact.
	 *
	 * @param string $list_id
	 * @param string $email
	 * @param array  $tags Array of tag names.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function remove_tag_from_contact( $list_id, $email, $tags ) {
		$response = $this->api_request(
			array(
				'action'    => 'update_subscriber_tags',
				'list_id'   => $list_id,
				'user_hash' => $this->generate_user_email_hash( $email ),
				'tags'      => wp_json_encode( $this->format_tags_for_api( $tags, 'inactive' ) ),
			)
		);

		return $response['data'] ?? array();
	}

	/**
	 * Format tags array for Mailchimp API.
	 *
	 * Converts array of tag names to the format expected by Mailchimp:
	 * {"tags": [{"name": "tag-name", "status": "active|inactive"}]}
	 *
	 * @param array  $tags   Array of tag names.
	 * @param string $status Tag status: 'active' or 'inactive'.
	 *
	 * @return array Formatted tags structure.
	 */
	private function format_tags_for_api( $tags, $status ) {
		$formatted_tags = array();

		foreach ( $tags as $tag_name ) {
			$formatted_tags[] = array(
				'name'   => $tag_name,
				'status' => $status,
			);
		}

		return array( 'tags' => $formatted_tags );
	}

	/**
	 * Add note to contact.
	 *
	 * @param string $list_id The list ID.
	 * @param string $email The contact email.
	 * @param string $note The note content.
	 *
	 * @return array The response data.
	 * @throws Exception If the request fails.
	 */
	public function add_note_to_contact( $list_id, $email, $note ) {
		$response = $this->api_request(
			array(
				'action'    => 'add_subscriber_note',
				'list_id'   => $list_id,
				'user_hash' => $this->generate_user_email_hash( $email ),
				'note'      => wp_json_encode( array( 'note' => $note ) ),
			)
		);

		return $response['data'] ?? array();
	}

	/**
	 * Generate user hash from email.
	 *
	 * @param string $email
	 *
	 * @return string
	 */
	private function generate_user_email_hash( $email ) {
		return md5( strtolower( trim( $email ) ) );
	}
}
