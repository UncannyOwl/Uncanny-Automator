<?php
namespace Uncanny_Automator\Integrations\Get_Response;

use Uncanny_Automator\Api_Server;
use WP_REST_Response;

/**
 * Class Get_Response_Helpers
 *
 * @package Uncanny_Automator
 */
class Get_Response_Helpers {

	/**
	 * The helpers options object.
	 *
	 * @var string|object
	 */
	public $options = '';

	/**
	 * Settings tab id
	 *
	 * @var string|object
	 */
	public $settings_tab = 'getresponse';

	/**
	 * The account details.
	 *
	 * @var array
	 */
	private $account_details = array(
		'id'     => '',
		'email'  => '',
		'status' => '',
		'error'  => '',
	);

	/**
	 * The wp_options table key for selecting the integration options.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'automator_getresponse_api_key';

	/**
	 * The wp_options table key for selecting the integration account details.
	 *
	 * @var string
	 */
	const ACCOUNT_KEY = 'automator_getresponse_account';

	/**
	 * The public API edge.
	 *
	 * @var string
	 */
	const API_ENDPOINT = 'v2/getresponse';

	/**
	 * The public API edge.
	 *
	 * @var string
	 */
	const WEBHOOK = '/getresponse/';

	/**
	 * The nonce.
	 *
	 * @var string
	 */
	const NONCE = 'automator_getresponse_api_authentication';

	/**
	 * Get_Response_Helpers constructor.
	 */
	public function __construct() {
	}

	/**
	 * Get settings page url.
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
	 * Get API Key.
	 *
	 * @return string
	 */
	public function get_api_key() {
		return automator_get_option( self::OPTION_KEY, '' );
	}

	/**
	 * Integration status.
	 *
	 * @return string
	 */
	public function integration_status() {
		$account = $this->get_saved_account_details();
		return $account['status'];
	}

	/**
	 * Get Account Details.
	 *
	 * @return array
	 */
	public function get_saved_account_details() {

		// No API key set return defaults.
		if ( empty( $this->get_api_key() ) ) {
			return $this->account_details;
		}

		return automator_get_option( self::ACCOUNT_KEY, $this->account_details );
	}

	/**
	 * Create and retrieve a disconnect url for GetResponse Integration.
	 *
	 * @return string The disconnect uri.
	 */
	public function get_disconnect_url() {

		return add_query_arg(
			array(
				'action' => 'automator_getresponse_disconnect_account',
				'nonce'  => wp_create_nonce( self::NONCE ),
			),
			admin_url( 'admin-ajax.php' )
		);

	}

	/**
	 * Disconnect GetResponse integration.
	 *
	 * @return void
	 */
	public function disconnect() {

		if ( wp_verify_nonce( filter_input( INPUT_GET, 'nonce', FILTER_UNSAFE_RAW ), self::NONCE ) ) {

			$this->remove_credentials();
		}

		wp_safe_redirect( $this->get_settings_page_url() );

		exit;
	}

	/**
	 * Remove credentials.
	 *
	 * @return void
	 */
	public function remove_credentials() {
		// Remove the stored options.
		delete_option( self::OPTION_KEY );
		delete_option( self::ACCOUNT_KEY );

		// Remove any stored transients.
		delete_transient( 'automator_getresponse_contact/fields' );
		delete_transient( 'automator_getresponse_contact/lists' );
	}

	/**
	 * Get account - validates the connection, saves and returns the account info
	 *
	 * @return array
	 */
	public function get_account() {

		// Set defaults.
		$account = $this->account_details;

		// Validate api key.
		$api_key = $this->get_api_key();
		if ( empty( $api_key ) ) {
			return $account;
		}

		// Get account.
		try {
			$response = $this->api_request( 'get_account', null, null, false );
		} catch ( \Exception $e ) {
			$error            = $e->getMessage();
			$account['error'] = ! empty( $error ) ? $error : _x( 'GetResponse API error', 'GetResponse', 'uncanny-automator' );
			update_option( self::ACCOUNT_KEY, $account );

			return $account;
		}

		// Success.
		if ( ! empty( $response['data']['accountId'] ) ) {
			$account['id']     = $response['data']['accountId'];
			$account['email']  = $response['data']['email'];
			$account['status'] = 'success';
		} else {
			$account['status'] = '';
			$account['error']  = _x( 'GetResponse API error', 'GetResponse', 'uncanny-automator' );
		}

		// Check for invalid key.
		if ( ! empty( $response['data']['httpStatus'] ) ) {
			// [code] => 1014 && [httpStatus] => 401
			$account['status'] = '';
			$account['error']  = ! empty( $response['data']['message'] )
				? $response['data']['message']
				: _x( 'Invalid API key', 'GetResponse', 'uncanny-automator' );
		}

		update_option( self::ACCOUNT_KEY, $account );

		return $account;
	}

