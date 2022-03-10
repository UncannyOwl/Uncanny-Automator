<?php

namespace Uncanny_Automator;

/**
 * Class Hubspot_Helpers
 *
 * @package Uncanny_Automator
 */
class Hubspot_Helpers {


	/**
	 * @var Hubspot_Helpers
	 */
	public $options;

	/**
	 * @var Hubspot_Helpers
	 */
	public $setting_tab;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Hubspot_Helpers constructor.
	 */
	public function __construct() {

		$this->automator_api = AUTOMATOR_API_URL . 'v2/hubspot';

		add_action( 'init', array( $this, 'capture_oauth_tokens' ), 100, 3 );
		add_action( 'init', array( $this, 'disconnect' ), 100, 3 );

		$this->load_settings();

	}

	public function load_settings() {
		$this->setting_tab   = 'hubspot-api';
		$this->tab_url = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-config&tab=premium-integrations&integration=' . $this->setting_tab;
		include_once __DIR__ . '/../settings/settings-hubspot.php';
		new Hubspot_Settings( $this );
	}

	/**
	 * @param Hubspot_Helpers $options
	 */
	public function setOptions( Hubspot_Helpers $options ) { // phpcs:ignore
		$this->options = $options;
	}

	/**
	 *
	 * @return array $tokens
	 */
	public function get_client() {

		$tokens = get_option( '_automator_hubspot_settings', array() );

		if ( empty( $tokens['access_token'] ) || empty( $tokens['refresh_token'] ) ) {
			return false;
		}

		return $tokens;
	}

	/**
	 * store_client
	 *
	 * @param  mixed $tokens
	 * @return void
	 */
	public function store_client( $tokens ) {

		$tokens['stored_at'] = time();

		update_option( '_automator_hubspot_settings', $tokens );

		delete_transient( '_automator_hubspot_token_info' );

		return $tokens;
	}

	/**
	 * Capture tokens returned by Automator API.
	 *
	 * @return mixed
	 */
	public function capture_oauth_tokens() {

		if ( automator_filter_input( 'integration' ) !== $this->setting_tab ) {
			return;
		}

		$automator_message = automator_filter_input( 'automator_api_message' );

		if ( empty( $automator_message ) ) {
			return;
		}

		$nonce = wp_create_nonce( 'automator_hubspot_api_authentication' );

		$tokens = (array) Automator_Helpers_Recipe::automator_api_decode_message( $automator_message, $nonce );

		$redirect_url = $this->tab_url;

		if ( $tokens ) {
			$this->store_client( $tokens );
			$redirect_url .= '&connect=1';
		} else {
			$redirect_url .= '&connect=2';
		}

		wp_safe_redirect( $redirect_url );

		die;
	}

	/**
	 * disconnect
	 *
	 * @return void
	 */
	public function disconnect() {

		if ( automator_filter_input( 'integration' ) !== $this->setting_tab ) {
			return;
		}

		if ( ! automator_filter_has_var( 'disconnect' ) ) {
			return;
		}

		delete_transient( '_automator_hubspot_token_info' );
		delete_option( '_automator_hubspot_settings' );

		$redirect_url = $this->tab_url;

		wp_safe_redirect( $redirect_url );

		die;
	}

	/**
	 * maybe_refresh_token
	 *
	 * @param  mixed $tokens
	 * @return void
	 */
	public function maybe_refresh_token( $tokens ) {

		$expiration_timestamp = $tokens['stored_at'] + $tokens['expires_in'];

		// Check if token will expire in the next minute
		if ( time() > $expiration_timestamp - MINUTE_IN_SECONDS ) {
			// Token is expired or will expire soon, refresh it
			return $this->api_refresh_token( $tokens );
		}

		return $tokens;
	}

