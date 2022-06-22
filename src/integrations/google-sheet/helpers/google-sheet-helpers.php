<?php

namespace Uncanny_Automator;

global $google_sheet_meeting_token_renew;

use Uncanny_Automator\Api_Server;
use Uncanny_Automator_Pro\Google_Sheet_Pro_Helpers;

/**
 * Class Google_Sheet_Helpers
 *
 * @package Uncanny_Automator
 */
class Google_Sheet_Helpers {

	/**
	 * The scope for fetching users google drives.
	 *
	 * @var string SCOPE_DRIVE The scope for drive.
	 */
	const SCOPE_DRIVE = 'https://www.googleapis.com/auth/drive';

	/**
	 * The scope for fetching users spreadsheets.
	 *
	 * @var SCOPE_SPREADSHEETS The scope for spreadsheets.
	 */
	const SCOPE_SPREADSHEETS = 'https://www.googleapis.com/auth/spreadsheets';

	/**
	 * The scope for fetching profile info.
	 *
	 * @var SCOPE_USERINFO The scope for user info.
	 */
	const SCOPE_USERINFO = 'https://www.googleapis.com/auth/userinfo.profile';

	/**
	 * The scope for fetching user email.
	 *
	 * @var SCOPE_USER_EMAIL The scope for email.
	 */
	const SCOPE_USER_EMAIL = 'https://www.googleapis.com/auth/userinfo.email';

	/**
	 * The API endpoint address.
	 *
	 * @var API_ENDPOINT The endpoint adress.
	 */
	const API_ENDPOINT = 'v2/google';

