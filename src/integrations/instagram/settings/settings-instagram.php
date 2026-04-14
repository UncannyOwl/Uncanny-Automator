<?php
/**
 * Creates the settings page for Instagram.
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 * @author  Joseph G.
 */

namespace Uncanny_Automator\Integrations\Instagram;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Uncanny_Automator\Settings\OAuth_App_Integration;
use Uncanny_Automator\Integrations\Facebook\Facebook_Bridge;

use WP_Error;
use Exception;

/**
 * Instagram Settings
 */
class Instagram_Settings extends App_Integration_Settings {

	use OAuth_App_Integration;

	/**
	 * The Facebook bridge instance for shared credential storage and UI helpers.
	 *
	 * @var Facebook_Bridge
	 */
	private $facebook_bridge;

	////////////////////////////////////////////////////////////
	// Required abstract method
	////////////////////////////////////////////////////////////

	/**
	 * Get formatted account information for connected user info display
	 *
	 * @return array
	 */
	protected function get_formatted_account_info() {
		return $this->facebook_bridge->get_formatted_account_info();
	}

	////////////////////////////////////////////////////////////
	// Abstract methods.
	////////////////////////////////////////////////////////////

	/**
	 * Set additional non-standard properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Set OAuth properties - Instagram purposely uses facebook pages settings.
		$this->oauth_action    = 'facebook_authorization_request';
		$this->redirect_param  = 'user_url';
		$this->facebook_bridge = Facebook_Bridge::get_instance();

		// Override default is_connected property.
		$this->is_connected = $this->facebook_bridge->user_has_connected_facebook();
	}

	/**
	 * Get connect button label
	 *
	 * @return string
	 */
	public function get_connect_button_label() {
		return $this->facebook_bridge->get_connect_button_label();
	}

	/**
	 * Validate integration credentials received from OAuth.
	 *
	 * @param array $credentials The credentials from OAuth response.
	 *
	 * @return array The validated credentials.
	 * @throws Exception If credentials are invalid.
	 */
	protected function validate_integration_credentials( $credentials ) {
		return $this->facebook_bridge->validate_oauth_credentials( $credentials );
	}

	/**
	 * Authorize account after OAuth.
	 *
	 * Called by framework after credentials are stored.
	 * Delegates to bridge which fetches user info and linked pages.
	 *
	 * @param array $response    The current response array.
	 * @param array $credentials The stored credentials.
	 *
	 * @return array The response array.
	 */
	protected function authorize_account( $response, $credentials ) {
		$this->facebook_bridge->authorize_account( $this->api );
		return $response;
	}

	/**
	 * Get connected alert - customize based on whether this is initial connection or update.
	 *
	 * @param string $message Optional custom message.
	 * @param string $heading Optional custom heading.
	 *
	 * @return array
	 */
	protected function get_connected_alert( $message = '', $heading = '' ) {
		// If already connected before this OAuth flow, it's an update.
		if ( $this->is_connected ) {
			return $this->get_success_alert(
				esc_html_x( 'Your linked Instagram accounts have been updated.', 'Instagram', 'uncanny-automator' ),
				esc_html_x( 'Accounts updated', 'Instagram', 'uncanny-automator' )
			);
		}

		// Initial connection - explain that Facebook was connected and Instagram accounts are now available.
		return $this->get_success_alert(
			esc_html_x( 'Your Facebook account has been connected. Instagram accounts linked to your Facebook pages are now available.', 'Instagram', 'uncanny-automator' ),
			esc_html_x( 'Connected', 'Instagram', 'uncanny-automator' )
		);
	}

	/**
	 * Before Authorized Account Disconnect.
	 *
	 * Instagram shares credentials with Facebook, so disconnecting
	 * from Instagram also disconnects Facebook.
	 *
	 * @param array $response The current response array.
	 * @param array $data The posted data.
	 *
	 * @return array The response array.
	 */
	protected function before_disconnect( $response = array(), $data = array() ) {
		$this->facebook_bridge->disconnect( $this->api );

		return $response;
	}

	////////////////////////////////////////////////////////////
	// Helper methods.
	////////////////////////////////////////////////////////////