	/**
	 * Get custom contact fields.
	 *
	 * @return array
	 */
	public function get_contact_fields() {

		$transient = 'automator_getresponse_contact/fields';
		$fields    = get_transient( $transient );

		if ( ! empty( $fields ) ) {
			return $fields;
		}

		$fields = array();

		try {
			$response = $this->api_request( 'get_contact_fields' );
		} catch ( \Exception $e ) {
			automator_log( $e->getMessage(), 'GetResponse::get_contact_fields Error', true, 'getresponse' );
			return false;
		}

		$data = isset( $response['data'] ) && isset( $response['data']['fields'] ) ? $response['data']['fields'] : array();

		// Mapping arrays.
		$multiple_types  = array( 'multi_select', 'checkbox' );
		$select_types    = array( 'single_select', 'multi_select', 'radio', 'checkbox' );
		$convert_formats = array( 'date', 'datetime', 'number', 'phone', 'url' );

		if ( ! empty( $data ) ) {
			foreach ( $data as $field ) {

				$name        = $field['name'];
				$format      = $field['format']; //text, textarea, single_select, multi_select, radio, checkbox
				$field_type  = $field['type']; // text, textarea, date, datetime, country, currency, checkbox, multi_select, number, phone, url, gender
				$options     = false;
				$multiple    = false;
				$is_datetime = false;

				// Map all option type fields to select.
				$input_type = in_array( $format, $select_types, true ) ? 'select' : $format;

				// Adjust text fields to supported types.
				if ( 'text' === $input_type ) {
					$input_type = in_array( $field_type, $convert_formats, true ) ? $field_type : 'text';
					$input_type = 'number' === $field_type ? 'int' : $input_type;
				}

				// Set options for select fields.
				$fields[ $field['customFieldId'] ] = array(
					'name'          => $name,
					'type'          => $input_type,
					'original_type' => $field_type,
					'options'       => 'select' === $input_type && ! empty( $field['values'] ) ? $field['values'] : false,
					'multiple'      => in_array( $format, $multiple_types, true ),
				);
			}
		}

		uasort(
			$fields,
			function( $a, $b ) {
				return strcmp( $a['name'], $b['name'] );
			}
		);

		set_transient( $transient, $fields, DAY_IN_SECONDS );

		return $fields;
	}

	/**
	 * Get custom field options.
	 *
	 * @return array
	 */
	public function get_custom_field_options() {

		$fields  = $this->get_contact_fields();
		$options = array();
		if ( ! empty( $fields ) ) {
			foreach ( $fields as $field_id => $field ) {
				$options[] = array(
					'value' => $field_id,
					'text'  => $field['name'],
				);
			}
		}

		return $options;
	}

	/**
	 * Get contact lists ( Campaigns )
	 *
	 * @return array
	 */
	public function get_lists() {

		$transient = 'automator_getresponse_contact/lists';
		$lists     = get_transient( $transient );

		if ( ! empty( $lists ) ) {
			return $lists;
		}

		$lists = array();

		try {
			$response = $this->api_request( 'get_lists' );
		} catch ( \Exception $e ) {
			automator_log( $e->getMessage(), 'GetResponse::get_lists Error', true, 'getresponse' );
			return $lists;
		}

		$data = isset( $response['data'] ) && isset( $response['data']['lists'] ) ? $response['data']['lists'] : array();
		if ( ! empty( $data ) ) {
			foreach ( $data as $list ) {
				$lists[] = array(
					'value' => $list['campaignId'],
					'text'  => $list['name'],
				);
			}
		}

		set_transient( $transient, $lists, DAY_IN_SECONDS );
		return $lists;
	}

	/**
	 * Ajax get list options.
	 *
	 * @return json
	 */
	public function ajax_get_list_options() {

		Automator()->utilities->ajax_auth_check();

		wp_send_json( $this->get_lists() );

		die();
	}

