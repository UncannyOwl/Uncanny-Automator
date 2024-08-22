<?php

namespace Uncanny_Automator;

use Exception;
use Uncanny_Automator\Api_Server;
use Uncanny_Automator\Integrations\Google_Sheet\Migrations\Migrate_58;
use Uncanny_Automator_Pro\Google_Sheet_Pro_Helpers;

/**
 * Class Google_Sheet_Helpers
 *
 * @package Uncanny_Automator
 */
class Google_Sheet_Helpers {

	/**
	 * The scope for fetching users google drives (legacy &).
	 *
	 * @var string SCOPE_DRIVE The scope for drive.
	 */
	const LEGACY_SCOPE_DRIVE = 'https://www.googleapis.com/auth/drive';

	/**
	 * The scope for fetching users google drives.
	 *
	 * @var string SCOPE_DRIVE The scope for drive.
	 */
	const SCOPE_DRIVE = 'https://www.googleapis.com/auth/drive.file';

	/**
	 * The scope for fetching users spreadsheets.
	 *
	 * @var string The scope for spreadsheets.
	 */
	const SCOPE_SPREADSHEETS = 'https://www.googleapis.com/auth/spreadsheets';

	/**
	 * The scope for fetching profile info.
	 *
	 * @var string The scope for user info.
	 */
	const SCOPE_USERINFO = 'https://www.googleapis.com/auth/userinfo.profile';

	/**
	 * The scope for fetching user email.
	 *
	 * @var string The scope for email.
	 */
	const SCOPE_USER_EMAIL = 'https://www.googleapis.com/auth/userinfo.email';

	/**
	 * The API endpoint address.
	 *
	 * @var string The endpoint adress.
	 */
	const API_ENDPOINT = 'v2/google';

	/**
	 * Google Sheet Options.
	 *
	 * @var Google_Sheet_Helpers
	 */
	public $options;

	/**
	 * Google Sheet Pro Helpers.
	 *
	 * @var Google_Sheet_Pro_Helpers
	 */
	public $pro;

	/**
	 * The settings tab.
	 *
	 * @var Google_Sheet_Pro_Helpers
	 */
	public $setting_tab;

	/**
	 * Load options.
	 *
	 * @var bool
	 */
	public $load_options = true;

	/**
	 * Client scope.
	 *
	 * @var string
	 */
	public $client_scope;

	/**
	 * The hash string.
	 *
	 * @var string
	 */
	public static $hash_string = 'Uncanny Automator Pro Google Sheet Integration';

	/**
	 * The API endpoint.
	 *
	 * @var string $automator_api
	 */
	public $automator_api = '';

	/**
	 * The spreadsheets option key.
	 */
	const SPREADSHEETS_OPTIONS_KEY = 'automator_google_sheets_spreadsheets';

	/**
	 * Googlesheet_Pro_Helpers constructor.
	 */
	public function __construct() {

		// Try migrating the googlesheet to new version.
		$this->maybe_migrate_googlesheets();

		// Initialize the 5.8 migration.
		$migration = new Migrate_58( $this );
		$migration->register_hooks();

		// Selectively load options.
		if ( method_exists( '\Uncanny_Automator\Automator_Helpers_Recipe', 'maybe_load_trigger_options' ) ) {
			$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
		} else {

		}

		$this->setting_tab = 'premium-integrations';

		$this->automator_api = AUTOMATOR_API_URL . 'v2/google';

		$this->client_scope = implode(
			' ',
			array(
				self::SCOPE_DRIVE,
				self::SCOPE_SPREADSHEETS,
				self::SCOPE_USERINFO,
				self::SCOPE_USER_EMAIL,
			)
		);

		// Would probably be a good idea if we move 'validate_oauth_tokens' away from the 'init' hook to its own endpoint.
		add_action( 'init', array( $this, 'validate_oauth_tokens' ), 100, 3 );

		// Classic methods.
		add_action( 'wp_ajax_select_gsspreadsheet_from_gsdrive', array( $this, 'select_gsspreadsheet_from_gsdrive' ) );
		add_action( 'wp_ajax_select_gsworksheet_from_gsspreadsheet', array( $this, 'select_gsworksheet_from_gsspreadsheet' ) );
		add_action( 'wp_ajax_select_gsworksheet_from_gsspreadsheet_columns', array( $this, 'select_gsworksheet_from_gsspreadsheet_columns' ) );
		add_action( 'wp_ajax_get_worksheet_ROWS_GOOGLESHEETS', array( $this, 'get_worksheet_rows_gsspreadsheet' ) );
		add_action( 'wp_ajax_uo_google_disconnect_user', array( $this, 'disconnect_user' ) );

		// Fix the OAuth credentials.
		add_filter( 'automator_google_api_call', array( $this, 'resend_with_current_credentials' ), 10, 1 );

		// Fix the Worksheet IDs.
		add_filter( 'automator_google_api_call', array( $this, 'resend_with_correct_worksheet_id' ), 20, 1 );

		// New wp_ajax endpoints for changes related to file picker.
		add_action( 'wp_ajax_automator_fetch_googlesheets_spreadsheets', array( $this, 'fetch_spreadsheets' ) );
		add_action( 'wp_ajax_automator_fetch_googlesheets_worksheets', array( $this, 'fetch_worksheets' ) );
		add_action( 'wp_ajax_automator_fetch_googlesheets_worksheets_columns', array( $this, 'fetch_worksheets_columns' ) );

		// Update action colum search field options.
		add_action( 'wp_ajax_automator_fetch_googlesheets_worksheets_columns_search', array( $this, 'fetch_worksheets_columns_search' ) );
		add_action( 'wp_ajax_automator_handle_file_picker', array( $this, 'handle_file_picker' ) );
		add_action( 'wp_ajax_automator_googlesheets_file_picker_auth', array( $this, 'handle_file_picker_auth' ) );

		// Delete spreadsheet handler.
		add_action( 'wp_ajax_automator_google_sheet_remove_spreadsheet', array( $this, 'remove_spreadsheet' ) );

		new Google_Sheet_Settings( $this );

	}

