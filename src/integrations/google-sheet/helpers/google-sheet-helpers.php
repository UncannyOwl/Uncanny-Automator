<?php


namespace Uncanny_Automator;

global $google_sheet_meeting_token_renew;

use Uncanny_Automator_Pro\Google_Sheet_Pro_Helpers;

/**
 * Class Google_Sheet_Helpers
 * @package Uncanny_Automator
 */
class Google_Sheet_Helpers {

	/**
	 * @var SCOPE_DRIVE The scope for fetching users google drives.
	 */
	const SCOPE_DRIVE = 'https://www.googleapis.com/auth/drive';

	/**
	 * @var SCOPE_SPREADSHEETS The scope for fetching users spreadsheets.
	 */
	const SCOPE_SPREADSHEETS = 'https://www.googleapis.com/auth/spreadsheets';

	/**
	 * @var SCOPE_USERINFO The scope for fetching profile info.
	 */
	const SCOPE_USERINFO = 'https://www.googleapis.com/auth/userinfo.profile';
	/**
	 * @var SCOPE_USER_EMAIL The scope for fetching user email.
	 */
	const SCOPE_USER_EMAIL = 'https://www.googleapis.com/auth/userinfo.email';
	/**
	 * @var Google_Sheet_Pro_Helpers
	 */
	public $options;
	/**
	 * @var Google_Sheet_Pro_Helpers
	 */
	public $pro;
	/**
	 * @var Google_Sheet_Pro_Helpers
	 */
	public $setting_tab;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * @var string
	 */
	private $client_scope;

	/**
	 * @var string
	 */
	public static $hash_string = 'Uncanny Automator Pro Google Sheet Integration';

	/**
	 * Googlesheet_Pro_Helpers constructor.
	 */
	public function __construct() {
		// Selectively load options
		if ( method_exists( '\Uncanny_Automator\Automator_Helpers_Recipe', 'maybe_load_trigger_options' ) ) {
			global $uncanny_automator;
			$this->load_options = $uncanny_automator->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
		} else {
			$this->load_options = true;
		}

		$this->setting_tab   = 'googlesheets_api';
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

		add_filter( 'uap_settings_tabs', array( $this, 'add_google_api_settings' ), 15 );
		add_action( 'init', array( $this, 'validate_oauth_tokens' ), 100, 3 );
		add_filter( 'automator_after_settings_extra_content', array( $this, 'google_sheet_connect_html' ), 10, 3 );
		add_action( 'wp_ajax_select_gsspreadsheet_from_gsdrive', array( $this, 'select_gsspreadsheet_from_gsdrive' ) );
		add_action(
			'wp_ajax_select_gsworksheet_from_gsspreadsheet',
			array(
				$this,
				'select_gsworksheet_from_gsspreadsheet',
			)
		);
		add_action( 'wp_ajax_get_worksheet_ROWS_GOOGLESHEETS', array( $this, 'get_worksheet_rows_gsspreadsheet' ) );

		add_action( 'wp_ajax_uo_google_disconnect_user', array( $this, 'disconnect_user' ) );
	}

