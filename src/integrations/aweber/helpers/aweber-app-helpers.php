<?php
//phpcs:disable PHPCompatibility.Operators.NewOperators.t_coalesceFound

namespace Uncanny_Automator\Integrations\Aweber;

use Exception;

/**
 * Class Aweber_App_Helpers
 *
 * @package Uncanny_Automator
 * @property Aweber_Api_Caller $api
 */
class Aweber_App_Helpers extends \Uncanny_Automator\App_Integrations\App_Helpers {

	/**
	 * Account field action meta key.
	 *
	 * @var string
	 */
	const ACTION_ACCOUNT_META_KEY = 'ACCOUNT';

	/**
	 * List field action meta key.
	 *
	 * @var string
	 */
	const ACTION_LIST_META_KEY = 'LIST';

	////////////////////////////////////////////////////////////
	// Abstract overrides
	////////////////////////////////////////////////////////////

	/**
	 * Prepare credentials for storage.
	 *
	 * @param array $credentials The credentials.
	 *
	 * @return array
	 */
	public function prepare_credentials_for_storage( $credentials ) {
		// Add date_added if not present (for token refresh tracking)
		if ( is_array( $credentials ) && ! isset( $credentials['date_added'] ) ) {
			$credentials['date_added'] = time();
		}

		return $credentials;
	}

	////////////////////////////////////////////////////////////
	// Ajax option fetch methods
	////////////////////////////////////////////////////////////

	/**
	 * Fetches all accounts.
	 *
	 * Callback from "wp_ajax_automator_aweber_accounts_fetch".
	 *
	 * @return void
	 */
	public function accounts_fetch() {

		Automator()->utilities->verify_nonce();

		try {

			$accounts = $this->api->get_accounts();
			$entries  = $accounts['data']['entries'] ?? array();
			$options  = array();

			foreach ( (array) $entries as $entry ) {
				$options[] = array(
					'value' => $entry['id'],
					'text'  => $entry['company'],
				);
			}

			$response = array(
				'success' => true,
				'options' => $options,
			);

		} catch ( Exception $e ) {

			$response = array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}

		wp_send_json( $response );
	}

	/**
	 * Fetch all lists.
	 *
	 * @return void
	 */
	public function lists_fetch() {

		Automator()->utilities->verify_nonce();

		$values = automator_filter_has_var( 'values', INPUT_POST )
			? automator_filter_input_array( 'values', INPUT_POST )
			: array();

		$account_id = sanitize_text_field( $values['ACCOUNT'] ?? '' );

		try {

			$lists   = $this->api->get_lists( $account_id );
			$entries = $lists['data']['entries'] ?? array();
			$options = array();

			foreach ( (array) $entries as $entry ) {
				$options[] = array(
					'value' => $entry['id'],
					'text'  => $entry['name'],
				);
			}

			$response = array(
				'success' => true,
				'options' => $options,
			);

		} catch ( Exception $e ) {

			$response = array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}

		wp_send_json( $response );
	}

	/**
	 * Fetch all custom fields.
	 *
	 * @return void
	 */
	public function custom_fields_fetch() {

		Automator()->utilities->verify_nonce();

		$values = automator_filter_has_var( 'values', INPUT_POST )
			? automator_filter_input_array( 'values', INPUT_POST )
			: array();

		$account_id = sanitize_text_field( $values['ACCOUNT'] ?? '' );
		$list_id    = sanitize_text_field( $values['LIST'] ?? '' );
		$rows       = array();

		try {

			$custom_fields = $this->api->get_custom_fields( $account_id, $list_id );
			$entries       = $custom_fields['data']['entries'] ?? array();

			foreach ( (array) $entries as $entry ) {
				$rows[] = array(
					'FIELD_ID'    => $entry['id'],
					'FIELD_NAME'  => $entry['name'],
					'FIELD_VALUE' => '',
				);
			}

			$response = array(
				'success' => true,
				'rows'    => $rows,
			);

		} catch ( Exception $e ) {

			$response = array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}

		wp_send_json( $response );
	}

	////////////////////////////////////////////////////////////
	// Recipe UI option config methods
	////////////////////////////////////////////////////////////

	/**
	 * Get account option config.
	 *
	 * @return array
	 */
	public function get_account_option_config() {
		return array(
			'option_code' => self::ACTION_ACCOUNT_META_KEY,
			'label'       => esc_html_x( 'Account', 'AWeber', 'uncanny-automator' ),
			'input_type'  => 'select',
			'options'     => array(),
			'required'    => true,
			'ajax'        => array(
				'endpoint' => 'automator_aweber_accounts_fetch',
				'event'    => 'on_load',
			),
		);
	}

	/**
	 * Get list option config.
	 *
	 * @return array
	 */
	public function get_list_option_config() {
		return array(
			'option_code' => self::ACTION_LIST_META_KEY,
			'label'       => esc_html_x( 'List', 'AWeber', 'uncanny-automator' ),
			'input_type'  => 'select',
			'options'     => array(),
			'required'    => true,
			'ajax'        => array(
				'endpoint'      => 'automator_aweber_list_fetch',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( self::ACTION_ACCOUNT_META_KEY ),
			),
		);
	}

