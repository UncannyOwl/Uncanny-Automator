<?php

namespace Uncanny_Automator\Integrations\Facebook_Lead_Ads\Utilities;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Rest_Api.
 *
 * Handles REST API endpoints for the Facebook Lead Ads integration.
 *
 * @package Uncanny_Automator\Integrations\Facebook_Lead_Ads\Utilities
 */
class Rest_Api {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	const REST_NAMESPACE = 'automator/v1';

	/**
	 * Listener REST route.
	 *
	 * @var string
	 */
	const LISTENER_REST_ROUTE = '/integration/facebook-lead-ads';

	/**
	 * Verification REST route.
	 *
	 * @var string
	 */
	const VERIFICATION_REST_ROUTE = '/integration/facebook-lead-ads/verification';

	/**
	 * Returns the listener endpoint URL.
	 *
	 * @return string Listener endpoint URL.
	 */
	public static function get_listener_endpoint_url() {
		return rest_url( self::REST_NAMESPACE . self::LISTENER_REST_ROUTE );
	}

	/**
	 * Registers the REST API endpoints.
	 *
	 * @return void
	 */
	public function register_endpoint() {

		// Listener endpoint.
		register_rest_route(
			self::REST_NAMESPACE,
			self::LISTENER_REST_ROUTE,
			array(
				'methods'             => array( WP_REST_Server::CREATABLE ),
				'callback'            => array( $this, 'handle_request' ),
				'permission_callback' => '__return_true', // Public route.
			)
		);

		// Verification endpoint.
		register_rest_route(
			self::REST_NAMESPACE,
			self::VERIFICATION_REST_ROUTE,
			array(
				'methods'             => WP_REST_Server::CREATABLE, // Equivalent to POST.
				'callback'            => array( $this, 'verification_handle_request' ),
				'permission_callback' => '__return_true', // Public route.
			)
		);
	}

	/**
	 * Handles the verification request.
	 *
	 * Processes the verification request from the REST API.
	 *
	 * @param WP_REST_Request $request The REST API request object.
	 * @return WP_REST_Response The REST API response.
	 */
	public function verification_handle_request( WP_REST_Request $request ) {

		// Your processing logic here.
		return new WP_REST_Response( array( 'received' => time() ), 200 );
	}

	/**
	 * Handles the listener request.
	 *
	 * Processes incoming POST or GET requests for the listener endpoint.
	 *
	 * @param WP_REST_Request $request The REST API request object.
	 * @return WP_REST_Response The REST API response.
	 */
	public function handle_request( WP_REST_Request $request ) {

		$data = $request->get_params();

		if ( empty( $data ) ) {
			return rest_ensure_response(
				array(
					'code'    => 'rest_invalid_data',
					'message' => esc_html_x( 'No data provided.', 'Facebook Lead Ads', 'uncanny-automator' ),
				)
			)->set_status( 400 );
		}

		$args = array(
			'data'    => $data,
			'request' => $request,
		);

		/**
		 * Fires after the Facebook Lead Ads REST API request is processed.
		 *
		 * @param array $args {
		 *     Arguments for the action.
		 *
		 *     @type array          $data    Data received in the request.
		 *     @type WP_REST_Request $request The REST API request object.
		 * }
		 */
		do_action( 'automator_facebook_lead_ads_rest_api_handle_request_after', $args );

		// Your processing logic here.
		return rest_ensure_response(
			array(
				'code'    => 'rest_success',
				'message' => esc_html_x( 'Data processed successfully.', 'Facebook Lead Ads', 'uncanny-automator' ),
				'data'    => $data,
			)
		)->set_status( 200 );
	}

	/**
	 * Returns the arguments for the REST API endpoint.
	 *
	 * Defines the structure and validation rules for the endpoint arguments.
	 *
	 * @return array Endpoint argument definitions.
	 */
	private function get_endpoint_args() {
		return array(
			'data' => array(
				'required'          => true,
				'type'              => 'object',
				'validate_callback' => function ( $param ) {
					return is_array( $param );
				},
			),
		);
	}
}