	/**
	 * Ajax sync transient data.
	 *
	 * @return json
	 */
	public function ajax_sync_transient_data() {

		if ( ! wp_verify_nonce( automator_filter_input( 'nonce', INPUT_POST ), 'uncanny_automator' ) ) {
			wp_send_json_error( array( 'message' => _x( 'Invalid request', 'GetResponse', 'uncanny-automator' ) ) );
		}

		$key = automator_filter_input( 'key', INPUT_POST );
		if ( ! $key || ! in_array( $key, array( 'contact/lists', 'contact/fields' ), true ) ) {
			wp_send_json_error( array( 'message' => _x( 'Invalid key', 'GetResponse', 'uncanny-automator' ) ) );
		}

		// Delete existing transient.
		delete_transient( "automator_getresponse_{$key}" );

		// Get selected options.
		switch ( $key ) {
			case 'contact/lists':
				$options = $this->get_lists();
				break;
			case 'contact/fields':
				$options = $this->get_contact_fields();
				break;
		}

		if ( empty( $options ) ) {
			wp_send_json_error( array( 'message' => _x( 'No data returned from the API', 'GetResponse', 'uncanny-automator' ) ) );
		}

		// Ensure everything is set with a slight delay.
		sleep( 1 );

		// Send updated count.
		wp_send_json_success(
			array(
				'count' => count( $options ),
			)
		);
	}

	/**
	 * Make API request.
	 *
	 * @param  string $action
	 * @param  mixed $body
	 * @param  mixed $action_data
	 * @param  bool $check_for_errors
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function api_request( $action, $body = null, $action_data = null, $check_for_errors = true ) {

		$body            = is_array( $body ) ? $body : array();
		$body['action']  = $action;
		$body['api-key'] = $this->get_api_key();

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => $body,
			'action'   => $action_data,
		);

		$response = Api_Server::api_call( $params );

		if ( $check_for_errors ) {
			$this->check_for_errors( $response );
		}

		return $response;
	}

	/**
	 * Check response for errors.
	 *
	 * @param  mixed $response
	 *
	 * @return void
	 */
	public function check_for_errors( $response ) {

		// Check for error.
		if ( ! empty( $response['data']['error'] ) ) {
			throw new \Exception( $response['data']['error'], 400 );
		}

		if ( $response['statusCode'] >= 400 ) {

			// Check for message.
			$message = isset( $response['data']['message'] ) ? $response['data']['message'] : false;
			if ( ! empty( $message ) ) {

				// Check for context message and append.
				if ( isset( $response['data']['context'] ) ) {
					if ( ! empty( $response['data']['context'] ) && is_array( $response['data']['context'] ) ) {
						foreach ( $response['data']['context'] as $item ) {
							$item = is_string( $item ) ? json_decode( $item, true ) : $item;
							if ( is_array( $item ) ) {
								if ( isset( $item['message'] ) ) {
									$message .= ' ' . $item['message'];
								} elseif ( isset( $item['errorDescription'] ) ) {
									$message .= ' ' . $item['errorDescription'];
								}
							}
						}
					}

					throw new \Exception( $message, 400 );
				}
			}

			switch ( $response['statusCode'] ) {
				case 401:
					throw new \Exception( _x( 'Invalid API key', 'GetResponse', 'uncanny-automator' ), 400 );
				case 429:
					$message = _x( 'The throttling limit has been reached', 'GetResponse', 'uncanny-automator' );
					throw new \Exception( $message, 400 );
				default:
					$message = _x( 'GetResponse API error', 'GetResponse', 'uncanny-automator' );
					throw new \Exception( $message, 400 );
			}
		}
	}

		/**
		 * Get email from parsed.
		 *
		 * @param  array $parsed
		 * @param  string $meta_key
		 * @return string
		 */
	public function get_email_from_parsed( $parsed, $meta_key ) {

		if ( ! isset( $parsed[ $meta_key ] ) ) {
			throw new \Exception( esc_html_x( 'Missing email', 'GetResponse', 'uncanny-automator' ) );
		}

		$email = sanitize_text_field( $parsed[ $meta_key ] );

		if ( ! $email || ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new \Exception( esc_html_x( 'Invalid email', 'GetResponse', 'uncanny-automator' ) );
		}

		return $email;
	}

		/**
		 * Get list_id from parsed.
		 *
		 * @param  array $parsed
		 * @param  string $meta_key
		 * @return mixed
		 */
	public function get_list_id_from_parsed( $parsed, $meta_key ) {

		if ( ! isset( $parsed[ $meta_key ] ) ) {
			throw new \Exception( esc_html_x( 'List is required', 'GetResponse', 'uncanny-automator' ) );
		}

		$list_id = sanitize_text_field( $parsed[ $meta_key ] );

		if ( ! $list_id ) {
			throw new \Exception( esc_html_x( 'List is required', 'GetResponse', 'uncanny-automator' ) );
		}

		return $list_id;
	}

		/**
		 * Get class const.
		 *
		 * @param  string $const
		 *
		 * @return string
		 */
	public function get_const( $const ) {
		return constant( 'self::' . $const );
	}

}