	/**
	 * Get name option config.
	 *
	 * @param string $option_code The option code for the name field.
	 *
	 * @return array
	 */
	public function get_name_option_config( $option_code ) {
		return array(
			'option_code' => $option_code,
			'label'       => esc_html_x( 'Name', 'AWeber', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => true,
		);
	}

	/**
	 * Get email option config.
	 *
	 * @param string $option_code The option code for the email field.
	 * @param string $label       Optional custom label. Defaults to 'Email'.
	 *
	 * @return array
	 */
	public function get_email_option_config( $option_code, $label = '' ) {
		return array(
			'option_code' => $option_code,
			'label'       => ! empty( $label ) ? $label : esc_html_x( 'Email', 'AWeber', 'uncanny-automator' ),
			'input_type'  => 'email',
			'required'    => true,
		);
	}

	/**
	 * Get custom fields repeater option config.
	 *
	 * @return array
	 */
	public function get_custom_fields_option_config() {
		return array(
			'option_code'     => 'CUSTOM_FIELDS',
			'input_type'      => 'repeater',
			'relevant_tokens' => array(),
			'label'           => esc_html_x( 'Custom fields', 'Aweber', 'uncanny-automator' ),
			'required'        => false,
			'fields'          => array(
				array(
					'input_type'  => 'text',
					'option_code' => 'FIELD_ID',
					'label'       => esc_html_x( 'ID', 'Aweber', 'uncanny-automator' ),
					'read_only'   => true,
				),
				array(
					'input_type'  => 'text',
					'option_code' => 'FIELD_NAME',
					'label'       => esc_html_x( 'Name', 'Aweber', 'uncanny-automator' ),
					'read_only'   => true,
				),
				array(
					'input_type'  => 'text',
					'option_code' => 'FIELD_VALUE',
					'label'       => esc_html_x( 'Value', 'Aweber', 'uncanny-automator' ),
					'read_only'   => false,
				),
			),
			'hide_actions'    => true,
			'ajax'            => array(
				'event'          => 'parent_fields_change',
				'listen_fields'  => array( self::ACTION_LIST_META_KEY ),
				'endpoint'       => 'automator_aweber_custom_fields_fetch',
				'mapping_column' => 'FIELD_ID',
			),
		);
	}

	////////////////////////////////////////////////////////////
	// Parsed value validation methods
	////////////////////////////////////////////////////////////

	/**
	 * Get account ID from parsed values.
	 *
	 * @param array  $parsed   The parsed values.
	 * @param string $meta_key Optional meta key override.
	 *
	 * @return string
	 * @throws Exception If account is not set.
	 */
	public function get_account_from_parsed( $parsed, $meta_key = self::ACTION_ACCOUNT_META_KEY ) {
		if ( empty( $parsed[ $meta_key ] ) ) {
			throw new Exception( esc_html_x( 'Account is required.', 'AWeber', 'uncanny-automator' ) );
		}

		return sanitize_text_field( $parsed[ $meta_key ] );
	}

	/**
	 * Get list ID from parsed values.
	 *
	 * @param array  $parsed   The parsed values.
	 * @param string $meta_key Optional meta key override.
	 *
	 * @return string
	 * @throws Exception If list is not set.
	 */
	public function get_list_from_parsed( $parsed, $meta_key = self::ACTION_LIST_META_KEY ) {
		if ( empty( $parsed[ $meta_key ] ) ) {
			throw new Exception( esc_html_x( 'List is required.', 'AWeber', 'uncanny-automator' ) );
		}

		return sanitize_text_field( $parsed[ $meta_key ] );
	}

	/**
	 * Get and validate email from parsed values.
	 *
	 * @param array  $parsed   The parsed values.
	 * @param string $meta_key The meta key for the email field.
	 *
	 * @return string
	 * @throws Exception If email is not set or invalid.
	 */
	public function get_email_from_parsed( $parsed, $meta_key ) {
		if ( empty( $parsed[ $meta_key ] ) ) {
			throw new Exception( esc_html_x( 'Email is required.', 'AWeber', 'uncanny-automator' ) );
		}

		$email = sanitize_email( $parsed[ $meta_key ] );

		if ( false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new Exception(
				sprintf(
					// translators: %s: Email address.
					esc_html_x( 'The email address [%s] is invalid.', 'AWeber', 'uncanny-automator' ),
					esc_html( $parsed[ $meta_key ] )
				)
			);
		}

		return $email;
	}

	/**
	 * Get and validate name from parsed values.
	 *
	 * @param array  $parsed   The parsed values.
	 * @param string $meta_key The meta key for the name field.
	 *
	 * @return string
	 * @throws Exception If name is not set.
	 */
	public function get_name_from_parsed( $parsed, $meta_key ) {
		if ( empty( $parsed[ $meta_key ] ) ) {
			throw new Exception( esc_html_x( 'Name is required.', 'AWeber', 'uncanny-automator' ) );
		}

		return sanitize_text_field( $parsed[ $meta_key ] );
	}

	/**
	 * Process custom fields from action data.
	 *
	 * @param array $action_data The action data containing maybe_parsed.
	 *
	 * @return array Processed custom fields as key => value pairs.
	 */
	public function process_custom_fields( $action_data ) {
		$custom_fields = (array) json_decode( $action_data['maybe_parsed']['CUSTOM_FIELDS'] ?? '[]', true );
		$processed     = array();

		foreach ( $custom_fields as $custom_field ) {
			if ( ! empty( $custom_field['FIELD_NAME'] ) ) {
				$processed[ $custom_field['FIELD_NAME'] ] = $custom_field['FIELD_VALUE'] ?? '';
			}
		}

		return $processed;
	}
}