	/**
	 * Remove spreadsheets.
	 *
	 * @return never
	 */
	public function remove_spreadsheet() {

		// Verify permissions and nonce.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permission. Only administrators can use file picker.' );
		}

		if ( ! wp_verify_nonce( automator_filter_input( 'nonce' ), 'automator_google_sheet_remove_spreadsheet' ) ) {
			wp_die( 'Invalid nonce.' );
		}

		$spreadsheet_id = automator_filter_input( 'id' );

		// Retrieve the current spreadsheets.
		$current_spreadsheets = (array) get_option( self::SPREADSHEETS_OPTIONS_KEY, array() );

		// Unset the spreadsheet item that matches the requested spreadsheet id.
		foreach ( $current_spreadsheets as $key => $spreadsheet ) {
			if ( isset( $spreadsheet['id'] ) && $spreadsheet_id === $spreadsheet['id'] ) {
				unset( $current_spreadsheets[ $key ] );
			}
		}

		// Update the spreadsheet. There should be no problem when there is no unset,
		update_option( self::SPREADSHEETS_OPTIONS_KEY, $current_spreadsheets );

		// Redirect back to google sheet settings.
		wp_safe_redirect( admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-config&tab=premium-integrations&integration=google-sheet' ) );

		exit;

	}

	/**
	 * Handles file picker authentication.
	 *
	 * @return void
	 */
	public function handle_file_picker_auth() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json(
				array(
					'error' => 'Insufficient permission. Only administrators can use file picker.',
				),
				401
			);
		}

		$json_data = (array) json_decode( file_get_contents( 'php://input' ), true ); // We'ere using wp-ajax so wp_rest_request json method is not available.
		$nonce     = $json_data['nonce'] ?? '';

		if ( ! wp_verify_nonce( $nonce, 'automator_file_picker_create_picker' ) ) {
			wp_send_json(
				array(
					'error' => 'Invalid nonce. Unauthorized',
				),
				403
			);
		}

		try {

			$current_client = $this->get_google_client();
			$refresh_token  = $current_client['refresh_token'] ?? '';
			$since          = $current_client['since'] ?? 0;
			$expires        = $current_client['expires_in'] ?? 0;

			// Current time minus the since + expiry in seconds is greater than 1 hour.
			$timestamp_expires = intval( $since ) + intval( $expires );

			// Is current time greater than expiration of access token?
			$is_access_token_expired = time() >= $timestamp_expires;

			// If the access token is still good. Just return the current credentials.
			// Refresh the access token on first instance since we need to compare it later on.
			if ( ! empty( $since ) && ! $is_access_token_expired ) {
				// Invokes die statement so no need to return.
				wp_send_json(
					array(
						'data' => $current_client,
					),
					200
				);
			}

			// Otherwise, request new access token from the API.
			$body = array(
				'action' => 'refresh_access_token',
			);

			$response = $this->api_call( $body, null );

			if ( 200 === $response['statusCode'] && isset( $response['data'] ) ) {

				$data = $response['data'];

				// Insert the refresh token manually. Once the access token is refreshed, Google wont return a new set of refresh token.
				$data['refresh_token'] = $refresh_token;
				$data['since']         = time();

				update_option( '_uncannyowl_google_sheet_settings', $data );

			}
		} catch ( Exception $e ) {

			wp_send_json(
				array(
					'error' => 'Exception: ' . $e->getMessage(),
				),
				400
			);
		}

		wp_send_json(
			$response,
			200
		);
	}

	/**
	 * Handles file picker callback.
	 *
	 * @return void
	 */
	public function handle_file_picker() {

		$json_data = file_get_contents( 'php://input' ); // We'ere using wp-ajax so wp_rest_request json method is not available.

		$request_data = (array) json_decode( $json_data, true );

		$nonce = $request_data['nonce'] ?? '';

		// Verify nonce for security (if applicable)
		if ( ! wp_verify_nonce( $nonce, 'automator_google_file_picker' ) ) {
			wp_send_json_error(
				array(
					'error' => esc_html_x( 'Invalid nonce', 'Google sheets', 'uncanny-automator' ),
				),
				420
			);
		}

		$spreadsheets = $request_data['spreadsheets'] ?? array();

		$current_spreadsheets = get_option( self::SPREADSHEETS_OPTIONS_KEY );

		$new_spreadsheets_collection = $this->merge_spreadsheets_options( $current_spreadsheets, $spreadsheets );

		update_option( self::SPREADSHEETS_OPTIONS_KEY, $new_spreadsheets_collection, false );

		wp_send_json_success(
			(array) get_option( self::SPREADSHEETS_OPTIONS_KEY, array() )
		);

	}

	/**
	 * Remove duplicate spreadsheets by spreadsheet id.
	 *
	 * @param string[string[]] $documents
	 *
	 * @return array
	 */
	public static function remove_duplicate_spreadsheets_by_id( $documents ) {

		$unique_documents = array();
		$unique_ids       = array();

		foreach ( (array) $documents as $key => $document ) {
			if ( ! isset( $document['id'] ) ) {
				continue;
			}
			if ( ! in_array( $document['id'], $unique_ids ) ) {
				$unique_ids[]       = $document['id'];
				$unique_documents[] = $document;
			}
		}

		return $unique_documents;
	}

	/**
	 * Merge the spreadsheets options.
	 *
	 * @param string[string[]] $current_spreadsheets
	 * @param string[string[]] $spreadsheets
	 *
	 * @return string[string[]]
	 */
	public static function merge_spreadsheets_options( $current_spreadsheets, $spreadsheets ) {

		$documents = array_merge( (array) $current_spreadsheets, (array) $spreadsheets );

		return self::remove_duplicate_spreadsheets_by_id( $documents );
	}

	/**
	 * Fetches all spreadsheets.
	 *
	 * @return mixed
	 */
	public function fetch_spreadsheets() {

		Automator()->utilities->verify_nonce();

		$spreadsheets = self::get_spreadsheets();

		if ( empty( $spreadsheets ) ) {
			wp_send_json(
				array(
					'success' => false,
					'error'   => esc_html_x(
						'Unable to find any Sheets. Please go to Automator > App integrations > Google Sheets and select files.',
						'Google Sheets',
						'uncanny-automator'
					),
				)
			);
		}

		$options = array(
			array(
				'text'  => _x( 'Please select a spreadsheet', 'Google Sheets', 'uncanny-automator' ),
				'value' => '',
			),
		);

		foreach ( $spreadsheets as $spreadsheet ) {
			$options[] = array(
				'text'  => $spreadsheet['name'],
				'value' => $spreadsheet['id'],
			);
		}

		wp_send_json(
			array(
				'success' => true,
				'options' => $options,
			)
		);
	}

	/**
	 * @return void
	 */
	public function fetch_worksheets() {

		Automator()->utilities->verify_nonce();

		$values = automator_filter_input_array( 'values', INPUT_POST );

		$spreadsheet_id = sanitize_text_field( $values['GSSPREADSHEET'] );

		$worksheets = $this->api_get_worksheets_from_spreadsheet( $spreadsheet_id );

		wp_send_json(
			array(
				'success' => true,
				'options' => $worksheets,
			)
		);
	}

	/**
	 * Retrieve worksheet columns.
	 *
	 * @return void
	 */
	public function fetch_worksheets_columns() {

		Automator()->utilities->verify_nonce();

		$fields = array();

		$values = automator_filter_input_array( 'values', INPUT_POST );

		if ( ! isset( $values['GSSPREADSHEET'] ) ) {
			wp_send_json(
				array(
					'success' => false,
					'error'   => esc_html_x( 'Error: Unable to read the selected Sheet. Please make sure that you have selected a valid Sheet.', 'Google Sheets', 'uncanny-automator' ),
				)
			);
		}

		$gs_spreadsheet_id = sanitize_text_field( $values['GSSPREADSHEET'] );

		if ( ! isset( $values['GSWORKSHEET'] ) ) {
			wp_send_json(
				array(
					'success' => false,
					'error'   => esc_html_x( 'Error: Unable to find Worksheet ID. Please make sure that you have selected a valid Worksheet.', 'Google Sheets', 'uncanny-automator' ),
				)
			);
		}

		$worksheet_id = sanitize_text_field( $values['GSWORKSHEET'] );

		// Backwards compatibility.
		$worksheet_id = self::calculate_hash( $worksheet_id );

		$response = $this->api_get_rows( $gs_spreadsheet_id, $worksheet_id );

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

			foreach ( $rows as  $row ) {

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
	 * Retrieves all spreadsheets from the given options.
	 *
	 * @return string[]
	 */
	public static function get_spreadsheets() {

		$options_spreadsheets = (array) get_option( self::SPREADSHEETS_OPTIONS_KEY, array() );

		return apply_filters( 'automator_googlesheets_options_spreadsheets', $options_spreadsheets );
	}

	/**
	 * The options.
	 *
	 * @param Google_Sheet_Helpers $options
	 */
	public function setOptions( Google_Sheet_Helpers $options ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->options = $options;
	}

	/**
	 * Get the connected Google Drives.
	 *
	 * @deprecated 5.7
	 *
	 * @param null $label
	 * @param $option_code
	 * @param $args
	 *
	 * @return array|mixed|void
	 */
	public function get_google_drives( $label = null, $option_code = 'GSDRIVE', $args = array() ) {

		if ( ! $label ) {
			$label = __( 'Drive', 'uncanny-automator' );
		}

		$args = wp_parse_args(
			$args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => __( 'Any drive', 'uncanny-automator' ),
			)
		);

		$is_ajax                  = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field             = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point                = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$description              = key_exists( 'description', $args ) ? $args['description'] : null;
		$custom_value_description = key_exists( 'custom_value_description', $args ) ? $args['custom_value_description'] : null;
		$supports_custom_value    = key_exists( 'supports_custom_value', $args ) ? $args['supports_custom_value'] : false;
		$supports_tokens          = key_exists( 'supports_tokens', $args ) ? $args['supports_tokens'] : null;
		$placeholder              = key_exists( 'placeholder', $args ) ? $args['placeholder'] : null;
		$options                  = $this->api_get_google_drives();

		$option = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'description'              => $description,
			'input_type'               => 'select',
			'required'                 => true,
			'is_ajax'                  => $is_ajax,
			'fill_values_in'           => $target_field,
			'endpoint'                 => $end_point,
			'options'                  => $options,
			'supports_tokens'          => apply_filters( 'uap_option_' . $option_code . '_select_field', $supports_tokens ),
			'custom_value_description' => $custom_value_description,
			'supports_custom_value'    => $supports_custom_value,
			'placeholder'              => $placeholder,
			'integration'              => 'GOOGLESHEET',
		);

		return apply_filters( 'uap_option_get_google_drives', $option );
	}

	/**
	 * The the connected Google Spreadsheets.
	 *
	 * @deprecated 5.7
	 *
	 * @param $label
	 * @param $option_code
	 * @param $args
	 *
	 * @return mixed
	 */
	public function get_google_spreadsheets( $label = null, $option_code = 'GSSPREADSHEET', $args = array() ) {

		if ( ! $label ) {
			$label = __( 'Drive', 'uncanny-automator' );
		}

		$args = wp_parse_args(
			$args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => __( 'Any spreadsheet', 'uncanny-automator' ),
			)
		);

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = array();

		$option = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => 'select',
			'required'                 => true,
			'supports_tokens'          => $token,
			'is_ajax'                  => $is_ajax,
			'fill_values_in'           => $target_field,
			'endpoint'                 => $end_point,
			'custom_value_description' => '',
			'supports_custom_value'    => false,
			'options'                  => $options,
		);

		return apply_filters( 'uap_option_get_google_spreadsheets', $option );
	}

	/**
	 * Method select_gsspreadsheet_from_gsdrive
	 *
	 * @deprecated 5.7
	 *
	 * @return void
	 */
	public function select_gsspreadsheet_from_gsdrive() {

		// Nonce and post object validation
		Automator()->utilities->ajax_auth_check();

		$fields = array();

		if ( ! automator_filter_has_var( 'values', INPUT_POST ) ) {

			echo wp_json_encode( $fields );

			die();

		}

		$values = automator_filter_input_array( 'values', INPUT_POST );

		if ( ! isset( $values['GSDRIVE'] ) ) {

			echo wp_json_encode( $fields );

			die();

		}

		$gs_drive_id = sanitize_text_field( $values['GSDRIVE'] );

		$fields = $this->api_get_spreadsheets_from_drive( $gs_drive_id );

		echo wp_json_encode( $fields );

		die();
	}


	/**
	 * Method get_google_worksheets
	 *
	 * @deprecated 5.7
	 *
	 * @param $label $label [explicite description]
	 * @param $option_code $option_code [explicite description]
	 * @param $args $args [explicite description]
	 *
	 * @return mixed
	 */
	public function get_google_worksheets( $label = null, $option_code = 'GSWORKSHEET', $args = array() ) {

		if ( ! $label ) {
			$label = __( 'Worksheet', 'uncanny-automator' );
		}

		$args = wp_parse_args(
			$args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => __( 'Any worksheet', 'uncanny-automator' ),
			)
		);

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = array();

		$option = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => 'select',
			'required'                 => true,
			'supports_tokens'          => $token,
			'is_ajax'                  => $is_ajax,
			'fill_values_in'           => $target_field,
			'endpoint'                 => $end_point,
			'custom_value_description' => '',
			'supports_custom_value'    => false,
			'options'                  => $options,
			'hide_actions'             => isset( $args['hide_actions'] ) ? $args['hide_actions'] : false,
		);

		return apply_filters( 'uap_option_get_google_worksheets', $option );
	}

	/**
	 * Method select_gsworksheet_from_gsspreadsheet
	 *
	 * @deprecated 5.7
	 *
	 * @return void
	 */
	public function select_gsworksheet_from_gsspreadsheet() {

		// Nonce and post object validation
		Automator()->utilities->ajax_auth_check();

		$fields = array();

		$values = automator_filter_input_array( 'values', INPUT_POST );

		if ( ! isset( $values['GSSPREADSHEET'] ) ) {
			echo wp_json_encode( $fields );
			die();
		}

		$gs_spreadsheet_id = sanitize_text_field( $values['GSSPREADSHEET'] );

		$fields = $this->api_get_worksheets_from_spreadsheet( $gs_spreadsheet_id );

		echo wp_json_encode( $fields );

		die();

	}

	/**
	 * Method get_worksheet_rows_gsspreadsheet
	 *
	 * @deprecated 5.7
	 *
	 * @return void
	 */
	public function get_worksheet_rows_gsspreadsheet() {

		// Nonce and post object validation
		Automator()->utilities->ajax_auth_check();

		$response = (object) array(
			'success' => false,
			'samples' => array(),
		);

		if ( ! automator_filter_has_var( 'sheet', INPUT_POST ) && ! automator_filter_has_var( 'worksheet', INPUT_POST ) ) {
			echo wp_json_encode( $response );
			die();
		}

		$gs_spreadsheet_id = sanitize_text_field( automator_filter_input( 'sheet', INPUT_POST ) );
		$worksheet_id      = sanitize_text_field( automator_filter_input( 'worksheet', INPUT_POST ) );
		$hashed            = sha1( self::$hash_string );
		$sheet_id          = substr( $hashed, 0, 9 );

		if ( (string) $worksheet_id === (string) $sheet_id || intval( '-1' ) === intval( $worksheet_id ) ) {
			$worksheet_id = 0;
		}

		$response = $this->api_get_rows( $gs_spreadsheet_id, $worksheet_id );

		echo wp_json_encode( $response );
		die();
	}

	/**
	 * Get Google Client object
	 *
	 * @return false|object
	 */
	public function get_google_client() {

		$access_token = automator_get_option( '_uncannyowl_google_sheet_settings', array() );

		if ( empty( $access_token ) || ! isset( $access_token['access_token'] ) ) {
			return false;
		}

		return $access_token;
	}

	/**
	 * Callback function for OAuth redirect verification.
	 */
	public function validate_oauth_tokens() {

		// Bailout if integration is not google sheet.
		if ( 'google-sheet' !== automator_filter_input( 'integration' ) ) {
			return;
		}

		$api_message = automator_filter_input( 'automator_api_message' );

		// Bailout if no message from api.
		if ( empty( $api_message ) ) {
			return;
		}

		$error_google_sheet_url = 'edit.php?post_type=uo-recipe&page=uncanny-automator-config&tab=' . $this->setting_tab . '&integration=google-sheet';

		$secret = automator_filter_input( 'nonce' );

		$tokens = Automator_Helpers_Recipe::automator_api_decode_message( $api_message, $secret );

		if ( ! empty( $tokens['access_token'] ) ) {

			// On success.
			update_option( '_uncannyowl_google_sheet_settings', $tokens );
			// Set the transient.
			set_transient( '_uncannyowl_google_sheet_settings', $tokens['access_token'] . '|' . $tokens['refresh_token'], 60 * 50 );
			// Refresh the user info.
			delete_transient( '_uncannyowl_google_user_info' );
			// Delete expired settings.
			delete_option( '_uncannyowl_google_sheet_settings_expired' );

			if ( $this->has_missing_scope() ) {

				wp_safe_redirect( admin_url( $error_google_sheet_url ) . '&connect=3' );
				die;

			}

			wp_safe_redirect( admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-config&tab=' . $this->setting_tab . '&integration=google-sheet&connect=1' ) );
			die;

		} else {

			// On Error.
			wp_safe_redirect( admin_url( $error_google_sheet_url ) . '&connect=2' );
			die;

		}

	}

	/**
	 * Method has_missing_scope
	 *
	 * Checks the client if it has any missing scope or not.
	 *
	 * @return boolean True if there is a missing scope. Otherwise, false.
	 */
	public function has_missing_scope() {

		$client = $this->get_google_client();

		$scopes = array(
			self::SCOPE_DRIVE,
			self::SCOPE_SPREADSHEETS,
			self::SCOPE_USERINFO,
			self::SCOPE_USER_EMAIL,
		);

		if ( ! isset( $client['scope'] ) || empty( $client['scope'] ) ) {
			return true;
		}

		$has_missing_scope = false;

		foreach ( $scopes as $scope ) {
			if ( self::SCOPE_DRIVE === $scope || self::LEGACY_SCOPE_DRIVE === $scope ) {
				continue; // Skip drive scope check. If there is drive already then proceed.
			}
			if ( false === strpos( $client['scope'], $scope ) ) {
				$has_missing_scope = true;
			}
		}

		return $has_missing_scope;

	}

	/**
	 * Method api_get_google_drives
	 *
	 * @deprecated 5.7
	 *
	 * @return void|null|array
	 */
	public function api_get_google_drives() {

		$options = get_transient( 'automator_api_get_google_shared_drives' );

		if ( false !== $options ) {
			return $options;
		}

		try {

			$body = array(
				'action' => 'list_drives',
			);

			$response = $this->api_call( $body );

			$options = array();

			$options[] = array(
				'value' => '-1',
				'text'  => __( 'My google drive', 'uncanny-automator' ),
			);

			if ( ! empty( $response['data'] ) && is_array( $response['data'] ) ) {
				foreach ( $response['data'] as $drive ) {
					if ( ! empty( $drive['id'] ) && ! empty( $drive['name'] ) ) {
						$options[] = array(
							'value' => $drive['id'],
							'text'  => $drive['name'],
						);
					}
				}
			}

			set_transient( 'automator_api_get_google_shared_drives', $options, 60 );

			return $options;

		} catch ( \Exception $e ) {
			automator_log( $e->getMessage() );
		}

	}


	/**
	 * Method api_get_spreadsheets_from_drive
	 *
	 * @deprecated 5.7
	 *
	 * @param $drive_id
	 *
	 * @return void|null|array
	 */
	public function api_get_spreadsheets_from_drive( $drive_id ) {

		$options = array();

		try {

			$body = array(
				'action'   => 'list_files',
				'drive_id' => $drive_id,
			);

			$response = $this->api_call( $body );

			$options[] = array(
				'value' => '-1',
				'text'  => __( 'Select a Speadsheet', 'uncanny-automator' ),
			);

			if ( ! empty( $response['data'] ) && is_array( $response['data'] ) ) {

				foreach ( $response['data'] as $item ) {
					$options[] = array(
						'value' => $item['id'],
						'text'  => $item['name'],
					);
				}
			}
		} catch ( \Exception $e ) {
			$options[] = array(
				'value' => '-1',
				'text'  => 'API Exception ' . $e->getMessage(),
			);
		}

		return $options;

	}

	/**
	 * Method api_get_worksheets_from_spreadsheet
	 *
	 * @param $spreadsheet_id
	 *
	 * @return void|null|array
	 */
	public function api_get_worksheets_from_spreadsheet( $spreadsheet_id ) {

		$options = array();

		if ( '-1' === $spreadsheet_id ) {
			return $options;
		}

		$req_action_id = automator_filter_input( 'item_id', INPUT_POST );

		$current_worksheet_id = get_post_meta( $req_action_id, 'GSWORKSHEET', true );

		try {

			$body = array(
				'action'         => 'get_worksheets',
				'spreadsheet_id' => $spreadsheet_id,
			);

			$response = $this->api_call( $body );

			if ( is_array( $response['data'] ) ) {

				foreach ( $response['data'] as $worksheet ) {

					if ( ! isset( $worksheet['properties'] ) ) {
						continue;
					}

					$properties = $worksheet['properties'];

					if ( ! isset( $properties['sheetId'] ) || ! isset( $properties['title'] ) ) {
						continue;
					}

					$worksheet_id = $this->maybe_generate_sheet_id( $properties['sheetId'] );

					// Backwards compatibility for users who migrated already and re-built the action with zero worksheet id.
					if ( 0 === self::calculate_hash( $worksheet_id ) && '0' === $current_worksheet_id ) {
						$worksheet_id = 0;
					}

					$options[] = array(
						'value' => $worksheet_id,
						'text'  => $properties['title'],
					);
				}
			}
		} catch ( \Exception $e ) {
			$options[] = array(
				'value' => '-1',
				'text'  => 'API Exception ' . $e->getMessage(),
			);
		}

		return $options;

	}

	/**
	 * Method maybe_generate_sheet_id
	 *
	 * @param int $id
	 *
	 * @return string
	 */
	public function maybe_generate_sheet_id( $id ) {

		if ( 0 === (int) $id ) {
			$hashed = sha1( self::$hash_string );
			$id     = substr( $hashed, 0, 9 );
		}

		return $id;
	}

	/**
	 * Method api_get_rows
	 *
	 * @param $spreadsheet_id
	 * @param $worksheet_id
	 *
	 * @return void|null|array|object
	 */
	public function api_get_rows( $spreadsheet_id, $worksheet_id ) {

		$options = array();

		try {

			$body = array(
				'action'         => 'get_rows',
				'spreadsheet_id' => $spreadsheet_id,
				'worksheet_id'   => $worksheet_id,
			);

			$api_response = $this->api_call( $body );

			if ( is_array( $api_response['data'] ) ) {

				$alphas = range( 'A', 'Z' );

				if ( ! empty( $api_response['data'][0] ) ) {

					foreach ( $api_response['data'][0] as $key => $heading ) {
						if ( empty( $heading ) ) {
							$heading = 'COLUMN:' . $alphas[ $key ];
						}
						$options[] = array(
							'key'  => $heading,
							'type' => 'text',
							'data' => $heading,
						);
					}

					$response = (object) array(
						'success' => true,
						'samples' => array( $options ),
					);

				}
			}
		} catch ( \Exception $e ) {
			$response = (object) array(
				'success' => false,
				'error'   => 'Error: Couldn\'t fetch rows. ' . $e->getMessage(),
			);
		}

		return $response;

	}

	/**
	 * Method api_append_row
	 *
	 * @param $spreadsheet_id
	 * @param $worksheet_id
	 * @param $key_values
	 *
	 * @return void|null|array
	 */
	public function api_append_row( $spreadsheet_id, $worksheet_id, $key_values, $action = null ) {

		$body = array(
			'action'         => 'append_row',
			'spreadsheet_id' => $spreadsheet_id,
			'worksheet_id'   => $worksheet_id,
			'key_values'     => $key_values,
		);

		$response = $this->api_call( $body, $action );

		return $response;
	}

	/**
	 * Get the user info.
	 *
	 * @return array The user info.
	 */
	public function get_user_info() {

		$user_info = array(
			'avatar_uri' => '',
			'name'       => '',
			'email'      => '',
		);

		$transient_key = '_uncannyowl_google_user_info';

		$saved_user_info = get_transient( $transient_key );

		if ( false !== $saved_user_info ) {
			return $saved_user_info;
		}

		try {
			$user = $this->api_user_info();

			if ( empty( $user['data'] ) ) {
				return $user_info;
			}

			$user_info['name']       = $user['data']['name'];
			$user_info['avatar_uri'] = $user['data']['picture'];
			$user_info['email']      = $user['data']['email'];
			set_transient( $transient_key, $user_info, DAY_IN_SECONDS );
		} catch ( \Exception $e ) {
			return $user_info;
		}

		return $user_info;
	}

	/**
	 * Removes the google settings from wp_options table.
	 *
	 * @return void|null|array.
	 */
	public function disconnect_user() {

		if ( wp_verify_nonce( filter_input( INPUT_GET, 'nonce', FILTER_UNSAFE_RAW ), 'uo-google-user-disconnect' ) ) {

			$this->disconnect_and_revoke_credentials();

		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type'   => 'uo-recipe',
					'page'        => 'uncanny-automator-config',
					'tab'         => 'premium-integrations',
					'integration' => 'google-sheet',
				),
				admin_url( 'edit.php' )
			)
		);

		exit;
	}

	/**
	 * Disconnect and revokes the credentials.
	 *
	 * @return true
	 */
	public function disconnect_and_revoke_credentials() {

		$this->api_revoke_access();

		delete_option( '_uncannyowl_google_sheet_settings' );
		delete_option( '_uncannyowl_google_sheet_settings_expired' );
		delete_transient( '_uncannyowl_google_sheet_settings' );
		delete_transient( '_uncannyowl_google_user_info' );
		delete_option( self::SPREADSHEETS_OPTIONS_KEY );

		return true;

	}

	/**
	 * Revoke Access.
	 *
	 * @return void|null|array
	 */
	public function api_revoke_access() {

		try {

			$body = array(
				'action' => 'revoke_access',
			);

			$response = $this->api_call( $body );

			delete_option( '_uncannyowl_google_sheet_settings' );

		} catch ( \Exception $e ) {
			automator_log( $e->getMessage() );
		}

	}

	/**
	 * The user info from API.
	 *
	 * @return void|null|array
	 */
	public function api_user_info() {

		$client = $this->get_google_client();

		if ( empty( $client['scope'] ) ) {
			return;
		}

		$scope = $client['scope'];

		if ( ! ( strpos( $scope, self::SCOPE_USERINFO ) || strpos( $scope, self::SCOPE_USER_EMAIL ) ) ) {
			return;
		}

		$body = array(
			'action' => 'user_info',
		);

		$response = $this->api_call( $body );

		return $response;
	}

	/**
	 * Get all connected Google Sheet columns.
	 *
	 * @param $label
	 * @param $option_code
	 * @param $args
	 *
	 * @return array|mixed|void
	 */
	public function get_google_sheet_columns( $label = null, $option_code = 'GSWORKSHEETCOLUMN', $args = array() ) {

		if ( ! $label ) {
			$label = __( 'Columns', 'uncanny-automator' );
		}

		$args = wp_parse_args(
			$args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => __( 'Any column', 'uncanny-automator' ),
			)
		);

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = array();
		$option       = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => 'select',
			'required'                 => true,
			'supports_tokens'          => $token,
			'is_ajax'                  => $is_ajax,
			'fill_values_in'           => $target_field,
			'endpoint'                 => $end_point,
			'custom_value_description' => '',
			'supports_custom_value'    => false,
			'options'                  => $options,
			'hide_actions'             => isset( $args['hide_actions'] ) ? $args['hide_actions'] : false,
		);

		return apply_filters( 'uap_option_get_google_worksheets_columns', $option );
	}

	/**
	 * Convert number to corresponding excel range.
	 */
	public function num2alpha( $n ) {
		for ( $r = ''; $n >= 0; $n = intval( $n / 26 ) - 1 ) {
			$r = chr( $n % 26 + 0x41 ) . $r;
		}

		return $r;
	}

	/**
	 * @deprecated 5.7
	 *
	 * @return never
	 */
	public function select_gsworksheet_from_gsspreadsheet_columns() {

		// Nonce and post object validation.
		Automator()->utilities->ajax_auth_check();

		$values = automator_filter_input_array( 'values', INPUT_POST );

		$spreadsheet_id = $values['GSSPREADSHEET'] ?? '';
		$worksheet_id   = $values['GSWORKSHEET'] ?? '';

		$fields = (array) $this->get_columns( $spreadsheet_id, $worksheet_id );

		echo wp_json_encode( $fields );

		die();

	}

	/**
	 * Fetch worksheets columns for searching.
	 *
	 * @return void
	 */
	public function fetch_worksheets_columns_search() {

		// Nonce and post object validation.
		Automator()->utilities->ajax_auth_check();

		$field_values = automator_filter_input_array( 'values', INPUT_POST );

		$spreadsheet_id = $field_values['GSSPREADSHEET'] ?? '';
		$worksheet_id   = $field_values['GSWORKSHEET'] ?? '';

		// Backwards compatibility.
		$worksheet_id = self::calculate_hash( $worksheet_id );

		$fields = $this->get_columns( $spreadsheet_id, $worksheet_id );

		wp_send_json(
			array(
				'success' => true,
				'options' => $fields,
			)
		);

	}

	/**
	 * Legacy backwords compatibility.
	 *
	 * @param string $worksheet_id
	 *
	 * @return string The ID of sheet.
	 */
	public static function calculate_hash( $worksheet_id ) {

		$hashed   = sha1( self::$hash_string );
		$sheet_id = substr( $hashed, 0, 9 );

		if ( (string) $worksheet_id === (string) $sheet_id || intval( '-1' ) === intval( $worksheet_id ) ) {
			$worksheet_id = 0;
		}

		return $worksheet_id;
	}

	/**
	 * Retrieve sheets columns from API.
	 *
	 * @since 5.8
	 *
	 * @param string $spreadsheet_id
	 * @param string $worksheet_id
	 *
	 * @return string[]
	 */
	public function get_columns( $spreadsheet_id, $worksheet_id ) {

		$response = $this->api_get_rows( $spreadsheet_id, $worksheet_id );

		$fields = array();

		if ( ! empty( $response ) && ! empty( $response->samples ) ) {
			$rows = array_shift( $response->samples );
			foreach ( $rows as $index => $r ) {
				$num2alpha = sprintf( '1-%1$s2:%1$s', $this->num2alpha( $index ) );
				$fields[]  = array(
					'value' => $num2alpha,
					'text'  => $r['key'],
				);
			}
		}

		return $fields;

	}

	/**
	 * Changes the COLUMN_NAME and COLUMN_VALUE to GS_COLUMN_NAME and GS_COLUMN_VALUE in the postmeta.
	 *
	 * @return void
	 */
	public function maybe_migrate_googlesheets() {

		if ( 'yes' === automator_get_option( 'uncanny_automator_google_sheets_migrated' ) ) {
			return;
		}

		global $wpdb;

		// Fetch all postmeta records where key is equal to "WORKSHEET_FIELDS".
		// Only fetch meta_value that contains COLUMN_NAME and not GS_COLUMN_NAME.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_key, meta_value
				FROM $wpdb->postmeta
				WHERE meta_key = %s
				AND meta_value LIKE %s
				AND meta_value LIKE %s
				AND meta_value NOT LIKE %s
				AND meta_value NOT LIKE %s
				",
				'WORKSHEET_FIELDS',
				'%%COLUMN_NAME%%',
				'%%COLUMN_VALUE%%',
				'%%GS_COLUMN_NAME%%',
				'%%GS_COLUMN_VALUE%%'
			),
			OBJECT
		);

		if ( ! empty( $results ) ) {

			// Get the old meta value.
			foreach ( $results as $result ) {

				// Initiate the new meta value as empty array.
				$meta_value_new = array();

				// Get the post id.
				$post_id = $result->post_id;

				// Decode the old meta value to make it array.
				$meta_values = json_decode( $result->meta_value );

				if ( ! empty( $meta_values ) ) {
					// Iterate through each old value and construct new array with new keys.
					foreach ( $meta_values as $meta_value ) {

						$new_meta = array(
							// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
							'GS_COLUMN_NAME'  => $meta_value->COLUMN_NAME,
							// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
							'GS_COLUMN_VALUE' => $meta_value->COLUMN_VALUE,
						);

						// Add other meta keys and values if exists except for COLUMN_NAME and COLUMN_VALUE.
						if ( isset( $meta_value->COLUMN_UPDATE ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
							$new_meta['COLUMN_UPDATE'] = $meta_value->COLUMN_UPDATE; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						}

						$meta_value_new[] = $new_meta;

					}

					// Don't escape unicode characters.
					$new_meta_value = wp_json_encode( $meta_value_new, JSON_UNESCAPED_UNICODE );

					// Update the post meta with the new array containing the new keys.
					// Only update if $new_meta_value is not empty.
					if ( ! empty( $new_meta_value ) ) {
						update_post_meta( $post_id, 'WORKSHEET_FIELDS', $new_meta_value );
					}
				}
			}
		}

		// Update the option 'uncanny_automator_google_sheets_migrated'.
		update_option( 'uncanny_automator_google_sheets_migrated', 'yes', true );

	}

	/**
	 * Method api_get_range_values
	 *
	 * @param  mixed $spreadsheet_id
	 * @param  mixed $range
	 * @return void
	 */
	public function api_get_range_values( $spreadsheet_id, $range ) {

		$body = array(
			'action'         => 'get_column_rows',
			'spreadsheet_id' => $spreadsheet_id,
			'range'          => $range,
		);

		$response = $this->api_call( $body );

		return $response;

	}

	/**
	 * Method api_update_row
	 *
	 * @param  mixed $spreadsheet_id
	 * @param  mixed $ranges
	 * @param  mixed $row_values
	 * @return void
	 */
	public function api_update_row( $spreadsheet_id, $ranges, $row_values, $action = null ) {

		$values = wp_json_encode( array( $row_values ) );

		$body = array(
			'action'         => 'update_row_multiple',
			'range'          => $ranges,
			'spreadsheet_id' => $spreadsheet_id,
			'values'         => $values,
		);

		$response = $this->api_call( $body, $action );

		return $response;
	}

	/**
	 * Method api_call
	 *
	 * @param  mixed $body
	 * @param  mixed $action
	 * @return void
	 */
	public function api_call( $body, $action = null ) {

		$body['access_token'] = $this->get_google_client();

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => $body,
			'action'   => $action,
			'timeout'  => 10,
		);

		$response = Api_Server::api_call( $params );

		if ( 200 !== $response['statusCode'] ) {
			throw new \Exception( $params['endpoint'] . ' failed' );
		}

		return $response;

	}

	/**
	 * resend_with_current_credentials
	 *
	 * Make sure request replays are done with the current credentials.
	 *
	 * @param  array $params
	 * @return array
	 */
	public function resend_with_current_credentials( $params ) {

		// If it is not a resend, proceed as usual
		if ( empty( $params['resend'] ) ) {
			return $params;
		}

		// If the request didn't carry access token in the first place, proceed with no changes
		if ( empty( $params['body']['access_token'] ) ) {
			return $params;
		}

		try {
			$params['body']['access_token'] = $this->get_google_client();
		} catch ( \Exception $e ) {
			//If Google is not connected, proceed with the recorded credentials
		}

		return $params;
	}

	/**
	 * Resend with the correct worksheet ID.
	 *
	 * On version 5.8, existing worksheet ID that has hashed ID is breaking.
	 *
	 * @param mixed[] $params
	 *
	 * @return mixed[]
	 */
	public function resend_with_correct_worksheet_id( $params ) {

		// If it is not a resend, proceed as usual.
		if ( empty( $params['resend'] ) ) {
			return $params;
		}

		// Proceed with no changes if `worksheet_id` is not present.
		if ( ! isset( $params['body']['worksheet_id'] ) ) {
			return $params;
		}

		// Figure out the correct sheet ID.
		$worksheet_id = $this->calculate_hash( $params['body']['worksheet_id'] );

		$params['body']['worksheet_id'] = $worksheet_id;

		return $params;
	}

	/**
	 * Determine if the user is connected or not.
	 *
	 * @param bool $mock_disconnect
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function is_connected( $mock_disconnect = false ) {

		if ( is_bool( $mock_disconnect ) && false === $mock_disconnect ) {
			return false;
		}

		try {
			$client = $this->get_google_client();
		} catch ( Exception $e ) {
			return false;
		}

		if ( empty( $client ) ) {
			return false;
		}

		return true;

	}

	/**
	 * Retrieve the client scopes.
	 *
	 * @return false|string[]
	 */
	public function get_client_scopes() {

		try {
			$client = $this->get_google_client();
		} catch ( Exception $e ) {
			return false;
		}

		if ( empty( $client['scope'] ) ) {
			return false;
		}

		return explode( ' ', $client['scope'] );

	}

	/**
	 * Determines whether the current connected user has still generic drive scope.
	 *
	 * @return boolean
	 */
	public function has_generic_drive_scope() {

		$scopes = $this->get_client_scopes();

		return is_array( $scopes ) && in_array( 'https://www.googleapis.com/auth/drive', $scopes, true );

	}
}
