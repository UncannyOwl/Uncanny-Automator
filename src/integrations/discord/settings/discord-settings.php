<?php
/**
 * Creates the settings page
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator\Integrations\Discord;

use Exception;
use Uncanny_Automator\Settings\App_Integration_Settings;
use Uncanny_Automator\Settings\OAuth_App_Integration;

/**
 * Discord_Settings
 *
 * @property Discord_App_Helpers $helpers
 * @property Discord_Api_Caller $api
 */
class Discord_Settings extends App_Integration_Settings {

	use OAuth_App_Integration;

	////////////////////////////////////////////////////////////
	// Required abstract method
	////////////////////////////////////////////////////////////

	/**
	 * Get formatted account information for connected user info display
	 *
	 * @return array Formatted account information for UI display
	 */
	protected function get_formatted_account_info() {
		$account    = $this->helpers->get_account_info();
		$avatar_url = ! empty( $account['avatar'] )
			? 'https://cdn.discordapp.com/avatars/' . $account['id'] . '/' . $account['avatar'] . '.png'
			: '';

		return array(
			'avatar_url' => $avatar_url,
			'main_info'  => $account['email'],
			'additional' => $account['username'],
		);
	}

	////////////////////////////////////////////////////////////
	// Override framework methods.
	////////////////////////////////////////////////////////////

	/**
	 * Validate integration credentials
	 *
	 * @param array $credentials
	 *
	 * @return array
	 */
	public function validate_integration_credentials( $credentials ) {

		// Check for vault signature.
		$this->validate_vault_signature( $credentials );

		// Validate Discord ID was returned.
		if ( empty( $credentials['discord_id'] ) ) {
			throw new Exception(
				esc_html_x( 'Missing Discord ID', 'Integration settings', 'uncanny-automator' )
			);
		}

		return $credentials;
	}

	/**
	 * Store credentials in uap_options table
	 *
	 * @param array $credentials
	 *
	 * @return void
	 */
	protected function store_credentials( $credentials ) {
		$this->helpers->store_credentials( $credentials );

		// If this is initial main user account connection, sync the servers.
		if ( isset( $credentials['user'] ) ) {
			$this->api->get_servers();
		}
	}

	/**
	 * Register success message alert
	 *
	 * @param array $credentials
	 *
	 * @return void
	 */
	protected function register_oauth_success_alert( $credentials ) {
		// Check if this is a server connection.
		$is_bot = $credentials['bot'] ?? false;
		if ( $is_bot ) {
			$discord_id = $credentials['discord_id'] ?? '';
			$server     = $this->helpers->get_server_by_id( absint( $discord_id ) );
			$this->register_alert(
				array(
					'type'    => 'success',
					'heading' => esc_html_x( 'Server connected', 'Discord', 'uncanny-automator' ),
					'content' => sprintf(
						// translators: %s: Server name.
						esc_html_x( '%s has been connected successfully for use in your recipes.', 'Discord', 'uncanny-automator' ),
						$server['name']
					),
				)
			);
			return;
		}

		// Initial account connection flow.
		$this->register_alert( $this->get_connected_alert() );
	}

	/**
	 * Before Authorized Account Disconnect.
	 *
	 * @param array $response The current response array
	 * @param array $data The posted data
	 *
	 * @return void
	 */
	protected function before_disconnect( $response = array(), $data = array() ) {
		$this->helpers->remove_credentials();

		return $response;
	}

	/**
	 * Maybe filter the OAuth args for server ID bot connection flow.
	 *
	 * @param array $args
	 * @param array $data
	 *
	 * @return array
	 */
	protected function maybe_filter_oauth_args( $args, $data = array() ) {
		// Check if the server ID has been posted and add it to the args.
		$server_id = $this->maybe_get_posted_row_id( $data );
		if ( ! empty( $server_id ) ) {
			$args['bot_server_id'] = $server_id;
		}

		return $args;
	}

	////////////////////////////////////////////////////////////
	// Custom Discord form handling.
	////////////////////////////////////////////////////////////

