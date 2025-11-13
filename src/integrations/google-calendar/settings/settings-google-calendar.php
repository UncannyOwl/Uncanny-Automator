<?php
namespace Uncanny_Automator\Integrations\Google_Calendar;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Uncanny_Automator\Settings\OAuth_App_Integration;
use Exception;

/**
 * @package Uncanny_Automator\Integrations\Google Calendar
 *
 * @since 5.0
 *
 * @property Google_Calendar_Helpers $helpers
 * @property Google_Calendar_Api_Caller $api
 */
class Google_Calendar_Settings extends App_Integration_Settings {

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
	private function validate_user_transient_status() {
		try {
			// Try to get account info to verify connection is still valid.
			$this->helpers->get_account_info();
		} catch ( Exception $e ) {
			// Connection is no longer valid, clear stored data.
			$this->helpers->clear_connection();

			// Update the connection status for proper templating.
			$this->set_is_connected( false );

			// Register an alert to inform the user.
			$this->register_alert(
				$this->get_error_alert(
					sprintf(
						// translators: 1: error code, 2: error message
						esc_html_x( 'An error has occurred while fetching the resource owner: (%1$s) %2$s', 'Google Calendar', 'uncanny-automator' ),
						absint( $e->getCode() ),
						esc_html( $e->getMessage() )
					),
					esc_html_x( 'Error exception', 'Google Calendar', 'uncanny-automator' )
				)
			);
		}
	}

	/////////////////////////////////////////////////////////////
	// Required Abstract method.
	/////////////////////////////////////////////////////////////

