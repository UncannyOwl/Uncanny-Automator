<?php
namespace Uncanny_Automator\Integrations\Google_Sheet;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Uncanny_Automator\Settings\OAuth_App_Integration;
use Exception;

/**
 * @package Uncanny_Automator\Integrations\Google_Sheet
 *
 * @since 5.0
 *
 * @property Google_Sheet_Helpers $helpers
 */
class Google_Sheet_Settings extends App_Integration_Settings {

	use OAuth_App_Integration;

	/**
	 * Set properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		$this->show_connect_arrow = true;

		// Early connection validation - only on settings page
		if ( $this->is_current_page_settings() && $this->is_connected ) {
			$this->validate_user_transient_status();
		}
	}

	/**
	 * Validate if the user is still connected by checking account info
	 * If not connected, disconnect the account and update connection status
	 *
	 * @return void
	 */
	public function validate_user_transient_status() {
		try {
			$this->helpers->get_account_info();
		} catch ( Exception $e ) {
			// If there's an error getting account info, the connection is invalid.
			$this->helpers->clear_connection();
			$this->is_connected = false;
		}
	}

	//
	// Enqueue JS Hooks
	//

	/**
	 * Register additional hooks for script loading.
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Load scripts on settings page
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_file_picker_scripts' ) );
	}

	/**
	 * Enqueue file picker scripts for Google Sheets functionality.
	 *
	 * @return void
	 */
	public function enqueue_file_picker_scripts() {
		// Only load on connected Google Sheets settings page.
		if ( ! $this->is_current_page_settings() || ! $this->is_connected ) {
			return;
		}

		// Enqueue our custom file picker script first.
		wp_enqueue_script(
			'google-sheets-file-picker',
			plugins_url( '/src/integrations/google-sheet/settings/assets/google-sheets-file-picker.js', AUTOMATOR_BASE_FILE ),
			array( 'uap-admin' ),
			AUTOMATOR_PLUGIN_VERSION,
			true
		);

		// Enqueue Google API script with async defer (depends on our script)
		wp_enqueue_script(
			'google-api-js',
			'https://apis.google.com/js/api.js',
			array( 'google-sheets-file-picker' ),
			time(),
			true
		);

		// Add async and defer attributes.
		add_filter( 'script_loader_tag', array( $this, 'add_async_defer_to_google_api' ), 10, 3 );
	}

	/**
	 * Add async and defer attributes to Google API script.
	 *
	 * @param string $tag
	 * @param string $handle
	 * @param string $src
	 *
	 * @return string
	 */
	public function add_async_defer_to_google_api( $tag, $handle, $src ) {
		if ( 'google-api-js' === $handle ) {
			// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Valid method for adding async/defer to external scripts
			return '<script src="' . esc_url( $src ) . '" async defer onload="gapiLoaded()"></script>';
		}
		return $tag;
	}

	//
	// Required Abstract Methods
	//

	/**
	 * Get formatted account info for display.
	 *
	 * @return array
	 */
	protected function get_formatted_account_info() {
		// Get the account info.
		$account = $this->helpers->get_account_info();

		// Prepare main info with Google icon
		$main_info  = ! empty( $account['name'] ) ? $account['name'] : $account['email'];
		$main_info .= ' <uo-icon id="google"></uo-icon>';

		return array(
			'avatar_type'  => ! empty( $account['avatar_uri'] ) ? 'image' : 'icon',
			'avatar_value' => ! empty( $account['avatar_uri'] ) ? $account['avatar_uri'] : 'google',
			'main_info'    => $main_info,
			'additional'   => ! empty( $account['name'] ) && ! empty( $account['email'] ) ? $account['email'] : '',
		);
	}

	//
	// OAuth Trait Overrides
	//

	/**
	 * Filter the OAuth args - to add Google scopes.
	 *
	 * @param array $args
	 * @param array $data
	 *
	 * @return array
	 */
	protected function maybe_filter_oauth_args( $args, $data ) {
		$args['scope'] = implode(
			' ',
			$this->helpers->get_required_scopes()
		);
		return $args;
	}

	/**
	 * Validate access token and scopes.
	 *
	 * @param array $credentials
	 *
	 * @return array
	 * @throws Exception
	 */
	protected function validate_integration_credentials( $credentials ) {
		// Validate we have an access token.
		$token = $credentials['access_token'] ?? '';
		if ( empty( $token ) ) {
			throw new Exception( esc_html_x( 'An error has occurred while connecting to the Google API. Please try again later.', 'Google Sheets', 'uncanny-automator' ) );
		}

		// Validate scopes.
		if ( $this->helpers->has_missing_scope( $credentials ) ) {
			$error  = esc_html_x( 'Required permissions not granted.', 'Google Sheets', 'uncanny-automator' );
			$error .= esc_html_x( 'Make sure everything is checked off in the list of required permissions. Sometimes the last 2 checkboxes are unchecked by default.', 'Google Sheets', 'uncanny-automator' );
			throw new Exception( esc_html( $error ) );
		}

		return $credentials;
	}