	/**
	 * Handle server disconnect
	 *
	 * @param array $response - The current response array
	 * @param array $data - The data posted to the settings page.
	 *
	 * @return array
	 */
	protected function handle_server_disconnect( $response = array(), $data = array() ) {
		try {
			$server_id = $this->maybe_get_posted_row_id( $data );
			$this->helpers->disconnect( $server_id );
			$this->register_alert(
				array(
					'type'    => 'success',
					'heading' => esc_html_x( 'Server disconnected', 'Discord', 'uncanny-automator' ),
					'content' => sprintf(
						// translators: %1$s is the server name.
						esc_html_x( 'The server "%1$s" has been disconnected successfully.', 'Discord', 'uncanny-automator' ),
						$this->get_server_name( $server_id )
					),
				)
			);
		} catch ( Exception $e ) {
			$this->register_alert(
				array(
					'type'    => 'error',
					'heading' => esc_html_x( 'Server disconnect failed', 'Discord', 'uncanny-automator' ),
					'content' => $e->getMessage(),
				)
			);
		}

		// Redirect to settings page.
		$response['reload'] = true;
		return $response;
	}

	/**
	 * Handle server sync
	 *
	 * @param array $response - The current response array
	 * @param array $data - The data posted to the settings page.
	 *
	 * @return array
	 */
	protected function handle_server_sync( $response = array(), $data = array() ) {
		try {
			$this->api->get_servers( true ); // Force refresh
			$this->register_alert(
				array(
					'type'    => 'success',
					'heading' => esc_html_x( 'Server list synced', 'Discord', 'uncanny-automator' ),
					'content' => esc_html_x( 'The list of available servers have been synced successfully.', 'Discord', 'uncanny-automator' ),
				)
			);
		} catch ( Exception $e ) {
			$this->register_alert(
				array(
					'type'    => 'error',
					'heading' => esc_html_x( 'Server sync failed', 'Discord', 'uncanny-automator' ),
					'content' => $e->getMessage(),
				)
			);
		}

		// Redirect to settings page.
		$response['reload'] = true;
		return $response;
	}

	////////////////////////////////////////////////////////////
	// Custom Settings helper methods.
	////////////////////////////////////////////////////////////

	/**
	 * Get server name by ID
	 *
	 * @param int $server_id
	 *
	 * @return string
	 */
	private function get_server_name( $server_id ) {
		$server = $this->helpers->get_server_by_id( absint( $server_id ) );
		return $server['name'] ?? '';
	}

	////////////////////////////////////////////////////////////
	// Content output methods.
	////////////////////////////////////////////////////////////

