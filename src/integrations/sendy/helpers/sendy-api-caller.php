<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Sendy;

use Uncanny_Automator\App_Integrations\Api_Caller;
use Exception;

/**
 * Class Sendy_Api_Caller
 *
 * @package Uncanny_Automator
 * @property Sendy_App_Helpers $helpers
 */
class Sendy_Api_Caller extends Api_Caller {

	/**
	 * Sendy API request wrapper.
	 *
	 * Used internally to apply both API key and URL to requests
	 * until properly migrated to vault.
	 *
	 * @param string $action
	 * @param mixed  $body
	 * @param mixed  $action_data
	 *
	 * @return array
	 */
	private function sendy_api_request( $action, $body = null, $action_data = null ) {
		// Format body.
		$body              = is_array( $body ) ? $body : array();
		$body['action']    = $action;
		$body['api_key']   = $this->helpers->get_sendy_setting( 'api_key' );
		$body['sendy_url'] = $this->helpers->get_sendy_setting( 'url' );

		// Set args to skip framework authentication.
		$args = array(
			'exclude_credentials' => true,
		);

		return $this->api_request( $body, $action_data, $args );
	}

	/**
	 * Refresh Sendy's bespoke credential keys on a log resend.
	 *
	 * The action path bakes the API key + Sendy URL (read from settings, not
	 * get_credentials()) straight into the body, so re-read the current settings
	 * and overwrite those keys before a resend replays the stale ones. Mirrors
	 * sendy_api_request().
	 *
	 * @param array $body The stored request body being replayed.
	 *
	 * @return array
	 */
	protected function replace_resend_credentials( $body ) {

		if ( array_key_exists( 'api_key', $body ) ) {
			$body['api_key'] = $this->helpers->get_sendy_setting( 'api_key' );
		}

		if ( array_key_exists( 'sendy_url', $body ) ) {
			$body['sendy_url'] = $this->helpers->get_sendy_setting( 'url' );
		}

		return parent::replace_resend_credentials( $body );
	}

	/**
	 * Add contact to list.
	 *
	 * @param string $email
	 * @param string $list_id
	 * @param array  $fields
	 * @param array  $action_data
	 *
	 * @return array
	 * @throws Exception
	 */
	public function add_contact_to_list( $email, $list_id, $fields, $action_data ) {

		if ( ! isset( $fields['referrer'] ) ) {
			$fields['referrer'] = get_site_url();
		}

		$body = array(
			'email'  => $email,
			'list'   => $list_id,
			'fields' => $fields,
		);

		return $this->sendy_api_request( 'add_contact_to_list', $body, $action_data );
	}

	/**
	 * Unsubscribe contact from list.
	 *
	 * @param string $email
	 * @param string $list_id
	 * @param array  $action_data
	 *
	 * @return array
	 * @throws Exception
	 */
	public function unsubscribe_contact_from_list( $email, $list_id, $action_data ) {

		$body = array(
			'email' => $email,
			'list'  => $list_id,
		);

		$response = $this->sendy_api_request( 'unsubscribe_contact_from_list', $body, $action_data );

		return $response;
	}

	/**
	 * Delete contact from list.
	 *
	 * @param string $email
	 * @param string $list_id
	 * @param array  $action_data
	 *
	 * @return array
	 * @throws Exception
	 */
	public function delete_contact_from_list( $email, $list_id, $action_data ) {

		$body = array(
			'email' => $email,
			'list'  => $list_id,
		);

		$response = $this->sendy_api_request( 'delete_contact_from_list', $body, $action_data );

		return $response;
	}

	/**
	 * Get Lists.
	 *
	 * @param bool $refresh
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_lists( $refresh = false ) {

		$transient_key = $this->helpers->get_const( 'LIST_TRANSIENT_KEY' );

		if ( $refresh ) {
			delete_transient( $transient_key );
		}

		$options = get_transient( $transient_key );
		if ( ! empty( $options ) ) {
			return $options;
		}

		$response = $this->sendy_api_request( 'get_lists' );
		$lists    = $response['data']['lists'] ?? array();

		if ( empty( $lists ) ) {
			$message = $response['data']['error'] ?? esc_html_x( 'No lists were found', 'Sendy', 'uncanny-automator' );
			throw new Exception( esc_html( $message ) );
		}

		// Generate options array.
		$options = array();
		foreach ( $lists as $list ) {
			$options[] = array(
				'value' => $list['id'],
				'text'  => $list['name'],
			);
		}

		// Sort by text value.
		usort(
			$options,
			function ( $a, $b ) {
				return strcmp( $a['text'], $b['text'] );
			}
		);

		// Set transient.
		set_transient( $transient_key, $options, DAY_IN_SECONDS );

		return $options;
	}

	/**
	 * Check response for errors.
	 *
	 * @param mixed $response
	 * @param array $args
	 *
	 * @return void
	 */
	public function check_for_errors( $response, $args = array() ) {

		// Vendor error nested in data.
		if ( ! empty( $response['data']['error'] ) ) {
			throw new Exception( esc_html( $response['data']['error'] ), 400 );
		}

		if ( ! isset( $response['statusCode'] ) || $response['statusCode'] < 400 ) {
			return;
		}

		// Prefer the real platform/vendor message (e.g. "Email does not exist in
		// list") over the generic fallback so the action layer can react to it.
		// On the exception path api_request passes the message as $response['error']
		// (string); the normal path nests it under error.description. Mirrors the
		// slack/ontraport/campaign-monitor fix.
		$message = '';

		if ( ! empty( $response['data']['message'] ) ) {
			$message = $response['data']['message'];
		} elseif ( ! empty( $response['error'] ) ) {
			$message = is_array( $response['error'] )
				? ( $response['error']['description'] ?? $response['error']['message'] ?? '' )
				: (string) $response['error'];
		}

		if ( '' === $message ) {
			$message = esc_html_x( 'Sendy API Error', 'Sendy', 'uncanny-automator' );
		}

		throw new Exception( esc_html( $message ), 400 );
	}
}
