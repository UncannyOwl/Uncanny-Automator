<?php
/**
 * Creates the settings page
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator\Integrations\Discord;

use Exception;

/**
 * Discord_Settings
 */
class Discord_Settings extends \Uncanny_Automator\Settings\Premium_Integration_Settings {

	/**
	 * The connected status
	 *
	 * @var bool
	 */
	private $is_connected;

	/**
	 * Resync servers
	 *
	 * @var bool
	 */
	private $resync_servers = false;

	/**
	 * The nonce key
	 *
	 * @var string
	 */
	const NONCE_KEY = 'automator_discord';

	/**
	 * Integration status.
	 *
	 * @return string - 'success' or empty string
	 */
	public function get_status() {
		return $this->helpers->integration_status();
	}

	/**
	 * Set the properties of the class and the integration
	 */
	public function set_properties() {

		// The unique page ID that will be added to the URL.
		$this->set_id( 'discord' );

		// The integration icon will be used for the settings page.
		$this->set_icon( 'DISCORD' );

		// The name of the settings tab
		$this->set_name( 'Discord' );

		$this->is_connected = $this->helpers->is_connected();

		$this->check_for_errors();

		// Handle the disconnect button action.
		add_action( 'init', array( $this, 'disconnect' ), AUTOMATOR_APP_INTEGRATIONS_PRIORITY );
		// Capture the OAuth tokens.
		add_action( 'init', array( $this, 'capture_oauth_tokens' ), AUTOMATOR_APP_INTEGRATIONS_PRIORITY );
	}

	/**
	 * Check for request errors.
	 *
	 * @return void
	 */
	public function check_for_errors() {

		if ( ! $this->is_current_page_settings() ) {
			return;
		}

		// Initial connection.
		$connection_result = automator_filter_input( 'connect' );
		if ( '1' === $connection_result ) {
			$this->add_alert(
				array(
					'type'    => 'success',
					'heading' => esc_html_x( 'Connected', 'Discord', 'uncanny-automator' ),
					'content' => esc_html_x( 'The integration has been connected successfully.', 'Discord', 'uncanny-automator' ),
				)
			);
		}

		// Check for server sync.
		$sync_servers = automator_filter_input( 'sync-servers' );
		if ( '1' === $sync_servers ) {
			$this->resync_servers = true;
			$this->add_alert(
				array(
					'type'    => 'success',
					'heading' => esc_html_x( 'Server list synced', 'Discord', 'uncanny-automator' ),
					'content' => esc_html_x( 'The list of available servers have been synced successfully.', 'Discord', 'uncanny-automator' ),
				)
			);
		}

		// Server connected.
		$server_connected = automator_filter_input( 'server-connect' );
		if ( absint( $server_connected ) > 0 ) {
			$server = $this->helpers->get_server_by_id( $server_connected );
			$this->add_alert(
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
		}

		// Server disconnected.
		$server_disconnected = automator_filter_input( 'server-disconnect' );
		if ( absint( $server_disconnected ) > 0 ) {
			$server = $this->helpers->get_server_by_id( $server_disconnected );
			$this->add_alert(
				array(
					'type'    => 'success',
					'heading' => esc_html_x( 'Server disconnected', 'Discord', 'uncanny-automator' ),
					'content' => sprintf(
						// translators: %s: Server name.
						esc_html_x( '%s has been disconnected successfully.', 'Discord', 'uncanny-automator' ),
						$server['name']
					),
				)
			);
		}

		$error = automator_filter_input( 'error' );

		if ( '' !== $error ) {
			$this->add_alert(
				array(
					'type'    => 'error',
					'heading' => esc_html_x( 'Something went wrong', 'Discord', 'uncanny-automator' ),
					'content' => $error,
				)
			);
		}
	}

	/**
	 * Generate a secure disconnect URL with nonce.
	 *
	 * @param string $type The type of disconnect ('user' or 'server').
	 * @param int    $id   The server ID if type is 'server'.
	 *
	 * @return string The secure disconnect URL.
	 */
	private function get_secure_disconnect_url( $type, $id = 0 ) {
		$args = array(
			'_wpnonce' => wp_create_nonce( self::NONCE_KEY ),
		);

		if ( 'user' === $type ) {
			$args['disconnect'] = '1';
		} elseif ( 'server' === $type && absint( $id ) > 0 ) {
			$args['disconnect-server'] = absint( $id );
		}

		return add_query_arg( $args, $this->get_settings_page_url() );
	}

	/**
	 * Disconnect the integration
	 *
	 * @return void
	 */
	public function disconnect() {

		// Make sure this settings page is the one that is active
		if ( ! $this->is_current_page_settings() ) {
			return;
		}

		$disconnect_server = automator_filter_input( 'disconnect-server' );
		$disconnect_user   = automator_filter_input( 'disconnect' );

		// Bail early if no disconnect action is requested
		if ( '1' !== $disconnect_user && empty( $disconnect_server ) ) {
			return;
		}

		// Validate nonce
		$nonce = automator_filter_input( '_wpnonce' );
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, self::NONCE_KEY ) ) {
			wp_die( 'Error 403: Invalid security token.' );
		}