	/**
	 * Get linked Instagram accounts data.
	 *
	 * @return mixed - Array of accounts or WP_Error message.
	 */
	private function get_linked_accounts_data() {
		$pages = $this->facebook_bridge->get_facebook_pages_settings();

		if ( empty( $pages ) ) {
			return new WP_Error(
				'instagram_accounts_error',
				esc_html_x( 'No Facebook pages found.', 'Instagram', 'uncanny-automator' )
			);
		}

		return $pages;
	}

	/**
	 * Refresh the linked account.
	 *
	 * @param array $response - The current response array
	 * @param array $data - The data posted to the settings page.
	 *
	 * @return void
	 */
	public function handle_refresh_account( $response = array(), $data = array() ) {

		// Get the page ID from the REST data.
		$page_id  = $this->maybe_get_posted_row_id( $data );
		$is_queue = ! empty( $data['is_queue'] );

		// Bailout if no page ID.
		if ( empty( $page_id ) ) {
			return array(
				'success' => false,
				'alert'   => $this->get_error_alert(
					esc_html_x( 'Page ID is required', 'Instagram', 'uncanny-automator' )
				),
			);
		}

		// Refresh the account.
		$pages = $this->helpers->resync_instagram_account( $page_id );
		$data  = $this->get_linked_accounts_rows( $pages );

		// Prepare the response.
		$response = array(
			'success' => true,
			'data'    => $data,
		);

		// Check if the account has been connected.
		if ( ! $is_queue ) {
			foreach ( $pages as $page ) {
				if ( (string) $page['value'] === (string) $page_id ) {
					if ( 'not_connected' === $page['ig_account']['connection_status'] ) {
						$response['alert'] = $this->get_error_alert(
							esc_html_x(
								'There was an error connecting to your Instagram account. Please ensure you check the box next to the desired Instagram account during the authentication process.',
								'Instagram',
								'uncanny-automator'
							)
						);
					}
					break;
				}
			}
		}

		return $response;
	}

	/**
	 * Get the linked accounts columns for component.
	 *
	 * @return array
	 */
	private function get_linked_accounts_columns() {
		return array(
			array(
				'key' => 'account',
			),
			array(
				'key' => 'action',
			),
		);
	}

	/**
	 * Get the linked accounts rows for component.
	 *
	 * @param array $accounts
	 *
	 * @return array
	 */
	private function get_linked_accounts_rows( $accounts ) {
		$rows = array();
		foreach ( array_values( $accounts ) as $key => $account ) {
			$rows[] = $this->get_linked_account_row( $account );
		}
		return $rows;
	}

	/**
	 * Get the linked account formatted row for component.
	 *
	 * @param array $page
	 *
	 * @return array
	 */
	private function get_linked_account_row( $page ) {

		// Instagram info
		$page         = json_decode( wp_json_encode( $page ), true );
		$account      = $page['ig_account'] ?? array();
		$username     = $account['username'] ?? null;
		$account_id   = $account['id'] ?? null;
		$connected    = ! empty( $account_id );
		$status       = $account['connection_status'] ?? 'not_attempted';
		$photo_url    = $account['profile_pic'] ?? null;
		$should_queue = ! $connected && 'not_attempted' === $status;

		$account_info = array(
			'username'    => $username ? sprintf( '[%s](https://www.instagram.com/%s)', $username, $username ) : '',
			'avatar'      => $photo_url,
			'integration' => 'INSTAGRAM',
			'variant'     => ! empty( $username ) ? 'pill' : 'badge',
			'message'     => '',
			'status'      => 'success',
		);

		if ( empty( $username ) ) {
			$account_info['message'] = '*' . esc_html_x( 'No Instagram Business or Professional account connected to this Facebook page.', 'Instagram', 'uncanny-automator' ) . '*';
			$account_info['status']  = 'error';
		}

		// Facebook page description (with link)
		$description = sprintf(
			'%s [%s](https://facebook.com/%s)',
			esc_html_x( 'Account linked to Facebook Page:', 'Instagram', 'uncanny-automator' ),
			$page['text'],
			$page['value']
		);

		return array(
			'id'          => $page['value'],
			'queue'       => $should_queue, // Maybe queue this row for processing on load if not connected.
			'columns'     => array(
				'account' => array(
					'options' => array(
						array(
							'type' => 'account',
							'data' => $account_info,
						),
					),
				),
				'action'  => array(
					'options' => array(
						array(
							'type' => 'button',
							'data' => array(
								'label'          => esc_html_x( 'Refresh', 'Instagram', 'uncanny-automator' ),
								'icon'           => array(
									'id' => 'rotate',
								),
								'color'          => 'secondary',
								'size'           => 'extra-small',
								'type'           => 'button',
								'name'           => 'automator_action',
								'value'          => 'refresh_account',
								'row-submission' => true,
							),
						),
					),
				),
			),
			'description' => $description,
		);
	}

