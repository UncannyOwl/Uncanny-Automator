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
	 * Single source of truth for the cached account info: get_account_info(),
	 * store_account_info() and delete_account_info() must ALL use this constant.
	 * Previously get_account_info() read/wrote a hardcoded literal instead, so
	 * the key it cached under never matched the one disconnect deleted — the
	 * prior account reappeared on reconnect until the 24h TTL expired.
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

		$saved_user_info = get_transient( self::RESOURCE_OWNER_KEY );

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
			$this->store_account_info( $user_info );
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

	////////////////////////////////////////////////////////////
	// Remote-data REST handlers
	//
	// Reachable via POST /wp-json/uap/v2/remote-data/googlesheet/{data},
	// where {data} matches the suffix on the method name. Dispatched
	// through Abstract_Helpers::process_remote_data_request().
	////////////////////////////////////////////////////////////

	/**
	 * Fetch spreadsheets for the top-level select.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_spreadsheets( $request ): array {
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

			return $this->remote_data_success( $options );

		} catch ( Exception $e ) {
			return $this->remote_data_error( $e->getMessage() );
		}
	}

	/**
	 * Fetch worksheets for the selected spreadsheet.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_worksheets( $request ): array {
		$spreadsheet_id = $request->get_field_value( 'GSSPREADSHEET' );

		if ( empty( $spreadsheet_id ) ) {
			return $this->remote_data_error(
				esc_html_x( 'Please select a spreadsheet', 'Google Sheets', 'uncanny-automator' )
			);
		}

		$worksheets = $this->get_worksheets_from_spreadsheet( $spreadsheet_id );

		return $this->remote_data_success( $worksheets );
	}

	/**
	 * Fetch worksheet columns as repeater rows.
	 *
	 * Returns rows under the `rows` key (not `options`) — `mapping_column` on
	 * the field config tells the repeater to key each row by `GS_COLUMN_NAME`.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_worksheets_columns( $request ): array {
		$spreadsheet_id = $request->get_field_value( 'GSSPREADSHEET' );
		$worksheet_id   = $request->get_field_value( 'GSWORKSHEET' );

		if ( empty( $spreadsheet_id ) ) {
			return $this->remote_data_error(
				esc_html_x( 'Please select a spreadsheet', 'Google Sheets', 'uncanny-automator' ),
				'rows'
			);
		}

		if ( empty( $worksheet_id ) ) {
			return $this->remote_data_error(
				esc_html_x( 'Please select a worksheet', 'Google Sheets', 'uncanny-automator' ),
				'rows'
			);
		}

		// Backwards compatibility.
		$worksheet_id = $this->calculate_hash( $worksheet_id );
		$response     = $this->api->get_rows( $spreadsheet_id, $worksheet_id );

		if ( false === $response->success ) {
			return $this->remote_data_error( $response->error, 'rows' );
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

		return $this->remote_data_success( $fields, 'rows' );
	}

	/**
	 * Fetch worksheet columns as a flat select list for the lookup-match column
	 * picker on the update-row action.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_lookup_columns( $request ): array {
		$spreadsheet_id = $request->get_field_value( 'GSSPREADSHEET' );
		$worksheet_id   = $request->get_field_value( 'GSWORKSHEET' );

		if ( empty( $spreadsheet_id ) || empty( $worksheet_id ) ) {
			$error = empty( $spreadsheet_id )
				? esc_html_x( 'Please select a spreadsheet', 'Google Sheets', 'uncanny-automator' )
				: esc_html_x( 'Please select a worksheet', 'Google Sheets', 'uncanny-automator' );

			return $this->remote_data_error( $error );
		}

		$worksheet_id = $this->calculate_hash( $worksheet_id );
		$fields       = $this->api->get_columns( $spreadsheet_id, $worksheet_id );

		return $this->remote_data_success( $fields );
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
			'remote_data'           => $this->remote_data_load_config( 'spreadsheets' ),
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
			'remote_data'           => $this->remote_data_parent_config( 'worksheets', array( 'GSSPREADSHEET' ) ),
		);
	}
}

// Pro compatibility - provide alias for backward compatibility
class_alias(
	'Uncanny_Automator\Integrations\Google_Sheet\Google_Sheet_Helpers',
	'Uncanny_Automator\Google_Sheet_Helpers'
);
