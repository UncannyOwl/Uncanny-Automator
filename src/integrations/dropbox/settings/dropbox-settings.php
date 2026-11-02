<?php
/**
 * Dropbox Settings
 *
 * @package Uncanny_Automator
 * @author  Uncanny Owl
 */

namespace Uncanny_Automator\Integrations\Dropbox;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Uncanny_Automator\Settings\OAuth_App_Integration;
use Uncanny_Automator\Settings\OAuth_App_Integration_V3;
use Uncanny_Automator\Settings\Premium_Integration_Webhook_Settings;

/**
 * Class Dropbox_Settings
 *
 * @property Dropbox_App_Helpers $helpers
 * @property Dropbox_Api_Caller $api
 * @property Dropbox_Webhooks $webhooks
 *
 * @package Uncanny_Automator\Integrations\Dropbox
 */
class Dropbox_Settings extends App_Integration_Settings {

	// Alerts and the connect button come from the older OAuth trait; the
	// three methods that talk to the platform come from the v3 one.
	use OAuth_App_Integration, OAuth_App_Integration_V3 {
		OAuth_App_Integration_V3::handle_oauth_init insteadof OAuth_App_Integration;
		OAuth_App_Integration_V3::process_oauth_authentication insteadof OAuth_App_Integration;
		OAuth_App_Integration_V3::validate_vault_signature insteadof OAuth_App_Integration;
	}
	use Premium_Integration_Webhook_Settings;

	/**
	 * Register connected options
	 *
	 * @return void
	 */
	protected function register_connected_options() {
		// Register webhook-related options
		$this->register_webhook_options();
	}

	/**
	 * Display main disconnected content.
	 *
	 * @return void
	 */
	public function output_main_disconnected_content() {
		if ( $this->output_default_connection_chooser() ) {
			return;
		}

		$this->output_disconnected_header(
			esc_html_x( 'Connect your WordPress site with Dropbox to automate file management, monitoring, and sync workflows', 'Dropbox', 'uncanny-automator' )
		);

		$this->output_available_items();
	}

	/**
	 * Display bottom-left disconnected content: Connect, unless the site holds
	 * Dropbox accounts and the admin is choosing one.
	 *
	 * @return void
	 */
	public function output_bottom_left_disconnected_content() {
		if ( $this->is_default_connection_unchosen() ) {
			return;
		}

		$this->output_oauth_connect_button();
	}

	/**
	 * Get formatted account information for connected user info display.
	 *
	 * @return array Formatted account information for UI display
	 */
	protected function get_formatted_account_info() {
		// The platform flattens every vendor's account info to
		// `{name, email, avatar_url}`.
		$account = $this->helpers->get_account_info();
		if ( ! is_array( $account ) ) {
			$account = array();
		}

		return array(
			'avatar_url' => (string) ( $account['avatar_url'] ?? '' ),
			'main_info'  => (string) ( $account['email'] ?? '' ),
			'additional' => (string) ( $account['name'] ?? '' ),
		);
	}

	/**
	 * Output main connected content
	 *
	 * @return void
	 */
	public function output_main_connected_content() {
		// Output standard single account message
		$this->output_single_account_message();

		// Output webhook settings with switch and conditional content
		$this->output_webhook_settings();
	}

	/**
	 * Output webhook content
	 *
	 * @return void
	 */
	public function output_webhook_content() {
		// No setup instructions needed - webhook is automatically registered
	}

	/**
	 * Output account panel content after connection.
	 *
	 * @return void
	 */
	public function output_after_connected_panel_content() {
		// Deprecated - content moved to output_main_connected_content()
	}

	/**
	 * Before save settings
	 *
	 * @param array $response Current response
	 * @param array $data Posted data
	 *
	 * @return array
	 */
	protected function before_save_settings( $response = array(), $data = array() ) {

		// Toggle transition detection.
		$current_webhook_enabled = (bool) $this->webhooks->get_webhooks_enabled_status();
		$posted_webhook_enabled  = (bool) $this->get_data_option( $this->webhooks->get_webhooks_enabled_option_name(), $data );

		// OFF → ON: register on platform FIRST, then let the option save.
		// On register failure, throw so handle_save_settings() aborts
		// store_registered_options(), so the local toggle never says ON
		// while the platform has no registration.
		if ( ! $current_webhook_enabled && $posted_webhook_enabled ) {

			// Gate: a v3 connection must exist before triggers can be
			// enabled — register requires a connection_id.
			if ( ! $this->is_v3_connection_ready() ) {
				throw new \Exception(
					esc_html_x(
						'Connect Dropbox before enabling triggers.',
						'Dropbox',
						'uncanny-automator'
					)
				);
			}

			// Throws on non-2xx — aborts the save.
			$this->api->register_site_for_webhooks();
		}

		// ON → OFF: unregister on platform, but treat platform errors as
		// non-fatal. End-state is "user wants OFF locally" — local OFF +
		// platform-still-registered is degraded but recoverable (re-toggle
		// or wait for the platform's eventual subscription cleanup).
		if ( $current_webhook_enabled && ! $posted_webhook_enabled ) {
			try {
				$this->api->unregister_site_for_webhooks();
			} catch ( \Exception $e ) {
				// Silently fail; local disable still succeeds.
				unset( $e );
			}
		}

		// Final step — set the transition-status alert. Only reachable
		// when register either succeeded or wasn't needed.
		return $this->handle_webhook_status_before_save( $response, $data );
	}

	/**
	 * Whether a v3 Dropbox connection is currently registered locally.
	 *
	 * Gate for turning triggers on: the platform's
	 * `register-site-for-webhooks` endpoint requires `connection_id` in
	 * the request body. Without a v3 connection in the registry, the
	 * call has no connection_id and would fail at the auth middleware.
	 * Surfacing this as a user-facing exception is clearer than letting
	 * the platform reject with a generic 401.
	 *
	 * @return bool
	 */
	private function is_v3_connection_ready(): bool {

		try {
			$creds = $this->helpers->get_credentials();
		} catch ( \Exception $e ) {
			return false;
		}

		return is_array( $creds ) && ! empty( $creds['connection_id'] );
	}

	/**
	 * End every triggers registration under way before the disconnect turns
	 * the switch off, so that turning triggers on after a reconnect is a new
	 * request to the platform, not a replay of the old "on"
	 * ({@see Dropbox_Api_Caller::forget_pending_registration()}).
	 *
	 * It needs no connection to be readable (it ends Dropbox's registrations,
	 * not one connection's), so it ends them whatever state the site's
	 * connections are in. It runs again on a retry when the platform does not
	 * disconnect; ending a registration only makes the next "on" a new
	 * request, so either is safe.
	 *
	 * @param array $response The current response array
	 * @param array $data The posted data
	 *
	 * @return array Unmodified response array
	 */
	protected function before_disconnect( $response = array(), $data = array() ) {
		$this->api->forget_pending_registration();

		return $response;
	}

	/**
	 * Forget the disconnected account's cached folder and file lists, so the
	 * next account connected never sees them. Reconnecting runs this too.
	 *
	 * @param array $response The current response array
	 * @param array $data The posted data
	 *
	 * @return array Modified response array
	 */
	protected function after_disconnect( $response = array(), $data = array() ) {
		$this->api->clear_picker_cache();

		return $response;
	}
}