	////////////////////////////////////////////////////////////
	// Templating methods.
	////////////////////////////////////////////////////////////

	/**
	 * Display - Main disconnected content.
	 *
	 * @return void - Outputs HTML directly
	 */
	public function output_main_disconnected_content() {

		// Default header with custom description and name.
		$this->output_disconnected_header(
			// Custom description.
			esc_html_x(
				'Automatically post photos, hashtags and text to Instagram when a new blog post is published, or when users perform any other supported actions on your site.',
				'Instagram',
				'uncanny-automator'
			),
			// Custom name.
			esc_html_x( 'Instagram Business', 'Instagram', 'uncanny-automator' )
		);

		// Automatically generated list of available triggers and actions.
		$this->output_available_items();

		// Output the separator
		$this->output_panel_separator();

		// Build the alert content with supported action button
		$this->alert_html(
			array(
				'type'    => 'notice',
				'heading' => esc_html_x( 'To connect Uncanny Automator to Instagram Business, you must first connect Facebook Pages.', 'Instagram', 'uncanny-automator' ),
				'content' => esc_html_x( "Due to Instagram limitations, to use Uncanny Automator with Instagram you'll first need to connect a Facebook Page that's associated with your Instagram Professional or Business Account.", 'Instagram', 'uncanny-automator' ),
			)
		);
	}

	/**
	 * Display - Main connected content.
	 *
	 * @return void - Outputs HTML directly
	 */
	public function output_main_connected_content() {
		// Title.
		$this->output_panel_subtitle(
			esc_html_x( 'Linked Instagram accounts', 'Instagram', 'uncanny-automator' ),
			'uap-spacing-bottom'
		);

		// List of linked accounts.
		$this->output_linked_accounts_list();

		// Update / re-authorize button via OAuth.
		$this->output_action_button(
			'oauth_init',
			esc_html_x( 'Update linked accounts', 'Instagram', 'uncanny-automator' ),
			array(
				'color' => 'secondary',
			)
		);
	}

	/**
	 * Output the linked accounts list or error message.
	 *
	 * @return void - Outputs HTML directly
	 */
	private function output_linked_accounts_list() {
		// Retrieves facebook pages from settings or Fetch from API.
		$pages = $this->get_linked_accounts_data();

		// If there's an error, output the error.
		if ( is_wp_error( $pages ) ) {
			$this->alert_html(
				array(
					'type'    => 'error',
					'heading' => esc_html_x( 'Error retrieving linked accounts', 'Instagram', 'uncanny-automator' ),
					'content' => esc_html( $pages->get_error_message() ),
					'class'   => 'uap-spacing-bottom',
				)
			);
			return;
		}

		// Get the column definitions.
		$columns = $this->get_linked_accounts_columns();
		// Transform accounts data into the format expected by the component.
		$rows = $this->get_linked_accounts_rows( $pages );

		// Queue configuration for refreshing accounts on load
		$queue_config = array(
			'postValues' => array(
				'automator_action' => 'refresh_account',
				'is_queue'         => true,
			),
			'rateLimit'  => 100, // 100ms delay between refreshes
		);

		// Output the component
		$this->output_settings_table( array_values( $columns ), $rows, 'card', false, $queue_config );
	}
}