	//
	// Abstract Templating methods
	//

	/**
	 * Display - Main panel disconnected content.
	 *
	 * @return void - Outputs HTML directly
	 */
	public function output_main_disconnected_content() {
		$this->output_disconnected_header(
			esc_html_x(
				'Connect Uncanny Automator to Google Sheets to automatically create and update spreadsheet rows when users perform actions like submitting forms, joining groups and making purchases on your site.',
				'Google Sheets',
				'uncanny-automator'
			)
		);

		// Output available recipe items.
		$this->output_available_items();
	}

	/**
	 * Display - Main panel connected content.
	 *
	 * @return void - Outputs HTML directly
	 */
	public function output_main_connected_content() {
		// One account warning.
		$this->alert_html(
			array(
				'heading' => esc_html_x( 'Uncanny Automator only supports connecting to one Google Sheets account at a time.', 'Google Sheets', 'uncanny-automator' ),
				'content' => esc_html_x( 'You can only link Google Sheets that you have read and write access to.', 'Google Sheets', 'uncanny-automator' ),
				'class'   => 'uap-spacing-bottom',
			)
		);

		// Add separator
		$this->output_panel_separator();

		// Spreadsheets subtitle.
		$this->output_panel_subtitle(
			esc_html_x( 'Linked spreadsheets', 'Google Sheets', 'uncanny-automator' ),
			'uap-spacing-bottom'
		);

		$this->output_spreadsheet_list();
	}

	/**
	 * Output the OAuth connect button - overriding the abstract method.
	 *
	 * @return void
	 */
	public function output_oauth_connect_button() {
		$this->output_action_button(
			'oauth_init',
			esc_html_x( 'Sign in with Google', 'Google Sheets', 'uncanny-automator' ),
			array(
				'class' => 'uap-settings-button-google',
				'icon'  => 'google',
			)
		);
	}

	//
	// Integration specific templating methods
	//

	/**
	 * Output the file picker button.
	 *
	 * @return void
	 */
	private function output_file_picker_button() {
		?>
		<div class="uap-spacing-top" id="filePickerBtn">
			<uo-button
				uap-tooltip="Hold down the Shift key to select multiple sheets, or use Ctrl (Cmd on Mac) to select specific sheets."
				id="filePickerBtnComponent"
				onclick="createFilePickerButton();"
				color="secondary">
				<?php echo esc_html_x( 'Select new sheet(s)', 'Google Sheets', 'uncanny-automator' ); ?>
			</uo-button>
		</div>

		<div id="filePickerErrorContainer" class="uap-spacing-top" style="display: none">
			<uo-alert heading="<?php echo esc_html_x( 'An error occurred while authorizing the request to use File selection feature.', 'Google Sheets', 'uncanny-automator' ); ?>" type="error">
			</uo-alert>
		</div>
		<?php
	}

	//
	// Spreadsheet handlers.
	//

	/**
	 * Output the spreadsheet list.
	 *
	 * @return void
	 */
	private function output_spreadsheet_list() {
		$spreadsheets = $this->helpers->get_spreadsheets();

		if ( empty( $spreadsheets ) ) {
			$this->alert_html(
				array(
					'type'    => 'warning',
					'heading' => esc_html_x( 'No spreadsheets found.', 'Google Sheets', 'uncanny-automator' ),
					'content' => esc_html_x( 'Please use the file picker to select spreadsheets from your Google Drive.', 'Google Sheets', 'uncanny-automator' ),
					'class'   => 'uap-spacing-bottom',
				)
			);

			// Show file picker button
			$this->output_file_picker_button();
			return;
		}

		$table_data = $this->get_spreadsheet_table_data( $spreadsheets );
		$this->output_settings_table( $table_data['columns'], $table_data['data'], 'card' );

		$this->output_panel_separator();

		$this->output_file_picker_button();
	}