	/**
	 * Get formatted account information for connected user info display
	 *
	 * @return array Formatted account information for UI display
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

	/////////////////////////////////////////////////////////////
	// Templating methods.
	/////////////////////////////////////////////////////////////

	/**
	 * Display - Main disconnected content
	 *
	 * @return void - Outputs HTML directly
	 */
	public function output_main_disconnected_content() {
		$this->output_disconnected_header(
			esc_html_x(
				'Connect Uncanny Automator to Google Calendar to automatically create calendar events when users perform actions like submitting forms, joining groups and making purchases on your site.',
				'Google Calendar',
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
		// Single account warning.
		$this->output_single_account_message(
			esc_html_x( 'You can only link Google Calendars that you have read and write access to.', 'Google Calendar', 'uncanny-automator' )
		);

		// Calendars subtitle.
		$this->output_panel_subtitle(
			esc_html_x( 'Linked calendars', 'Google Calendar', 'uncanny-automator' ),
			'uap-spacing-bottom'
		);

		// Calendars list.
		$this->output_calendar_list();
	}

	/**
	 * Output the OAuth connect button - overriding the abstract method.
	 *
	 * @return void
	 */
	public function output_oauth_connect_button() {
		$this->output_action_button(
			'oauth_init',
			esc_html_x( 'Sign in with Google', 'Google Calendar', 'uncanny-automator' ),
			array(
				'class' => 'uap-settings-button-google',
				'icon'  => 'google',
			)
		);
	}

	/////////////////////////////////////////////////////////////
	// OAuth handling overrides.
	/////////////////////////////////////////////////////////////

	/**
	 * Register error message alert - override to handle auth_error formatting.
	 *
	 * @return void
	 */
	public function register_oauth_error_alert( $message ) {
		$message = str_replace( ' ', '_', strtolower( rawurlencode( $message ) ) );
		$this->register_alert( $this->get_error_alert( $message ) );
	}

	/**
	 * Validate integration-specific credentials
	 *
	 * @param array $credentials
	 * @return array
	 */
	protected function validate_integration_credentials( $credentials ) {
		if ( $this->has_missing_scopes( $credentials ) ) {
			$this->register_oauth_error_alert( esc_html_x( 'missing_scope', 'Google Calendar', 'uncanny-automator' ) );
		}

		return $credentials;
	}

	/**
	 * Authorize the account.
	 *
	 * @param array $response
	 * @param array $credentials
	 *
	 * @return array
	 * @throws Exception
	 */
	protected function authorize_account( $response, $credentials ) {
		// Ensure any transients are cleared.
		$this->helpers->delete_account_info();

		// Validate the connected account and let it throw an exception if it fails.
		// Abstract will handle it from there.
		$this->helpers->get_account_info();

		return $response;
	}

	/////////////////////////////////////////////////////////////
	// Custom form handling.
	/////////////////////////////////////////////////////////////

	/**
	 * Handle calendar sync
	 *
	 * @param array $response - The current response array
	 * @param array $data - The data posted to the settings page.
	 *
	 * @return array
	 */
	protected function handle_calendar_sync( $response = array(), $data = array() ) {
		try {
			// Clear the calendar list transient to force refresh
			$this->helpers->get_calendars( true );

			$this->register_alert(
				array(
					'type'    => 'success',
					'heading' => esc_html_x( 'Calendar list refreshed', 'Google Calendar', 'uncanny-automator' ),
					'content' => esc_html_x( 'The list of available calendars has been refreshed successfully.', 'Google Calendar', 'uncanny-automator' ),
				)
			);
		} catch ( Exception $e ) {
			$this->register_alert(
				array(
					'type'    => 'error',
					'heading' => esc_html_x( 'Calendar refresh failed', 'Google Calendar', 'uncanny-automator' ),
					'content' => $e->getMessage(),
				)
			);
		}

		// Reload the page.
		$response['reload'] = true;
		return $response;
	}

	/////////////////////////////////////////////////////////////
	// OAuth custom methods.
	/////////////////////////////////////////////////////////////

	/**
	 * Check if the user has missing scopes.
	 *
	 * @param array $token The access token combination.
	 *
	 * @return boolean True if there are scopes missing. Otherwise, false.
	 */
	private function has_missing_scopes( $token ) {

		if ( ! isset( $token['scope'] ) || empty( $token['scope'] ) ) {
			return true;
		}

		$scopes = array(
			'https://www.googleapis.com/auth/calendar',
			'https://www.googleapis.com/auth/calendar.events',
		);

		$has_missing_scope = false;

		foreach ( $scopes as $scope ) {
			if ( false === strpos( $token['scope'], $scope ) ) {
				$has_missing_scope = true;
			}
		}

		return $has_missing_scope;
	}

	/////////////////////////////////////////////////////////////
	// Custom Google Calendar templating.
	/////////////////////////////////////////////////////////////

	/**
	 * Output the calendar list
	 *
	 * @return void - Outputs HTML directly
	 */
	private function output_calendar_list() {
		try {
			$calendars = $this->helpers->get_calendars();

			if ( empty( $calendars ) ) {
				$this->alert_html(
					array(
						'type'    => 'warning',
						'heading' => esc_html_x( 'No calendars found.', 'Google Calendar', 'uncanny-automator' ),
						'content' => esc_html_x( 'Please make sure you have access to Google Calendar and try refreshing the list.', 'Google Calendar', 'uncanny-automator' ),
						'class'   => 'uap-spacing-bottom',
					)
				);
				return;
			}

			$this->output_calendar_table( $calendars );
			$this->output_calendar_sync_alert();

		} catch ( Exception $e ) {
			$this->alert_html(
				array(
					'type'    => 'error',
					'heading' => esc_html_x( 'Error loading calendars', 'Google Calendar', 'uncanny-automator' ),
					'content' => esc_html( $e->getMessage() ),
					'class'   => 'uap-spacing-bottom',
				)
			);
		}
	}

	/**
	 * Output the calendar table
	 *
	 * @param array $calendars The calendars to output
	 *
	 * @return void - Outputs HTML directly
	 */
	private function output_calendar_table( $calendars ) {
		// Define the columns for the table.
		$columns = array(
			array(
				'key' => 'icon',
			),
			array(
				'key' => 'name',
			),
			array(
				'key' => 'access',
			),
		);

		// Ensure we have a sequential array
		$calendars = array_values( $calendars );

		// Format the data for the table component following Brevo pattern.
		$table_data = array_map(
			function ( $calendar ) {
				$is_primary    = ! empty( $calendar['primary'] );
				$calendar_name = esc_html( $calendar['summary'] ?? $calendar['id'] );
				$access_role   = esc_html( ucfirst( $calendar['accessRole'] ?? 'reader' ) );

				// Add primary indicator to name if it's the primary calendar
				if ( $is_primary ) {
					$calendar_name .= ' ' . esc_html_x( '(Primary)', 'Google Calendar', 'uncanny-automator' );
				}

				return array(
					'id'      => $calendar['id'],
					'columns' => array(
						'icon'   => array(
							'options' => array(
								array(
									'type' => 'icon',
									'data' => array(
										'integration' => 'GOOGLE_CALENDAR',
									),
								),
							),
						),
						'name'   => array(
							'options' => array(
								array(
									'type' => 'text',
									'data' => $calendar_name,
								),
							),
						),
						'access' => array(
							'options' => array(
								array(
									'type' => 'text',
									'data' => $access_role,
								),
							),
						),
					),
				);
			},
			$calendars
		);

		$this->output_settings_table( $columns, $table_data, 'card' );
	}

	/**
	 * Output the calendar sync alert
	 *
	 * @return void - Outputs HTML directly
	 */
	private function output_calendar_sync_alert() {
		?>
		<uo-alert heading="<?php echo esc_attr_x( 'Need to refresh your calendar list?', 'Google Calendar', 'uncanny-automator' ); ?>" 
			class="uap-spacing-bottom">
			<p><?php echo esc_html_x( "If you've added or removed calendars since connecting, click the Refresh Calendars button below to update the list.", 'Google Calendar', 'uncanny-automator' ); ?></p>
			<?php
			$this->output_action_button(
				'calendar_sync',
				esc_html_x( 'Refresh calendars', 'Google Calendar', 'uncanny-automator' ),
				array(
					'color' => 'secondary',
				)
			);
			?>
		</uo-alert>
		<?php
	}
}
