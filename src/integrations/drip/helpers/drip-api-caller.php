<?php

namespace Uncanny_Automator\Integrations\Drip;

use Exception;

/**
 * Class Drip_Api_Caller
 *
 * @package Uncanny_Automator
 *
 * @property Drip_App_Helpers $helpers
 */
class Drip_Api_Caller extends \Uncanny_Automator\App_Integrations\Api_Caller {

	////////////////////////////////////////////////////////////
	// Abstract method implementations
	////////////////////////////////////////////////////////////

	/**
	 * Set properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Backward compatibility - Drip uses the 'client' key for the credentials param.
		$this->set_credential_request_key( 'client' );
	}

	/**
	 * Prepare request credentials.
	 *
	 * JSON-encodes the full stored credentials for the API proxy.
	 *
	 * @param array $credentials The stored credentials.
	 * @param array $args        Additional arguments.
	 *
	 * @return string JSON-encoded credentials.
	 */
	public function prepare_request_credentials( $credentials, $args ) {
		return wp_json_encode( $credentials );
	}

	/**
	 * Check for errors in API response.
	 *
	 * Handles Drip-specific error format: $response['data']['errors'][] with code + message.
	 *
	 * @param array $response The API response.
	 * @param array $args     The request arguments.
	 *
	 * @return void
	 * @throws Exception If errors are found.
	 */
	public function check_for_errors( $response, $args = array() ) {

		if ( isset( $response['statusCode'] ) && 400 === $response['statusCode'] ) {
			$this->handle_400_error( $response, $args );
		}

		if ( empty( $response['data']['errors'] ) ) {
			return;
		}

		$error_message = '';

		foreach ( $response['data']['errors'] as $error ) {
			$error_message .= $error['code'] . ': ' . $error['message'] . "\r\n";
		}

		throw new Exception( esc_html( $error_message ), absint( $response['statusCode'] ) );
	}

	////////////////////////////////////////////////////////////
	// Data fetching — account, tags, campaigns, fields
	////////////////////////////////////////////////////////////

	/**
	 * Get account info using a raw OAuth token.
	 *
	 * Uses Api_Server directly since this is called during OAuth
	 * before credentials are stored.
	 *
	 * @param array $token The OAuth token.
	 *
	 * @return array|false Account info or false on failure.
	 */
	public function get_account_info( $token ) {

		$body = array(
			'action' => 'account_info',
			'client' => wp_json_encode( array( 'token' => $token ) ),
		);

		$response = $this->api_request(
			$body,
			null,
			array( 'exclude_credentials' => true )
		);

		if ( empty( $response['data']['accounts'] ) ) {
			return false;
		}

		return array_shift( $response['data']['accounts'] );
	}

	/**
	 * Get tags from Drip account.
	 *
	 * @return array
	 */
	public function get_tags() {

		try {
			$response = $this->api_request( 'get_tags' );
		} catch ( Exception $e ) {
			automator_log( $e->getMessage() );
			return array();
		}

		return $response['data']['tags'] ?? array();
	}

	/**
	 * Get campaigns from Drip account.
	 *
	 * @return array
	 */
	public function get_campaigns() {

		try {
			$response = $this->api_request( 'get_campaigns' );
		} catch ( Exception $e ) {
			automator_log( $e->getMessage() );
			return array();
		}

		return $response['data']['campaigns'] ?? array();
	}

	/**
	 * Get custom fields from Drip account.
	 *
	 * @return array
	 */
	public function get_custom_fields() {

		$custom_fields = array();

		try {

			$response = $this->api_request( 'custom_fields' );

			if ( empty( $response['data']['custom_field_identifiers'] ) ) {
				return $custom_fields;
			}

			$custom_fields = $this->format_custom_fields( $response['data']['custom_field_identifiers'] );

		} catch ( Exception $e ) {
			automator_log( $e->getMessage() );
		}

		return $custom_fields;
	}

	////////////////////////////////////////////////////////////
	// Option formatters — data → select options
	////////////////////////////////////////////////////////////

	/**
	 * Get tags formatted as select options.
	 *
	 * @return array
	 */
	public function get_tags_as_options() {
		$options = array();
		foreach ( $this->get_tags() as $tag ) {
			$options[] = array(
				'text'  => $tag,
				'value' => $tag,
			);
		}

		return $options;
	}