	/**
	 * extract_data
	 *
	 * @param  mixed $response
	 * @return void
	 */
	public function extract_data( $response ) {

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['data'] ) ) {
			return false;
		}

		return $body['data'];
	}

	/**
	 * api_refresh_token
	 *
	 * @param  mixed $tokens
	 * @return void
	 */
	public function api_refresh_token( $tokens ) {

		$args = array(
			'body' => array(
				'action' => 'refresh_token',
				'client' => wp_json_encode( $tokens ),
			),
		);

		$response = wp_remote_post( $this->automator_api, $args );

		$data = $this->extract_data( $response );

		if ( empty( $data['access_token'] ) ) {
			return false;
		}

		$tokens = $this->store_client( $data );

		return $tokens;

	}

	/**
	 * api_token_info
	 *
	 * @return void
	 */
	public function api_token_info() {

		$token_info = get_transient( '_automator_hubspot_token_info' );

		if ( ! $token_info ) {

			$params = array(
				'action' => 'access_token_info',
			);

			$response = $this->api_request( $params );

			$token_info = $this->extract_data( $response );

			if ( ! $token_info ) {
				return false;
			}

			set_transient( '_automator_hubspot_token_info', $token_info, DAY_IN_SECONDS );
		}

		return $token_info;
	}

	/**
	 * create_contact
	 *
	 * @param  mixed $email
	 * @return void
	 */
	public function create_contact( $properties, $update = true ) {

		$action = 'create_contact';

		if ( $update ) {
			$action = 'create_or_update_contact';
		}

		$params = array(
			'action'     => $action,
			'properties' => wp_json_encode( $properties ),
		);

		$response = $this->api_request( $params );

		return $response;
	}

	/**
	 * Method log_action_error
	 *
	 * @param $response
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 *
	 * @return void
	 */
	public function log_action_error( $response, $user_id, $action_data, $recipe_id ) {

		// log error when no token found.
		$error_msg = __( 'API error: ', 'uncanny-automator' );

		if ( isset( $response['data']['status'] ) && 'error' === $response['data']['status'] ) {
			$error_msg .= ' ' . $response['data']['message'];
		}

		$action_data['do-nothing']           = true;
		$action_data['complete_with_errors'] = true;
		Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_msg );
	}

	/**
	 * Method api_request
	 *
	 * @param $params
	 *
	 * @return void
	 */
	public function api_request( $params ) {

		$params = apply_filters( 'automator_hubspot_api_request_params', $params );

		$client = $this->get_client();

		if ( ! $client ) {
			return false;
		}

		$client = $this->maybe_refresh_token( $client );

		$body = array(
			'client'     => $client,
			'api_ver'    => '2.0',
			'plugin_ver' => InitializePlugin::PLUGIN_VERSION,
		);

		$body = array_merge( $body, $params );

		$response = wp_remote_post(
			$this->automator_api,
			array(
				'method'  => 'POST',
				'body'    => $body,
				'timeout' => 15,
			)
		);

		$response = apply_filters( 'automator_hubspot_api_response', $response );

		return $response;
	}

	/**
	 * get_fields
	 *
	 * @return void
	 */
	public function get_fields( $exclude = array() ) {

		$fields = array(
			array(
				'value' => '',
				'text'  => __( 'Select a field', 'uncanny-automator' ),
			),
		);

		$request_params = array(
			'action' => 'get_fields',
		);

		$response = $this->api_request( $request_params );

		if ( is_wp_error( $response ) ) {

			$error_msg = implode( ', ', $response->get_error_messages() );
			automator_log( 'WordPress was unable to communicate with HubSpot and returned an error: ' . $error_msg );

			return $fields;

		} else {

			$json_data = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $json_data && 200 === intval( $json_data['statusCode'] ) ) {
				foreach ( $json_data['data'] as $field ) {

					if ( in_array( $field['name'], $exclude, true ) ) {
						continue;
					}

					if ( $field['readOnlyValue'] ) {
						continue;
					}

					$fields[] = array(
						'value' => $field['name'],
						'text'  => $field['label'],
					);
				}
			} else {
				automator_log( $json_data );
			}
		}

		return $fields;
	}



	/**
	 * get_lists
	 *
	 * @return void
	 */
	public function get_lists() {

		$options[] = array(
			'value' => '',
			'text'  => __( 'Select a list', 'uncanny-automator' ),
		);

		$params = array(
			'action' => 'get_lists',
		);

		$response = $this->api_request( $params );

		if ( is_wp_error( $response ) ) {

			$error_msg = implode( ', ', $response->get_error_messages() );
			automator_log( 'WordPress was unable to communicate with HubSpot and returned an error: ' . $error_msg );

			return $options;

		} else {

			$json_data = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $json_data && 200 === intval( $json_data['statusCode'] ) ) {

				foreach ( $json_data['data']['lists'] as $list ) {

					if ( 'STATIC' !== $list['listType'] ) {
						continue;
					}

					$options[] = array(
						'value' => $list['listId'],
						'text'  => $list['name'],
					);
				}
			} else {
				automator_log( $json_data );
			}
		}

		return apply_filters( 'automator_hubspot_options_get_lists', $options );
	}

	/**
	 * add_contact_to_list
	 *
	 * @param  mixed $email
	 * @return void
	 */
	public function add_contact_to_list( $list, $email ) {

		$params = array(
			'action' => 'add_contact_to_list',
			'email'  => $email,
			'list'   => $list,
		);

		$response = $this->api_request( $params );

		return $response;
	}

	/**
	 * remove_contact_from_list
	 *
	 * @param  mixed $list
	 * @param  mixed $email
	 * @return void
	 */
	public function remove_contact_from_list( $list, $email ) {
		$params = array(
			'action' => 'remove_contact_from_list',
			'email'  => $email,
			'list'   => $list,
		);

		$response = $this->api_request( $params );

		return $response;
	}

	public function disconnect_url() {
		return $this->tab_url . '&disconnect=1';
	}

	public function connect_url() {

		$nonce      = wp_create_nonce( 'automator_hubspot_api_authentication' );
		$plugin_ver = AUTOMATOR_PLUGIN_VERSION;
		$api_ver    = '1.0';

		$action       = 'authorization_request';
		$redirect_url = rawurlencode( $this->tab_url );
		$url   = $this->automator_api . "?action={$action}&redirect_url={$redirect_url}&nonce={$nonce}&api_ver={$api_ver}&plugin_ver={$plugin_ver}";

		return $url;
	}

}