	/**
	 * Handle file picker authentication and token refresh.
	 *
	 * IMPORTANT: This method handles token refresh for the file picker UI only.
	 *
	 * Token Architecture Overview:
	 * - Google OAuth uses REUSABLE refresh tokens (unlike most APIs where they're single-use)
	 * - Access tokens expire after 1 hour
	 * - Refresh tokens are long-lived (months/years) and can be used multiple times
	 *
	 * Why This Method Exists:
	 * - This is an OPTIMIZATION for the file picker UI to avoid delays
	 * - Pre-emptively refreshes access tokens before they expire
	 * - Regular recipe actions DON'T need this because:
	 *   1. WordPress sends both access_token AND refresh_token to the proxy
	 *   2. The proxy's Google Client Library automatically uses the refresh_token to get fresh access_tokens
	 *   3. The refresh_token remains valid and reusable after each use
	 *
	 * Token Flow for Actions:
	 * WordPress → Sends { access_token (may be expired), refresh_token (valid) } → Proxy
	 * Proxy → Google Client uses refresh_token → Gets new access_token → API call succeeds
	 * WordPress → Still has same refresh_token → Works for next request
	 *
	 * @param array $response The response array.
	 * @param array $data The request data.
	 *
	 * @return array The response with credentials or error.
	 */
	/**
	 * Handle file picker auth.
	 *
	 * @param mixed $response The response.
	 * @param mixed $data The data.
	 * @return mixed
	 */
	public function handle_file_picker_auth( $response = array(), $data = array() ) {
		try {
			$current_client = $this->helpers->get_credentials();
			$refresh_token  = $current_client['refresh_token'] ?? '';
			$since          = $current_client['since'] ?? 0;
			$expires        = $current_client['expires_in'] ?? 0;

			// Check if access token is still valid (expires after 1 hour)
			$timestamp_expires       = intval( $since ) + intval( $expires );
			$is_access_token_expired = time() >= $timestamp_expires;

			// If access token is still good, return current credentials.
			if ( ! empty( $since ) && ! $is_access_token_expired ) {
				$response['data'] = $current_client;
				return $response;
			}

			// Otherwise, request new access token from the API proxy.
			// Note: We do this here for the file picker to avoid UI delays.
			// Regular actions don't need this - the proxy handles it automatically.
			$api_response = $this->api->api_request( 'refresh_access_token' );

			if ( 200 === $api_response['statusCode'] && isset( $api_response['data'] ) ) {
				$data = $api_response['data'];
				// Insert the refresh token manually back into the response.
				// Google doesn't return a new refresh token when refreshing - the old one remains valid.
				$data['refresh_token'] = $refresh_token;
				$data['since']         = time();
				$this->helpers->store_credentials( $data );
				$response['data'] = $data;
			} else {
				$response['error'] = esc_html_x( 'Failed to refresh access token', 'Google Sheets', 'uncanny-automator' );
			}
		} catch ( Exception $e ) {
			$response['error'] = 'Exception: ' . $e->getMessage();
		}

		return $response;
	}

	/**
	 * Handle file picker selections REST request
	 *
	 * @param array $response
	 * @param array $data
	 * @return array
	 */
	public function handle_file_picker( $response = array(), $data = array() ) {

		$spreadsheets = $data['spreadsheets'] ?? array();

		if ( empty( $spreadsheets ) ) {
			$response['error'] = esc_html_x( 'No spreadsheets provided', 'Google Sheets', 'uncanny-automator' );
			return $response;
		}

		try {
			$current_spreadsheets        = $this->helpers->get_spreadsheets();
			$new_spreadsheets_collection = $this->merge_spreadsheets_options( $current_spreadsheets, $spreadsheets );
			$update_result               = $this->helpers->store_spreadsheets( $new_spreadsheets_collection );

			if ( $update_result ) {
				$final_spreadsheets  = $this->helpers->get_spreadsheets();
				$response['data']    = $final_spreadsheets;
				$response['success'] = true;
			} else {
				$response['error'] = esc_html_x( 'Failed to save spreadsheets', 'Google Sheets', 'uncanny-automator' );
			}
		} catch ( Exception $e ) {
			$response['error'] = 'Exception: ' . $e->getMessage();
		}

		return $response;
	}

