<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Brevo;

use Uncanny_Automator\Api_Server;
use WP_REST_Response;

/**
 * Class Brevo_Helpers
 *
 * @package Uncanny_Automator
 */
class Brevo_Helpers {

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
	public $settings_tab = 'brevo';

	/**
	 * The invalid key message.
	 *
	 * @var string
	 */
	public $invalid_key_message = '';

	/**
	 * Brevo_Helpers constructor.
	 */
	public function __construct() {

		$this->invalid_key_message = _x( 'Invalid API Key : ', 'Brevo', 'uncanny-automator' );

	}

	/**
	 * The wp_options table key for selecting the integration options.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'automator_brevo_api_key';

	/**
	 * The public API edge.
	 *
	 * @var string
	 */
	const API_ENDPOINT = 'v2/brevo';

	/**
	 * The public API edge.
	 *
	 * @var string
	 */
	const WEBHOOK = '/brevo/';

	/**
	 * The nonce.
	 *
	 * @var string
	 */
	const NONCE = 'automator_brevo_api_authentication';

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
	 * Get class const.
	 *
	 * @param  string $const
	 *
	 * @return string
	 */
	public function get_const( $const ) {
		return constant( 'self::' . $const );
	}

	/**
	 * Check if API Key Invalid.
	 *
	 * @return bool
	 */
	public function is_api_key_invalid() {

		$api_key = $this->get_api_key();
		if ( empty( $api_key ) ) {
			return true;
		}

		return 0 === strpos( $api_key, $this->invalid_key_message ) ? true : false;
	}

	/**
	 * Integration status.
	 *
	 * @return string
	 */
	public function integration_status() {

		if ( $this->is_api_key_invalid() ) {
			return '';
		}

		try {
			$is_account_connected = $this->get_account();
		} catch ( \Exception $e ) {
			$is_account_connected = false;
		}

		return $is_account_connected ? 'success' : '';
	}

	/**
	 * Create and retrieve a disconnect url for Brevo Integration.
	 *
	 * @return string The disconnect uri.
	 */
	public function get_disconnect_url() {

		return add_query_arg(
			array(
				'action' => 'automator_brevo_disconnect_account',
				'nonce'  => wp_create_nonce( self::NONCE ),
			),
			admin_url( 'admin-ajax.php' )
		);

	}

	/**
	 * Disconnect Brevo integration.
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
		// Remove any stored transients.
		delete_transient( 'automator_brevo_account' );
		delete_transient( 'automator_brevo_contacts/lists' );
		delete_transient( 'automator_brevo_contacts/attributes' );
		delete_transient( 'automator_brevo_templates' );
	}

	/**
	 * Make API request.
	 *
	 * @param  string $action
	 * @param  mixed $body
	 * @param  mixed $action_data
	 * @param  bool $check_for_errors
	 * @return array
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
	 * Get account - validates the connection and return the account info
	 *
	 * @return mixed array|false
	 */
	public function get_account() {

		$transient = 'automator_brevo_account';
		$account   = get_transient( $transient );

		if ( ! empty( $account ) ) {
			return $account;
		}

		$response = $this->api_request( 'get_account', null, null, false );

		// Success.
		if ( ! empty( $response['data']['companyName'] ) ) {

			$account = array(
				'company' => $response['data']['companyName'],
				'email'   => $response['data']['email'],
			);

			set_transient( $transient, $account, 60 * 60 * 24 );

			return $account;
		}

		// Check for invalid key.
		if ( ! empty( $response['data']['code'] ) ) {

			if ( 'unauthorized' === $response['data']['code'] ) {
				// Re-save the invalid key with the invalid message.
				$api_key = $this->get_api_key();
				$api_key = "{$this->invalid_key_message}{$api_key}";
				update_option( self::OPTION_KEY, $api_key );
			}

			return false;
		}

		return false;
	}

	/**
	 * Get Contact.
	 *
	 * @param  mixed (ID, Email or SMS) $identifier
	 *
	 * @return array
	 */
	public function get_contact( $identifier ) {

		$body = array(
			'identifier' => $identifier,
		);

		$response = $this->api_request( 'get_contact', $body, null, false );

		return ! empty( $response['data']['id'] ) ? $response['data'] : false;
	}

