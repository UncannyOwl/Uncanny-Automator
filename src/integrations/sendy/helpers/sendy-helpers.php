<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Sendy;

use Uncanny_Automator\Api_Server;
/**
 * Class Sendy_Helpers
 *
 * @package Uncanny_Automator
 */
class Sendy_Helpers {

	/**
	 * The helpers options object.
	 *
	 * @var string|object
	 */
	public $options = '';

	/**
	 * Saved API Option Settings.
	 *
	 * @var null|array
	 */
	private $api_settings = null;

	/**
	 * Settings tab id
	 *
	 * @var string|object
	 */
	public $settings_tab = 'sendy';

	/**
	 * Sendy_Helpers constructor.
	 */
	public function __construct() {
	}

	/**
	 * The wp_options table key for selecting the integration options.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'automator_sendy_api';

	/**
	 * Temporary wp_options table key for saving API Key on settings submit.
	 *
	 * @var string
	 */
	const KEY_OPTION_KEY = 'automator_sendy_api_key';

	/**
	 * Temporary wp_options table key for saving Sendy Installation URL on settings submit.
	 *
	 * @var string
	 */
	const URL_OPTION_KEY = 'automator_sendy_url';

	/**
	 * The public API edge.
	 *
	 * @var string
	 */
	const API_ENDPOINT = 'v2/sendy';