	/**
	 * Handle spreadsheet removal via modern framework
	 *
	 * @param array $response The current response array
	 * @param array $data The posted data
	 *
	 * @return array
	 */
	public function handle_remove_spreadsheet( $response = array(), $data = array() ) {
		$spreadsheet_id = $this->maybe_get_posted_row_id( $data );

		if ( empty( $spreadsheet_id ) ) {
			$response['alert'] = $this->get_error_alert(
				esc_attr_x( 'Unable to remove spreadsheet', 'Google Sheets', 'uncanny-automator' ),
				esc_html_x( 'Invalid spreadsheet ID', 'Google Sheets', 'uncanny-automator' )
			);
			return $response;
		}

		// Get current spreadsheets.
		$current_spreadsheets = $this->helpers->get_spreadsheets();

		// Remove the specified spreadsheet.
		$updated_spreadsheets = array();
		$removed              = false;

		foreach ( $current_spreadsheets as $spreadsheet ) {
			if ( $spreadsheet['id'] !== $spreadsheet_id ) {
				$updated_spreadsheets[] = $spreadsheet;
			} else {
				$removed = true;
			}
		}

		if ( ! $removed ) {
			$response['alert'] = $this->get_error_alert(
				esc_attr_x( 'Spreadsheet not found', 'Google Sheets', 'uncanny-automator' ),
				esc_html_x( 'The specified spreadsheet could not be found', 'Google Sheets', 'uncanny-automator' )
			);
			return $response;
		}

		// Store updated list.
		$this->helpers->store_spreadsheets( $updated_spreadsheets );

		// Get updated table data.
		$table_data = $this->get_spreadsheet_table_data( $updated_spreadsheets );

		// Set success response.
		$response['data']  = $table_data['data'];
		$response['alert'] = $this->get_success_alert(
			esc_html_x( 'Spreadsheet removed', 'Google Sheets', 'uncanny-automator' ),
			esc_html_x( 'The spreadsheet has been successfully removed', 'Google Sheets', 'uncanny-automator' )
		);

		return $response;
	}

	/**
	 * Get spreadsheet table data for dynamic updates.
	 *
	 * @param array|null $spreadsheets
	 *
	 * @return array
	 */
	private function get_spreadsheet_table_data( $spreadsheets = null ) {
		$spreadsheets = $spreadsheets ?? $this->helpers->get_spreadsheets();

		$columns = array(
			array( 'key' => 'icon' ),
			array( 'key' => 'name' ),
			array( 'key' => 'actions' ),
		);

		$table_data = array();

		foreach ( $spreadsheets as $spreadsheet ) {
			$table_data[] = array(
				'id'      => $spreadsheet['id'],
				'columns' => array(
					'icon'    => array(
						'options' => array(
							array(
								'type' => 'icon',
								'data' => array( 'integration' => 'GOOGLESHEET' ),
							),
						),
					),
					'name'    => array(
						'options' => array(
							array(
								'type' => 'text',
								'data' => $spreadsheet['name'],
							),
						),
					),
					'actions' => array(
						'options' => array(
							array(
								'type' => 'button',
								// phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
								'data' => array(
									'type'                      => 'submit',
									'name'                      => 'automator_action',
									'value'                     => 'remove_spreadsheet',
									'row-submission'            => true,
									'label'                     => esc_html_x( 'Remove', 'Google Sheet', 'uncanny-automator' ),
									'size'                      => 'small',
									'color'                     => 'danger',
									'needs-confirmation'        => true,
									'confirmation-heading'      => esc_html_x( 'This action is irreversible', 'Google Sheet', 'uncanny-automator' ),
									'confirmation-content'      => esc_html_x( 'Are you sure you want to remove this spreadsheet?', 'Google Sheet', 'uncanny-automator' ),
									'confirmation-button-label' => esc_html_x( 'Confirm', 'Google Sheet', 'uncanny-automator' ),
									'icon'                      => 'trash',
								),
								// phpcs:enable
							),
						),
					),
				),
			);
		}

		return array(
			'columns' => $columns,
			'data'    => $table_data,
		);
	}

	/**
	 * Merge the spreadsheets options.
	 *
	 * @param array $current_spreadsheets
	 * @param array $spreadsheets
	 *
	 * @return array
	 */
	private function merge_spreadsheets_options( $current_spreadsheets, $spreadsheets ) {
		$documents = array_merge( (array) $current_spreadsheets, (array) $spreadsheets );
		return $this->remove_duplicate_spreadsheets_by_id( $documents );
	}

	/**
	 * Remove duplicate spreadsheets by spreadsheet id.
	 *
	 * @param array $documents
	 *
	 * @return array
	 */
	private function remove_duplicate_spreadsheets_by_id( $documents ) {
		$unique_documents = array();
		$unique_ids       = array();

		foreach ( (array) $documents as $key => $document ) {
			if ( ! isset( $document['id'] ) ) {
				continue;
			}
			if ( ! in_array( $document['id'], $unique_ids ) ) { // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
				$unique_ids[]       = $document['id'];
				$unique_documents[] = $document;
			}
		}

		return $unique_documents;
	}
}
