<?php // phpcs:ignoreFile PHPCompatibility.Operators.NewOperators.t_coalesceFound
// phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Keap;

use Exception;
use WP_Error;
use Uncanny_Automator\Api_Server;
use Uncanny_Automator\Automator_Helpers_Recipe;

/**
 * Class Keap_Helpers
 *
 * @package Uncanny_Automator
 */
class Keap_Helpers {

	/**
	 * Settings tab id
	 *
	 * @var string|object
	 */
	public $settings_tab = 'keap';

	/**
	 * The public API edge.
	 *
	 * @var string
	 */
	const API_ENDPOINT = 'v2/keap';

	/**
	 * The nonce.
	 *
	 * @var string
	 */
	const NONCE = 'automator_keap_api_authentication';

	/**
	 * Delete key - to signify deletion of a value.
	 *
	 * @var string
	 */
	const DELETE_KEY = '[delete]';

	/**
	 * WP Options keys.
	 *
	 * @var array
	 */
	private static $option_keys = array(
		'account'        => 'automator_keap_account',
		'companies'      => 'automator_keap_companies',
		'company_custom' => 'automator_keap_company_custom_fields',
		'contact_custom' => 'automator_keap_contact_custom_fields',
		'credentials'    => 'automator_keap_credentials',
		'tags'           => 'automator_keap_tags',
		'users'          => 'automator_keap_users',
	);

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
	 * Create and retrieve a disconnect url for Keap Integration.
	 *
	 * @return string The disconnect uri.
	 */
	public static function get_disconnect_url() {

		return add_query_arg(
			array(
				'action' => 'automator_keap_disconnect_account',
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

		$data        = automator_filter_input( 'automator_api_message' );
		$nonce       = automator_filter_input( 'nonce' );
		$credentials = Automator_Helpers_Recipe::automator_api_decode_message( $data, $nonce );

		// Handle errors.
		if ( false === $credentials ) {
			$this->redirect_with_error( _x( 'Unable to decode credentials with the secret provided. Please refresh and retry.', 'Keap', 'uncanny-automator' ) );
		}

		if ( empty( $credentials['data'] ) ) {
			$this->redirect_with_error( _x( 'Authentication failed. Please refresh and retry.', 'Keap', 'uncanny-automator' ) );
		}

		$this->save_credentials( $credentials['data'] );

		// Extract App ID from scope.
		$app_id = str_replace( 'full|', '', $credentials['data']['scope'] );
		$app_id = explode( '.', $app_id );
		$app_id = $app_id[0] ?? '';

		// Get / set account details.
		$account = $this->get_account_details( $app_id );
		if ( is_wp_error( $account ) ) {
			$this->redirect_with_error( $account->get_error_message() );
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

		// Calculate the expiration timestamp minus an hour.
		$expires_in                = absint( $credentials['expires_in'] ) - HOUR_IN_SECONDS;
		$credentials['expires_on'] = time() + $expires_in;

		automator_update_option( self::$option_keys['credentials'], $credentials, false );
	}

	/**
	 * Retrieve the credentials from the options table.
	 *
	 * @return array
	 */
	public static function get_credentials() {
		return (array) automator_get_option( self::$option_keys['credentials'], array() );
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
	 * @param  string $app_id
	 *
	 * @return mixed - Details of connected account || WP_Error
	 */
	public function get_account_details( $app_id = '' ) {

		$account = automator_get_option( self::$option_keys['account'], array() );

		if ( empty( $account ) ) {
			try {

				$response = $this->api_request( 'get_account_details' );
				$data     = $response['data'] ?? array();
				$user     = $data['user'] ?? array();
				$app      = $data['app'] ?? array();

				// Format contact config data.
				$contact  = $app['contact'] ?? array();
				$contact  = $this->format_contact_config_data( $contact );

				// Set account details.
				$account  = array(
					'app_id'    => $app_id,
					'email'     => $user['email'] ?? '',
					'company'   => $app['application']['company'] ?? '',
					'user_id'   => $user['sub'] ?? '',
					'time_zone' => $app['time_zone'] ?? '',
					'contact'   => $contact,
				);

				automator_update_option( self::$option_keys['account'], $account, false );
			}
			catch ( Exception $e ) {
				return new WP_Error( 'keap_get_account_details_error', $e->getMessage() );
			}
		}

		return $account;
	}

	/**
	 * Get Account Detail.
	 *
	 * @param  string $key
	 * @param  string $default
	 *
	 * @return mixed
	 */
	public function get_account_detail( $key, $default = '' ) {
		$account = $this->get_account_details();
		return $account[ $key ] ?? $default;
	}

	/**
	 * Get Account Contact Config.
	 *
	 * @param  string $key
	 * @param  string $default
	 *
	 * @return mixed
	 */
	public function get_account_contact_config( $key, $dafault = '' ) {

		$contact = $this->get_account_detail( 'contact', array() );
		if ( empty( $contact ) ) {
			return array();
		}
		$value = $contact[ $key ] ?? '';
		return empty( $value ) ? $default : $value;
	}

	/**
	 * Format contact config data.
	 *
	 * @param  array $contact
	 *
	 * @return array
	 */
	private function format_contact_config_data( $contact ) {
		if ( ! empty( $contact ) ) {
			foreach ( $contact as $key => $value ) {
				if ( 'disable_contact_edit_in_client_login' === $key || 'default_new_contact_form' === $key ) {
					unset( $contact[ $key ] );
					continue;
				}
				// Convert comma separated values to array.
				$value = is_string( $value ) ? explode( ',', trim( $value ) ) : $value;
				// Remove empty values and reset keys.
				$contact[ $key ] = array_map( 'trim', array_values( array_filter( $value ) ) );
			}
		}
		return $contact;
	}

	/**
	 * Get Companies.
	 *
	 * @param  bool $refresh
	 *
	 * @return array
	 */
	public function get_companies( $refresh = false ) {

		$company_data   = $this->get_app_option( 'companies' );
		$companies      = $company_data['data'];
		$should_refresh = $refresh || $company_data['refresh'];

		if ( empty( $companies ) || $should_refresh ) {
			try {
				$response  = $this->api_request( 'get_companies' );
				$data      = $response['data']['companies'] ?? array();
				$companies = array();
				foreach ( $data as $company ) {
					$companies[ $company['id'] ] = array(
						'value' => $company['id'],
						'text'  => $company['company_name'],
					);
				}
				$this->save_app_option( 'companies', $companies );
			}
			catch ( Exception $e ) {
				return $companies; // Return previous data if any.
			}
		}

		return $companies;
	}

	/**
	 * Get Companies Ajax handler.
	 *
	 * @return array
	 */
	public function get_companies_ajax() {

		Automator()->utilities->verify_nonce();

		$options = array();

		// Check if we should add an empty option.
		$group_id  = automator_filter_input( 'group_id', INPUT_POST );
		if ( 'KEAP_ADD_UPDATE_CONTACT_META' === $group_id ) {
			$options[] = array(
				'value' => '',
				'text'  => _x( 'Select a company', 'Keap', 'uncanny-automator' ),
			);
		}

		$companies = $this->get_companies( $this->is_ajax_refresh() );
		if ( ! empty( $companies ) ) {
			$options = array_merge( $options, array_values( $companies ) );
		}

		wp_send_json( array(
			'success' => true,
			'options' => $options,
		) );
	}

	/**
	 * Get Company Selection.
	 *
	 * @param  mixed string|int $selected - ID or Name.
	 *
	 * @return mixed object || WP_Error - Company || Error.
	 */
	public function get_valid_company_selection( $selected, $refresh = false ) {

		if ( empty( $selected ) ) {
			return new WP_Error( 'empty', _x( 'Company required.', 'Keap', 'uncanny-automator' ) );
		}

		$can_refresh = false === $refresh;

		$companies = $this->get_companies( $refresh );
		if ( empty( $companies ) ) {
			if ( ! $can_refresh ) {
				return new WP_Error( 'empty', _x( 'No companies found.', 'Keap', 'uncanny-automator' ) );
			}
			return $this->get_valid_company_selection( $selected, true );
		}

		// Check by ID.
		$company_id = is_numeric( $selected ) ? absint( $selected ) : 0;
		if ( ! empty( $company_id ) && key_exists( $company_id, $companies ) ) {
			$company = $companies[ $company_id ];
			return (object) array(
				'id'           => $company['value'],
				'company_name' => $company['text'],
			);
		}

		// Check by name.
		$company_name = empty( $company_id ) ? trim( $selected ) : '';
		foreach ( $companies as $company ) {
			if ( strcasecmp( $company['text'], $company_name ) == 0 ) {
				return (object) array(
					'id'           => $company['value'],
					'company_name' => $company['text'],
				);
			}
		}

		// Try to refresh and re-validate.
		if ( $can_refresh ) {
			// Confirm last timestamp is at least 2 minutes old.
			$company_data = $this->get_app_option( 'companies', 120 );
			if ( $company_data['refresh'] ) {
				return $this->get_valid_company_selection( $selected, true );
			}
		}

		// No match found.
		return new WP_Error( 'invalid', _x( 'Invalid company.', 'Keap', 'uncanny-automator' ) );
	}

	/**
	 * Add new company to saved options.
	 *
	 * @param  int $id
	 * @param  string $name
	 *
	 * @return void
	 */
	public function add_new_company_to_saved_options( $id, $name ) {
		$companies = $this->get_companies();
		$companies[ $id ] = array(
			'value' => $id,
			'text'  => $name,
		);
		$this->save_app_option( 'companies', $companies );
	}

	/**
	 * Get App Account Users.
	 *
	 * @return array
	 */
	public function get_account_users( $refresh = false ) {

		$user_data      = $this->get_app_option( 'users' );
		$users          = $user_data['data'];
		$should_refresh = $refresh || $user_data['refresh'];

		if ( empty( $users ) || $should_refresh ) {
			try {
				$response = $this->api_request( 'get_app_account_users' );
				$data     = $response['data']['users'] ?? array();
				$users    = array();
				foreach ( $data as $user ) {
					if ( 'Active' !== $user['status'] ) {
						continue;
					}
					$name  = ! empty( $user['preferred_name'] )
						? $user['preferred_name']
						: trim( $user['first_name'] . ' ' . $user['last_name'] );
					$label = $name . ( ! empty( $user['email_address'] ) ? ' (' . $user['email_address'] . ')' : '' );
					$users[ $user['id'] ] = array(
						'value'    => $user['id'],
						'text'     => $label,
						'email'    => $user['email_address'],
					);
				}
				$this->save_app_option( 'users', $users );
			}
			catch ( Exception $e ) {
				return $users; // Return previous data if any.
			}
		}

		return $users;
	}

	/**
	 * Get App Account Users Ajax handler.
	 *
	 * @return string - JSON response.
	 */
	public function get_account_users_ajax() {

		Automator()->utilities->verify_nonce();

		$options = array();

		// Check if we should add an empty option.
		$group_id  = automator_filter_input( 'group_id', INPUT_POST );
		if ( 'KEAP_ADD_UPDATE_CONTACT_META' === $group_id ) {
			$options[] = array(
				'value' => '',
				'text'  => _x( 'Select an owner', 'Keap', 'uncanny-automator' ),
			);
		}

		$account_users = $this->get_account_users( $this->is_ajax_refresh() );
		if ( ! empty( $account_users ) ) {
			// Remove email prop and reset keys.
			$account_users = array_values( array_map( function( $user ) {
				unset( $user['email'] );
				return $user;
			}, $account_users ) );

			$options = array_merge( $options, $account_users );
		}

		wp_send_json( array(
			'success' => true,
			'options' => $options,
		) );
	}

	/**
	 * Get Account User Selection.
	 *
	 * @param  mixed string|int $selected
	 *
	 * @return mixed int || WP_Error - Account User ID || Error.
	 */
	public function get_valid_account_user_selection( $selected, $refresh = false ) {

		$selected = sanitize_text_field( $selected );
		if ( empty( $selected ) ) {
			return new WP_Error( 'empty', _x( 'Account user is required.', 'Keap', 'uncanny-automator' ) );
		}

		$can_refresh = false === $refresh;

		$users = $this->get_account_users( $refresh );
		if ( empty( $users ) ) {
			if ( ! $can_refresh ) {
				return new WP_Error( 'empty', _x( 'No account users found.', 'Keap', 'uncanny-automator' ) );
			}
			return $this->get_valid_account_user_selection( $selected, true );
		}

		// Check by ID.
		$user_id = is_numeric( $selected ) ? absint( $selected ) : 0;
		if ( ! empty( $user_id ) && key_exists( $user_id, $users ) ) {
			return $user_id;
		}

		// Check by email.
		foreach ( $users as $user ) {
			if ( strcasecmp( $user['email'], $user_email ) == 0 ) {
				return $user['value'];
			}
		}

		// Try to refresh and re-validate.
		if ( $can_refresh ) {
			// Confirm last timestamp is at least 2 minutes old.
			$user_data = $this->get_app_option( 'users', 120 );
			if ( $user_data['refresh'] ) {
				return $this->get_valid_account_user_selection( $selected, true );
			}
		}

		// No match found.
		return new WP_Error( 'invalid', _x( 'Invalid account user.', 'Keap', 'uncanny-automator' ) );
	}

	/**
	 * Disconnect Keap integration.
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
		foreach( self::$option_keys as $key ) {
			automator_delete_option( $key );
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
			throw new Exception( 'Your Keap integration is currently disconnected. ' . $this->common_reconnect_message(), 500 );
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
		return _x( 'Please navigate to the settings page to establish a connection with your Keap account.', 'Keap', 'uncanny-automator' );
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

		$valid_codes = array( 200, 201, 204 );
		if ( ! in_array( $response['statusCode'], $valid_codes ) ) {
			$message = _x( 'Keap API Error : ', 'Keap', 'uncanny-automator' );
			if ( isset( $response['data']['message'] ) ) {
				$message = $response['data']['message'];
			} else {
				$message .= sprintf(
					// Translators: %s Status code.
					_x( 'request failed with status code: %s', 'Keap', 'uncanny-automator' ),
					$response['statusCode']
				);
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
				'action'   => 'authorize',
				'user_url' => rawurlencode( get_bloginfo( 'url' ) ),
				'nonce'    => wp_create_nonce( self::NONCE ),
			),
			AUTOMATOR_API_URL . 'v2/keap'
		);
	}

	/**
	 * Get App Option.
	 *
	 * @param  string $key
	 *
	 * @return array
	 */
	public function get_app_option( $key, $refresh_check = DAY_IN_SECONDS ) {
		$data = automator_get_option( self::$option_keys[ $key ], array(
			'data'      => array(),
			'timestamp' => 0,
		) );
		$timestamp = $data['timestamp'] ?? 0;
		return array(
			'data'      => $data['data'] ?? array(),
			'timestamp' => $timestamp,
			'refresh'   => ( time() - $timestamp ) > $refresh_check,
		);
	}

	/**
	 * Save App Option.
	 *
	 * @param  string $key
	 * @param  mixed $data
	 *
	 * @return void
	 */
	public function save_app_option( $key, $data ) {
		automator_update_option( self::$option_keys[ $key ], array(
			'data'      => $data,
			'timestamp' => time(),
		), false );
	}

	/**
	 * Get Address Fields Config.
	 *
	 * @param  string $type - 'contact' || 'company'
	 *
	 * @return array
	 */
	public function get_address_fields_config( $type ) {

		$cap_type = ucfirst( $type );
		$prefix   = strtoupper( $type ) . '_';

		// Dynamic visibility rules.
		$visibility = array(
			'default_state'    => 'hidden',
			'visibility_rules' => array(
				array(
					'operator' => 'AND',
					'rule_conditions' => array(
						array(
							'option_code' => "{$prefix}ADDRESS_ENABLED",
							'compare'     => '==',
							'value'       => true,
						),
					),
					'resulting_visibility' => 'show',
				),
			)
		);

		// Checkbox to enable/disable address visibility.
		$fields[] = array(
			'input_type'      => 'checkbox',
			'option_code'     => "{$prefix}ADDRESS_ENABLED",
			'label'           => sprintf(
				// Translators: %s: Address type.
				_x( 'Add/Update %s address', 'Keap', 'uncanny-automator' ),
				$type
			),
			'required'        => false,
			'supports_tokens' => false,
			'is_toggle'   => true,
		);

		// Address fields.
		$fields[] = array(
			'input_type'         => 'text',
			'option_code'        => "{$prefix}LINE1",
			'dynamic_visibility' => $visibility,
			'label'              => sprintf(
				// Translators: %s: Address type.
				_x( '%s address line 1', 'Keap', 'uncanny-automator' ),
				$cap_type
			),
		);

		$fields[] = array(
			'input_type'         => 'text',
			'option_code'        => "{$prefix}LINE2",
			'dynamic_visibility' => $visibility,
			'label'              => sprintf(
				// Translators: %s: Address type.
				_x( '%s address line 2', 'Keap', 'uncanny-automator' ),
				$cap_type
			),
		);

		$fields[] = array(
			'input_type'         => 'text',
			'option_code'        => "{$prefix}LOCALITY",
			'dynamic_visibility' => $visibility,
			'label'              => sprintf(
				// Translators: %s: Address type.
				_x( '%s address city/locality', 'Keap', 'uncanny-automator' ),
				$cap_type
			),
		);

		$fields[] = array(
			'input_type'         => 'text',
			'option_code'        => "{$prefix}REGION_CODE",
			'dynamic_visibility' => $visibility,
			'label'              => sprintf(
				// Translators: %s: Address type.
				_x( '%s address region code', 'Keap', 'uncanny-automator' ),
				$cap_type
			),
			'description'        => sprintf(
				// translators: %1$s opening anchor tag, %2$s: closing anchor tag
				_x( 'An %1$sISO 3166-2%2$s province/region code, such as "US-CA" for California.', 'Keap', 'uncanny-automator' ),
				'<a href="https://en.wikipedia.org/wiki/ISO_3166-2:US" target="_blank">',
				'</a>'
			),
		);

		$fields[] = array(
			'input_type'         => 'text',
			'option_code'        => "{$prefix}ZIP_CODE",
			'dynamic_visibility' => $visibility,
			'label'              => sprintf(
				// Translators: %s: Address type.
				_x( '%s address zip/postal code', 'Keap', 'uncanny-automator' ),
				$cap_type
			),
		);

		$fields[] = array(
			'input_type'         => 'text',
			'option_code'        => "{$prefix}COUNTRY_CODE",
			'dynamic_visibility' => $visibility,
			'label'              => sprintf(
				// Translators: %s: Address type.
				_x( '%s address country code', 'Keap', 'uncanny-automator' ),
				$cap_type
			),
			'description'        => sprintf(
				// translators: %1$s opening anchor tag, %2$s: closing anchor tag
				_x( 'An %1$sISO 3166-2%2$s Country Code, such as "USA" for the United States of America.', 'Keap', 'uncanny-automator' ),
				'<a href="https://en.wikipedia.org/wiki/ISO_3166-1_alpha-3" target="_blank">',
				'</a>'
			),
		);

		return $fields;
	}

	/**
	 * Get Address Fields from Parsed Data.
	 *
	 * @param  array $parsed
	 * @param  string $type - billing || shipping || other || company
	 * @param  string $field - Keap field key
	 * @return mixed object || false
	 */
	public function get_address_fields_from_parsed( $parsed, $type ) {

		// Bail if we have invalid data.
		if ( empty( $parsed ) || ! is_array( $parsed ) || ! is_string( $type ) ) {
			return false;
		}

		// Set prefix.
		$prefix = strtoupper( $type ) . '_';

		// Check if enabled.
		if ( ! $this->get_bool_value_from_parsed( $parsed, "{$prefix}ADDRESS_ENABLED" ) ) {
			return false;
		}

		// Get field values.
		$fields = array(
			'line1'        => (string) $parsed["{$prefix}LINE1"] ?? '',
			'line2'        => (string) $parsed["{$prefix}LINE2"] ?? '',
			'locality'     => (string) $parsed["{$prefix}LOCALITY"] ?? '',
			'region_code'  => (string) $parsed["{$prefix}REGION_CODE"] ?? '',
			'zip_code'     => (string) $parsed["{$prefix}ZIP_CODE"] ?? '',
			'country_code' => (string) $parsed["{$prefix}COUNTRY_CODE"] ?? '',
		);

		// Sanitize and remove empty fields.
		$fields = array_filter( array_map( 'sanitize_text_field', $fields ) );
		if ( empty( $fields ) ) {
			return false;
		}

		// Convert all delete keys to empty strings.
		$fields = array_map( function( $value ) {
			return $this->get_delete_key() === $value ? '' : $value;
		}, $fields );

		// Add Keap field type.
		$fields['field'] = 'company' === $type ? 'ADDRESS_FIELD_UNSPECIFIED' : strtoupper( $type );

		// Return as object with fields for the request.
		return (object) $fields;
	}

	/**
	 * Get common Custom Fields Repeater Config.
	 *
	 * @param string $type - 'contact' || 'company'
	 *
	 * @return array
	 */
	public function get_custom_fields_repeater_config( $type = 'contact' ) {
		return array(
			'option_code'       => 'CUSTOM_FIELDS',
			'input_type'        => 'repeater',
			'label'             => _x( 'Custom fields', 'Keap', 'uncanny-automator' ),
			'required'          => false,
			'add_row_button'    => _x( 'Add a field', 'Keap', 'uncanny-automator' ),
			'remove_row_button' => _x( 'Remove field', 'Keap', 'uncanny-automator' ),
			'description'       => sprintf(
				/* translators: %1$s: [delete], %2$s opening anchor tag, %3$s: closing anchor tag */
				_x( "Custom field values must align with how they are defined in your Keap account. To delete a value from a field, set its value to %1\$s, including the square brackets. Multiple values for checkboxes may be separated with commas. For more details, be sure to check out Keap's tutorial on %2\$scustom fields management.%3\$s", 'Keap', 'uncanny-automator' ),
				$this->get_delete_key(),
				'<a href="https://help.keap.com/help/custom-fields-management" target="_blank">',
				'</a>'
			),
			'fields'            => array(),
			'ajax'              => array(
				'event'    => 'on_load',
				'endpoint' => "automator_keap_get_{$type}_custom_fields",
			),
			'relevant_tokens'   => array(),
		);
	}

	/**
	 * Get common custom fields repeater config.
	 *
	 * @param  string $type - 'contact' || 'company'
	 * @param  bool $refresh
	 *
	 * @return array
	 */
	public function get_custom_fields_repeater_fields_config( $type, $refresh = false ) {

		// Get field map.
		$fields  = $this->get_custom_field_options( $type, $refresh );
		$options = array_map( function( $item ) {
			return array(
				'value' => $item['value'],
				'text' => $item['text'],
			);
		}, $fields );

		return array(
			array(
				'input_type'            => 'select',
				'option_code'           => 'FIELD',
				'label'                 => _x( 'Field', 'Keap', 'uncanny-automator' ),
				'options'               => array_values( $options ),
				'options_show_id'       => false,
				'required'              => true,
				'supports_custom_value' => false
			),
			array(
				'input_type'      => 'text',
				'option_code'     => 'FIELD_VALUE',
				'label'           => _x( 'Value', 'Keap', 'uncanny-automator' ),
				'supports_tokens' => true,
				'required'        => true,
			),
		);
	}

	/**
	 * Get custom fields by option key.
	 *
	 * @param  string $type - 'contact' || 'company'
	 * @param  bool $refresh
	 *
	 * @return array
	 */
	public function get_custom_field_options( $type, $refresh = false ) {

		$option_key     = "{$type}_custom";
		$fields_data    = $this->get_app_option( $option_key );
		$fields         = $fields_data['data'];
		$should_refresh = $refresh || $fields_data['refresh'];
		$not_found      = array(
			array(
				'value' => '',
				'text'  => _x( 'No custom fields found', 'Keap', 'uncanny-automator' ),
			),
		);

		if ( ! $should_refresh ) {
			if ( is_array( $fields ) && ! empty( $fields ) ) {
				return $fields;
			}
			if ( 'no_custom_fields' === $fields ) {
				return $not_found;
			}
		}

		try {
			$method   = 'contact' === $type ? 'get_contact_model' : 'get_company_model';
			$response = $this->api_request( $method );
			$data     = $response['data'] ?? array();
			$data     = $data['custom_fields'] ?? array();

			if ( empty( $data ) ) {
				$this->save_app_option( $option_key, 'no_custom_fields' );
				return $not_found;
			}

			// Keap to Automator UI - mapping.
			$types_map = array(
				'WEBSITE'       => 'url',
				'DATE'          => 'date',
				'EMAIL'         => 'email',
				'LISTBOX'       => 'select',
				'DAYOFWEEK'     => 'select',
				'DROPDOWN'      => 'select',
				'MONTH'         => 'select',
				'RADIO'         => 'select',
				'STATE'         => 'select',
				'YESNO'         => 'select',
				'CURRENCY'      => 'number',
				'DECIMALNUMBER' => 'number',
				'PERCENT'       => 'number',
				'WHOLENUMBER'   => 'number',
				'YEAR'          => 'number',
				'PHONENUMBER'   => 'text',
				'TEXT'          => 'text',
				'TEXTAREA'      => 'textarea',
			);
			$fields = array();
			foreach ( $data as $field ) {

				// Normalize Keap Type ( Differences between Contact and Company models ).
				$keap_type = strtoupper( str_replace( '_', '', $field['field_type'] ) );

				if ( 'YESNO' === $keap_type ) {
					$field['options'] = array(
						array(
							'id'    => 'Yes',
							'label' => 'Yes',
						),
						array(
							'id'    => 'No',
							'label' => 'No',
						),
					);
				}

				$options = null;
				if ( is_array( $field['options'] ) ) {
					foreach( $field['options'] as $option ) {
						$options[ $option['id'] ] = array(
							'value' => $option['id'],
							'text'  => $option['label'],
						);
					}
				}

				$fields[ $field['id'] ] = array(
					'value'                    => $field['id'],
					'text'                     => $field['label'],
					'type'                     => $types_map[ $keap_type ] ?? 'text',
					'keap_type'                => $keap_type,
					'options'                  => $options,
				);
			}

			// Set option.
			$this->save_app_option( $option_key, $fields );
			return $fields;

		} catch ( Exception $e ) {
			// Return previous results or not found.
			return ! empty( $fields ) && is_array( $fields) ? $fields : $not_found;
		}

	}

	/**
	 * Get Contact Custom Fields Ajax handler.
	 *
	 * @return string - JSON response.
	 */
	public function get_contact_custom_fields_repeater_ajax() {

		Automator()->utilities->verify_nonce();

		// Prepare response.
		$response = array(
			'success' => true,
			'field_properties' => array(
				'fields' => $this->get_custom_fields_repeater_fields_config( 'contact', $this->is_ajax_refresh() )
			),
		);

		wp_send_json( $response );
	}

	/**
	 * Get Company Custom Fields Ajax handler.
	 *
	 * @return string - JSON response.
	 */
	public function get_company_custom_fields_repeater_ajax() {

		Automator()->utilities->verify_nonce();

		// Prepare response.
		$response = array(
			'success' => true,
			'field_properties' => array(
				'fields' => $this->get_custom_fields_repeater_fields_config( 'company', $this->is_ajax_refresh() )
			),
		);

		wp_send_json( $response );
	}

	/**
	 * Build custom fields request data.
	 *
	 * @param array $fields
	 * @param string $type
	 *
	 * @return array
	 */
	public function build_custom_fields_request_data( $fields, $type = 'contact' ) {

		$data = array(
			'fields' => array(),
			'errors' => array(),
		);

		// Bail if no custom fields set.
		if ( empty( $fields ) ) {
			return $data;
		}

		// Get custom fields config.
		$config = $this->get_custom_field_options( $type );

		// Bail if no custom fields config.
		if ( empty( $config ) ) {
			$data['errors'][] = _x( 'Unable to validate Custom Field(s).', 'Keap', 'uncanny-automator' );
			return $data;
		}

		foreach ( $fields as $field ) {

			$field_id = isset( $field['FIELD'] ) ? sanitize_text_field( $field['FIELD'] ) : '';
			$value    = isset( $field['FIELD_VALUE'] ) ? sanitize_text_field( trim( $field['FIELD_VALUE'] ) ) : '';
			if ( empty( $field_id ) || empty( $value ) ) {
				continue;
			}

			// Bail if no config for key.
			if ( ! key_exists( $field_id, $config ) ) {
				$data['errors'][] = sprintf(
					// translators: %s: custom field key
					_x( 'Invalid custom field id: %s', 'Keap', 'uncanny-automator' ),
					$field_id
				);
				continue;
			}

			// If [delete] is set, remove the field.
			if ( $this->get_delete_key() === $value ) {
				$data['fields'][] = (object) array(
					'id'      => $field_id,
					'content' => '',
				);
				continue;
			}

			// Validate custom field value.
			$validated_value = $this->validate_custom_field_value( $field_id, $value, $config[ $field_id ] );
			if ( is_wp_error( $validated_value ) ) {
				$data['errors'][] = $validated_value->get_error_message();
				continue;
			}

			// Add validated field.
			$data['fields'][] = (object) array(
				'id'      => $field_id,
				'content' => $validated_value,
			);
		}

		// Log errors.
		if ( ! empty( $data['errors'] ) ) {
			$errors = implode( ', ', $data['errors'] );
			$data['errors'] = _x( 'Invalid Custom Field(s) :', 'Keap', 'uncanny-automator' ) . ' ' . $errors;
		}

		return $data;
	}

	/**
	 * Validate custom field value.
	 *
	 * @param int $field_id
	 * @param string $value
	 * @param array $config
	 *
	 * @return mixed|\WP_Error
	 */
	public function validate_custom_field_value( $field_id, $value, $config ) {

		// Stash original value for filters.
		$original_value = $value;

		// Sanitize value by type.
		$value = $this->sanitize_custom_field_value_by_type( $value, $config['type'], $config );

		// Validate value by options.
		if ( ! is_wp_error( $value ) && ! empty( $config['options'] ) ) {
			$value = $this->validate_custom_field_value_by_options( $value, $config );
		}

		/**
		 * Filter custom field value.
		 *
		 * @param mixed $value           - The custom field value or WP_Error.
		 * @param string $field_id       - The custom field key.
		 * @param string $original_value - The original custom field value.
		 * @param array $config          - The custom field config.
		 *
		 * @return mixed
		 */
		$value = apply_filters( 'automator_keap_validate_custom_field_value', $value, $field_id, $original_value, $config );

		return $value;
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
	 * Get update existing option config.
	 *
	 * @param  string $type - 'contact' || 'company'
	 *
	 * @return array
	 */
	public function get_update_existing_option_config( $type = 'contact' ) {
		return array(
			'option_code' => 'UPDATE_EXISTING_' . strtoupper( $type ),
			'input_type'  => 'checkbox',
			'is_toggle'   => true,
			'label'       => sprintf(
				_x( 'Update existing %s', 'Keap', 'uncanny-automator' ),
				$type
			),
			'description' => sprintf(
				// translators: %1$s: [delete]
				_x( 'To exclude fields from being updated, leave them empty. To delete a value from a field, set its value to %1$s, including the square brackets.', 'Keap', 'uncanny-automator' ),
				$this->get_delete_key()
			),
		);
	}

	/**
	 * Get Email Field.
	 *
	 * @param  string $code
	 * @param  bool $required
	 *
	 * @return array
	 */
	public function get_email_field_config( $code, $required = true ) {
		return array(
			'input_type'      => 'text',
			'option_code'     => $code,
			'label'           => _x( 'Email', 'Keap', 'uncanny-automator' ),
			'supports_tokens' => true,
			'required'        => $required,
		);
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
			throw new \Exception( esc_html_x( 'Missing email', 'Keap', 'uncanny-automator' ) );
		}

		$email = $this->get_valid_email( $parsed[ $meta_key ] );

		if ( ! $email ) {
			throw new \Exception( esc_html_x( 'Invalid email', 'Keap', 'uncanny-automator' ) );
		}

		return $email;
	}

	/**
	 * Get Tags Select.
	 *
	 * @param  string $code
	 *
	 * @return array
	 */
	public function get_tags_select_field_config( $code = 'TAG' ) {
		return array(
			'input_type'               => 'select',
			'option_code'              => $code,
			'label'                    => _x( 'Tag(s)', 'Keap', 'uncanny-automator' ),
			'required'                 => true,
			'supports_multiple_values' => true,
			'show_label_in_sentence'   => true,
			'options'                  => array(),
			'ajax'                     => array(
				'event'    => 'on_load',
				'endpoint' => 'automator_keap_get_tags',
			)
		);
	}

	/**
	 * Get Tags.
	 *
	 * @param  bool $refresh
	 *
	 * @return array
	 */
	public function get_tags( $refresh = false ) {
		$tag_data       = $this->get_app_option( 'tags' );
		$tags           = $tag_data['data'];
		$should_refresh = $refresh || $tag_data['refresh'];
		if ( empty( $tags ) || $should_refresh ) {
			try {
				$response = $this->api_request( 'get_tags' );
				$data     = $response['data']['tags'] ?? array();
				$tags     = array();
				foreach ( $data as $tag ) {
					$tags[ $tag['id'] ] = array(
						'value'    => $tag['id'],
						'text'     => $tag['name'],
					);
				}
				$this->save_app_option( 'tags', $tags );
			}
			catch ( Exception $e ) {
				return $tags; // Return previous results or empty.
			}
		}

		return $tags;
	}

	/**
	 * Get Tags Ajax handler.
	 *
	 * @return string - JSON response.
	 */
	public function get_tags_ajax() {

		Automator()->utilities->verify_nonce();
		$tags = $this->get_tags( $this->is_ajax_refresh() );
		$tags = ! empty( $tags ) ? array_values( $tags ) : array();

		wp_send_json( array(
			'success' => true,
			'options' => $tags,
		) );
	}

	/**
	 * Get tags from parsed.
	 *
	 * @param  array $parsed
	 * @param  string $meta_key
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function get_tags_from_parsed( $parsed, $meta_key = 'TAG' ) {

		if ( ! isset( $parsed[ $meta_key ] ) || empty( $parsed[ $meta_key ] ) ) {
			throw new \Exception( esc_html_x( 'Missing tag(s)', 'Keap', 'uncanny-automator' ) );
		}

		// Extract tags from the parsed data and remove empty values after converting to integers.
		$tags = array_filter( array_map( 'intval', json_decode( $parsed[ $meta_key ], true ) ) );
		if ( empty( $tags ) ) {
			throw new \Exception( esc_html_x( 'Invalid tag id(s)', 'Keap', 'uncanny-automator' ) );
		}

		// Return as CSV.
		return implode( ',', $tags );
	}

	/**
	 * Check if email is valid.
	 *
	 * @param  string $email
	 *
	 * @return mixed - false | string
	 */
	public function get_valid_email( $email ) {
		if (  empty( $email ) || ! is_string( $email ) ) {
			return false;
		}
		$email    = sanitize_text_field( $email );
		$is_valid = $email && filter_var( $email, FILTER_VALIDATE_EMAIL );
		return $is_valid ? $email : false;
	}

	/**
	 * Check if URL is valid.
	 *
	 * @param  string $url
	 *
	 * @return mixed - false | string
	 */
	public function get_valid_url( $url ) {
		if ( empty( $url ) || ! is_string( $url ) ) {
			return false;
		}
		$url = esc_url( $url );
		return $url ? $url : false;
	}

	/**
	 * Check if phone number is valid.
	 *
	 * @param  string $phone
	 *
	 * @return mixed - false | string
	 */
	public function get_valid_phone_number( $phone ) {
		if ( empty( $phone ) || ! is_string( $phone ) ) {
			return false;
		}
		// Allow the plus sign and remove all other non-numeric characters.
		$phone = preg_replace( '/(?!^\+)[^0-9]/', '', $phone );
		return $phone;
	}

	/**
	 * Get formatted date.
	 *
	 * @param  string $input
	 * @param  string $format
	 *
	 * @return mixed - WP_Error | string
	 * @throws Exception
	 */
	public function get_formatted_date( $input, $format = 'Y-m-d' ) {

		try {
			// Get the date object.
			$date = is_numeric( $input ) ? date_create_from_format( 'U', $input ) : date_create( $input );
			if ( ! $date ) {
				throw new Exception( _x( 'Invalid date', 'Keap', 'uncanny-automator' ) );
			}
			// Return the date in the requested format
			$formatted = date_format( $date, $format );
			if ( ! $formatted ) {
				throw new Exception( _x( 'Invalid format', 'Keap', 'uncanny-automator' ) );
			}
			return $formatted;
		} catch ( Exception $e ) {
			// Return WP_Error on exception
			return new \WP_Error(
				'invalid_date',
				$e->getMessage()
			);
		}
	}

	/**
	 * Get valid custom field number.
	 *
	 * @param  string $number
	 * @param  string $keap_type
	 *
	 * @return mixed - false | string
	 */
	public function get_valid_custom_field_number( $number, $keap_type ) {
		if ( ! is_numeric( $number ) ) {
			return false;
		}

		$number = sanitize_text_field( $number );

		switch ( $keap_type ) {
			case 'DECIMALNUMBER':
				$number = floatval( $number );
				break;
			case 'PERCENT':
			case 'CURRENCY':
				$number = number_format( floatval( $number ), 2, '.', '' );
				break;
			case 'YEAR':
				$number = absint( $number );
				// Check if year is within the 4-digit range
				if ( $number < 1000 || $number > 9999 ) {
					return false;
				}
				break;
			default:
				$number = absint( $number );
				break;
		}

		return $number === 0 ? '0' : ( $number ?: false );
	}

	/**
	 * Sanitize / format custom field value by type.
	 *
	 * @param string $value
	 * @param string $type
	 * @param array $config
	 *
	 * @return mixed|\WP_Error
	 */
	public function sanitize_custom_field_value_by_type( $value, $type, $config ) {

		$error     = false;
		$validated = '';

		// Sanitize / Validate by type.
		switch ( $type ) {
			case 'text':
			case 'select':
				$validated = sanitize_text_field( $value );
				break;
			case 'textarea':
				$validated = sanitize_textarea_field( $value );
				break;
			case 'number':
				$validated = $this->get_valid_custom_field_number( $value, $config['keap_type'] );
				$error     = false === $validated ? _x( 'Invalid number', 'Keap', 'uncanny-automator' ) : false;
				break;
			case 'date':
				$date      = $this->get_formatted_date( $value );
				$validated = is_wp_error( $date ) ? '' : $date;
				$error     = is_wp_error( $date ) ? $date->get_error_message() : false;
				break;
			case 'url':
				$validated = esc_url( $value );
				$error     = empty( $validated ) ? _x( 'Invalid URL', 'Keap', 'uncanny-automator' ) : '';
				break;
			case 'email':
				$validated = $this->get_valid_email( $value );
				$error     = empty( $validated ) ? _x( 'Invalid email', 'Keap', 'uncanny-automator' ) : false;
				break;
			default:
				$error = sprintf(
					// translators: %s: custom field type
					_x( 'Invalid custom field type: %s', 'Keap', 'uncanny-automator' ),
					$type
				);
				break;
		}

		if ( $error ) {
			$error .= ' ' . sprintf(
				// translators: %s: custom field label.
				_x( 'for field: %s', 'Keap', 'uncanny-automator' ),
				$config['text']
			);
			return new \WP_Error( 'invalid_field_' . $type, $error );
		}

		return $validated;
	}

	/**
	 * Validate custom field value by options.
	 *
	 * @param string $value
	 * @param array $config
	 *
	 * @return mixed - String or WP_Error or array if multiples ( List box ).
	 */
	public function validate_custom_field_value_by_options( $value, $config ) {

		$options   = $config['options'];
		$keap_type = $config['keap_type'];
		$value     = trim( $value );

		// Fomat value by type.
		$dates = array( 'DAYOFWEEK', 'MONTH' );
		if ( in_array( $keap_type, $dates, true ) ) {
			// Keaps wants the number representation of the day of the week or month of the year.
			if ( ! is_numeric( $value ) && false !== strtotime( $value ) ) {
				if ( 'DAYOFWEEK' === $keap_type ) {
					$value = (int) wp_date( 'w', strtotime( $value ) );
					// Adjust for Keap's week numbering, making Sunday = 1, Monday = 2, ..., Saturday = 7
					$value = $value === 0 ? 1 : $value + 1;
				} elseif ( 'MONTH' === $keap_type ) {
					$value = (int) wp_date( 'm', strtotime( $value ) );
				}
			} elseif ( is_numeric( $value ) ) {
				// Ensure we have no leading zeros.
				$value = (int) $value;
			}
		}

		if ( 'STATE' === $keap_type ) {
			// Keap wants the US 2 character state abbreviation, otherwise capitalized state name.
			$value = strlen( $value ) === 2 ? strtoupper( $value ) : ucwords( strtolower( $value ) );
		}

		if ( 'YESNO' === $keap_type ) {
			// Keap wants 'Yes' or 'No' values.
			$value = ucfirst( strtolower( trim( $value ) ) );
		}

		// Check if we have multiple values.
		if ( 'LISTBOX' === $keap_type ) {
			$values = array_map( 'trim', explode( ',', $value ) );
			if ( count( $values ) > 1 ) {
				// Validate each value.
				$validated = array();
				// Adjust type to avoid checking again.
				$config['keap_type'] = 'LISTBOXITEM';
				foreach ( $values as $val ) {
					$result = $this->validate_custom_field_value_by_options( $val, $config );
					if ( is_wp_error( $result ) ) {
						return $result;
					}
					$validated[] = $result;
				}

				return array_values( $validated );
			}
		}

		// Check if value is in option values.
		if ( key_exists( $value, $options ) ) {
			return $value;
		}

		// Check if value is in option labels.
		$labels = wp_list_pluck( $options, 'text', 'value' );
		if ( in_array( $value, $labels, true ) ) {
			return array_search( $value, $labels, true );
		}

		return new \WP_Error(
			'invalid_custom_field_value',
			sprintf(
				// translators: %1$s: custom field value, %2$s: custom field label.
				_x( 'Invalid custom field value: %1$s for field: %2$s', 'Keap', 'uncanny-automator' ),
				$value,
				$config['text']
			)
		);
	}

	/**
	 * Get bool value from parsed.
	 *
	 * @param  array $parsed
	 * @param  string $meta_key
	 *
	 * @return bool
	 */
	public function get_bool_value_from_parsed( $parsed, $meta_key, $default = false ) {
		if ( ! isset( $parsed[ $meta_key ] ) ) {
			return $default;
		}
		return filter_var( strtolower( $parsed[ $meta_key ] ), FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Get [delete] key.
	 *
	 * @return string
	 */
	public function get_delete_key() {
		return self::DELETE_KEY;
	}

	/**
	 * Check if value is [delete] key.
	 *
	 * @param string $value
	 *
	 * @return bool
	 */
	public function is_delete_value( $value ) {
		return is_string( $value ) && self::DELETE_KEY === trim( $value );
	}

	/**
	 * Maybe remove [delete] value.
	 *
	 * @param string $value
	 * @param bool $sanitize
	 *
	 * @return string
	 */
	public function maybe_remove_delete_value( $value, $sanitize = true ) {
		return $this->is_delete_value( $value )
			? ''
			: ( $sanitize ? sanitize_text_field( $value ) : $value );
	}

	/**
	 * Define Contact Action Tokens.
	 *
	 * @return array
	 */
	public function define_contact_action_tokens() {
		return array(
			'CONTACT_ID' => array(
				'name'  => _x( 'Contact ID', 'Keap', 'uncanny-automator' ),
				'type' => 'int',
			),
			'CONTACT_FIRST_NAME' => array(
				'name'  => _x( 'Contact first name', 'Keap', 'uncanny-automator' ),
				'type' => 'text',
			),
			'CONTACT_LAST_NAME' => array(
				'name'  => _x( 'Contact last name', 'Keap', 'uncanny-automator' ),
				'type' => 'text',
			),
			'CONTACT_TAG_IDS' => array(
				'name'  => _x( 'Contact tag IDs', 'Keap', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Get Tag name(s) Token config.
	 *
	 * @return array
	 */
	public function define_tag_name_action_token() {
		return array(
			'TAG_NAME' => array(
				'name' => _x( 'Tag name(s)', 'Keap', 'uncanny-automator' ),
				'type' => 'text',
			)
		);
	}

	/**
	 * Hydrate contact tokens.
	 *
	 * @param  array $contact
	 *
	 * @return array
	 */
	public function hydrate_contact_tokens( $contact ) {
		$tags = $contact['tag_ids'] ?? '';
		return array(
			'CONTACT_ID'         => $contact['id'] ?? 0,
			'CONTACT_FIRST_NAME' => $contact['given_name'] ?? '',
			'CONTACT_LAST_NAME'  => $contact['family_name'] ?? '',
			'CONTACT_TAG_IDS'    => ! empty( $tags ) && is_array( $tags ) ? implode( ',', $tags ) : '',
		);
	}

	/**
	 * Get Tag names from IDs.
	 *
	 * @param  mixed $tag_ids - array | string
	 *
	 * @return mixed - array | string
	 */
	public function get_tag_names_from_ids( $tag_ids, $string = true ) {
		$ids   = is_array( $tag_ids ) ? $tag_ids : array_map( 'trim', explode( ',', $tag_ids ) );
		$tags  = $this->get_tags();
		$names = array();
		foreach ( $ids as $tag_id ) {
			if ( isset( $tags[ $tag_id ] ) ) {
				$names[] = $tags[ $tag_id ]['text'];
			}
		}

		return $string ? implode( ', ', $names ) : $names;
	}

	/**
	 * Prepare tag notices.
	 *
	 * @param  array $results
	 * @param  array $statuses
	 *
	 * @return mixed - array | false
	 */
	public function prepare_tag_notices( $results, $statuses ) {
		// Group errors
		$errors = array();
		foreach ( $results as $tag_id => $result ) {
			if ( 'SUCCESS' === $result ) {
				continue;
			}
			$errors[ $result ]   = isset( $errors[ $result ] ) ? $errors[ $result ] : array();
			$errors[ $result ][] = $tag_id;
		}

		// Prepare notices.
		$notices = ! empty( $errors ) ? array() : false;
		if ( ! empty( $errors ) ) {
			foreach ( $errors as $status => $tag_ids ) {
				$notices[] = sprintf(
					// Translators: %s Tag ID(s)
					$statuses[ $status ],
					$this->get_tag_names_from_ids( $tag_ids )
				);
			}
		}

		return $notices;
	}

}