	/**
	 * @param Google_Sheet_Helpers $options
	 */
	public function setOptions( Google_Sheet_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Google_Sheet_Helpers $pro
	 */
	public function setPro( Google_Sheet_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param null $label
	 * @param $option_code
	 * @param $args
	 *
	 * @return array|mixed|void
	 */
	public function get_google_drives( $label = null, $option_code = 'GSDRIVE', $args = array() ) {
		if ( ! $this->load_options ) {
			global $uncanny_automator;

			return $uncanny_automator->helpers->recipe->build_default_options_array( $label, $option_code );
		}
		global $uncanny_automator;

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
		$options                  = array();

		$options['-1'] = __( 'My google drive', 'uncanny-automator' );

		if ( $uncanny_automator->helpers->recipe->load_helpers ) {

			$options = $this->api_get_google_drives();

		}

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
	 * @param $label
	 * @param $option_code
	 * @param $args
	 *
	 * @return mixed
	 */
	public function get_google_spreadsheets( $label = null, $option_code = 'GSSPREADSHEET', $args = array() ) {
		if ( ! $this->load_options ) {
			global $uncanny_automator;

			return $uncanny_automator->helpers->recipe->build_default_options_array( $label, $option_code );
		}

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
		global $uncanny_automator;

		if ( $uncanny_automator->helpers->recipe->load_helpers ) {
			// Loading by ajax
		}
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
		global $uncanny_automator;

		// Nonce and post object validation
		$uncanny_automator->utilities->ajax_auth_check();

		$fields = array();

		if ( ! isset( $_POST ) ) {
			echo wp_json_encode( $fields );
			die();
		}

		$gs_drive_id = sanitize_text_field( $_POST['values']['GSDRIVE'] );

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
		if ( ! $this->load_options ) {
			global $uncanny_automator;

			return $uncanny_automator->helpers->recipe->build_default_options_array( $label, $option_code );
		}

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
		global $uncanny_automator;

		if ( $uncanny_automator->helpers->recipe->load_helpers ) {
			// Loading by ajax.
		}
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
		global $uncanny_automator;

		// Nonce and post object validation
		$uncanny_automator->utilities->ajax_auth_check();

		$gs_spreadsheet_id = sanitize_text_field( $_POST['values']['GSSPREADSHEET'] );

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
		global $uncanny_automator;

		// Nonce and post object validation
		$uncanny_automator->utilities->ajax_auth_check();

		$fields = array();

		$response = (object) array(
			'success' => false,
			'samples' => array(),
		);

		if ( ! isset( $_POST ) ) {
			echo wp_json_encode( $response );
			die();
		}

		$gs_spreadsheet_id = sanitize_text_field( $_POST['sheet'] );
		$worksheet_id      = sanitize_text_field( $_POST['worksheet'] );
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
	 * Check if the settings tab should display.
	 *
	 * @return boolean.
	 */
	public function display_settings_tab() {

		if ( Automator()->utilities->has_valid_license() ) {
			return true;
		}

		if ( Automator()->utilities->is_from_modal_action() ) {
			return true;
		}

		return ! empty( $this->get_google_client() );
	}

	/**
	 * @param $tabs
	 *
	 * @return mixed
	 */
	public function add_google_api_settings( $tabs ) {

		if ( $this->display_settings_tab() ) {

			$is_uncannyowl_google_sheet_settings_expired = get_option( '_uncannyowl_google_sheet_settings_expired', false );

			$tab_url = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-settings&tab=' . $this->setting_tab;

			$tabs[ $this->setting_tab ] = array(
				'name'           => __( 'Google', 'uncanny-automator' ),
				'title'          => __( 'Google account settings', 'uncanny-automator' ),
				'description'    => $this->get_google_api_settings_description(),
				'is_pro'         => false,
				'is_expired'     => $is_uncannyowl_google_sheet_settings_expired,
				'settings_field' => 'uap_automator_google_sheet_api_settings',
				'wp_nonce_field' => 'uap_automator_google_sheet_api_nonce',
				'save_btn_name'  => 'uap_automator_google_sheet_api_save',
				'save_btn_title' => __( 'Connect Google Sheets', 'uncanny-automator' ),
				'fields'         => array(),
			);

		}

		return $tabs;
	}

	/**
	 * Method get_google_api_settings_description
	 *
	 * @return void
	 */
	protected function get_google_api_settings_description() {

		$description = __(
			'Connecting to Google requires signing into your account to link it to Automator.
			To get started, click the "Connect an account" button below or the "Change account" button if you need to connect a new account.
			Uncanny Automator can only connect to a single Google account at one time. (It is not possible to set some recipes up under one
			account and then switch accounts, all recipes are mapped to the account selected on this page and existing recipes may break if
			they were set up under another account.)',
			'uncanny-automator'
		);
		ob_start();
		?>
        <p>
			<?php echo esc_html( $description ); ?>
        </p>

		<?php $user = $this->get_user_info(); ?>
		<?php if ( ! empty( $user['name'] ) ) : ?>
            <div class="uo-google-user-info">
				<?php if ( ! empty( $user['avatar_uri'] ) ) : ?>
                    <div class="uo-google-user-info__avatar">
                        <img width="32" src="<?php echo esc_url( $user['avatar_uri'] ); ?>"
                             alt="<?php esc_attr_e( $user['name'] ); ?>"/>
                    </div>
				<?php endif; ?>

				<?php if ( ! empty( $user['email'] ) ) : ?>
                    <div class="uo-google-user-info__email">
						<?php echo esc_html( $user['email'] ); ?>
                    </div>
				<?php endif; ?>

				<?php if ( ! empty( $user['name'] ) ) : ?>
                    <div class="uo-google-user-info__name">
                        (<?php echo esc_html( $user['name'] ); ?>)
                    </div>
				<?php endif; ?>
            </div>
		<?php endif; ?>
		<?php
		return ob_get_clean();
	}

	/**
	 * @param $content
	 * @param $active
	 * @param $tab
	 *
	 * @return mixed
	 */
	public function google_sheet_connect_html( $content, $active, $tab ) {

		if ( 'googlesheets_api' === $active ) {
			$action       = 'authorization_request';
			$redirect_url = urlencode( admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-settings&tab=' . $this->setting_tab );
			$nonce        = wp_create_nonce( 'automator_api_google_authorize' );
			set_transient( 'automator_api_google_authorize_nonce', $nonce, 3600 );
			$automator_version = InitializePlugin::PLUGIN_VERSION;
			$auth_url          = $this->automator_api . "?action={$action}&scope={$this->client_scope}&redirect_url={$redirect_url}&nonce={$nonce}&plugin_ver={$automator_version}&api_ver=1.0";

			$gs_client    = $this->get_google_client();
			$button_text  = __( 'Connect an account', 'uncanny-automator' );
			$button_class = '';
			if ( $gs_client ) {
				$button_text  = __( 'Change account', 'uncanny-automator' );
				$button_class = 'uo-connected-button';
			}
			ob_start();
			?>
            <div class="uo-settings-content-form">

                <a href="<?php echo $auth_url; ?>"
                   class="uo-settings-btn uo-settings-btn--primary <?php echo $button_class; ?>">
					<?php
					echo $button_text;
					?>
                </a>
				<?php if ( $gs_client ) : ?>
					<?php
					$disconnect_uri = add_query_arg(
						array(
							'action' => 'uo_google_disconnect_user',
							'nonce'  => wp_create_nonce( 'uo-google-user-disconnect' ),
						),
						admin_url( 'admin-ajax.php' )
					);
					?>
                    <a href="<?php echo esc_url( $disconnect_uri ); ?>" class="uo-settings-btn uo-settings-btn--error ">
						<?php esc_html_e( 'Disconnect', 'uncanny-automator' ); ?>
                    </a>
				<?php endif; ?>

            </div>
            <style>
                .uo-google-user-info {
                    display: flex;
                    align-items: center;
                    margin: 20px 0;
                }

                .uo-google-user-info__avatar {
                    display: inline-flex;
                    align-items: center;
                    overflow: hidden;
                    border-radius: 32px;
                    margin-right: 10px;
                }

                .uo-google-user-info__name {
                    margin-left: 5px;
                    opacity: 0.75;
                }

                .uo-connected-button {
                    color: #fff;
                    background-color: #0790e8;
                }

                .uo-settings-content-footer {
                    display: none !important;
                }
            </style>
			<?php
		}

		return $content;
	}

	/**
	 * Get Google Client object
	 *
	 * @return false|object
	 */
	public function get_google_client() {
		$access_token = get_option( '_uncannyowl_google_sheet_settings', array() );
		if ( empty( $access_token ) || ! isset( $access_token['access_token'] ) ) {
			return false;
		}

		return $access_token;
	}

	/**
	 * Callback function for OAuth redirect verification.
	 */
	public function validate_oauth_tokens() {

		if ( ! empty( $_GET['automator_api_message'] ) && isset( $_REQUEST['tab'] ) && $this->setting_tab == $_REQUEST['tab'] ) {
			try {
				if ( ! empty( $_GET['automator_api_message'] ) ) {
					global $uncanny_automator;
					$secret = get_transient( 'automator_api_google_authorize_nonce' );
					$tokens = Automator_Helpers_Recipe::automator_api_decode_message( $_GET['automator_api_message'], $secret );
					if ( ! empty( $tokens['access_token'] ) ) {
						// On success
						update_option( '_uncannyowl_google_sheet_settings', $tokens );
						delete_option( '_uncannyowl_google_sheet_settings_expired' );
						//set the transient
						set_transient( '_uncannyowl_google_sheet_settings', $tokens['access_token'] . '|' . $tokens['refresh_token'], 60 * 50 );
						//Refresh the user info.
						delete_transient( '_uncannyowl_google_user_info' );
						wp_safe_redirect( admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-settings&tab=' . $this->setting_tab . '&connect=1' ) );
						die;

					} else {
						// On Error
						wp_safe_redirect( admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-settings&tab=' . $this->setting_tab . '&connect=2' ) );
						die;
					}
				}
			} catch ( \Exception $e ) {
				// On Error
				wp_safe_redirect( admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-settings&tab=' . $this->setting_tab . '&connect=2' ) );
				die;
			}
		}
	}


	/**
	 * Method api_get_google_drives
	 *
	 * @return void
	 */
	public function api_get_google_drives() {

		$gs_client = $this->get_google_client();

		if ( ! $gs_client ) {
			return;
		}

		$response = wp_remote_post(
			$this->automator_api,
			array(
				'method' => 'POST',
				'body'   => array(
					'action'       => 'list_drives',
					'access_token' => $gs_client,
					'api_ver'      => '2.0',
					'plugin_ver'   => InitializePlugin::PLUGIN_VERSION,
				),
			)
		);

		$body = null;

		$options = array();

		$options['-1'] = __( 'My google drive', 'uncanny-automator' );

		if ( is_array( $response ) && ! is_wp_error( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ) );

			if ( $body && $body->statusCode == 200 ) {
				foreach ( $body->data as $drive ) {
					$options[ $drive->id ] = $drive->name;
				}
			} else {
				if ( ! empty( $body->error->description) ) {
					automator_log( $body->error->description );
				}
			}
		} else {

			$error_response = __('The API returned an invalid format.', 'uncanny-automator');

			if ( is_wp_error( $response ) ) {
				$error_response = $response->get_error_message();
			}

			if ( ! empty( $body->error->description) ) {
				automator_log( $error_response );
			}

		}

		set_transient( 'automator_api_get_google_drives', $options, 60 );

		return $options;

	}


	/**
	 * Method api_get_spreadsheets_from_drive
	 *
	 * @param $drive_id
	 *
	 * @return void
	 */
	public function api_get_spreadsheets_from_drive( $drive_id ) {

		$client = $this->get_google_client();

		if ( ! $client ) {
			return;
		}

		$response = wp_remote_post(
			$this->automator_api,
			array(
				'method' => 'POST',
				'body'   => array(
					'action'       => 'list_files',
					'access_token' => $client,
					'drive_id'     => $drive_id,
					'api_ver'      => '2.0',
					'plugin_ver'   => InitializePlugin::PLUGIN_VERSION,
				),
			)
		);

		$body = null;

		$fields   = array();
		$fields[] = array(
			'value' => '-1',
			'text'  => __( 'Select a Google Sheet', 'uncanny-automator' ),
		);

		if ( is_array( $response ) && ! is_wp_error( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ) );

			if ( $body && $body->statusCode == 200 ) {
				foreach ( $body->data as $item ) {
					$fields[] = array(
						'value' => $item->id,
						'text'  => $item->name,
					);
				}
			} else {
				$fields['-1'] = __( 'Google returned an error: ', 'uncanny-automator' ) . $body->error->description;
			}
		} else {

			$fields['-1'] = __( 'Google returned an error. Please try again in a few minutes.', 'uncanny-automator' );
		}

		return $fields;

	}

	/**
	 * Method api_get_worksheets_from_spreadsheet
	 *
	 * @param $spreadsheet_id
	 *
	 * @return void
	 */
	public function api_get_worksheets_from_spreadsheet( $spreadsheet_id ) {

		$client = $this->get_google_client();

		if ( ! $client || empty( $spreadsheet_id ) ) {
			return;
		}

		$response = wp_remote_post(
			$this->automator_api,
			array(
				'method' => 'POST',
				'body'   => array(
					'action'         => 'get_worksheets',
					'access_token'   => $client,
					'spreadsheet_id' => $spreadsheet_id,
					'api_ver'        => '2.0',
					'plugin_ver'     => InitializePlugin::PLUGIN_VERSION,
				),
			)
		);

		$fields[] = array(
			'value' => '-1',
			'text'  => __( 'Select a worksheet', 'uncanny-automator' ),
		);

		if ( is_array( $response ) && ! is_wp_error( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ) );

			if ( $body && $body->statusCode == 200 ) {

				foreach ( $body->data as $worksheet ) {

					$sheet_id    = $worksheet->properties->sheetId;
					$sheet_title = $worksheet->properties->title;
					if ( 0 === (int) $sheet_id ) {
						$hashed   = sha1( self::$hash_string );
						$sheet_id = substr( $hashed, 0, 9 );
					}
					$fields[] = array(
						'value' => $sheet_id,
						'text'  => $sheet_title,
					);
				}

				return $fields;
			} else {
				return array(
					'text'  => 'Error communicating with the Google',
					'value' => 0,
				);
			}
		}

		return array(
			'text'  => 'Error communicating with the API',
			'value' => 0,
		);

	}

	/**
	 * Method api_get_rows
	 *
	 * @param $spreadsheet_id
	 * @param $worksheet_id
	 *
	 * @return void
	 */
	public function api_get_rows( $spreadsheet_id, $worksheet_id ) {

		$client = $this->get_google_client();

		if ( ! $client || empty( $spreadsheet_id ) ) {
			return;
		}

		$response = wp_remote_post(
			$this->automator_api,
			array(
				'method' => 'POST',
				'body'   => array(
					'action'         => 'get_rows',
					'access_token'   => $client,
					'spreadsheet_id' => $spreadsheet_id,
					'worksheet_id'   => $worksheet_id,
					'api_ver'        => '2.0',
					'plugin_ver'     => InitializePlugin::PLUGIN_VERSION,
				),
			)
		);

		if ( is_array( $response ) && ! is_wp_error( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ) );

			if ( $body && $body->statusCode == 200 ) {
				$rows = $body->data;

				$alphas = range( 'A', 'Z' );

				if ( $rows[0] ) {
					foreach ( $rows[0] as $key => $heading ) {
						if ( empty( $heading ) ) {
							$heading = 'COLUMN:' . $alphas[ $key ];
						}
						$fields[] = array(
							'key'  => $heading,
							'type' => 'text',
							'data' => $heading,
						);
					}
					$response = (object) array(
						'success' => true,
						'samples' => array( $fields ),
					);

					return $response;

				}
			}
		}

		$response = (object) array(
			'success' => false,
			'error'   => 'Couldn\'t fetch rows',
		);

		return $response;

	}

	/**
	 * Method api_append_row
	 *
	 * @param $spreadsheet_id
	 * @param $worksheet_id
	 * @param $key_values
	 *
	 * @return void
	 */
	public function api_append_row( $spreadsheet_id, $worksheet_id, $key_values ) {

		$client = $this->get_google_client();

		if ( ! $client || empty( $spreadsheet_id ) ) {
			return;
		}

		$response = wp_remote_post(
			$this->automator_api,
			array(
				'method' => 'POST',
				'body'   => array(
					'action'         => 'append_row',
					'access_token'   => $client,
					'spreadsheet_id' => $spreadsheet_id,
					'worksheet_id'   => $worksheet_id,
					'key_values'     => $key_values,
					'api_ver'        => '2.0',
					'plugin_ver'     => InitializePlugin::PLUGIN_VERSION,
				),
			)
		);

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
		);

		$transient_key = '_uncannyowl_google_user_info';

		$saved_user_info = get_transient( $transient_key );

		if ( false !== $saved_user_info ) {
			return $saved_user_info;
		}

		$response = $this->api_user_info();

		if ( ! is_wp_error( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ) );

			if ( $body && $body->statusCode == 200 ) {
				$user = $body->data;
				$user_info['name']       = $user->name;
				$user_info['avatar_uri'] = $user->picture;
				$user_info['email']      = $user->email;
				set_transient( '_uncannyowl_google_user_info', $user_info, DAY_IN_SECONDS );
			}
		}

		return $user_info;
	}

	/**
	 * Removes the google settings from wp_options table.
	 *
	 * @return void.
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
					'post_type' => 'uo-recipe',
					'page'      => 'uncanny-automator-settings',
					'tab'       => 'googlesheets_api',
				),
				admin_url( 'edit.php' )
			)
		);

		exit;
	}

	/**
	 * revoke_access
	 *
	 * @return void
	 */
	public function api_revoke_access() {

		$gs_client = $this->get_google_client();

		if ( ! $gs_client ) {
			return;
		}

		$response = wp_remote_post(
			$this->automator_api,
			array(
				'method' => 'POST',
				'body'   => array(
					'action'       => 'revoke_access',
					'access_token' => $gs_client,
					'api_ver'      => '2.0',
					'plugin_ver'   => InitializePlugin::PLUGIN_VERSION,
				),
			)
		);

		delete_option( '_uncannyowl_google_sheet_settings' );

	}

	/**
	 * api_user_info
	 *
	 * @return void
	 */
	public function api_user_info() {

		$gs_client = $this->get_google_client();

		if ( ! $gs_client ) {
			return;
		}

		if ( empty( $gs_client['scope'] ) ) {
			return;
		}

		$scope = $gs_client['scope'];

		if ( ! ( strpos( $scope, self::SCOPE_USERINFO ) || strpos( $scope, self::SCOPE_USER_EMAIL ) ) ) {
			return;
		}

		$response = wp_remote_post(
			$this->automator_api,
			array(
				'method' => 'POST',
				'body'   => array(
					'action'       => 'user_info',
					'access_token' => $gs_client,
					'api_ver'      => '2.0',
					'plugin_ver'   => InitializePlugin::PLUGIN_VERSION,
				),
			)
		);

		return $response;
	}
}
