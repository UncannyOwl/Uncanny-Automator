<?php

namespace Uncanny_Automator\Integrations\Instagram;

use Uncanny_Automator\App_Integrations\Api_Caller;
use Uncanny_Automator\Integrations\Facebook\Facebook_Bridge;
use Exception;
use WP_Error;

/**
 * Class Instagram_Api_Caller
 *
 * Instagram uses Facebook's API endpoint and credentials.
 * Makes direct API requests with Facebook vault credentials.
 *
 * @package Uncanny_Automator
 *
 * @property Instagram_App_Helpers $helpers
 */
class Instagram_Api_Caller extends Api_Caller {

	/**
	 * The Facebook bridge for shared credential access.
	 *
	 * @var Facebook_Bridge
	 */
	private $facebook_bridge;

	////////////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////////////

	/**
	 * Set class properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Initialize Facebook dependencies.
		$this->facebook_bridge = Facebook_Bridge::get_instance();
	}

	/**
	 * Prepare credentials for use in API requests.
	 *
	 * Instagram uses Facebook's credentials stored in the vault.
	 * Delegates to Facebook_Bridge for credential validation and preparation.
	 *
	 * @param array $credentials The credentials to prepare.
	 * @param array $args        Additional arguments that may be needed for preparation.
	 *
	 * @return string - Prepared credentials for API request.
	 * @throws Exception If credentials are invalid or empty.
	 */
	public function prepare_request_credentials( $credentials, $args ) {
		return $this->facebook_bridge->prepare_vault_credentials( $credentials );
	}

	/**
	 * Check for errors in API response.
	 *
	 * @param array $response The response.
	 * @param array $args     Additional arguments.
	 *
	 * @return void
	 * @throws Exception If an error occurs.
	 */
	public function check_for_errors( $response, $args = array() ) {
		$error = $response['data']['error']['message'] ?? false;
		if ( $error ) {
			throw new Exception( esc_html( $error ), absint( $response['statusCode'] ) );
		}
	}

	////////////////////////////////////////////////////////////
	// Integration Specific Methods
	////////////////////////////////////////////////////////////

	/**
	 * Publish a photo to Instagram.
	 *
	 * @param string $page_id      The Facebook page ID.
	 * @param string $image_uri    The image URI.
	 * @param string $caption      The caption.
	 * @param string $container_id Optional. Existing container ID for retry optimization.
	 * @param array  $action_data  Optional. The action data for logging/retry.
	 *
	 * @return array - The response from the Facebook API.
	 * @throws Exception If any invalid data is provided or error occurs.
	 */
	public function publish_photo( $page_id, $image_uri, $caption, $container_id = '', $action_data = null ) {

		$page = $this->facebook_bridge->get_facebook_page_by_id( $page_id );

		// Bailout if no facebook account connected.
		if ( empty( $page ) ) {
			throw new Exception(
				esc_html_x( 'Could not find any settings for Facebook Authentication. Please reconnect your account.', 'Instagram', 'uncanny-automator' ),
				404
			);
		}

		// Bailout if no instagram account connected.
		if ( empty( $page['ig_account'] ) ) {
			throw new Exception(
				esc_html_x( 'Cannot find any Instagram account connected to the Facebook page. Please ensure you select the correct Instagram account during authentication.', 'Instagram', 'uncanny-automator' )
			);
		}

		// Get the business account id.
		$business_id = $this->helpers->get_facebook_page_instagram_business_id( $page );

		// Set the body data.
		$body = array(
			'action'                 => 'page-ig-media-publish',
			'page_id'                => $page_id,
			'ig_business_account_id' => $business_id,
			'image_uri'              => $image_uri,
			'caption'                => $caption,
		);

		// Include container_id for retry optimization (skips container creation on API proxy).
		if ( ! empty( $container_id ) ) {
			$body['container_id'] = $container_id;
		}

		// Make a direct API request.
		return $this->api_request( $body, $action_data );
	}

	/**
	 * Fetch Instagram accounts for a given page.
	 *
	 * Callback method to fetch connected pages business instagram accounts. The response will include all the
	 * Instagram business account connected and then validate to check if it has permission or not during fetch.
	 * This request also update the Facebook settings to include the IG data.
	 *
	 * @param string $page_id The Facebook page ID.
	 *
	 * @return mixed array or WP_Error - Facebook page settings with instagram account data.
	 */
	public function fetch_instagram_account_for_page( $page_id ) {

		try {
			$body = array(
				'action'  => 'page-list-ig-account',
				'page_id' => $page_id,
			);

			$response = $this->api_request( $body, null );
			$data     = $response['data']['data'][0] ?? array();

			if ( empty( $data ) ) {
				throw new Exception( esc_html_x( 'No Instagram accounts found for the given page.', 'Instagram', 'uncanny-automator' ), 404 );
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'error', $e->getMessage() );
		}

		return $data;
	}
}