		// Validate user capabilities
		$this->validate_user_capabilities();

		// Handle user disconnect.
		if ( '1' === $disconnect_user ) {
			$this->helpers->remove_credentials();
			wp_safe_redirect( $this->get_settings_page_url() );
			exit;
		}

		// Handle server disconnect.
		if ( absint( $disconnect_server ) > 0 ) {
			$this->helpers->disconnect( $disconnect_server );
			$this->helpers->update_server_connected_status( $disconnect_server, 0 );
			wp_safe_redirect( $this->get_settings_page_url() . '&server-disconnect=' . $disconnect_server );
			exit;
		}
	}

	/**
	 * Capture Oauth tokens
	 *
	 * @return void
	 */
	public function capture_oauth_tokens() {

		if ( ! $this->is_current_page_settings() ) {
			return;
		}

		$automator_message = automator_filter_input( 'automator_api_message' );

		if ( empty( $automator_message ) ) {
			return;
		}

		// Validate user capabilities.
		$this->validate_user_capabilities();

		$credentials = (array) \Uncanny_Automator\Automator_Helpers_Recipe::automator_api_decode_message( $automator_message, wp_create_nonce( self::NONCE_KEY ) );

		// Validate token vault details.
		if ( empty( $credentials['discord_id'] ) || empty( $credentials['vault_signature'] ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'error' => esc_html_x( 'Missing credentials', 'Discord', 'uncanny-automator' ),
					),
					$this->get_settings_page_url()
				)
			);
			die;
		}

		$connect = $this->helpers->store_credentials( $credentials );
		$args    = array();

		// Check for user details ( initial connection )
		if ( isset( $credentials['user'] ) ) {
			// Get the servers.
			$this->helpers->api()->get_servers();
			$args['connect'] = $connect;
		}

		// Check for server details ( connecting a server )
		if ( isset( $credentials['bot'] ) ) {
			$args['server-connect'] = $credentials['discord_id'];
		}

		wp_safe_redirect(
			add_query_arg(
				$args,
				$this->get_settings_page_url()
			)
		);

		die;
	}

	/**
	 * Validate user capabilities.
	 *
	 * @return void
	 */
	public function validate_user_capabilities() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Error 403: Insufficient permissions.' );
		}
	}

	/**
	 * Generates the OAuth2 URL.
	 *
	 * @param mixed null | string $server_id - The server ID.
	 *
	 * @return string The OAuth URL.
	 */
	public function get_oauth_url( $server_id = null ) {

		$args = array(
			'action'       => 'authorization_request',
			'nonce'        => wp_create_nonce( self::NONCE_KEY ),
			'redirect_url' => rawurlencode( $this->get_settings_page_url() ),
			'plugin_ver'   => AUTOMATOR_PLUGIN_VERSION,
		);

		if ( ! is_null( $server_id ) ) {
			$args['bot_server_id'] = $server_id;
		}

		return add_query_arg(
			$args,
			AUTOMATOR_API_URL . $this->helpers->api()->get_api_endpoint()
		);
	}

	/**
	 * Display - Settings panel.
	 *
	 * @return string - HTML
	 * @throws Exception
	 */
	public function output_panel() {
		?>
		<div class="uap-settings-panel">
			<div class="uap-settings-panel-top">
				<?php $this->output_panel_top(); ?>
				<?php $this->display_alerts(); ?>
				<div class="uap-settings-panel-content">
					<?php $this->output_panel_content(); ?>
				</div>
			</div>
			<div class="uap-settings-panel-bottom" <?php echo esc_attr( ! $this->is_connected ? 'has-arrow' : '' ); ?>>
				<?php $this->output_panel_bottom(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Display - Main panel content.
	 *
	 * @return string - HTML
	 */
	public function output_panel_content() {

		if ( $this->is_connected ) {
			$this->output_panel_content_connected();
			return;
		}

		$this->output_panel_content_disconnected();
	}

	/**
	 * Display - Connected main panel content.
	 *
	 * @return string - HTML
	 */
	public function output_panel_content_connected() {
		$link = $this->get_settings_page_url() . '&sync-servers=1';
		?>
		<uo-alert
			heading="<?php echo esc_attr_x( 'Uncanny Automator only supports connecting to one Discord account at a time, although you may connect multiple servers.', 'Discord', 'uncanny-automator' ); ?>" <?php // phpcs:ignore Uncanny_Automator.Strings.SentenceCase.PotentialCaseIssue ?>
			class="uap-spacing-bottom">
		</uo-alert>

		<div class="uap-settings-panel-content-subtitle">
			<?php echo esc_html_x( 'Servers', 'Discord', 'uncanny-automator' ); ?>
		</div>

		<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
			<p><?php echo esc_html_x( 'The following servers are available to connect for use in your recipes :', 'Discord', 'uncanny-automator' ); ?></p>
		</div>

		<?php
		$servers = $this->helpers->api()->get_servers( $this->resync_servers );
		if ( empty( $servers ) ) :
			?>
			<uo-alert type="warning" heading="<?php echo esc_attr_x( 'No servers found.', 'Discord', 'uncanny-automator' ); ?>" class="uap-spacing-bottom"></uo-alert>
			<?php
			return;
		endif;

		// Load the connected settings styles.
		$this->load_css( '/discord/settings/assets/style.css' );

		?>
		<div id="uap-discord-server-connect-list">
			<table>
				<thead>
					<tr>
						<th><?php echo esc_attr_x( 'Server', 'Discord', 'uncanny-automator' ); ?></th>
						<th><?php echo esc_attr_x( 'Status', 'Discord', 'uncanny-automator' ); ?></th>
						<th><?php echo esc_attr_x( 'Action', 'Discord', 'uncanny-automator' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $servers as $server ) :
						$this->output_server_row( $server );
					endforeach;
					?>
				</tbody>
			</table>
		</div>

		<uo-alert 
			heading="<?php echo esc_attr_x( 'Need to update your server list?', 'Discord', 'uncanny-automator' ); ?>" 
			class="uap-spacing-bottom">
			<p><?php echo esc_html_x( "If you've added or removed servers since connecting, click the Resync Servers button below to refresh the list.", 'Discord', 'uncanny-automator' ); ?></p>
			<uo-button href="<?php echo esc_url( $link ); ?>" type="button">
			<?php echo esc_html( esc_html_x( 'Resync servers', 'Discord', 'uncanny-automator' ) ); ?>
			</uo-button>
		</uo-alert>

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

	/**
	 * Display - Disconnected main panel content.
	 *
	 * @return string - HTML
	 */
	public function output_panel_content_disconnected() {
		?>
		<div class="uap-settings-panel-content-subtitle">
			<?php echo esc_html_x( 'Connect Uncanny Automator to Discord', 'Discord', 'uncanny-automator' ); ?>
		</div>

		<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
			<?php echo esc_html_x( 'Connect Uncanny Automator to Discord to streamline automations to message and manage Servers and Members', 'Discord', 'uncanny-automator' ); ?>
		</div>

		<p>
			<strong>
				<?php echo esc_html_x( 'Activating this integration will enable the following for use in your recipes:', 'Discord', 'uncanny-automator' ); ?>
			</strong>
		</p>

		<ul>
			<li>
				<uo-icon id="bolt"></uo-icon> <strong>
				<?php echo esc_html_x( 'Action:', 'Discord', 'uncanny-automator' ); ?></strong> 
				<?php echo esc_html_x( 'Send a message to a channel', 'Discord', 'uncanny-automator' ); ?>
			</li>
			<li>
				<uo-icon id="bolt"></uo-icon> <strong>
				<?php echo esc_html_x( 'Action:', 'Discord', 'uncanny-automator' ); ?></strong> 
				<?php echo esc_html_x( 'Send a direct message to a Discord member', 'Discord', 'uncanny-automator' ); ?>
			</li>
			<li>
				<uo-icon id="bolt"></uo-icon> <strong>
				<?php echo esc_html_x( 'Action:', 'Discord', 'uncanny-automator' ); ?></strong> 
				<?php echo esc_html_x( 'Assign a role to a member', 'Discord', 'uncanny-automator' ); ?>
			</li>
			<li>
				<uo-icon id="bolt"></uo-icon> <strong>
				<?php echo esc_html_x( 'Action:', 'Discord', 'uncanny-automator' ); ?></strong> 
				<?php echo esc_html_x( 'Remove a role from a member', 'Discord', 'uncanny-automator' ); ?>
			</li>
			<li>
				<uo-icon id="bolt"></uo-icon> <strong>
				<?php echo esc_html_x( 'Action:', 'Discord', 'uncanny-automator' ); ?></strong> 
				<?php echo esc_html_x( 'Remove a member', 'Discord', 'uncanny-automator' ); ?>
			</li>
			<li>
				<uo-icon id="bolt"></uo-icon> <strong>
				<?php echo esc_html_x( 'Action:', 'Discord', 'uncanny-automator' ); ?></strong> 
				<?php echo esc_html_x( 'Update a member', 'Discord', 'uncanny-automator' ); ?>
			</li>
			<li>
				<uo-icon id="bolt"></uo-icon> <strong>
				<?php echo esc_html_x( 'Action:', 'Discord', 'uncanny-automator' ); ?></strong> 
				<?php echo esc_html_x( 'Add a member to a channel', 'Discord', 'uncanny-automator' ); ?>
			</li>
			<li>
				<uo-icon id="bolt"></uo-icon> <strong>
				<?php echo esc_html_x( 'Action:', 'Discord', 'uncanny-automator' ); ?></strong> 
				<?php echo esc_html_x( 'Create a channel', 'Discord', 'uncanny-automator' ); ?>
			</li>
			<li>
				<uo-icon id="bolt"></uo-icon> <strong>
				<?php echo esc_html_x( 'Action:', 'Discord', 'uncanny-automator' ); ?></strong> 
				<?php echo esc_html_x( 'Send an invitation to join a server to an email', 'Discord', 'uncanny-automator' ); ?>
			</li>
		</ul>
		<?php
	}

	/**
	 * Display - Bottom left panel content.
	 *
	 * @return string - HTML
	 */
	public function output_panel_bottom_left() {

		// Add the connect button if not connected
		if ( ! $this->is_connected ) {
			?>
			<uo-button href="<?php echo esc_url( $this->get_oauth_url() ); ?>" type="button" target="_self" unsafe-force-target>
				<?php echo esc_html_x( 'Connect Discord account', 'Discord', 'uncanny-automator' ); ?>
			</uo-button>
			<?php
			return;
		}

		// Show the connected account details.
		$user  = $this->helpers->get_user_info();
		$name  = $user['username'];
		$email = $user['email'];
		// If the user has an avatar, get the URL.
		$avatar = $user['avatar'];
		if ( ! empty( $avatar ) ) {
			$avatar = 'https://cdn.discordapp.com/avatars/' . $user['id'] . '/' . $avatar . '.png';
		}

		?>
		<div class="uap-settings-panel-user">
			<div class="uap-settings-panel-user__avatar">
				<?php if ( ! empty( $avatar ) ) : ?>
					<img src="<?php echo esc_url( $avatar ); ?>" alt="<?php echo esc_attr( $name ); ?>">
				<?php else : ?>
					<uo-icon integration="DISCORD"></uo-icon>
				<?php endif; ?>
			</div>
			<div class="uap-settings-panel-user-info">
				<div class="uap-settings-panel-user-info__main">
					<?php echo esc_html( $email ); ?>
				</div>
				<div class="uap-settings-panel-user-info__additional">
					<?php echo esc_html( $name ); ?>		
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Display - Outputs the bottom right panel content
	 *
	 * @return string - HTML
	 */
	public function output_panel_bottom_right() {

		if ( ! $this->is_connected ) {
			return;
		}

		$link = $this->get_secure_disconnect_url( 'user' );

		?>
		<uo-button color="danger" href="<?php echo esc_url( $link ); ?>">
			<uo-icon id="sign-out"></uo-icon>
			<?php echo esc_html_x( 'Disconnect', 'Discord', 'uncanny-automator' ); ?>
		</uo-button>

		<?php
	}

	/**
	 * Output a server table row.
	 *
	 * @param array $server The server data.
	 *
	 * @return string
	 */
	public function output_server_row( $server ) {

		// Server is connected.
		if ( ! empty( $server['connected'] ) ) {
			$date  = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $server['connected'] );
			$url   = $this->get_secure_disconnect_url( 'server', $server['id'] );
			$label = esc_html_x( 'Disconnect server', 'Discord', 'uncanny-automator' );
		}

		// Server not connected.
		if ( empty( $server['connected'] ) ) {
			$date  = esc_html_x( 'Not connected', 'Discord', 'uncanny-automator' );
			$url   = $this->get_oauth_url( $server['id'] );
			$label = esc_html_x( 'Connect server', 'Discord', 'uncanny-automator' );
		}

		?>
		<tr>
			<td><?php echo esc_html( $server['name'] ); ?></td>
			<td class="uap-discord-server-connect-date"><?php echo esc_html( $date ); ?></td>
			<td>
				<uo-button href="<?php echo esc_url( $url ); ?>" type="button">
					<?php echo esc_html( $label ); ?>
				</uo-button>
			</td>
		</tr>
		<?php
	}
}
