<?php
namespace Uncanny_Automator\Integrations\Google_Sheet;

use Uncanny_Automator\App_Integrations\App_Helpers;
use Exception;

/**
 * Class Google_Sheet_Helpers
 *
 * @property Google_Sheet_Api_Caller $api
 */
class Google_Sheet_Helpers extends App_Helpers {

	/**
	 * The uap_options table key for selecting the integration options.
	 * Using the original legacy key to preserve existing connections.
	 *
	 * @var string
	 */
	const OPTION_KEY = '_uncannyowl_google_sheet_settings';

	/**
	 * "Account info" transient key.
	 *
	 * @var string RESOURCE_OWNER_KEY
	 */
	const RESOURCE_OWNER_KEY = 'automator_google_sheet_user_info';

	/**
	 * The scope for fetching users google drives (legacy).
	 */
	const LEGACY_SCOPE_DRIVE = 'https://www.googleapis.com/auth/drive';

	/**
	 * The scope for fetching users google drives.
	 */
	const SCOPE_DRIVE = 'https://www.googleapis.com/auth/drive.file';

	/**
	 * The scope for fetching users spreadsheets.
	 */
	const SCOPE_SPREADSHEETS = 'https://www.googleapis.com/auth/spreadsheets';

	/**
	 * The scope for fetching profile info.
	 */
	const SCOPE_USERINFO = 'https://www.googleapis.com/auth/userinfo.profile';

	/**
	 * The scope for fetching user email.
	 */
	const SCOPE_USER_EMAIL = 'https://www.googleapis.com/auth/userinfo.email';

	/**
	 * The API endpoint.
	 */
	const API_ENDPOINT = 'v2/google';

	/**
	 * The spreadsheets option key.
	 */
	const SPREADSHEETS_OPTIONS_KEY = 'automator_google_sheets_spreadsheets';

	/**
	 * Hash string for backwards compatibility
	 *
	 * @var string
	 */
	const HASH_STRING = 'Uncanny Automator Pro Google Sheet Integration';


	//
	// Abstract overrides
	//

	/**
	 * Set properties for the helpers.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Set the credentials option name to the legacy key for backward compatibility
		$this->set_credentials_option_name( self::OPTION_KEY );
	}

	/**
	 * Method get_account_info - Override to use transients.
	 *
	 * @return array The user info.
	 */
	public function get_account_info() {
		$user_info = array(
			'avatar_uri' => '',
			'name'       => '',
			'email'      => '',
		);

		$transient_key   = '_uncannyowl_google_user_info';
		$saved_user_info = get_transient( $transient_key );

		if ( false !== $saved_user_info ) {
			return $saved_user_info;
		}

		try {
			$user = $this->api->get_user_info();

			if ( empty( $user['data'] ) ) {
				return $user_info;
			}

			$user_info['name']       = $user['data']['name'];
			$user_info['avatar_uri'] = $user['data']['picture'];
			$user_info['email']      = $user['data']['email'];
			set_transient( $transient_key, $user_info, DAY_IN_SECONDS );
		} catch ( Exception $e ) {
			return $user_info;
		}

		return $user_info;
	}

	/**
	 * Store account info - Override to use transients.
	 *
	 * @param array $user_info The user info.
	 *
	 * @return void
	 */
	public function store_account_info( $user_info ) {
		set_transient( self::RESOURCE_OWNER_KEY, $user_info, DAY_IN_SECONDS );
	}

	/**
	 * Delete account info - Override to use transients.
	 *
	 * @return void
	 */
	public function delete_account_info() {
		delete_transient( self::RESOURCE_OWNER_KEY );
	}

	//
	// Integration specific methods
	//

	/**
	 * Get the stored spreadsheets.
	 *
	 * @return array
	 */
	public function get_spreadsheets() {
		return (array) automator_get_option( self::SPREADSHEETS_OPTIONS_KEY, array() );
	}

	/**
	 * Store spreadsheets.
	 *
	 * @param array $spreadsheets The spreadsheets to store.
	 *
	 * @return boolean
	 */
	public function store_spreadsheets( $spreadsheets ) {
		return automator_update_option( self::SPREADSHEETS_OPTIONS_KEY, $spreadsheets, false );
	}

	/**
	 * Clears the connection data.
	 *
	 * @return true
	 */
	public function clear_connection() {
		$this->delete_credentials();
		$this->delete_account_info();
		return true;
	}

	//
	// AJAX methods
	//