	/**
	 * The nonce.
	 *
	 * @var string
	 */
	const NONCE = 'automator_sendy_api_authentication';

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
		return $this->get_sendy_settings( 'api_key' );
	}

	/**
	 * Get Sendy URL Key.
	 *
	 * @return string
	 */
	public function get_sendy_url() {
		return $this->get_sendy_settings( 'url' );
	}

	/**
	 * Get Sendy Settings.
	 *
	 * @param string $key - key to get from settings
	 *
	 * @return mixed array|string
	 */
	public function get_sendy_settings( $key = '' ) {

		if ( is_null( $this->api_settings ) ) {

			$defaults = array(
				'api_key' => '',
				'url'     => '',
				'status'  => false,
				'error'   => '',
			);

			$settings = automator_get_option( self::OPTION_KEY, array() );

			// Merge defaults.
			$this->api_settings = wp_parse_args( $settings, $defaults );
		}

		// Return all settings.
		if ( empty( $key ) ) {
			return $this->api_settings;
		}

		// Return specific setting.
		return isset( $this->api_settings[ $key ] ) ? $this->api_settings[ $key ] : '';
	}

	/**
	 * Set Sendy Settings.
	 *
	 * @param mixed  $value
	 * @param mixed  $key string|false - key to set in settings or false to set all settings
	 *
	 * @return void
	 */
	public function set_sendy_setting( $value, $key = false ) {

		// Updating key.
		if ( ! empty( $key ) ) {
			$settings         = $this->get_sendy_settings();
			$settings[ $key ] = $value;
		} else {
			// Updating all settings.
			$settings = $value;
		}

		update_option( self::OPTION_KEY, $settings );
		$this->api_settings = $settings;
	}

	/**
	 * Integration status.
	 *
	 * @return string
	 */
	public function integration_status() {
		return $this->get_sendy_settings( 'status' ) ? 'success' : '';
	}

	/**
	 * Verify Sendy Settings.
	 *
	 * @return void
	 *
	 * @throws \Exception
	 */
	public function verify_sendy_settings() {

		$settings = $this->get_sendy_settings();
		$key      = get_option( self::KEY_OPTION_KEY );
		$url      = get_option( self::URL_OPTION_KEY );

		if ( $key !== $settings['api_key'] || $url !== $settings['url'] ) {
			$settings['api_key'] = $key;
			$settings['url']     = esc_url( rtrim( $url, '/' ) );
		}

		$settings['status'] = false;
		$settings['error']  = '';

		// Have Key and URL.
		if ( ! empty( $settings['api_key'] ) && ! empty( $settings['url'] ) ) {
			// Save updated settings.
			$this->set_sendy_setting( $settings );
			// Make API Call to get lists and validate URL and API Key.
			$lists = $this->get_lists( true );
			if ( $lists && is_array( $lists ) ) {
				// Update settings with status.
				$this->set_sendy_setting( true, 'status' );
				$this->set_sendy_setting( '', 'error' );
			}
		} elseif ( empty( $settings['api_key'] ) || empty( $settings['url'] ) ) {
			// Both are empty.
			if ( empty( $settings['api_key'] ) && empty( $settings['url'] ) ) {
				$settings['error'] = _x( 'Please enter a valid Sendy installation URL and API key.', 'Sendy', 'uncanny-automator' );
			} else {
				// Missing API Key.
				if ( empty( $settings['api_key'] ) ) {
					$settings['error'] = _x( 'Please enter a valid Sendy API key.', 'Sendy', 'uncanny-automator' );
				}
				// Missing / Invalid URL.
				if ( empty( $settings['url'] ) ) {
					$settings['error'] = _x( 'Please enter a valid Sendy installation URL.', 'Sendy', 'uncanny-automator' );
				}
			}
			// Update Settings with error.
			$this->set_sendy_setting( $settings );
		}

		// Delete temp options.
		delete_option( self::KEY_OPTION_KEY );
		delete_option( self::URL_OPTION_KEY );
	}

	/**
	 * Create and retrieve a disconnect url for Sendy Integration.
	 *
	 * @return string The disconnect uri.
	 */
	public function get_disconnect_url() {

		return add_query_arg(
			array(
				'action' => 'automator_sendy_disconnect_account',
				'nonce'  => wp_create_nonce( self::NONCE ),
			),
			admin_url( 'admin-ajax.php' )
		);

	}

	/**
	 * Disconnect Sendy integration.
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
		// Remove stored transients.
		delete_transient( 'automator_sendy/lists' );
	}

	/**
	 * Make API request.
	 *
	 * @param string $action
	 * @param mixed  $body
	 * @param mixed  $action_data
	 * @param bool   $check_for_errors
	 *
	 * @return array
	 */
	public function api_request( $action, $body = null, $action_data = null, $check_for_errors = true ) {

		$body              = is_array( $body ) ? $body : array();
		$body['action']    = $action;
		$body['api_key']   = $this->get_api_key();
		$body['sendy_url'] = $this->get_sendy_url();

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
	 * Get Lists.
	 *
	 * @param bool $refresh
	 *
	 * @return array
	 */
	public function get_lists( $refresh = false ) {

		if ( $refresh ) {
			delete_transient( 'automator_sendy_lists' );
		}

		return $this->get_transient_options( 'lists' );
	}

	/**
	 * Ajax get list options.
	 *
	 * @return json
	 */
	public function ajax_get_list_options() {

		Automator()->utilities->ajax_auth_check();

		$lists = $this->get_lists();

		wp_send_json( $lists );

		die();
	}

	/**
	 * Add contact to list.
	 *
	 * @param string $email
	 * @param string $list
	 * @param array  $fields
	 * @param array  $action_data
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function add_contact_to_list( $email, $list, $fields, $action_data ) {

		if ( ! isset( $fields['referrer'] ) ) {
			$fields['referrer'] = get_site_url();
		}

		$body = array(
			'email'  => $email,
			'list'   => $list,
			'fields' => $fields,
		);

		$response = $this->api_request( 'add_contact_to_list', $body, $action_data );

		return $response;
	}

	/**
	 * Unsubscribe contact from list.
	 *
	 * @param string $email
	 * @param string $list
	 * @param array  $action_data
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function unsubscribe_contact_from_list( $email, $list, $action_data ) {

		$body = array(
			'email' => $email,
			'list'  => $list,
		);

		$response = $this->api_request( 'unsubscribe_contact_from_list', $body, $action_data );

		return $response;
	}

	/**
	 * Delete contact from list.
	 *
	 * @param string $email
	 * @param string $list
	 * @param array  $action_data
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function delete_contact_from_list( $email, $list, $action_data ) {

		$body = array(
			'email' => $email,
			'list'  => $list,
		);

		$response = $this->api_request( 'delete_contact_from_list', $body, $action_data );

		return $response;
	}

	/**
	 * Get transient options - Retrieve from transient or loop api requests with offset and limits.
	 *
	 * @param string $type - lists
	 *
	 * @return array
	 */
	public function get_transient_options( $type ) {

		$transient = "automator_sendy_{$type}";
		$options   = get_transient( $transient );

		if ( $options ) {
			return $options;
		}

		$results  = array();
		$error_id = "sync_{$type}";

		try {
			if ( 'lists' === $type ) {
				$response = $this->api_request( 'get_lists' );
			} else {
				throw new \Exception( esc_html_x( 'Invalid type', 'Sendy', 'uncanny-automator' ) );
			}

			$items = ! empty( $response['data'][ $type ] ) ? $response['data'][ $type ] : array();
			if ( empty( $items ) ) {
				$message = isset( $response['data']['error'] ) ? $response['data']['error'] : sprintf(
					/* translators: %s - type of items */
					_x( 'No %s were found', 'Sendy API', 'uncanny-automator' ),
					$type
				);
				throw new \Exception( $message );
			}
		} catch ( \Exception $e ) {
			automator_log( $e->getMessage(), "Sendy::{$error_id} Error", true, 'sendy' );
			$this->check_connection_error( $e->getMessage() );

			return false;
		}

		// Generate options array.
		$options = array();
		foreach ( $items as $item ) {
			$options[] = array(
				'value' => $item['id'],
				'text'  => $item['name'],
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
		set_transient( $transient, $options, DAY_IN_SECONDS );

		return $options;
	}

	/**
	 * Re-Sync Transient Data.
	 *
	 * @return json
	 */
	public function ajax_sync_transient_data() {

		if ( ! wp_verify_nonce( automator_filter_input( 'nonce', INPUT_POST ), 'uncanny_automator' ) ) {
			wp_send_json_error( array( 'message' => _x( 'Invalid request', 'Sendy', 'uncanny-automator' ) ) );
		}

		$key = automator_filter_input( 'key', INPUT_POST );
		if ( ! $key || ! in_array( $key, array( 'lists' ), true ) ) {
			wp_send_json_error( array( 'message' => _x( 'Invalid key', 'Sendy', 'uncanny-automator' ) ) );
		}

		// Delete existing transient.
		delete_transient( "automator_sendy_{$key}" );

		// Get selected options.
		switch ( $key ) {
			case 'lists':
				$options = $this->get_lists();
				break;
		}

		if ( empty( $options ) ) {
			wp_send_json_error( array( 'message' => _x( 'No data returned from the API', 'Sendy', 'uncanny-automator' ) ) );
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
	 * Check response for errors.
	 *
	 * @param mixed $response
	 *
	 * @return void
	 */
	public function check_for_errors( $response ) {

		if ( ! empty( $response['data']['error'] ) ) {
			$this->check_connection_error( $response['data']['error'] );
			throw new \Exception( $response['data']['error'], 400 );
		}

		if ( $response['statusCode'] >= 400 ) {
			$message = isset( $response['data']['message'] ) ? $response['data']['message'] : _x( 'Sendy API Error', 'Sendy', 'uncanny-automator' );
			throw new \Exception( $message, 400 );
		}

	}

	/**
	 * Disable connection.
	 *
	 * @param string $error
	 *
	 * @return void
	 */
	public function check_connection_error( $error ) {

		$connection_error = false;
		// Invalid API key.
		if ( false !== strpos( $error, 'Invalid API key' ) ) {
			$connection_error = true;
		}
		// Invalid URL.
		if ( false !== strpos( $error, 'Invalid Sendy URL' ) ) {
			$connection_error = true;
		}

		if ( $error === 'No brands found in Sendy account.' ) {
			$connection_error = true;
		}

		if ( $error === 'No lists found in Sendy account.' ) {
			$connection_error = true;
		}

		if ( $connection_error ) {
			$this->set_sendy_setting( false, 'status' );
			$this->set_sendy_setting( $error, 'error' );
		}
	}

	/**
	 * Get email from parsed.
	 *
	 * @param array  $parsed
	 * @param string $meta_key
	 *
	 * @return string
	 */
	public function get_email_from_parsed( $parsed, $meta_key ) {

		if ( ! isset( $parsed[ $meta_key ] ) ) {
			throw new \Exception( esc_html_x( 'Missing email', 'Sendy', 'uncanny-automator' ) );
		}

		$email = sanitize_text_field( $parsed[ $meta_key ] );

		if ( ! $email || ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new \Exception( esc_html_x( 'Invalid email', 'Sendy', 'uncanny-automator' ) );
		}

		return $email;
	}

	/**
	 * Get list from parsed.
	 *
	 * @param array  $parsed
	 * @param string $meta_key
	 *
	 * @return mixed
	 */
	public function get_list_from_parsed( $parsed, $meta_key ) {

		if ( ! isset( $parsed[ $meta_key ] ) ) {
			throw new \Exception( esc_html_x( 'List is required.', 'Sendy', 'uncanny-automator' ) );
		}

		$list_id = sanitize_text_field( $parsed[ $meta_key ] );

		if ( ! $list_id ) {
			throw new \Exception( esc_html_x( 'List is required.', 'Sendy', 'uncanny-automator' ) );
		}

		return $list_id;
	}

	/**
	 * Get class const.
	 *
	 * @param string $const
	 *
	 * @return string
	 */
	public function get_const( $const ) {
		return constant( 'self::' . $const );
	}
}
