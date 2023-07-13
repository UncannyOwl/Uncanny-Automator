<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

use Uncanny_Automator\Api_Server;

use function cli\err;

/**
 * Class Drip_Functions
 *
 * @package Uncanny_Automator
 */
class Drip_Functions {

	/**
	 * API endpoint.
	 *
	 * @var string
	 */
	const API_ENDPOINT = 'v2/drip';

	/**
	 * The nonce.
	 *
	 * @var string
	 */
	const NONCE = 'automator_drip_api_authentication';

	/**
	 * The option key for the token.
	 *
	 * @var string
	 */
	const TOKEN_OPTION = 'automator_drip_credentials';

	/**
	 * The transient for account info.
	 *
	 * @var string
	 */
	const ACCOUNT_TRANSIENT = 'automator_drip_account_info';


	/**
	 * Settings tab id
	 *
	 * @var string|object
	 */
	public $settings_tab = 'drip';

	/**
	 * get_settings_page_url
	 *
	 * @return string
	 */
	public function get_settings_page_url() {
		return add_query_arg(
			array(
				'post_type'   => 'uo-recipe',
				'page'        => 'uncanny-automator-config',
				'tab'         => 'premium-integrations',
				'integration' => $this->settings_tab,
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * get_client
	 *
	 * @return array
	 */
	public function get_client() {
		return automator_get_option( self::TOKEN_OPTION, false );
	}

	public function integration_status() {
		return $this->get_client() ? 'success' : '';
	}

	/**
	 * get_auth_url
	 *
	 * @return string
	 */
	public function get_auth_url() {

		// Define the parameters of the URL
		$parameters = array(
			// Authentication nonce
			'nonce'        => wp_create_nonce( self::NONCE ),
			// Action
			'action'       => 'authorization_request',
			// Redirect URL
			'redirect_url' => rawurlencode( $this->get_settings_page_url() ),
		);

		// Return the URL
		return add_query_arg(
			$parameters,
			AUTOMATOR_API_URL . self::API_ENDPOINT
		);
	}

	/**
	 * get_disconnect_url
	 *
	 * @return string
	 */
	public function get_disconnect_url() {

		return add_query_arg(
			array(
				'action' => 'automator_drip_disconnect',
				'nonce'  => wp_create_nonce( self::NONCE ),
			),
			admin_url( 'admin-ajax.php' )
		);

	}

	/**
	 * is_current_settings_tab
	 *
	 * @return boolean
	 */
	public function is_current_settings_tab() {

		if ( 'uo-recipe' !== automator_filter_input( 'post_type' ) ) {
			return false;
		}

		if ( 'uncanny-automator-config' !== automator_filter_input( 'page' ) ) {
			return false;
		}

		if ( 'premium-integrations' !== automator_filter_input( 'tab' ) ) {
			return false;
		}

		if ( automator_filter_input( 'integration' ) !== $this->settings_tab ) {
			return false;
		}

		return true;
	}

	/**
	 * capture_oauth_tokens
	 *
	 * @return void
	 */
	public function capture_oauth_tokens() {

		if ( ! $this->is_current_settings_tab() ) {
			return;
		}

		$automator_message = automator_filter_input( 'automator_api_message' );

		if ( empty( $automator_message ) ) {
			return;
		}

		$nonce = wp_create_nonce( self::NONCE );

		$token = (array) Automator_Helpers_Recipe::automator_api_decode_message( $automator_message, $nonce );

		$connect = $this->store_token( $token );

		wp_safe_redirect(
			add_query_arg(
				array(
					'connect' => $connect,
				),
				$this->get_settings_page_url()
			)
		);

		die;
	}

	/**
	 * store_token
	 *
	 * @param mixed $token
	 *
	 * @return int
	 */
	public function store_token( $token ) {

		if ( ! $token ) {
			return 2;
		}

		$account = $this->get_account( $token );

		if ( ! $account ) {
			return 2;
		}

		$client = array(
			'token'   => $token,
			'account' => $account,
		);

		update_option( self::TOKEN_OPTION, $client );

		return 1;
	}

	/**
	 * get_account
	 *
	 * @return array
	 */
	public function get_account( $token ) {

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => array(
				'action' => 'account_info',
				'client' => wp_json_encode( array( 'token' => $token ) ),
			),
		);

		$response = Api_Server::api_call( $params );

		if ( empty( $response['data']['accounts'] ) ) {
			return false;
		}

		$account = array_shift( $response['data']['accounts'] );

		return $account;
	}

	/**
	 * disconnect
	 *
	 * @return void
	 */
	public function disconnect() {

		if ( wp_verify_nonce( filter_input( INPUT_GET, 'nonce', FILTER_UNSAFE_RAW ), self::NONCE ) ) {

			delete_option( self::TOKEN_OPTION );
			delete_transient( self::ACCOUNT_TRANSIENT );
		}

		wp_safe_redirect( $this->get_settings_page_url() );

		exit;

	}

	/**
	 * api_request
	 *
	 * @param mixed $body
	 * @param mixed $action_data
	 *
	 * @return mixed
	 */
	public function api_request( $body, $action_data = null ) {

		$body['client'] = wp_json_encode( $this->get_client() );

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => $body,
			'action'   => $action_data,
		);

		$response = Api_Server::api_call( $params );

		return $response;
	}

	/**
	 * default_fields
	 *
	 * @return array
	 */
	public function default_fields() {

		$default_fields = array(
			'First name'                                 => 'first_name',
			'Last name'                                  => 'last_name',
			'Address line 1'                             => 'address1',
			'Address line 2'                             => 'address2',
			'City'                                       => 'city',
			'State'                                      => 'state',
			'Zip'                                        => 'zip',
			'Country'                                    => 'country',
			'Phone'                                      => 'phone',
			'SMS number'                                 => 'sms_number',
			'SMS consent (boolean)'                      => 'sms_consent',
			'Custom user ID'                             => 'user_id',
			'Time zone (in Olson format)'                => 'time_zone',
			'Lifetime value (in cents)'                  => 'lifetime_value',
			'IP address (E.g. "111.111.111.11")'         => 'ip_address',
			'Add tags (comma separated)'                 => 'tags',
			'Remove tags (comma separated)'              => 'remove_tags',
			'Prospect (boolean)'                         => 'prospect',
			'Base lead score (integer)'                  => 'base_lead_score',
			'EU consent ("granted" or "denied")'         => 'eu_consent',
			'EU consent message'                         => 'eu_consent_message',
			'Status (either "active" or "unsubscribed")' => 'status',
			'Initial status (either "active" or "unsubscribed")' => 'initial_status',
		);

		return apply_filters( 'automator_drip_default_subscriber_fields', $default_fields );
	}

	/**
	 * get_fields_options
	 *
	 * @return array
	 */
	public function get_fields_options() {

		$custom_fields = $this->get_custom_fields();

		$all_fields = array_merge( $this->default_fields(), $custom_fields );

		return $this->fields_as_options( $all_fields );
	}

	/**
	 * fields_as_options
	 *
	 * @param array $fields
	 *
	 * @return array
	 */
	public function fields_as_options( $fields ) {

		$options = array();

		foreach ( $fields as $field_name => $field_value ) {
			$options[] = array(
				'value' => $field_value,
				'text'  => $field_name,
			);
		}

		return $options;
	}

	/**
	 * get_custom_fields
	 *
	 * @param mixed $exclude
	 *
	 * @return array
	 */
	public function get_custom_fields( $exclude = array() ) {

		$custom_fields = array();

		$request_params = array(
			'action' => 'custom_fields',
		);

		try {

			$response = $this->api_request( $request_params );

			if ( empty( $response['data']['custom_field_identifiers'] ) ) {
				return $custom_fields;
			}

			$custom_fields = $this->format_custom_fields( $response['data']['custom_field_identifiers'] );

		} catch ( \Exception $e ) {
			return $custom_fields;
		}

		return $custom_fields;
	}

	/**
	 * format_custom_fields
	 *
	 * @param array $fields
	 *
	 * @return array
	 */
	public function format_custom_fields( $fields ) {

		$custom_fields = array();

		$fields_to_exclude = array(
			'first_name',
			'last_name',
		);

		foreach ( $fields as $key => $field ) {

			if ( in_array( $field, $fields_to_exclude, true ) ) {
				unset( $fields[ $key ] );
				continue;
			}

			$custom_fields[ $field . ' (custom field)' ] = $field;
		}

		return $custom_fields;
	}

	/**
	 * create_subscriber
	 *
	 * @param string $email
	 * @param array $fields
	 * @param array $action_data
	 *
	 * @return array
	 */
	public function create_subscriber( $email, $fields, $action_data ) {

		$subscriber = $this->subscriber_fields( $email, $fields );

		$request_params = array(
			'action'     => 'create_subscriber',
			'subscriber' => wp_json_encode( $subscriber ),
		);

		$response = $this->api_request( $request_params, $action_data );

		$this->check_response_for_errors( $response );

		return $response;
	}

	/**
	 * subscriber_fields
	 *
	 * @param string $email
	 * @param array $fields
	 *
	 * @return array
	 */
	public function subscriber_fields( $email, $fields ) {

		$subscriber = array();
		if ( ! empty( $fields ) ) {
			$default_fields = $this->default_fields();

			foreach ( $fields as $field ) {

				if ( empty( $field['FIELD_NAME'] ) || ! isset( $field['FIELD_VALUE'] ) ) {
					continue;
				}

				if ( ! in_array( $field['FIELD_NAME'], $default_fields, true ) ) {
					$subscriber['custom_fields'][ $field['FIELD_NAME'] ] = $field['FIELD_VALUE'];
					continue;
				}

				$subscriber[ $field['FIELD_NAME'] ] = $field['FIELD_VALUE'];
			}

			$subscriber = $this->type_cast_subscriber_fields( $subscriber );
		}

		$subscriber['email'] = $email;

		return apply_filters( 'automator_drip_subscriber_fields', $subscriber, $email, $fields );
	}

	/**
	 * type_cast_subscriber_fields
	 *
	 * @param array $subscriber
	 *
	 * @return array
	 */
	public function type_cast_subscriber_fields( $subscriber ) {

		$formatted_subscriber = $subscriber;

		foreach ( $formatted_subscriber as $field_name => &$field_value ) {
			switch ( $field_name ) {
				case 'sms_consent':
				case 'prospect':
					$field_value = filter_var( $field_value, FILTER_VALIDATE_BOOLEAN );
					break;
				case 'tags':
				case 'remove_tags':
					$field_value = explode( ',', $field_value );
					$field_value = array_map( 'trim', $field_value );
					break;
				case 'base_lead_score':
					$field_value = intval( $field_value );
					break;
			}
		}

		return $formatted_subscriber;
	}

	/**
	 * check_response_for_errors
	 *
	 * @param mixed $response
	 *
	 * @return void
	 */
	public function check_response_for_errors( $response ) {

		if ( empty( $response['data']['errors'] ) ) {
			return;
		}

		$error_message = '';

		foreach ( $response['data']['errors'] as $error ) {
			$error_message .= $error['code'] . ': ' . $error['message'] . "\r\n";
		}

		throw new \Exception( $error_message, $response['statusCode'] );
	}

	/**
	 * get_tags_options
	 *
	 * @return array
	 */
	public function get_tags_options() {

		$options = array();

		$tags = $this->get_tags();

		foreach ( $tags as $tag ) {
			$options[] = array(
				'text'  => $tag,
				'value' => $tag,
			);
		}

		return $options;
	}

	/**
	 * get_tags
	 *
	 * @return array
	 */
	public function get_tags() {

		$request_params = array(
			'action' => 'get_tags',
		);

		try {
			$response = $this->api_request( $request_params );
		} catch ( \Exception $e ) {
			return array( $e->getMessage() );
		}

		return $response['data']['tags'];
	}

	/**
	 * add_tag
	 *
	 * @param string $email
	 * @param string $tag
	 *
	 * @return mixed
	 */
	public function add_tag( $email, $tag ) {

		$request_params = array(
			'action' => 'add_tag',
			'email'  => $email,
			'tag'    => $tag,
		);

		$response = $this->api_request( $request_params );

		$this->check_response_for_errors( $response );

		return $response;
	}

	/**
	 * remove_tag
	 *
	 * @param string $email
	 * @param string $tag
	 *
	 * @return mixed
	 */
	public function remove_tag( $email, $tag ) {

		$request_params = array(
			'action' => 'remove_tag',
			'email'  => $email,
			'tag'    => $tag,
		);

		$response = $this->api_request( $request_params );

		$this->check_response_for_errors( $response );

		return $response;
	}

	/**
	 * unsubscribe_all
	 *
	 * @param string $email
	 *
	 * @return mixed
	 */
	public function unsubscribe_all( $email ) {

		$request_params = array(
			'action' => 'unsubscribe_all',
			'email'  => $email,
		);

		$response = $this->api_request( $request_params );

		$this->check_response_for_errors( $response );

		return $response;
	}

	/**
	 * delete_subscriber
	 *
	 * @param string $email
	 *
	 * @return mixed
	 */
	public function delete_subscriber( $email ) {

		$request_params = array(
			'action' => 'delete_subscriber',
			'email'  => $email,
		);

		$response = $this->api_request( $request_params );

		$this->check_response_for_errors( $response );

		return $response;
	}

	/**
	 * get_campaigns_options
	 *
	 * @return array
	 */
	public function get_campaigns_options() {

		$options = array();

		$campaigns = $this->get_campaigns();

		foreach ( $campaigns as $campaign ) {
			$options[] = array(
				'text'  => $campaign['name'],
				'value' => $campaign['id'],
			);
		}

		return $options;
	}

	/**
	 * get_campaigns
	 *
	 * @return array
	 */
	public function get_campaigns() {

		$request_params = array(
			'action' => 'get_campaigns',
		);

		try {
			$response = $this->api_request( $request_params );
		} catch ( \Exception $e ) {
			return array( $e->getMessage() );
		}

		return $response['data']['campaigns'];
	}

	/**
	 * remove_from_campaign
	 *
	 * @param string $email
	 * @param string $campaign
	 *
	 * @return mixed
	 */
	public function remove_from_campaign( $email, $campaign_id ) {

		$request_params = array(
			'action'      => 'remove_from_campaign',
			'email'       => $email,
			'campaign_id' => $campaign_id,
		);

		$response = $this->api_request( $request_params );

		$this->check_response_for_errors( $response );

		return $response;
	}

	/**
	 * subscribe_to_campaign
	 *
	 * @param string $email
	 * @param string $campaign
	 *
	 * @return mixed
	 */
	public function subscribe_to_campaign( $email, $campaign_id ) {

		$request_params = array(
			'action'      => 'subscribe_to_campaign',
			'email'       => $email,
			'campaign_id' => $campaign_id,
		);

		$response = $this->api_request( $request_params );

		$this->check_response_for_errors( $response );

		return $response;
	}
}