	/**
	 * Google Sheet Options.
	 *
	 * @var Google_Sheet_Pro_Helpers
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
	public $load_options;

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
	 * Googlesheet_Pro_Helpers constructor.
	 */
	public function __construct() {

		// Try migrating the googlesheet to new version.
		$this->maybe_migrate_googlesheets();

		// Selectively load options.
		if ( method_exists( '\Uncanny_Automator\Automator_Helpers_Recipe', 'maybe_load_trigger_options' ) ) {
			$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
		} else {
			$this->load_options = true;
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
		add_action( 'wp_ajax_select_gsspreadsheet_from_gsdrive', array( $this, 'select_gsspreadsheet_from_gsdrive' ) );
		add_action( 'wp_ajax_select_gsworksheet_from_gsspreadsheet', array( $this, 'select_gsworksheet_from_gsspreadsheet' ) );
		add_action( 'wp_ajax_select_gsworksheet_from_gsspreadsheet_columns', array( $this, 'select_gsworksheet_from_gsspreadsheet_columns' ) );
		add_action( 'wp_ajax_get_worksheet_ROWS_GOOGLESHEETS', array( $this, 'get_worksheet_rows_gsspreadsheet' ) );
		add_action( 'wp_ajax_uo_google_disconnect_user', array( $this, 'disconnect_user' ) );

		// Load the settings page.
		require_once __DIR__ . '/../settings/settings-google-sheet.php';

		new Google_Sheet_Settings( $this );

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
	 * Set pro.
	 *
	 * @param Google_Sheet_Helpers $pro
	 */
	public function setPro( Google_Sheet_Pro_Helpers $pro ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->pro = $pro;
	}

	/**
	 * Get the connected Google Drives.
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

		$access_token = get_option( '_uncannyowl_google_sheet_settings', array() );

		if ( empty( $access_token ) || ! isset( $access_token['access_token'] ) ) {
			throw new \Exception( 'Google is not connected' );
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

		$secret = get_transient( 'automator_api_google_authorize_nonce' );

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
			if ( false === strpos( $client['scope'], $scope ) ) {
				$has_missing_scope = true;
			}
		}

		return $has_missing_scope;

	}

	/**
	 * Method api_get_google_drives
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
				'text'  => __( 'Google returned an error. Please try again in a few minutes.', 'uncanny-automator' ),
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

		$options[] = array(
			'value' => '',
			'text'  => __( 'Select a worksheet', 'uncanny-automator' ),
		);

		if ( '-1' === $spreadsheet_id ) {
			return $options;
		}

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

					$options[] = array(
						'value' => $this->maybe_generate_sheet_id( $properties['sheetId'] ),
						'text'  => $properties['title'],
					);
				}
			}
		} catch ( \Exception $e ) {
			$options[] = array(
				'value' => '-1',
				'text'  => __( 'Google returned an error. Please try again in a few minutes.', 'uncanny-automator' ),
			);
		}

		return $options;

	}

	/**
	 * Method maybe_generate_sheet_id
	 *
	 * @param  mixed $id
	 * @return void
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
	 * @return void|null|array
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
				'error'   => 'Couldn\'t fetch rows',
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

		if ( wp_verify_nonce( filter_input( INPUT_GET, 'nonce', FILTER_SANITIZE_STRING ), 'uo-google-user-disconnect' ) ) {

			$this->api_revoke_access();

			delete_option( '_uncannyowl_google_sheet_settings' );
			delete_option( '_uncannyowl_google_sheet_settings_expired' );
			delete_transient( '_uncannyowl_google_sheet_settings' );
			delete_transient( '_uncannyowl_google_user_info' );
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
	 * Get samples JS.
	 *
	 * @return false|string
	 */
	public static function get_samples_js() {
		// Start output
		ob_start();

		// It's optional to add the <script> tags
		// This must have only one anonymous function
		?>

		<script>

			// Do when the user clicks on send test
			function ($button, data, modules) {

				// Create a configuration object
				let config = {
					// In milliseconds, the time between each call
					timeBetweenCalls: 1 * 1000,
					// In milliseconds, the time we're going to check for samples
					checkingTime: 60 * 1000,
					// Links
					links: {
						noResultsSupport: 'https://automatorplugin.com/knowledge-base/google-sheets/'
					},
					// i18n
					i18n: {
						checkingHooks: "<?php /* translators: Non-personal infinitive verb */ printf( esc_html__( "We're checking for columns. We'll keep trying for %s seconds.", 'uncanny-automator' ), '{{time}}' ); ?>",
						noResultsTrouble: "<?php esc_html_e( 'We had trouble finding columns.', 'uncanny-automator' ); ?>",
						noResultsSupport: "<?php esc_html_e( 'See more details or get help', 'uncanny-automator' ); ?>",
						samplesModalTitle: "<?php esc_html_e( "Here is the data we've collected", 'uncanny-automator' ); ?>",
						samplesModalWarning: "<?php /* translators: 1. Button */ printf( esc_html__( 'Clicking on \"%1$s\" will remove your current fields and will use the ones on the table above instead.', 'uncanny-automator' ), '{{confirmButton}}' ); ?>",
						samplesTableValueType: "<?php esc_html_e( 'Value type', 'uncanny-automator' ); ?>",
						samplesTableReceivedData: "<?php esc_html_e( 'Received data', 'uncanny-automator' ); ?>",
						samplesModalButtonConfirm: "<?php /* translators: Non-personal infinitive verb */ esc_html_e( 'Use these fields', 'uncanny-automator' ); ?>",
						samplesModalButtonCancel: "<?php /* translators: Non-personal infinitive verb */ esc_html_e( 'Do nothing', 'uncanny-automator' ); ?>",
					}
				}

				// Create the variable we're going to use to know if we have to keep doing calls
				let foundResults = false;

				// Get the date when this function started
				let startDate = new Date();

				// Create array with the data we're going to send
				let dataToBeSent = {
					action: 'get_worksheet_ROWS_GOOGLESHEETS',
					nonce: UncannyAutomator.nonce,
					recipe_id: UncannyAutomator.recipe.id,
					item_id: data.item.id,
					drive: data.values.GSDRIVE,
					sheet: data.values.GSSPREADSHEET,
					worksheet: data.values.GSWORKSHEET
				};

				// Add notice to the item
				// Create notice
				let $notice = jQuery('<div/>', {
					'class': 'item-options__notice item-options__notice--warning'
				});

				// Add notice message
				$notice.html(config.i18n.checkingHooks.replace('{{time}}', parseInt(config.checkingTime / 1000)));

				// Get the notices container
				let $noticesContainer = jQuery('.item[data-id="' + data.item.id + '"] .item-options__notices');

				// Add notice
				$noticesContainer.html($notice);

				// Create the function we're going to use recursively to
				// do check for the samples
				var getSamples = function () {
					// Do AJAX call
					jQuery.ajax({
						method: 'POST',
						dataType: 'json',
						url: ajaxurl,
						data: dataToBeSent,

						// Set the checking time as the timeout
						timeout: config.checkingTime,

						success: function (response) {
							// Get new date
							let currentDate = new Date();

							// Define the default value of foundResults
							let foundResults = false;

							// Check if the response was successful
							if (response.success) {
								// Check if we got the rows from a sample
								if (response.samples.length > 0) {
									// Update foundResults
									foundResults = true;
								}
							}

							// Check if we have to do another call
							let shouldDoAnotherCall = false;

							// First, check if we don't have results
							if (!foundResults) {
								// Check if we still have time left
								if ((currentDate.getTime() - startDate.getTime()) <= config.checkingTime) {
									// Update result
									shouldDoAnotherCall = true;
								}
							}

							if (shouldDoAnotherCall) {
								// Wait and do another call
								setTimeout(function () {
									// Invoke this function again
									getSamples();
								}, config.timeBetweenCalls);
							} else {
								// Add loading animation to the button
								$button.removeClass('uap-btn--loading uap-btn--disabled');
								// Iterate samples and create an array with the rows
								let rows = [];
								let keys = {}
								jQuery.each(response.samples, function (index, sample) {
									// Iterate keys
									jQuery.each(sample, function (index, row) {
										// Check if the we already added this key
										if (typeof keys[row.key] !== 'undefined') {
											// Then just append the value
											// rows[ keys[ row.key ] ].data = rows[ keys[ row.key ] ].data + ', ' + row.data;
										} else {
											// Add row and save the index
											keys[row.key] = rows.push(row);
										}
									});
								});
								// Get the field with the fields (WEBHOOK_DATA)
								let worksheetFields = data.item.options.GOOGLESHEETROW.fields[3];

								// Remove all the current fields
								worksheetFields.fieldRows = [];

								// Add new rows. Iterate rows from the sample
								jQuery.each(rows, function (index, row) {
									// Add row
									worksheetFields.addRow({
										GS_COLUMN_NAME: row.key
									}, false);
								});

								// Render again
								worksheetFields.reRender();

								// Check if it has results
								if (foundResults) {
									// Remove notice
									$notice.remove();

								} else {
									// Change the notice type
									$notice.removeClass('item-options__notice--warning').addClass('item-options__notice--error');

									// Create a new notice message
									let noticeMessage = config.i18n.noResultsTrouble;

									// Change the notice message
									$notice.html(noticeMessage + ' ');

									// Add help link
									let $noticeHelpLink = jQuery('<a/>', {
										target: '_blank',
										href: config.links.noResultsSupport
									}).text(config.i18n.noResultsSupport);
									$notice.append($noticeHelpLink);
								}
							}
						},

						statusCode: {
							403: function () {
								location.reload();
							}
						},

						fail: function (response) {
						}
					});
				}

				// Add loading animation to the button
				$button.addClass('uap-btn--loading uap-btn--disabled');

				// Try to get samples
				getSamples();
			}

		</script>

		<?php

		// Get output
		$output = ob_get_clean();

		// Return output.
		return $output;
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

	public function select_gsworksheet_from_gsspreadsheet_columns() {

		// Nonce and post object validation
		Automator()->utilities->ajax_auth_check();
		$fields = array();
		$values = automator_filter_input_array( 'values', INPUT_POST );
		if ( ! isset( $values['GSSPREADSHEET'] ) ) {
			echo wp_json_encode( $fields );
			die();
		}
		$gs_spreadsheet_id = sanitize_text_field( $values['GSSPREADSHEET'] );

		if ( empty( $values['GSWORKSHEET'] ) ) {
			echo wp_json_encode( $fields );
			die();
		}

		$worksheet_id = sanitize_text_field( $values['GSWORKSHEET'] );
		$hashed       = sha1( self::$hash_string );
		$sheet_id     = substr( $hashed, 0, 9 );

		if ( (string) $worksheet_id === (string) $sheet_id || intval( '-1' ) === intval( $worksheet_id ) ) {
			$worksheet_id = 0;
		}

		$response = $this->api_get_rows( $gs_spreadsheet_id, $worksheet_id );

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

		echo wp_json_encode( $fields );

		die();

	}

	/**
	 * Changes the COLUMN_NAME and COLUMN_VALUE to GS_COLUMN_NAME and GS_COLUMN_VALUE in the postmeta.
	 *
	 * @return void
	 */
	public function maybe_migrate_googlesheets() {

		if ( 'yes' === get_option( 'uncanny_automator_google_sheets_migrated' ) ) {
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
		update_option( 'uncanny_automator_google_sheets_migrated', 'yes', false );

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
	 * @param  mixed $range
	 * @param  mixed $row_values
	 * @return void
	 */
	public function api_update_row( $spreadsheet_id, $range, $row_values, $action = null ) {

		$values = wp_json_encode( array( $row_values ) );

		$body = array(
			'action'         => 'update_row',
			'range'          => $range,
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
}
