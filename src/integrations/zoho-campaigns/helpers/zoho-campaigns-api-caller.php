<?php
namespace Uncanny_Automator\Integrations\Zoho_Campaigns;

use Exception;

/**
 * API caller for Zoho Campaigns
 *
 * Implements vault-based authentication for the App_Integration framework.
 *
 * @since 4.10
 *
 * @property Zoho_Campaigns_App_Helpers $helpers
 */
class Zoho_Campaigns_Api_Caller extends \Uncanny_Automator\App_Integrations\Api_Caller {

	////////////////////////////////////////////////////////////
	// Abstract override methods
	////////////////////////////////////////////////////////////

	/**
	 * Prepare credentials for use in API requests.
	 *
	 * @param array $credentials The raw credentials from options to prepare.
	 * @param array $args        Additional arguments that may be needed for preparation.
	 *
	 * @return string JSON-encoded credentials for the API proxy.
	 */
	public function prepare_request_credentials( $credentials, $args ) {
		return wp_json_encode(
			array(
				'vault_signature' => $credentials['vault_signature'] ?? '',
				'user_id'         => $credentials['user_id'] ?? '',
			)
		);
	}

	/**
	 * Check for errors in the API response.
	 *
	 * @param array $response The response from the API.
	 * @param array $args     The arguments used in the request.
	 *
	 * @return void
	 * @throws Exception If an error is detected.
	 */
	public function check_for_errors( $response, $args = array() ) {
		// Handle HTTP 400 errors.
		if ( isset( $response['statusCode'] ) && 400 === $response['statusCode'] ) {
			$this->handle_400_error( $response, $args );
		}

		// Handle Zoho error format: data.status === 'error'.
		if ( isset( $response['data']['status'] ) && 'error' === $response['data']['status'] ) {
			$error_message = $response['data']['message'] ?? 'Zoho Campaigns API error';
			$error_code    = $response['data']['code'] ?? 'UNKNOWN';

			throw new Exception(
				sprintf(
					/* translators: %1$s: Error code, %2$s: Error message */
					esc_html_x( 'Zoho Campaigns API has responded with error code %1$s: %2$s', 'Zoho Campaigns', 'uncanny-automator' ),
					esc_html( $error_code ),
					esc_html( $error_message )
				),
				400
			);
		}
	}

	////////////////////////////////////////////////////////////
	// API Action Methods
	////////////////////////////////////////////////////////////

	/**
	 * Create a new list in Zoho Campaigns.
	 *
	 * @param array $args        The payload with list_name, signup_form, email_ids.
	 * @param array $action_data The action data array.
	 *
	 * @return array The API response.
	 * @throws Exception If validation fails or API returns error.
	 */
	public function list_add( $args, $action_data ) {
		$args = wp_parse_args(
			$args,
			array(
				'list_name'   => '',
				'signup_form' => '',
				'email_ids'   => '',
			)
		);

		// Validate required fields.
		$this->validate_list_add( $args );

		$body = array(
			'action'      => 'list_add',
			'list_name'   => $args['list_name'],
			'signup_form' => $args['signup_form'],
			'email_ids'   => $args['email_ids'],
		);

		return $this->api_request( $body, $action_data );
	}

	/**
	 * Subscribe a contact to a list.
	 *
	 * @param array $args        The payload with contact, list_key, topic_id, fields.
	 * @param array $action_data The action data array.
	 *
	 * @return array The API response.
	 * @throws Exception If validation fails or API returns error.
	 */
	public function contact_list_sub( $args, $action_data ) {
		$args = wp_parse_args(
			$args,
			array(
				'list_key' => '',
				'contact'  => '',
				'topic_id' => '',
				'fields'   => '',
			)
		);

		$this->validate_email( $args['contact'] );

		$body = array(
			'action'   => 'contact_list_sub',
			'contact'  => $args['contact'],
			'list_key' => $args['list_key'],
			'topic_id' => $args['topic_id'],
			'fields'   => $args['fields'],
		);

		return $this->api_request( $body, $action_data );
	}

	/**
	 * Unsubscribe a contact from a list.
	 *
	 * @param array $args        The payload with contact, list_key.
	 * @param array $action_data The action data array.
	 *
	 * @return array The API response.
	 * @throws Exception If validation fails or API returns error.
	 */
	public function contact_list_unsub( $args, $action_data ) {
		$args = wp_parse_args(
			$args,
			array(
				'list_key' => '',
				'contact'  => '',
			)
		);

		$this->validate_email( $args['contact'] );

		$body = array(
			'action'   => 'contact_list_unsub',
			'contact'  => $args['contact'],
			'list_key' => $args['list_key'],
		);

		return $this->api_request( $body, $action_data );
	}

	/**
	 * Move a contact to Do-Not-Mail.
	 *
	 * @param string $contact     The contact email address.
	 * @param array  $action_data The action data array.
	 *
	 * @return array The API response.
	 * @throws Exception If validation fails or API returns error.
	 */
	public function contact_donotmail_move( $contact, $action_data ) {
		$this->validate_email( $contact );

		$body = array(
			'action'  => 'contact_donotmail_move',
			'contact' => $contact,
		);

		return $this->api_request( $body, $action_data );
	}