	/**
	 * Create contact.
	 *
	 * @param  string $email
	 * @param  array $attributes
	 * @param  bool $update_enabled
	 *
	 * @return array
	 */
	public function create_contact( $email, $attributes, $update_enabled, $action_data ) {

		$contact = array(
			'attributes'    => $attributes,
			'updateEnabled' => $update_enabled,
			'email'         => $email,
		);

		$body = array(
			'contact' => wp_json_encode( $contact ),
		);

		$response = $this->api_request( 'create_contact', $body, $action_data );

		return $response;
	}

	/**
	 * Create contact with double optin.
	 *
	 * @param  string $email
	 * @param  array $attributes
	 * @param  int $template_id
	 * @param  string $redirect_url
	 * @param  int $list_id
	 * @param  bool $update_enabled
	 */
	public function create_contact_with_double_optin( $email, $attributes, $template_id, $redirect_url, $list_id, $update_existing, $action_data ) {

		// Check if contact exists.
		$contact = $this->get_contact( $email );
		if ( ! empty( $contact ) ) {
			if ( ! $update_existing ) {
				throw new \Exception( _x( 'Contact with that email already exists', 'Brevo', 'uncanny-automator' ) );
			}
			// TODO REVIEW - could compare attributes and update only if needed.
			return $this->create_contact( $email, $attributes, $update_existing );
		}

		// Create contact DOI.
		$body = array(
			'contact' => wp_json_encode(
				array(
					'attributes'     => $attributes,
					'email'          => $email,
					'templateId'     => (int) $template_id,
					'redirectionUrl' => $redirect_url,
					'includeListIds' => array( (int) $list_id ),
				)
			),
		);

		$response = $this->api_request( 'create_contact_with_doi', $body, $action_data );

		return $response;
	}

	/**
	 * Delete contact.
	 *
	 * @param  string $email
	 *
	 * @return array
	 */
	public function delete_contact( $email, $action_data ) {

		$body = array(
			'identifier' => $email,
		);

		$response = $this->api_request( 'delete_contact', $body, $action_data );

		return $response;
	}

	/**
	 * Get contact attributes.
	 *
	 * @return array
	 */
	public function get_contact_attributes() {

		$transient  = 'automator_brevo_contacts/attributes';
		$attributes = get_transient( $transient );

		if ( ! empty( $attributes ) ) {
			return $attributes;
		}

		try {
			$response = $this->api_request( 'get_contact_attributes' );

			if ( ! isset( $response['data']['attributes'] ) ) {
				throw new \Exception(
					_x( 'No attributes were returned from the API', 'Brevo API', 'uncanny-automator' )
				);
			}
		} catch ( \Exception $e ) {
			automator_log( $e->getMessage(), 'Brevo::get_contact_attributes Error', true, 'brevo' );
			return false;
		}
		$defaults   = array( 'FIRSTNAME', 'LASTNAME', 'SMS', 'DOUBLE_OPT-IN', 'OPT_IN' );
		$attributes = array();
		foreach ( $response['data']['attributes'] as $attribute ) {
			if ( 'global' === $attribute['category'] || ! empty( $attribute['calculatedValue'] ) || empty( $attribute['type'] ) ) {
				continue;
			}
			if ( in_array( $attribute['name'], $defaults, true ) ) {
				// Ignore default attributes.
				continue;
			}

			$type = $attribute['type']; //text date float id boolean
			if ( 'float' === $type || 'id' === $type ) {
				$type = 'number';
			}
			if ( 'boolean' === $type ) {
				$type = 'checkbox';
			}

			$attributes[] = array(
				'value' => $attribute['name'],
				'text'  => $attribute['name'],
				'type'  => $type,
			);
		}

		usort(
			$attributes,
			function( $a, $b ) {
				return strcmp( $a['text'], $b['text'] );
			}
		);

		set_transient( $transient, $attributes, HOUR_IN_SECONDS );

		return $attributes;
	}

