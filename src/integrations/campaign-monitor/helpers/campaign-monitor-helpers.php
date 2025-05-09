<?php // phpcs:ignoreFile PHPCompatibility.Operators.NewOperators.t_coalesceFound
// phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Campaign_Monitor;

use Exception;
use WP_Error;
use Uncanny_Automator\Api_Server;
use Uncanny_Automator\Automator_Helpers_Recipe;

/**
 * Class Campaign_Monitor_Helpers
 *
 * @package Uncanny_Automator
 */
class Campaign_Monitor_Helpers {

	/**
	 * Settings tab id
	 *
	 * @var string|object
	 */
	public $settings_tab = 'campaignmonitor';

	/**
	 * The public API edge.
	 *
	 * @var string
	 */
	const API_ENDPOINT = 'v2/campaignmonitor';

	/**
	 * The nonce.
	 *
	 * @var string
	 */
	const NONCE = 'automator_campaign_monitor_api_authentication';

	/**
	 * Credentials wp_options key.
	 *
	 * @var string
	 */
	const CREDENTIALS = 'automator_campaign_monitor_credentials';

	/**
	 * Account wp_options key.
	 *
	 * @var string
	 */
	const ACCOUNT = 'automator_campaign_monitor_account';

	/**
	 * Client field action meta key.
	 *
	 * @var string
	 */
	const ACTION_CLIENT_META_KEY = 'CAMPAIGN_MONITOR_CLIENT';

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
	 * Create and retrieve a disconnect url for Campaign_Monitor Integration.
	 *
	 * @return string The disconnect uri.
	 */
	public static function get_disconnect_url() {

		return add_query_arg(
			array(
				'action' => 'automator_campaign_monitor_disconnect_account',
				'nonce'  => wp_create_nonce( self::NONCE ),
			),
			admin_url( 'admin-ajax.php' )
		);

	}

	/**
	 * Authentication handler.
	 *
	 * @return never
	 */
	public function authenticate() {

		if ( ! wp_verify_nonce( automator_filter_input( 'nonce' ), self::NONCE ) ) {
			wp_die( 'Invalid nonce.' );
		}

		// Validate user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->redirect_with_error( esc_html_x( 'You do not have the required permissions to perform this action.', 'Campaign Monitor', 'uncanny-automator' ) );
		}

		// Validate request.
		$credentials = Automator_Helpers_Recipe::automator_api_decode_message( 
			automator_filter_input( 'automator_api_message' ), 
			wp_create_nonce( self::NONCE )
		);

		// Handle errors.
		if ( false === $credentials ) {
			$this->redirect_with_error( esc_html_x( 'Unable to decode credentials with the secret provided. Please refresh and retry.', 'Campaign Monitor', 'uncanny-automator' ) );
		}

		if ( empty( $credentials['data'] ) ) {
			$this->redirect_with_error( esc_html_x( 'Authentication failed. Please refresh and retry.', 'Campaign Monitor', 'uncanny-automator' ) );
		}

		$this->save_credentials( $credentials['data'] );

		// Get / set account details.
		$account = $this->get_account_details();
		if ( is_wp_error( $account ) ) {
			$this->redirect_with_error( esc_html( $account->get_error_message() ) );
		}

