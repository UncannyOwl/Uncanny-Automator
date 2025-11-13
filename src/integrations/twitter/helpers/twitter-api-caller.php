<?php

namespace Uncanny_Automator\Integrations\Twitter;

use Uncanny_Automator\App_Integrations\Api_Caller;
/**
 * Class Twitter_Api_Caller
 *
 * @package Uncanny_Automator
 *
 * @property Twitter_App_Helpers $helpers
 */
class Twitter_Api_Caller extends Api_Caller {

	/**
	 * Verify new credentials via proxy.
	 *
	 * @param array $client
	 *
	 * @return mixed
	 */
	public function verify_credentials( $client ) {

		// Validate the credentials have all been set.
		foreach ( $client as $value ) {
			if ( empty( $value ) ) {
				throw new \Exception(
					esc_html_x( 'Please enter a value for all required fields.', 'Twitter', 'uncanny-automator' )
				);
			}
		}

		// Build the body of the request.
		$body = array(
			'action' => 'verify_credentials',
			'client' => wp_json_encode( $client ),
		);

		// Set some conditional args to control the request.
		$args = array(
			'exclude_credentials' => true,
			'exclude_error_check' => true,
		);

		$response = $this->api_request( $body, null, $args );

		if ( 200 !== $response['statusCode'] || empty( $response['data'] ) ) {
			throw new \Exception( 'Could not verify your X/Twitter app credentials.' );
		}

		return $response['data'];
	}

	/**
	 * Make authenticated requests to the Twitter API via Proxy.
	 *
	 * @param array $body
	 * @param array $action_data
	 * @param int $timeout
	 *
	 * @return array
	 */
	public function twitter_request( $body, $action_data = null, $timeout = null ) {

		$credentials = $this->helpers->get_credentials();

		$body['oauth_token']        = $credentials['oauth_token'];
		$body['oauth_token_secret'] = $credentials['oauth_token_secret'];

		if ( ! empty( $credentials['api_key'] ) && ! empty( $credentials['api_secret'] ) ) {
			$body['api_key']    = $credentials['api_key'];
			$body['api_secret'] = $credentials['api_secret'];
		}

		$args = array();

		if ( ! is_null( $timeout ) && 0 < absint( $timeout ) ) {
			$args['include_timeout'] = absint( $timeout );
		}

		$response = $this->api_request( $body, $action_data, $args );

		return $response;
	}

	/**
	 * Send a status update to API proxy.
	 *
	 * @param string $status
	 *
	 * @return mixed
	 */
	public function statuses_update( $status, $media = '', $action_data = null ) {

		// Build the body of the request.
		$body = array(
			'action' => 'statuses_update',
			'status' => $status,
			'media'  => $media,
		);

		// If a user app is used, switch the action.
		if ( $this->helpers->is_user_app_connected() ) {
			$body['action'] = 'manage_tweets_user_app';
		}

		$response = $this->twitter_request( $body, $action_data, 60 );

		return $response;
	}
}