	/**
	 * Get list options.
	 *
	 * @return array
	 */
	public function get_lists() {
		return $this->get_transient_options( 'contacts/lists' );
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
	 * @param  string $email
	 * @param  int $list_id
	 *
	 * @return array
	 */
	public function add_contact_to_list( $email, $list_id, $action_data ) {

		$emails = array( 'emails' => array( $email ) );

		$body = array(
			'identifiers' => wp_json_encode( $emails ),
			'list_id'     => (int) $list_id,
		);

		$response = $this->api_request( 'add_contact_to_list', $body, $action_data );

		return $response;
	}

	/**
	 * Remove contact from list.
	 *
	 * @param  string $email
	 * @param  int $list_id
	 *
	 * @return array
	 */
	public function remove_contact_from_list( $email, $list_id, $action_data ) {

		$emails = array( 'emails' => array( $email ) );

		$body = array(
			'identifiers' => wp_json_encode( $emails ),
			'list_id'     => (int) $list_id,
		);

		$response = $this->api_request( 'remove_contact_from_list', $body, $action_data );

		return $response;
	}

	/**
	 * Get templates.
	 *
	 * @return array
	 */
	public function get_templates() {
		return $this->get_transient_options( 'templates' );
	}

	/**
	 * Ajax get templates.
	 *
	 * @return json
	 */
	public function ajax_get_templates() {

		Automator()->utilities->ajax_auth_check();

		$templates = $this->get_templates();

		wp_send_json( $templates );

		die();
	}

	/**
	 * Get transient options - Retrieve from transient or loop api requests with offset and limits.
	 *
	 * @param  string $type - templates or contacts/lists
	 *
	 * @return array
	 */
	public function get_transient_options( $type ) {

		$transient = "automator_brevo_{$type}";
		$options   = get_transient( $transient );

		if ( $options ) {
			return $options;
		}

		$results  = array();
		$param    = 'templates' === $type ? $type : 'lists';
		$error_id = "sync_{$param}";

		try {
			if ( 'templates' === $type ) {
				$response = $this->api_request( 'get_templates' );
			} else {
				$response = $this->api_request( 'get_lists' );
			}

			$items = ! empty( $response['data'][ $param ] ) ? $response['data'][ $param ] : array();
			if ( empty( $items ) ) {
				throw new \Exception(
					sprintf(
						/* translators: %s - type of item templates or lists */
						_x( 'No %s were found', 'Brevo API', 'uncanny-automator' ),
						$param
					)
				);
			}
		} catch ( \Exception $e ) {
			automator_log( $e->getMessage(), "Brevo::{$error_id} Error", true, 'brevo' );
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
			function( $a, $b ) {
				return strcmp( $a['text'], $b['text'] );
			}
		);

		// Set transient.
		set_transient( $transient, $options, HOUR_IN_SECONDS );

		return $options;
	}

	/**
	 * Re-Sync Transient Data.
	 *
	 * @return json
	 */
	public function ajax_sync_transient_data() {

		if ( ! wp_verify_nonce( automator_filter_input( 'nonce', INPUT_POST ), 'uncanny_automator' ) ) {
			wp_send_json_error( array( 'message' => _x( 'Invalid request', 'Brevo', 'uncanny-automator' ) ) );
		}

		$key = automator_filter_input( 'key', INPUT_POST );
		if ( ! $key || ! in_array( $key, array( 'templates', 'contacts/lists', 'contacts/attributes' ), true ) ) {
			wp_send_json_error( array( 'message' => _x( 'Invalid key', 'Brevo', 'uncanny-automator' ) ) );
		}

		// Delete existing transient.
		delete_transient( "automator_brevo_{$key}" );

		// Get selected options.
		switch ( $key ) {
			case 'templates':
				$options = $this->get_templates();
				break;
			case 'contacts/lists':
				$options = $this->get_lists();
				break;
			case 'contacts/attributes':
				$options = $this->get_contact_attributes();
				break;
		}

		if ( empty( $options ) ) {
			wp_send_json_error( array( 'message' => _x( 'No data returned from the API', 'Brevo', 'uncanny-automator' ) ) );
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
	 * @param  mixed $response
	 *
	 * @return void
	 */
	public function check_for_errors( $response ) {

		if ( ! empty( $response['data']['error'] ) ) {
			throw new \Exception( $response['data']['error'], 400 );
		}

		if ( $response['statusCode'] >= 400 ) {
			$message = isset( $response['data']['message'] ) ? $response['data']['message'] : _x( 'Brevo API Error', 'Brevo', 'uncanny-automator' );
			throw new \Exception( $message, 400 );
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
			throw new \Exception( esc_html_x( 'Missing email', 'Brevo', 'uncanny-automator' ) );
		}

		$email = sanitize_text_field( $parsed[ $meta_key ] );

		if ( ! $email || ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new \Exception( esc_html_x( 'Invalid email', 'Brevo', 'uncanny-automator' ) );
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
			throw new \Exception( esc_html_x( 'List ID is required.', 'Brevo', 'uncanny-automator' ) );
		}

		$list_id = sanitize_text_field( $parsed[ $meta_key ] );

		if ( ! $list_id ) {
			throw new \Exception( esc_html_x( 'List ID is required.', 'Brevo', 'uncanny-automator' ) );
		}

		return $list_id;
	}
}