		// Redirect to settings page. Flag as connected with success=yes.
		wp_safe_redirect( $this->get_settings_page_url() . '&success=yes' );
		die;
	}

	/**
	 * Redirect with error message.
	 *
	 * @param  string $message
	 *
	 * @return void
	 */
	private function redirect_with_error( $message ) {
		wp_safe_redirect( $this->get_settings_page_url() . '&error_message=' . urlencode( $message ) );
		die;
	}

	/**
	 * Save the credentials to the options table.
	 *
	 * @param  array $credentials
	 *
	 * @return void
	 */
	public function save_credentials( $credentials ) {

		// Calculate the expiration timestamp minus a day.
		$expires_in                = absint( $credentials['expires_in'] ) - 86400;
		$credentials['expires_on'] = time() + $expires_in;

		automator_update_option( self::CREDENTIALS, $credentials, false );
	}

	/**
	 * Retrieve the credentials from the options table.
	 *
	 * @return array
	 */
	public static function get_credentials() {
		return (array) automator_get_option( self::CREDENTIALS, array() );
	}

	/**
	 * Integration status.
	 *
	 * @return string
	 */
	public function integration_status() {
		$credentials = self::get_credentials();
		return isset( $credentials['access_token'] ) ? 'success' : '';
	}

	/**
	 * Get / Set account details.
	 *
	 * @param  bool $return_error
	 *
	 * @return mixed - Details of connected account || WP_Error
	 */
	public function get_account_details( $return_error = true ) {

		$account = automator_get_option( self::ACCOUNT, array() );

		if ( empty( $account ) ) {
			try {
				$response = $this->api_request( 'get_primary_contact' );
				$data     = $response['data'] ?? array();
				$primary  = $data['EmailAddress'] ?? false;

				// Get / set clients.
				$clients = $this->get_clients( true );
				$type    = count( $clients ) > 1 ? 'agency' : 'client';

				$account = array(
					'type'   => $type,
					'email'  => $primary,
					'client' => 'client' === $type ? $clients[0] : null,
				);

				// Save account details.
				automator_update_option( self::ACCOUNT, $account, false );

				if ( 'client' === $type ) {
					// Maybe update the hidden client field for existing actions.
					$this->maybe_update_actions_hidden_client_field_meta( $clients[0]['value'] );
				}

			}
			catch ( Exception $e ) {
				if ( $return_error ) {
					return new WP_Error( 'campaign_monitor_get_account_details_error', $e->getMessage() );
				}
				// Return default array for checks.
				return array(
					'type'   => 'client',
					'client' => array( 'value' => '' ),
				);
			}
		}

		return $account;
	}

	/**
	 * Get Clients.
	 *
	 * @return array
	 */
	public function get_clients( $refresh = false ) {

		// Get Clients from transient.
		$transient = "automator_campaign_monitor_clients";
		$clients   = get_transient( $transient );

		if ( empty( $clients ) || $refresh ) {
			$clients  = array();
			try {
				$response = $this->api_request( 'get_clients' );
				$data     = $response['data'] ?? array();
				foreach ( $data as $client ) {
					$clients[] = array(
						'value'    => $client['ClientID'],
						'text'     => $client['Name'],
					);
				}
				// Set transient.
				set_transient( $transient, $clients, DAY_IN_SECONDS );
			}
			catch ( Exception $e ) {
				return array();
			}

		}

		return $clients;
	}

	/**
	 * Get Clients Ajax handler.
	 *
	 * @return string - JSON response.
	 */
	public function get_clients_ajax() {

		Automator()->utilities->verify_nonce();

		wp_send_json( array(
			'success' => true,
			'options' => $this->get_clients( $this->is_ajax_refresh() ),
		) );
	}

	/**
	 * Disconnect Campaign_Monitor integration.
	 *
	 * @return void
	 */
	public function disconnect() {

		// Validate user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Error 403: Insufficient permissions.' );
		}

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

		// Delete options.
		automator_delete_option( self::CREDENTIALS );
		automator_delete_option( self::ACCOUNT );

		// Query all transients.
		global $wpdb;
		$table = "{$wpdb->prefix}uap_options";
		$transients = $wpdb->get_col( $wpdb->prepare(
			"SELECT option_name FROM {$table} WHERE option_name LIKE %s",
			$wpdb->esc_like( '_transient_automator_campaign_monitor_' ) . '%'
		) );

		// Delete all transients.
		if ( ! empty( $transients ) && is_array( $transients ) ) {
			foreach ( $transients as $transient ) {
				delete_transient( str_replace( '_transient_', '', $transient ) );
			}
		}
	}

	/**
	 * Make API request.
	 *
	 * @param  string $action
	 * @param  mixed $body
	 * @param  mixed $action_data
	 * @param  bool $check_for_errors
	 * @param  bool $refresh
	 *
	 * @return array
	 * @throws Exception
	 */
	public function api_request( $action, $body = null, $action_data = null, $check_for_errors = true, $refresh = true ) {

		// Only refresh the access token if the request is not coming from refresh token itself.
		if ( true === $refresh && $this->token_requires_refresh() ) {
			$this->refresh_access_token();
		}

		$body           = is_array( $body ) ? $body : array();
		$body['action'] = $action;

		// If action is not a refresh request add the access token to the request body.
		if ( 'refresh_access_token' !== $action ) {
			$credentials          = $this->get_credentials();
			$access_token         = $credentials['access_token'] ?? '';
			$body['access_token'] = $access_token;
		}

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
	 * Check if token needs to be refreshed.
	 *
	 * @return bool
	 */
	public function token_requires_refresh() {

		$credentials = $this->get_credentials();

		if ( empty( $credentials ) ) {
			throw new Exception( 'Your Campaign Monitor integration is currently disconnected. ' . $this->common_reconnect_message(), 500 );
		}

		$expires_on = $credentials['expires_on'] ?? 0;

		if ( empty( $expires_on ) ) {
			throw new Exception( 'Invalid authentication date detected. ' . $this->common_reconnect_message(), 500 );
		}

		return time() >= $expires_on;
	}

	/**
	 * Refresh the access token.
	 *
	 * @return void
	 * @throws Exception
	 */
	public function refresh_access_token() {

		$credentials = $this->get_credentials();
		$token       = $credentials['refresh_token'] ?? '';

		if ( empty( $token ) ) {
			throw new Exception( 'Invalid refresh token detected. ' . $this->common_reconnect_message(), 500 );
		}

		$response = $this->api_request(
			'refresh_access_token',
			array(
				'refresh_token' => $token,
			),
			null, // No action data.
			true, // Check for errors.
			false // Skip refresh check.
		);

		if ( empty( $response['data'] ) ) {
			throw new Exception( 'Refresh access token endpoint returned empty credentials. ' . $this->common_reconnect_message(), 400 );
		}

		$this->save_credentials( $response['data'] );
	}

	/**
	 * Common reconnect message.
	 *
	 * @return string
	 */
	public function common_reconnect_message() {
		return _x( 'Please navigate to the settings page to establish a connection with your Campaign Monitor account.', 'Campaign Monitor', 'uncanny-automator' );
	}

	/**
	 * Check response for errors.
	 *
	 * @param  mixed $response
	 *
	 * @return void
	 * @throws Exception
	 */
	public function check_for_errors( $response ) {

		if ( 201 !== $response['statusCode'] && 200 !== $response['statusCode'] ) {
			$message = _x( 'Campaign Monitor API Error', 'Campaign Monitor', 'uncanny-automator' );
			if ( isset( $response['data']['Message'] ) ) {
				$code    = $response['data']['Code'] ?? '';
				$message = $response['data']['Message'] . ( ! empty( $code ) ? " ({$code})" : '' );
			}
			throw new Exception( esc_html( $message ), absint( $response['statusCode'] ) );
		}

	}

	/**
	 * Get Authorization URL for OAuth.
	 *
	 * @return string
	 */
	public static function get_authorization_url() {
		return add_query_arg(
			array(
				'action'     => 'authorize',
				'user_url'   => rawurlencode( get_bloginfo( 'url' ) ),
				'nonce'      => wp_create_nonce( self::NONCE ),
				'plugin_ver' => AUTOMATOR_PLUGIN_VERSION,
			),
			AUTOMATOR_API_URL . 'v2/campaignmonitor'
		);
	}

	/**
	 * Get Client Field.
	 *
	 * @return array
	 */
	public function get_client_field() {

		$account = $this->get_account_details( false );
		$field   = array(
			'option_code' => self::ACTION_CLIENT_META_KEY,
			'label'       => _x( 'Client', 'Campaign Monitor', 'uncanny-automator' ),
		);

		// Client accounts have only one client.
		if ( 'client' === $account['type'] ) {
			$field['input_type'] = 'text';
			$field['default']    = $account['client']['value'];
			$field['read_only']  = true;
			$field['is_hidden']  = true;
			return $field;
		}

		// Agency accounts have multiple clients.
		$field['input_type'] = 'select';
		$field['options']    = array();
		$field['required']   = true;
		$field['ajax']       = array(
			'endpoint' => 'automator_campaign_monitor_get_clients',
			'event'    => 'on_load',
		);

		return $field;
	}

	/**
	 * Get Client List Field.
	 *
	 * @return array
	 */
	public function get_client_list_field() {

		$account   = $this->get_account_details( false );
		$is_client = 'client' === $account['type'];
		$field     = array(
			'option_code'            => 'LIST',
			'label'                  => _x( 'List', 'Campaign Monitor', 'uncanny-automator' ),
			'input_type'             => 'select',
			'options'                => $is_client ? $this->get_lists( $account['client']['value'] ) : array(),
			'required'               => true,
			'supports_custom_value'  => false,
			'show_label_in_sentence' => true,
			'ajax'                   => array(
				'endpoint' => 'automator_campaign_monitor_get_lists',
				'event'    => 'parent_fields_change',
				'listen_fields' => array( self::ACTION_CLIENT_META_KEY ),
			)
		);

		return $field;
	}

	/**
	 * Get Repeater Fields Config.
	 *
	 * @param  array $options
	 *
	 * @return array
	 */
	public function get_repeater_fields_config( $options = array() ) {

		return array(
			array(
				'input_type'  => 'select',
				'option_code' => 'FIELD',
				'label'       => _x( 'Field', 'Campaign Monitor', 'uncanny-automator' ),
				'options'     => $options,
				'required'    => true,
			),
			array(
				'input_type'      => 'text',
				'option_code'     => 'FIELD_VALUE',
				'label'           => _x( 'Value', 'Campaign Monitor', 'uncanny-automator' ),
				'supports_tokens' => true,
				'required'        => true,
			),
		);
	}

	/**
	 * Get Lists.
	 *
	 * @param  mixed $client_id
	 * @param  bool $refresh
	 *
	 * @return mixed - array || WP_Error
	 */
	public function get_lists( $client_id = null, $refresh = false ) {

		if ( empty( $client_id ) ) {
			$account   = $this->get_account_details( false );
			$client_id = $account['client']['value'] ?? null;
		}

		if ( empty( $client_id ) ) {
			return array();
		}

		$transient = "automator_campaign_monitor_lists_{$client_id}";
		$lists     = array();

		if ( ! $refresh ) {
			$lists = get_transient( $transient );
			if ( ! empty( $lists ) ) {
				return $lists;
			}
		}

		try {
			$response = $this->api_request( 'get_lists', array( 'client_id' => $client_id ) );
			$data     = $response['data'] ?? array();
			$lists    = array();
			foreach ( $data as $list ) {
				$lists[] = array(
					'value' => $list['ListID'],
					'text'  => $list['Name'],
				);
			}

			// Set transient.
			set_transient( $transient, $lists, DAY_IN_SECONDS );

			return $lists;

		} catch ( Exception $e ) {
			return new WP_Error( 'campaign_monitor_get_lists_error', $e->getMessage() );
		}
	}

	/**
	 * Get Lists Ajax handler.
	 *
	 * @return string - JSON response.
	 */
	public function get_lists_ajax() {

		Automator()->utilities->verify_nonce();
		$client_id = isset( $_POST['values'][ self::ACTION_CLIENT_META_KEY ] ) ? sanitize_text_field( wp_unslash( $_POST['values'][ self::ACTION_CLIENT_META_KEY ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$lists     = $this->get_lists( $client_id, $this->is_ajax_refresh() );

		if ( is_wp_error( $lists ) ) {
			wp_send_json( array(
				'success' => false,
				'error'   => $lists->get_error_message(),
			) );
		}

		wp_send_json( array(
			'success' => true,
			'options' => $lists,
		) );
	}

	/**
	 * Get Custom Fields.
	 *
	 * @param  mixed $list_id
	 * @param  bool $refresh
	 *
	 * @return mixed array || WP_Error
	 */
	public function get_custom_fields( $list_id = null, $refresh = false ) {

		if ( empty( $list_id ) ) {
			return array();
		}

		$transient = "automator_campaign_monitor_custom_fields_{$list_id}";
		$fields    = array();

		if ( ! $refresh ) {
			$fields = get_transient( $transient );
			if ( ! empty( $fields ) ) {
				return $fields;
			}
		}

		try {
			$response = $this->api_request( 'get_custom_fields', array( 'list_id' => $list_id ) );
			$data     = $response['data'] ?? array();

			if ( empty( $data ) ) {
				return array(
					array(
						'value' => '',
						'text'  => _x( 'No custom fields found', 'Campaign Monitor', 'uncanny-automator' ),
					),
				);
			}

			$types_map = array(
				'Text'            => 'text',
				'Number'          => 'number',
				'Date'            => 'date',
				'MultiSelectOne'  => 'select',
				'MultiSelectMany' => 'select',
			);

			foreach ( $data as $field ) {
				$fields[ $field['Key'] ] = array(
					'value'                    => $field['Key'],
					'text'                     => $field['FieldName'],
					'type'                     => $types_map[ $field['DataType'] ] ?? 'text',
					'options'                  => $field['FieldOptions'],
					'supports_multiple_values' => 'MultiSelectMany' === $field['DataType'],
				);
			}

			// Set transient.
			set_transient( $transient, $fields, DAY_IN_SECONDS );

			return $fields;

		} catch ( Exception $e ) {
			return new WP_Error( 'campaign_monitor_get_custom_fields_error', $e->getMessage() );
		}

	}

	/**
	 * Get Custom Fields Ajax handler.
	 *
	 * @return string - JSON response.
	 */
	public function get_custom_fields_repeater_ajax() {

		Automator()->utilities->verify_nonce();

		$list_id = isset( $_POST['values']['LIST'] ) ? sanitize_text_field( wp_unslash( $_POST['values']['LIST'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$fields  = $this->get_custom_fields( $list_id, $this->is_ajax_refresh() );

		if ( is_wp_error( $fields ) ) {
			wp_send_json( array(
				'success' => false,
				'error'   => $fields->get_error_message(),
			) );
		}

		// Format options.
		$options = array();
		foreach ( $fields as $field ) {
			$options[] = array(
				'value' => $field['value'],
				'text'  => $field['text'],
			);
		}

		// Prepare response.
		$response = array(
			'success' => true,
			'field_properties' => array(
				'fields' => $this->get_repeater_fields_config( $options )
			),
		);

		// Check if we need to clear out rows by comparing the current list id with the saved one.
		$item_post_id = automator_filter_input( 'item_id', INPUT_POST );
		$current_list = ! empty( absint( $item_post_id ) ) ? get_post_meta( $item_post_id, 'LIST', true ) : false;
		if ( (string) $current_list !== (string) $list_id ) {
			$response['rows'] = array();
		} else {
			// Get the current rows to avoid accidental data loss.
			$current_rows = ! empty( absint( $item_post_id ) ) ? get_post_meta( $item_post_id, 'CUSTOM_FIELDS', false ) : false;
			if ( ! empty( $current_rows ) ) {
				$response['rows'] = json_decode( $current_rows[0], true );
			}
		}

		wp_send_json( $response );
	}

	/**
	 * Check if the request is an AJAX refresh.
	 *
	 * @return bool
	 */
	public function is_ajax_refresh() {
		$context = automator_filter_has_var( 'context', INPUT_POST ) ? automator_filter_input( 'context', INPUT_POST ) : '';
		return 'refresh-button' === $context;
	}

	/**
	 * Maybe save action CLIENT meta value.
	 *
	 * @param  array $meta_value
	 * @param  WP_Post $item
	 *
	 * @return array
	 */
	public function maybe_save_action_client_meta( $meta_value, $item ) {

		// Check action post type and CLIENT key.
		if ( 'uo-action' !== $item->post_type || ! isset( $meta_value[ self::ACTION_CLIENT_META_KEY ] ) ) {
			return $meta_value;
		}

		// Action meta keys.
		$action_metas = array(
			'CAMPAIGN_MONITOR_ADD_UPDATE_SUBSCRIBER_META',
			'CAMPAIGN_MONITOR_REMOVE_SUBSCRIBER_META',
		);

		// Check if $meta_value contains a key from $action_metas.
		if ( ! array_intersect_key( $meta_value, array_flip( $action_metas ) ) ) {
			return $meta_value;
		}

		// Check if CLIENT is empty.
		if ( empty( $meta_value[ self::ACTION_CLIENT_META_KEY ] ) ) {
			$account                                    = $this->get_account_details( false );
			$meta_value[ self::ACTION_CLIENT_META_KEY ] = $account['client']['value'] ?? '';
		}

		return $meta_value;
	}

	/**
	 * Maybe update actions hidden client field meta.
	 *
	 * @param string $client_id
	 *
	 * @return void
	 */
	private function maybe_update_actions_hidden_client_field_meta( $client_id ) {

		// Query all action IDs with the client meta key.
		global $wpdb;
		$metas = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key LIKE %s AND meta_value != %s",
				self::ACTION_CLIENT_META_KEY,
				$client_id
			)
		);

		// Update the client meta key.
		if ( ! empty( $metas ) ) {
			foreach ( $metas as $meta ) {
				update_post_meta( $meta->post_id, self::ACTION_CLIENT_META_KEY, $client_id );
			}
		}

	}

	/**
	 * Get email from parsed.
	 *
	 * @param  array $parsed
	 * @param  string $meta_key
	 * @return mixed
	 * @throws Exception
	 */
	public function get_email_from_parsed( $parsed, $meta_key ) {

		if ( ! isset( $parsed[ $meta_key ] ) ) {
			throw new \Exception( esc_html_x( 'Missing email', 'Campaign Monitor', 'uncanny-automator' ) );
		}

		$email = sanitize_text_field( $parsed[ $meta_key ] );

		if ( ! $email || ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new \Exception( esc_html_x( 'Invalid email', 'Campaign Monitor', 'uncanny-automator' ) );
		}

		return $email;
	}

	/**
	 * Get list_id from parsed.
	 *
	 * @param  array $parsed
	 * @param  string $meta_key
	 * @return mixed
	 * @throws Exception
	 */
	public function get_list_id_from_parsed( $parsed, $meta_key ) {
		if ( ! isset( $parsed[ $meta_key ] ) ) {
			throw new \Exception( esc_html_x( 'List is required', 'Campaign Monitor', 'uncanny-automator' ) );
		}

		$list_id = sanitize_text_field( $parsed[ $meta_key ] );

		if ( ! $list_id ) {
			throw new \Exception( esc_html_x( 'List is required', 'Campaign Monitor', 'uncanny-automator' ) );
		}

		return $list_id;
	}

}
