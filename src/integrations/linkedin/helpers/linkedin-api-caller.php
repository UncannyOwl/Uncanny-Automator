<?php

namespace Uncanny_Automator\Integrations\Linkedin;

use Uncanny_Automator\App_Integrations\Api_Caller;
use Uncanny_Automator\App_Integrations\Token_Refresh_Lock;
use Exception;

/**
 * Class Linkedin_Api_Caller
 *
 * @package Uncanny_Automator
 *
 * @property Linkedin_App_Helpers $helpers
 */
class Linkedin_Api_Caller extends Api_Caller {

	use Token_Refresh_Lock;

	////////////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////////////

	/**
	 * Set properties for this API caller.
	 *
	 * @return void
	 */
	public function set_properties() {
		// LinkedIn API proxy expects 'access_token' as the body param for credentials.
		$this->set_credential_request_key( 'access_token' );

		// Refresh when <= 30 days remain, matching legacy behavior.
		$this->set_token_refresh_buffer_seconds( DAY_IN_SECONDS * 30 );
	}

	/**
	 * Prepare credentials for use in API requests.
	 * Handles token refresh if needed.
	 *
	 * @param array $credentials The raw credentials.
	 * @param array $args        Additional arguments.
	 *
	 * @return string The access token.
	 */
	public function prepare_request_credentials( $credentials, $args ) {

		$expires_on = $credentials['expires_on'] ?? 0;
		if ( $this->is_token_expiring( $expires_on ) ) {
			$credentials = $this->handle_token_refresh_with_lock(
				$credentials,
				array( $this, 'refresh_and_store_token' )
			);
		}

		return $credentials['access_token'];
	}

	/**
	 * Check for errors in the API response.
	 *
	 * @param array $response The API response.
	 * @param array $args     Additional arguments.
	 *
	 * @return void
	 * @throws Exception If status is not 200 or 201.
	 */
	public function check_for_errors( $response, $args = array() ) {

		// Call parent for common error handling.
		parent::check_for_errors( $response, $args );

		$status_code = $response['statusCode'] ?? 0;

		// OK statuses, return.
		if ( 200 === $status_code || 201 === $status_code ) {
			return;
		}

		$error_message = 'API Error: ' . wp_json_encode( $response['data'] ?? array() );

		throw new Exception( esc_html( $error_message ), absint( $status_code ) );
	}

	////////////////////////////////////////////////////////////
	// Token refresh
	////////////////////////////////////////////////////////////

	/**
	 * Refresh and store the token.
	 *
	 * @param array $credentials The current credentials.
	 *
	 * @return array The updated credentials.
	 * @throws Exception If token refresh fails.
	 */
	public function refresh_and_store_token( $credentials ) {

		$body = array(
			'refresh_token' => $credentials['refresh_token'],
			'action'        => 'refresh_token',
		);

		$response = $this->api_request(
			$body,
			null,
			array( 'exclude_credentials' => true )
		);

		// Validate response has all required token fields.
		if (
			empty( $response['data']['access_token'] ) ||
			empty( $response['data']['refresh_token'] ) ||
			empty( $response['data']['expires_in'] ) ||
			empty( $response['data']['refresh_token_expires_in'] )
		) {
			throw new Exception(
				esc_html_x( 'Invalid token refresh response from LinkedIn.', 'LinkedIn', 'uncanny-automator' )
			);
		}

		// store_credentials calls prepare_credentials_for_storage which computes expires_on timestamps.
		$this->helpers->store_credentials( $response['data'] );

		// Return the stored credentials with computed timestamps.
		return $this->helpers->get_credentials();
	}

	////////////////////////////////////////////////////////////
	// Integration-specific API methods
	////////////////////////////////////////////////////////////

	/**
	 * Publish a post to a LinkedIn page.
	 *
	 * @param string $content    The post content.
	 * @param string $urn        The LinkedIn page URN.
	 * @param array  $action_data The action data for logging.
	 * @param string $image_url  Optional image URL. When provided, publishes a media post.
	 * @param string $status     The post status. Default 'PUBLISHED'.
	 *
	 * @return array The API response.
	 * @throws Exception If API call fails.
	 */
	public function publish_post( $content, $urn, $action_data = null, $image_url = '', $status = 'PUBLISHED' ) {

		$is_media = ! empty( $image_url );

		$body = array(
			'action' => $is_media ? 'post_media_publish' : 'post_publish',
			'urn'    => $urn,
			'status' => $status,
		);

		// The API proxy expects 'content' for media posts and 'message' for text posts.
		if ( $is_media ) {
			$body['content']   = $content;
			$body['image_url'] = $image_url;
		} else {
			$body['message'] = $content;
		}

		return $this->api_request( $body, $action_data );
	}
}
