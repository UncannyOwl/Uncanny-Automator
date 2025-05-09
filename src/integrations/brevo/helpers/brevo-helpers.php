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
	 * The account details.
	 *
	 * @var array
	 */
	private $account_details = array(
		'company' => '',
		'email'   => '',
		'status'  => '',
		'error'   => '',
	);

	/**
	 * The wp_options table key for selecting the integration options.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'automator_brevo_api_key';

	/**
	 * The wp_options table key for selecting the integration account details.
	 *
	 * @var string
	 */
	const ACCOUNT_KEY = 'automator_brevo_account';

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
	 * Get Account Details.
	 *
	 * @return array
	 */
	public function get_saved_account_details() {

		// No API key set return defaults.
		$api_key = $this->get_api_key();
		if ( empty( $api_key ) ) {
			return $this->account_details;
		}

		// Get account details.
		$account = automator_get_option( self::ACCOUNT_KEY, false );

		// Legacy check.
		if ( ! $account ) {
			$account = $this->get_account();
		}

		return $account;
	}

	/**
	 * Get class const.
	 *
	 * @param  string $const
	 *
	 * @return string
	 */
	public function get_const( $const_name ) {
		return constant( 'self::' . $const_name );
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

		// Check user capabilities.
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
		// Remove the stored options.
		automator_delete_option( self::OPTION_KEY );
		automator_delete_option( self::ACCOUNT_KEY );
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

		// Check for Legacy invalid key.
		if ( 0 === strpos( $api_key, $this->get_invalid_key_message() ) ) {
			$account['error'] = $this->get_invalid_key_message();
			automator_update_option( self::ACCOUNT_KEY, $account );
			return $account;
		}

		// Get account.
		try {
			$response = $this->api_request( 'get_account', null, null, false );
		} catch ( \Exception $e ) {
			$error            = $e->getMessage();
			$account['error'] = ! empty( $error ) ? $error : esc_html_x( 'Brevo API Error', 'Brevo', 'uncanny-automator' );
			automator_update_option( self::ACCOUNT_KEY, $account );

			return $account;
		}

		// Success.
		if ( ! empty( $response['data']['companyName'] ) ) {
			$account['company'] = $response['data']['companyName'];
			$account['email']   = $response['data']['email'];
			$account['status']  = 'success';
		}

		// Check for invalid key.
		if ( ! empty( $response['data']['code'] ) ) {
			if ( 'unauthorized' === $response['data']['code'] ) {
				$account['status'] = '';
				// Check if error is in regards to IP address.
				$account['error'] = false !== strpos( $response['data']['error'], 'unrecognised' )
					? $response['data']['error']
					: $this->get_invalid_key_message() . $api_key;
			}
		}

		automator_update_option( self::ACCOUNT_KEY, $account );

		return $account;
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
	 * @param  array $action_data
	 *
	 * @return array
	 */
	public function create_contact( $email, $attributes, $update_enabled, $action_data ) {

		$contact = array(
			'attributes'    => $attributes,
			'updateEnabled' => $update_enabled ? true : false,
			'email'         => $email,
		);

		if ( empty( $contact['attributes'] ) ) {
			unset( $contact['attributes'] );
		}

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
				throw new \Exception(
					esc_html_x( 'Contact with that email already exists', 'Brevo', 'uncanny-automator' )
				);
			}
			// TODO REVIEW - could compare attributes and update only if needed.
			return $this->create_contact( $email, $attributes, $update_existing, $action_data );
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
					esc_html_x( 'No attributes were returned from the API', 'Brevo API', 'uncanny-automator' )
				);
			}
		} catch ( \Exception $e ) {
			automator_log( $e->getMessage(), 'Brevo::get_contact_attributes Error', true, 'brevo' );
			return false;
		}
		$defaults   = array( 'FIRSTNAME', 'LASTNAME', 'SMS', 'DOUBLE_OPT-IN', 'OPT_IN' );
		$attributes = array();
		foreach ( $response['data']['attributes'] as $attribute ) {
			// Add check for Multi-Choice and Category ( enumeration ) select options.
			$type     = $attribute['type'] ?? '';
			$type     = empty( $type ) && isset( $attribute['enumeration'] ) ? 'select' : $type;
			$multiple = 'multiple-choice' === $type;
			$type     = $multiple ? 'select' : $type;
			$options  = false;

			if ( 'global' === $attribute['category'] || ! empty( $attribute['calculatedValue'] ) || empty( $type ) ) {
				continue;
			}
			if ( in_array( $attribute['name'], $defaults, true ) ) {
				// Ignore default attributes.
				continue;
			}

			if ( 'float' === $type || 'id' === $type ) {
				$type = 'number';
			}
			if ( 'boolean' === $type ) {
				$type = 'checkbox';
			}
			if ( 'select' === $type ) {
				$options = $multiple ? $attribute['multiCategoryOptions'] : $attribute['enumeration'];
			}

			$attributes[] = array(
				'value'    => $attribute['name'],
				'text'     => $attribute['name'],
				'type'     => $type,
				'options'  => $options,
				'multiple' => $multiple,
			);
		}

		usort(
			$attributes,
			function ( $a, $b ) {
				return strcmp( $a['text'], $b['text'] );
			}
		);

		set_transient( $transient, $attributes, DAY_IN_SECONDS );

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
						// translators: %s - type of item templates or lists
						esc_html_x( 'No %s were found', 'Brevo API', 'uncanny-automator' ),
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
			wp_send_json_error( array( 'message' => esc_html_x( 'Invalid request', 'Brevo', 'uncanny-automator' ) ) );
		}

		$key = automator_filter_input( 'key', INPUT_POST );
		if ( ! $key || ! in_array( $key, array( 'templates', 'contacts/lists', 'contacts/attributes' ), true ) ) {
			wp_send_json_error( array( 'message' => esc_html_x( 'Invalid key', 'Brevo', 'uncanny-automator' ) ) );
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
			wp_send_json_error( array( 'message' => esc_html_x( 'No data returned from the API', 'Brevo', 'uncanny-automator' ) ) );
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
			throw new \Exception( esc_html( $response['data']['error'] ), 400 );
		}

		if ( $response['statusCode'] >= 400 ) {
			$message = isset( $response['data']['message'] ) ? $response['data']['message'] : esc_html_x( 'Brevo API Error', 'Brevo', 'uncanny-automator' );
			throw new \Exception( esc_html( $message ), 400 );
		}

		if ( isset( $response['data']['code'] ) && ! empty( $response['data']['code'] ) ) {
			if ( 'unauthorized' === $response['data']['code'] ) {
				throw new \Exception( esc_html( $this->get_invalid_key_message() ), 400 );
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

	/**
	 * Get the invalid key message.
	 *
	 * @return string
	 */
	public function get_invalid_key_message() {
		return esc_html_x( 'Invalid API Key : ', 'Brevo', 'uncanny-automator' );
	}
}