	/**
	 * AJAX: Fetch spreadsheets for dropdown.
	 *
	 * @return void
	 */
	public function fetch_spreadsheets_ajax() {
		Automator()->utilities->ajax_auth_check();

		try {
			$spreadsheets = $this->get_spreadsheets();
			$options      = array(
				array(
					'text'  => esc_html_x( 'Please select a spreadsheet', 'Google Sheets', 'uncanny-automator' ),
					'value' => '',
				),
			);

			foreach ( $spreadsheets as $spreadsheet ) {
				$options[] = array(
					'value' => $spreadsheet['id'],
					'text'  => $spreadsheet['name'],
				);
			}

			wp_send_json(
				array(
					'success' => true,
					'options' => $options,
				)
			);

		} catch ( Exception $e ) {
			wp_send_json(
				array(
					'success' => false,
					'error'   => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * AJAX: Fetch worksheets for dropdown.
	 *
	 * @return void
	 */
	public function fetch_worksheets_ajax() {
		Automator()->utilities->ajax_auth_check();

		$values         = automator_filter_input_array( 'values', INPUT_POST );
		$spreadsheet_id = sanitize_text_field( $values['GSSPREADSHEET'] ?? '' );

		if ( empty( $spreadsheet_id ) ) {
			wp_send_json(
				array(
					'success' => false,
					'error'   => esc_html_x( 'Please select a spreadsheet', 'Google Sheets', 'uncanny-automator' ),
				)
			);
		}

		$worksheets = $this->get_worksheets_from_spreadsheet( $spreadsheet_id );

		wp_send_json(
			array(
				'success' => true,
				'options' => $worksheets,
			)
		);
	}

	/**
	 * AJAX: Fetch worksheet columns.
	 *
	 * @return void
	 */
	public function fetch_worksheets_columns_ajax() {
		Automator()->utilities->ajax_auth_check();

		$fields         = array();
		$values         = automator_filter_input_array( 'values', INPUT_POST );
		$spreadsheet_id = sanitize_text_field( $values['GSSPREADSHEET'] ?? '' );
		$worksheet_id   = sanitize_text_field( $values['GSWORKSHEET'] ?? '' );

		if ( empty( $spreadsheet_id ) ) {
			wp_send_json(
				array(
					'success' => false,
					'error'   => esc_html_x( 'Please select a spreadsheet', 'Google Sheets', 'uncanny-automator' ),
				)
			);
		}

		if ( empty( $worksheet_id ) ) {
			wp_send_json(
				array(
					'success' => false,
					'error'   => esc_html_x( 'Please select a worksheet', 'Google Sheets', 'uncanny-automator' ),
				)
			);
		}

		// Backwards compatibility.
		$worksheet_id = $this->calculate_hash( $worksheet_id );
		$response     = $this->api->get_rows( $spreadsheet_id, $worksheet_id );

		if ( false === $response->success ) {
			wp_send_json(
				array(
					'success' => false,
					'error'   => $response->error,
				)
			);
		}

		$fields = array();

		if ( ! empty( $response ) && ! empty( $response->samples ) ) {
			$rows = array_shift( $response->samples );

			foreach ( $rows as $row ) {
				$fields[] = array(
					'GS_COLUMN_NAME'  => $row['key'],
					'GS_COLUMN_VALUE' => '',
				);
			}
		}

		wp_send_json(
			array(
				'success' => true,
				'rows'    => $fields,
			)
		);
	}

	/**
	 * AJAX: Fetch worksheet columns for search.
	 *
	 * @return void
	 */
	public function fetch_worksheets_columns_search_ajax() {
		Automator()->utilities->ajax_auth_check();

		$values         = automator_filter_input_array( 'values', INPUT_POST );
		$spreadsheet_id = sanitize_text_field( $values['GSSPREADSHEET'] ?? '' );
		$worksheet_id   = sanitize_text_field( $values['GSWORKSHEET'] ?? '' );

		if ( empty( $spreadsheet_id ) || empty( $worksheet_id ) ) {
			$error = empty( $spreadsheet_id )
				? esc_html_x( 'Please select a spreadsheet', 'Google Sheets', 'uncanny-automator' )
				: esc_html_x( 'Please select a worksheet', 'Google Sheets', 'uncanny-automator' );

			wp_send_json(
				array(
					'success' => false,
					'error'   => $error,
				)
			);
		}

		$worksheet_id = $this->calculate_hash( $worksheet_id );
		$fields       = $this->api->get_columns( $spreadsheet_id, $worksheet_id );

		wp_send_json(
			array(
				'success' => true,
				'options' => $fields,
			)
		);
	}

	//
	// Scopes
	//

	/**
	 * Get the scopes.
	 *
	 * @return array
	 */
	public function get_required_scopes() {
		return array(
			self::SCOPE_DRIVE,
			self::SCOPE_SPREADSHEETS,
			self::SCOPE_USERINFO,
			self::SCOPE_USER_EMAIL,
		);
	}

	/**
	 * Check if there are missing scopes - copied from original helpers
	 *
	 * @param null|array $credentials
	 *
	 * @return boolean
	 */
	public function has_missing_scope( $credentials = null ) {
		$credentials = is_null( $credentials )
			? $this->get_credentials()
			: $credentials;

		$scopes       = $this->get_required_scopes();
		$client_scope = $credentials['scope'] ?? '';

		if ( empty( $client_scope ) ) {
			return true;
		}

		$has_missing_scope = false;

		foreach ( $scopes as $scope ) {
			if ( self::SCOPE_DRIVE === $scope || self::LEGACY_SCOPE_DRIVE === $scope ) {
				continue; // Skip drive scope check. If there is drive already then proceed.
			}
			if ( false === strpos( $client_scope, $scope ) ) {
				$has_missing_scope = true;
			}
		}

		return $has_missing_scope;
	}

	/**
	 * Get client scopes - copied from original helpers
	 *
	 * @return false|string[]
	 */
	public function get_client_scopes() {
		try {
			$client = $this->get_credentials();
		} catch ( Exception $e ) {
			return false;
		}

		$scope = $client['scope'] ?? '';

		if ( empty( $scope ) ) {
			return false;
		}

		return explode( ' ', $scope );
	}

	/**
	 * Check if user has generic drive scope.
	 *
	 * @return boolean
	 */
	public function has_generic_drive_scope() {
		$scopes = $this->get_client_scopes();
		return is_array( $scopes ) && in_array( 'https://www.googleapis.com/auth/drive', $scopes, true );
	}

	//
	// Misc helpers
	//

	/**
	 * Get worksheets from spreadsheet.
	 *
	 * @param string $spreadsheet_id
	 * @return array
	 */
	public function get_worksheets_from_spreadsheet( $spreadsheet_id ) {
		$options = array();

		if ( '-1' === $spreadsheet_id ) {
			return $options;
		}

		$req_action_id        = automator_filter_input( 'item_id', INPUT_POST );
		$current_worksheet_id = get_post_meta( $req_action_id, 'GSWORKSHEET', true );

		try {
			$response   = $this->api->get_worksheets( $spreadsheet_id );
			$worksheets = $response['data'] ?? array();

			if ( empty( $worksheets ) ) {
				return $options;
			}

			foreach ( $worksheets as $worksheet ) {
				if ( ! isset( $worksheet['properties'] ) ) {
					continue;
				}

				$properties = $worksheet['properties'];

				if ( ! isset( $properties['sheetId'] ) || ! isset( $properties['title'] ) ) {
					continue;
				}

				$worksheet_id = $this->maybe_generate_sheet_id( $properties['sheetId'] );

				// Backwards compatibility for users who migrated already and re-built the action with zero worksheet id.
				if ( 0 === $this->calculate_hash( $worksheet_id ) && '0' === $current_worksheet_id ) {
					$worksheet_id = 0;
				}

				$options[] = array(
					'value' => $worksheet_id,
					'text'  => $properties['title'],
				);
			}
		} catch ( Exception $e ) {
			$options[] = array(
				'value' => '-1',
				'text'  => 'API Exception ' . $e->getMessage(),
			);
		}

		return $options;
	}

	/**
	 * Maybe generate sheet ID - copied from pre-release
	 *
	 * @param int $id
	 * @return string
	 */
	public function maybe_generate_sheet_id( $id ) {
		if ( 0 === (int) $id ) {
			$hashed = sha1( self::HASH_STRING );
			$id     = substr( $hashed, 0, 9 );
		}

		return $id;
	}

	/**
	 * Calculate hash for backwards compatibility.
	 *
	 * @param string $worksheet_id
	 *
	 * @return string
	 */
	public function calculate_hash( $worksheet_id ) {
		$hashed   = sha1( self::HASH_STRING );
		$sheet_id = substr( $hashed, 0, 9 );

		if ( (string) $worksheet_id === (string) $sheet_id || intval( '-1' ) === intval( $worksheet_id ) ) {
			$worksheet_id = 0;
		}

		return $worksheet_id;
	}

	//
	// Common recipe fields
	//

	/**
	 * Get spreadsheet field configuration.
	 *
	 * @return array
	 */
	public function get_spreadsheet_field() {
		$description = wp_kses(
			sprintf(
				esc_html_x(
					"If you don't see your spreadsheet or haven't selected any files yet, please go to the %1\$ssettings page%2\$s to add them.",
					'Google Sheets',
					'uncanny-automator'
				),
				'<a href="' . esc_url( $this->get_settings_page_url() ) . '" target="_blank">',
				'</a>'
			),
			array(
				'a' => array(
					'href'   => array(),
					'target' => array(),
				),
			)
		);

		return array(
			'option_code'           => 'GSSPREADSHEET',
			'label'                 => esc_html_x( 'Spreadsheet', 'Google Sheets', 'uncanny-automator' ),
			'description'           => $description,
			'input_type'            => 'select',
			'required'              => true,
			'options'               => array(),
			'supports_custom_value' => false,
			'options_show_id'       => false,
			'ajax'                  => array(
				'endpoint' => 'automator_fetch_googlesheets_spreadsheets',
				'event'    => 'on_load',
			),
		);
	}

	/**
	 * Get worksheet field configuration.
	 *
	 * @return array
	 */
	public function get_worksheet_field() {
		return array(
			'option_code'           => 'GSWORKSHEET',
			'label'                 => esc_html_x( 'Worksheet', 'Google Sheets', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'options'               => array(),
			'options_show_id'       => false,
			'supports_custom_value' => false,
			'ajax'                  => array(
				'endpoint'      => 'automator_fetch_googlesheets_worksheets',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( 'GSSPREADSHEET' ),
			),
		);
	}
}

// Pro compatibility - provide alias for backward compatibility
class_alias(
	'Uncanny_Automator\Integrations\Google_Sheet\Google_Sheet_Helpers',
	'Uncanny_Automator\Google_Sheet_Helpers'
);