	/**
	 * Display - Main panel disconnected content.
	 *
	 * @return string - HTML
	 */
	public function output_main_disconnected_content() {
		// Output the standard disconnected integration header with description.
		$this->output_disconnected_header(
			esc_html_x( 'Connect Uncanny Automator to Discord to streamline automations to message and manage Servers and Members', 'Discord', 'uncanny-automator' )
		);

		// Automatically generated list of available triggers and actions.
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
				'heading' => esc_html_x( 'Uncanny Automator only supports connecting to one Discord account at a time, although you may connect multiple servers.', 'Discord', 'uncanny-automator' ),
				'class'   => 'uap-spacing-bottom',
			)
		);

		// Servers subtitle.
		$this->output_panel_subtitle( esc_html_x( 'Servers', 'Discord', 'uncanny-automator' ) );

		// Servers description.
		$this->output_subtle_panel_paragraph(
			esc_html_x( 'The following servers are available to connect for use in your recipes :', 'Discord', 'uncanny-automator' )
		);

		// Servers list.
		$this->output_server_list();
	}

	////////////////////////////////////////////////////////////
	// Custom Discord templating.
	////////////////////////////////////////////////////////////

	/**
	 * Output the server list
	 *
	 * @return string HTML
	 */
	private function output_server_list() {
		$servers = $this->api->get_servers();
		if ( empty( $servers ) ) {
			$this->alert_html(
				array(
					'type'    => 'warning',
					'heading' => esc_html_x( 'No servers found.', 'Discord', 'uncanny-automator' ),
					'class'   => 'uap-spacing-bottom',
				)
			);
			return;
		}

		$this->output_server_table( $servers );
		$this->output_resync_alert();
		$this->output_user_verification_alert();
	}

	/**
	 * Output the server table
	 *
	 * @param array $servers The servers to output
	 *
	 * @return void - Outputs HTML directly
	 */
	private function output_server_table( $servers ) {
		// Define the columns for the table.
		$columns = array(
			array(
				'header' => esc_html_x( 'Server', 'Discord', 'uncanny-automator' ),
				'key'    => 'name',
			),
			array(
				'header' => esc_html_x( 'Status', 'Discord', 'uncanny-automator' ),
				'key'    => 'status',
			),
			array(
				'header' => esc_html_x( 'Action', 'Discord', 'uncanny-automator' ),
				'key'    => 'action',
			),
		);

		// Ensure we have a sequential array
		$servers = array_values( $servers );

		// Format the data for the table component.
		$table_data = array_map(
			function ( $server ) {
				$is_connected = ! empty( $server['connected'] );
				$action       = $is_connected ? 'server_disconnect' : 'oauth_init';
				return array(
					'id'      => $server['id'],
					'columns' => array(
						'name'   => array(
							'options' => array(
								array(
									'type' => 'text',
									'data' => $server['name'],
								),
							),
						),
						'status' => array(
							'options' => array(
								array(
									'type' => 'text',
									'data' => $is_connected
										? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $server['connected'] )
										: esc_html_x( 'Not connected', 'Discord', 'uncanny-automator' ),
								),
							),
						),
						'action' => array(
							'options' => array(
								array(
									'type' => 'button',
									'data' => array(
										// phpcs:disable WordPress.Arrays
										'name'                      => 'automator_action',
										'value'                     => $action,
										'label'                     => $is_connected
											? esc_html_x( 'Disconnect server', 'Discord', 'uncanny-automator' )
											: esc_html_x( 'Connect server', 'Discord', 'uncanny-automator' ),
										'color'                     => 'secondary',
										'type'                      => 'submit',
										'row-submission'            => true,
										'needs-confirmation'        => $is_connected,
										'confirmation-heading'      => $is_connected
											? esc_html_x( 'Disconnect Server', 'Discord', 'uncanny-automator' )
											: '',
										'confirmation-content'      => $is_connected
											? esc_html_x( 'Are you sure you want to disconnect this server? This will remove the connection between Automator and Discord.', 'Discord', 'uncanny-automator' )
											: '',
										'confirmation-button-label' => $is_connected
											? esc_html_x( 'Yes, disconnect server', 'Discord', 'uncanny-automator' )
											: '',
										// phpcs:enable WordPress.Arrays
									),
								),
							),
						),
					),
				);
			},
			$servers
		);

		$this->output_settings_table( $columns, $table_data );
	}

	/**
	 * Output the resync alert for the `handle_server_sync` method.
	 *
	 * @return void - Outputs HTML directly
	 */
	private function output_resync_alert() {
		?>
		<uo-alert heading="<?php echo esc_attr_x( 'Need to update your server list?', 'Discord', 'uncanny-automator' ); ?>" 
			class="uap-spacing-bottom">
			<p><?php echo esc_html_x( "If you've added or removed servers since connecting, click the Resync Servers button below to refresh the list.", 'Discord', 'uncanny-automator' ); ?></p>
			<?php
			$this->output_action_button(
				'server_sync',
				esc_html_x( 'Resync servers', 'Discord', 'uncanny-automator' ),
				array(
					'color' => 'secondary',
				)
			);
			?>
		</uo-alert>
		<?php
	}

	/**
	 * Output the user verification alert
	 *
	 * @return string HTML
	 */
	private function output_user_verification_alert() {
		?>
		<uo-alert 
			heading="<?php echo esc_attr_x( 'User Discord Verification', 'Discord', 'uncanny-automator' ); ?>" 
			class="uap-spacing-bottom">
			<p><?php echo esc_html_x( 'To fully automate your WordPress users for use within Discord actions requiring selecting a Member, have your users verify their account using this shortcode:', 'Discord', 'uncanny-automator' ); ?><code>[automator_discord_user_mapping]</code></p>
			<p><?php echo esc_html_x( 'This will capture their Discord Member ID and save it to user meta key:', 'Discord', 'uncanny-automator' ); ?><code>automator_discord_member_id</code></p>
			<p>
				<uo-button href="https://automatorplugin.com/knowledge-base/discord/#4-user-discord-verification" target="_blank">
					<?php echo esc_html_x( 'Learn more', 'Discord', 'uncanny-automator' ); ?>
				</uo-button>
			</p>
		</uo-alert>
		<?php
	}
}