	////////////////////////////////////////////////////////////
	// Data Retrieval Methods (for AJAX dropdowns)
	////////////////////////////////////////////////////////////

	/**
	 * Get all lists from Zoho Campaigns.
	 *
	 * @param bool $refresh Whether to refresh the cached data.
	 *
	 * @return array The formatted list options.
	 */
	public function get_lists( $refresh = false ) {

		$option_key  = $this->helpers->get_option_key( 'lists' );
		$cached_data = $this->helpers->get_app_option( $option_key );
		$lists       = $cached_data['data'];

		if ( ! $refresh && ! $cached_data['refresh'] && ! empty( $lists ) ) {
			return $lists;
		}

		try {
			$response = $this->api_request( 'list_fetch' );
			if ( empty( $response['data']['list_of_details'] ) || ! is_array( $response['data']['list_of_details'] ) ) {
				return $lists;
			}

			$options = array();
			foreach ( $response['data']['list_of_details'] as $list ) {
				$options[] = array(
					'text'  => $list['listname'],
					'value' => $list['listkey'],
				);
			}

			$this->helpers->save_app_option( $option_key, $options );

			return $options;
		} catch ( Exception $e ) {
			return $lists;
		}
	}

	/**
	 * Get all topics from Zoho Campaigns.
	 *
	 * @param bool $refresh Whether to refresh the cached data.
	 *
	 * @return array The formatted topic options.
	 */
	public function get_topics( $refresh = false ) {

		$option_key  = $this->helpers->get_option_key( 'topics' );
		$cached_data = $this->helpers->get_app_option( $option_key );
		$topics      = $cached_data['data'];

		if ( ! $refresh && ! $cached_data['refresh'] && ! empty( $topics ) ) {
			return $topics;
		}

		try {
			$response = $this->api_request( 'topic_fetch' );
			if ( empty( $response['data']['topicDetails'] ) || ! is_array( $response['data']['topicDetails'] ) ) {
				return $topics;
			}

			$options = array();
			foreach ( $response['data']['topicDetails'] as $topic ) {
				$options[] = array(
					'text'  => $topic['topicName'],
					'value' => $topic['topicId'],
				);
			}

			$this->helpers->save_app_option( $option_key, $options );

			return $options;
		} catch ( Exception $e ) {
			return $topics;
		}
	}

	/**
	 * Get all contact fields from Zoho Campaigns.
	 *
	 * @param bool $refresh Whether to refresh the cached data.
	 *
	 * @return array The formatted field rows for repeater.
	 */
	public function get_fields( $refresh = false ) {

		$option_key  = $this->helpers->get_option_key( 'fields' );
		$cached_data = $this->helpers->get_app_option( $option_key );
		$fields      = $cached_data['data'];

		if ( ! $refresh && ! $cached_data['refresh'] && ! empty( $fields ) ) {
			return $fields;
		}

		try {
			$response    = $this->api_request( 'fields_retrieve' );
			$field_names = isset( $response['data']['response']['fieldnames']['fieldname'] )
				? (array) $response['data']['response']['fieldnames']['fieldname']
				: array();

			if ( empty( $field_names ) ) {
				return $fields;
			}

			$rows = array();
			foreach ( $field_names as $field ) {
				if ( ! isset( $field['FIELD_NAME'] ) || ! isset( $field['DISPLAY_NAME'] ) ) {
					continue;
				}

				// Do not show email as a repeater field.
				if ( 'contact_email' === $field['FIELD_NAME'] ) {
					continue;
				}

				$rows[] = array(
					'FIELD_NAME'  => $field['DISPLAY_NAME'],
					'FIELD_VALUE' => '',
				);
			}

			$this->helpers->save_app_option( $option_key, $rows );

			return $rows;
		} catch ( Exception $e ) {
			return $fields;
		}
	}

	////////////////////////////////////////////////////////////
	// Validation Methods
	////////////////////////////////////////////////////////////

	/**
	 * Validate email address format.
	 *
	 * @param string $email The email to validate.
	 *
	 * @return void
	 * @throws Exception If email is invalid.
	 */
	protected function validate_email( $email ) {
		if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new Exception(
				esc_html_x( 'Invalid email address format. Check token value.', 'Zoho Campaigns', 'uncanny-automator' ),
				400
			);
		}
	}

	/**
	 * Validate list_add parameters.
	 *
	 * @param array $args The arguments to validate.
	 *
	 * @return void
	 * @throws Exception If validation fails.
	 */
	protected function validate_list_add( $args ) {
		if ( empty( $args['list_name'] ) ) {
			throw new Exception(
				esc_html_x( 'Error: parameter `list name` is empty. Check token value.', 'Zoho Campaigns', 'uncanny-automator' ),
				400
			);
		}

		if ( empty( $args['signup_form'] ) ) {
			throw new Exception(
				esc_html_x( 'Error: parameter `signup_form` is empty.', 'Zoho Campaigns', 'uncanny-automator' ),
				400
			);
		}

		if ( empty( $args['email_ids'] ) ) {
			throw new Exception(
				esc_html_x( 'Error: parameter `email_ids` is empty.', 'Zoho Campaigns', 'uncanny-automator' ),
				400
			);
		}
	}
}
