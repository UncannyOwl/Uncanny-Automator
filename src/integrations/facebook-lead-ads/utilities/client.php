<?php
namespace Uncanny_Automator\Integrations\Facebook_Lead_Ads\Utilities;

use Exception;
use Uncanny_Automator\Api_Server;
use WP_Error;

/**
 * Class Facebook_Lead_Ads_Client
 *
 * Handles communication with the Facebook Lead Ads API.
 *
 * @package Uncanny_Automator\Integrations\Facebook_Lead_Ads\Utilities
 */
class Client {

	/**
	 * API endpoint for Facebook Lead Ads integration.
	 */
	const ENDPOINT = 'v2/facebook-lead-ads';

	/**
	 * The full URL of the API endpoint.
	 *
	 * @var string
	 */
	protected $endpoint_url = '';

	/**
	 * Manages Facebook credentials.
	 *
	 * @var Credentials_Manager
	 */
	protected $credentials_manager = null;

	/**
	 * Constructor.
	 *
	 * Initializes the endpoint URL and credentials manager.
	 */
	public function __construct() {
		$this->endpoint_url        = AUTOMATOR_API_URL . self::ENDPOINT;
		$this->credentials_manager = new Credentials_Manager();
	}

	/**
	 * Retrieves page access tokens for the user.
	 *
	 * @return array|WP_Error Array of tokens on success or WP_Error on failure.
	 * @throws Exception If an error occurs during the request.
	 */
	public function get_page_access_tokens() {

		$args = array(
			'action'       => 'get_user_pages',
			'access_token' => $this->credentials_manager->get_user_access_token(),
		);

		try {
			$response = self::send_request( $args );
		} catch ( Exception $e ) {
			return new WP_Error( 'page_access_token_exception', $e->getMessage() );
		}

		return $response;
	}

	/**
	 * Retrieves forms associated with a Facebook page.
	 *
	 * @param int    $page_id           ID of the Facebook page.
	 * @param string $page_access_token Access token for the page.
	 *
	 * @return array Array of forms on success or WP_Error on failure.
	 */
	public function get_forms( int $page_id, string $page_access_token ) {

		$args = array(
			'action'            => 'get_forms',
			'page_id'           => $page_id,
			'page_access_token' => $page_access_token,
		);

		try {
			$response = self::send_request( $args, $page_id );
		} catch ( Exception $e ) {
			return new WP_Error( 'Error: get_forms method exception', $e->getMessage() );
		}

		return $response;
	}

	/**
	 * Retrieves form fields.
	 *
	 * @param int    $page_id           ID of the Facebook page.
	 * @param int    $form_id           ID of the form selected.
	 * @param string $page_access_token Access token for the page.
	 *
	 * @return array|WP_Error Array of forms on success or WP_Error on failure.
	 */
	public function get_form_fields( int $page_id, int $form_id, string $page_access_token ) {

		$args = array(
			'action'            => 'get_form_fields',
			'form_id'           => $form_id,
			'page_id'           => $page_id,
			'page_access_token' => $page_access_token,
		);

		try {
			$response = self::send_request( $args, $page_id );
		} catch ( Exception $e ) {
			return new WP_Error( 'Error: get_form_fields method exception', $e->getMessage() );
		}

		return $response;
	}

	/**
	 * Retrieves the single lead data.
	 *
	 * @param int $lead_id
	 *
	 * @return array
	 */
	public function get_lead( int $page_id, int $lead_id, string $page_access_token ) {

		$args = array(
			'action'            => 'get_lead',
			'lead_id'           => $lead_id,
			'page_access_token' => $page_access_token,
		);

		try {
			$response = self::send_request( $args, $page_id );
		} catch ( Exception $e ) {
			return new WP_Error( 'Error: get_lead method exception', $e->getMessage() );
		}

		return (array) $response['data'] ?? array();
	}


	/**
	 * Sends a request to the Facebook Lead Ads API.
	 *
	 * @param array       $body    The request body.
	 * @param string|int  $page_id Optional. The ID of the Facebook page.
	 *
	 * @return array Response from the API.
	 * @throws Exception If an error occurs during the request.
	 */
	public static function send_request( array $body = array(), $page_id = 0 ) {

		$credentials = ( new Credentials_Manager() )->get_credentials();

		$body['user']     = $credentials['user']['id'] ?? '';
		$body['site_url'] = Rest_Api::get_listener_endpoint_url();

		if ( ! empty( $page_id ) ) {
			$body['page_vault_signature'] = $credentials['vault_signatures'][ $page_id ] ?? '';
		}

		$params = array(
			'endpoint' => self::ENDPOINT,
			'body'     => $body,
			'action'   => null,
			'timeout'  => 30,
		);

		return Api_Server::api_call( $params );
	}
}