	/**
	 * Get campaigns formatted as select options.
	 *
	 * @return array
	 */
	public function get_campaigns_as_options() {

		$options = array();

		foreach ( $this->get_campaigns() as $campaign ) {
			$options[] = array(
				'text'  => $campaign['name'],
				'value' => $campaign['id'],
			);
		}

		return $options;
	}

	/**
	 * Get all subscriber fields formatted as select options.
	 *
	 * Merges default fields with custom fields from the API.
	 *
	 * @return array
	 */
	public function get_fields_as_options() {

		$all_fields = array_merge(
			DRIP_CREATE_SUBSCRIBER::default_fields(),
			$this->get_custom_fields()
		);

		$options = array();
		foreach ( $all_fields as $field_name => $field_value ) {
			$options[] = array(
				'value' => $field_value,
				'text'  => $field_name,
			);
		}

		return $options;
	}

	/**
	 * Format custom fields from API response.
	 *
	 * @param array $fields The raw custom field identifiers.
	 *
	 * @return array
	 */
	public function format_custom_fields( $fields ) {

		$custom_fields = array();

		$fields_to_exclude = array(
			'first_name',
			'last_name',
		);

		foreach ( $fields as $field ) {

			if ( in_array( $field, $fields_to_exclude, true ) ) {
				continue;
			}

			$custom_fields[ $field . ' (custom field)' ] = $field;
		}

		return $custom_fields;
	}

	////////////////////////////////////////////////////////////
	// Action requests — subscriber operations
	////////////////////////////////////////////////////////////

	/**
	 * Create a subscriber.
	 *
	 * @param string $subscriber_json JSON-encoded subscriber data.
	 * @param array  $action_data     The action data.
	 *
	 * @return array
	 */
	public function create_subscriber( $subscriber_json, $action_data ) {

		$body = array(
			'action'     => 'create_subscriber',
			'subscriber' => $subscriber_json,
		);

		return $this->api_request( $body, $action_data );
	}

	/**
	 * Add a tag to a subscriber.
	 *
	 * @param string $email       The subscriber email.
	 * @param string $tag         The tag to add.
	 * @param array  $action_data The action data.
	 *
	 * @return array
	 */
	public function add_tag( $email, $tag, $action_data ) {

		$body = array(
			'action' => 'add_tag',
			'email'  => $email,
			'tag'    => $tag,
		);

		return $this->api_request( $body, $action_data );
	}

	/**
	 * Remove a tag from a subscriber.
	 *
	 * @param string $email       The subscriber email.
	 * @param string $tag         The tag to remove.
	 * @param array  $action_data The action data.
	 *
	 * @return array
	 */
	public function remove_tag( $email, $tag, $action_data ) {

		$body = array(
			'action' => 'remove_tag',
			'email'  => $email,
			'tag'    => $tag,
		);

		return $this->api_request( $body, $action_data );
	}

	/**
	 * Unsubscribe from all mailings.
	 *
	 * @param string $email       The subscriber email.
	 * @param array  $action_data The action data.
	 *
	 * @return array
	 */
	public function unsubscribe_all( $email, $action_data ) {

		$body = array(
			'action' => 'unsubscribe_all',
			'email'  => $email,
		);

		return $this->api_request( $body, $action_data );
	}

	/**
	 * Delete a subscriber.
	 *
	 * @param string $email       The subscriber email.
	 * @param array  $action_data The action data.
	 *
	 * @return array
	 */
	public function delete_subscriber( $email, $action_data ) {

		$body = array(
			'action' => 'delete_subscriber',
			'email'  => $email,
		);

		return $this->api_request( $body, $action_data );
	}

	/**
	 * Subscribe to a campaign.
	 *
	 * @param string $email       The subscriber email.
	 * @param string $campaign_id The campaign ID.
	 * @param array  $action_data The action data.
	 *
	 * @return array
	 */
	public function subscribe_to_campaign( $email, $campaign_id, $action_data ) {

		$body = array(
			'action'      => 'subscribe_to_campaign',
			'email'       => $email,
			'campaign_id' => $campaign_id,
		);

		return $this->api_request( $body, $action_data );
	}

	/**
	 * Remove from a campaign.
	 *
	 * @param string $email       The subscriber email.
	 * @param string $campaign_id The campaign ID.
	 * @param array  $action_data The action data.
	 *
	 * @return array
	 */
	public function remove_from_campaign( $email, $campaign_id, $action_data ) {

		$body = array(
			'action'      => 'remove_from_campaign',
			'email'       => $email,
			'campaign_id' => $campaign_id,
		);

		return $this->api_request( $body, $action_data );
	}
}
