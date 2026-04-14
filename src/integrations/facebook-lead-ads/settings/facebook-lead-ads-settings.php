<?php
/**
 * Settings page for Facebook Lead Ads integration.
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator\Integrations\Facebook_Lead_Ads;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Uncanny_Automator\Settings\OAuth_App_Integration;
use Uncanny_Automator\Settings\Premium_Integration_Webhook_Settings;
use Exception;

/**
 * Facebook Lead Ads Settings
 *
 * Extends the App_Integration_Settings framework class to provide
 * the settings page for the Facebook Lead Ads integration.
 *
 * @package Uncanny_Automator\Integrations\Facebook_Lead_Ads
 *
 * @property Facebook_Lead_Ads_App_Helpers $helpers
 * @property Facebook_Lead_Ads_Api_Caller $api
 * @property Facebook_Lead_Ads_Webhooks $webhooks
 */
class Facebook_Lead_Ads_Settings extends App_Integration_Settings {

	// Use OAuth trait for standardized OAuth flow.
	use OAuth_App_Integration;

	// Use webhook settings trait for webhook instructions templating.
	use Premium_Integration_Webhook_Settings;

	/**
	 * Set properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		$this->oauth_action = 'authorization';
	}

	/////////////////////////////////////////////////////////////
	// Required abstract method.
	/////////////////////////////////////////////////////////////

	/**
	 * Get formatted account information for connected user info display.
	 *
	 * @return array Formatted account information for UI display.
	 */
	protected function get_formatted_account_info() {
		$user = $this->helpers->get_account_info();

		return array(
			'avatar_type'  => 'icon',
			'avatar_value' => 'FACEBOOK_LEAD_ADS',
			'main_info'    => ! empty( $user['name'] ) ? esc_html( $user['name'] ) : '',
			'additional'   => ! empty( $user['id'] ) ? esc_html( $user['id'] ) : '',
		);
	}

	/////////////////////////////////////////////////////////////
	// OAuth trait overrides.
	/////////////////////////////////////////////////////////////

	/**
	 * Filter OAuth args for Facebook-specific parameters.
	 *
	 * Facebook Lead Ads uses a different URL structure than the standard OAuth flow:
	 * - user_url: The site base URL (used to build full URLs and for vault storage)
	 * - redirect_url: Relative path to settings page callback
	 * - user_api_url: Relative path to webhook endpoint
	 *
	 * URLs are sent as relative paths (without site URL prefix) to:
	 * 1. Keep the auth URL shorter
	 * 2. Allow the API to identify framework vs legacy requests (redirect_url presence)
	 *
	 * The API combines user_url + relative paths to build full URLs.
	 *
	 * @param array $args The default OAuth args.
	 * @param array $data The data from the request.
	 *
	 * @return array Modified OAuth args.
	 */
	protected function maybe_filter_oauth_args( $args, $data ) {
		$site_url = trailingslashit( get_site_url() );

		// The redirect_url from trait is already URL encoded, so decode first.
		$callback_url = rawurldecode( $args[ $this->redirect_param ] );

		// Convert to relative path and re-encode.
		$args[ $this->redirect_param ] = rawurlencode( str_replace( $site_url, '', $callback_url ) );

		// Add Facebook-specific params with relative webhook path.
		$args['user_url']     = rtrim( $site_url, '/' );
		$args['user_api_url'] = rawurlencode( str_replace( $site_url, '', $this->webhooks->get_webhook_url() ) );

		// Add basic auth if configured.
		if ( $this->api->site_has_basic_auth() ) {
			$creds = wp_json_encode( $this->api->get_basic_auth_credentials() );

			$args['basic_auth'] = rawurlencode(
				base64_encode( $creds ) // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			);
		}

		return $args;
	}

	/**
	 * Validate integration-specific credentials.
	 * Facebook uses vault_signatures (plural) per page, not a single vault_signature.
	 *
	 * @param array $credentials The decoded credentials from OAuth.
	 *
	 * @return array The validated credentials.
	 * @throws Exception If credentials are invalid.
	 */
	protected function validate_integration_credentials( $credentials ) {
		// Build the credentials structure expected by the helpers.
		$formatted_credentials = array(
			'user_access_token' => $credentials['user_token'] ?? '',
			'vault_signatures'  => $credentials['vault_signatures'] ?? array(),
			'user'              => $credentials['user'] ?? array(),
		);

		// Validate required fields.
		if ( empty( $formatted_credentials['user_access_token'] ) ) {
			throw new Exception(
				esc_html_x( 'Missing user access token.', 'Facebook Lead Ads', 'uncanny-automator' )
			);
		}

		return $formatted_credentials;
	}

	/**
	 * Authorize account after OAuth credentials are stored.
	 * Fetches page access tokens to complete the connection.
	 *
	 * @param array $response    The current response array.
	 * @param array $credentials The stored credentials.
	 *
	 * @return array The modified response.
	 * @throws Exception If page tokens cannot be fetched.
	 */
	protected function authorize_account( $response, $credentials ) {
		// Fetch page access tokens.
		$page_tokens = $this->api->get_page_access_tokens();

		if ( is_wp_error( $page_tokens ) ) {
			throw new Exception( esc_html( $page_tokens->get_error_message() ) );
		}

		// Update credentials with page tokens.
		$credentials['pages_access_tokens'] = $page_tokens['data']['data'] ?? array();
		$this->helpers->store_credentials( $credentials );

		// Verify connection was successful.
		if ( ! $this->helpers->has_connection() ) {
			throw new Exception(
				esc_html_x( 'Connection incomplete. Please try again.', 'Facebook Lead Ads', 'uncanny-automator' )
			);
		}

		return $response;
	}

	/////////////////////////////////////////////////////////////
	// Templating methods.
	/////////////////////////////////////////////////////////////

	/**
	 * Display - Main disconnected content.
	 *
	 * @return void
	 */
	public function output_main_disconnected_content() {
		$this->output_disconnected_header(
			esc_html_x(
				'Connect Uncanny Automator with Facebook Lead Ads to automate workflows every time a new lead is created. Instantly trigger actions across your favorite apps, saving time and ensuring you never miss a chance to engage with your audience.',
				'Facebook Lead Ads',
				'uncanny-automator'
			)
		);

		$this->output_available_items();

		// Webhook test section.
		$this->output_panel_separator( 'uap-spacing-top' );
		$this->output_webhook_test_content();
	}

	/**
	 * Display - Main connected content.
	 *
	 * @return void
	 */
	public function output_main_connected_content() {
		// Pages section.
		$this->output_panel_subtitle(
			esc_html_x( 'Connected pages', 'Facebook Lead Ads', 'uncanny-automator' ),
			'uap-spacing-bottom'
		);

		$this->output_pages_info();
		$this->output_pages_table();

		// Webhook test section.
		$this->output_panel_separator( 'uap-spacing-top' );
		$this->output_webhook_test_content();
	}


	/////////////////////////////////////////////////////////////
	// Connected content sections.
	/////////////////////////////////////////////////////////////

	/**
	 * Output pages info message.
	 *
	 * @return void
	 */
	private function output_pages_info() {
		?>
		<p class="uap-spacing-bottom">
			<?php
			echo esc_html_x(
				'The following pages were selected during the OAuth process. Disconnect and reconnect if there are any pages missing and make sure those pages are selected.',
				'Facebook Lead Ads',
				'uncanny-automator'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Output the pages table.
	 *
	 * @return void
	 */
	private function output_pages_table() {
		$pages = $this->helpers->get_pages_credentials();

		if ( empty( $pages ) ) {
			$this->alert_html(
				array(
					'type'    => 'warning',
					'heading' => esc_html_x( 'No pages found', 'Facebook Lead Ads', 'uncanny-automator' ),
					'content' => esc_html_x( 'No Facebook pages are connected. Please disconnect and reconnect to select pages.', 'Facebook Lead Ads', 'uncanny-automator' ),
					'class'   => 'uap-spacing-bottom',
				)
			);
			return;
		}

		// Get table columns and rows.
		$columns = $this->get_pages_table_columns();
		$rows    = $this->get_pages_table_rows( $pages );

		// Queue config for auto-verification on page load.
		$queue_config = array(
			'postValues' => array(
				'automator_action' => 'refresh_page',
				'is_queue'         => true,
			),
			'rateLimit'  => 100, // 100ms delay between verifications.
		);

		$this->output_settings_table( $columns, $rows, 'card', false, $queue_config );
	}

	/**
	 * Get the pages table columns.
	 *
	 * @return array
	 */
	private function get_pages_table_columns() {
		return array(
			array( 'key' => 'page' ),
			array( 'key' => 'action' ),
		);
	}

	/**
	 * Get the pages table rows.
	 *
	 * Respects cached statuses - only queues pages without a cached status.
	 * This avoids unnecessary API calls on every settings page load.
	 *
	 * @param array $pages The pages credentials.
	 *
	 * @return array
	 */
	private function get_pages_table_rows( $pages ) {
		$rows = array();
		foreach ( $pages as $page ) {
			$page_id = $page['id'] ?? '';

			// Check for cached status WITHOUT triggering a fetch.
			$cached_status = $this->helpers->get_cached_page_status( $page_id );

			// If we have a cached status, use it and don't queue.
			// If no cache (null), queue for verification.
			$has_cache    = null !== $cached_status;
			$should_queue = ! $has_cache;

			$rows[] = $this->get_page_table_row( $page, $cached_status, $should_queue );
		}
		return $rows;
	}

	/**
	 * Get a single page table row.
	 *
	 * @param array       $page      The page data.
	 * @param string|null $status    Optional status string (null = pending verification).
	 * @param bool        $for_queue Whether this row should be queued for verification.
	 *
	 * @return array
	 */
	private function get_page_table_row( $page, $status = null, $for_queue = true ) {
		$page_id   = $page['id'] ?? '';
		$page_name = $page['name'] ?? '';

		// Determine status display.
		$status_info = $this->get_page_status_display( $status );

		// Build page column components.
		$page_components = array(
			array(
				'type' => 'icon',
				'data' => array(
					'integration' => 'FACEBOOK_LEAD_ADS',
				),
			),
			array(
				'type' => 'text',
				'data' => sprintf(
					'**%s** %s',
					esc_html( $page_name ),
					$status_info['message']
				),
			),
		);

		// Add status icon if available.
		if ( ! empty( $status_info['icon'] ) ) {
			$page_components[] = array(
				'type'  => 'icon',
				'data'  => array( 'id' => $status_info['icon']['id'] ),
				'style' => $status_info['icon']['style'] ?? '',
			);
		}

		return array(
			'id'          => $page_id,
			'queue'       => $for_queue, // Only queue if not yet verified.
			'columns'     => array(
				'page'   => array(
					'layout'  => 'horizontal',
					'options' => $page_components,
				),
				'action' => array(
					'options' => array(
						array(
							'type' => 'button',
							'data' => array(
								'label'          => esc_html_x( 'Reverify', 'Facebook Lead Ads', 'uncanny-automator' ),
								'icon'           => array( 'id' => 'rotate' ),
								'color'          => 'secondary',
								'size'           => 'extra-small',
								'type'           => 'button',
								'name'           => 'automator_action',
								'value'          => 'refresh_page',
								'row-submission' => true,
							),
						),
					),
				),
			),
			'description' => sprintf(
				'%s: %s',
				esc_html_x( 'Page ID', 'Facebook Lead Ads', 'uncanny-automator' ),
				esc_html( $page_id )
			),
		);
	}

	/**
	 * Get page status display info.
	 *
	 * @param string|null $status The status string or null for pending.
	 *
	 * @return array
	 */
	private function get_page_status_display( $status = null ) {
		// Default: verifying state (shown before queue processes).
		// Note: The list component handles loading state, so no icon needed here.
		if ( null === $status ) {
			return array(
				'message' => '',
				'icon'    => null,
			);
		}

		// Success state.
		if ( false !== strpos( $status, 'Ready' ) ) {
			return array(
				'message' => '*' . esc_html( $status ) . '*',
				'icon'    => array(
					'id'    => 'check',
					'style' => 'color: var(--uap-success-color, #2ecc40);',
				),
			);
		}

		// Error state.
		return array(
			'message' => '*' . esc_html( $status ) . '*',
			'icon'    => array(
				'id'    => 'xmark',
				'style' => 'color: var(--uap-error-color, #ff4136);',
			),
		);
	}

	/**
	 * Output the webhook test content.
	 * Displayed in both connected and disconnected states.
	 *
	 * @return void
	 */
	private function output_webhook_test_content() {
		$this->output_webhook_instructions(
			array(
				'heading'  => esc_html_x( 'Test your webhook connection', 'Facebook Lead Ads', 'uncanny-automator' ),
				'class'    => 'uap-spacing-top',
				'sections' => array(
					array(
						'type'    => 'text',
						'content' => esc_html_x(
							"Click the button below to test your website's accessibility and confirm API support for your WordPress site.",
							'Facebook Lead Ads',
							'uncanny-automator'
						),
					),
					array(
						'type'   => 'button',
						'action' => 'test_webhook',
						'label'  => esc_html_x( 'Check webhook delivery', 'Facebook Lead Ads', 'uncanny-automator' ),
						'args'   => array(
							'color'   => 'secondary',
							'icon'    => 'rotate',
							'class'   => 'uap-spacing-top',
							'tooltip' => esc_html_x(
								'Sends a test payload from our server to your site to confirm it can receive webhooks for Facebook Lead Ads.',
								'Facebook Lead Ads',
								'uncanny-automator'
							),
						),
					),
				),
			)
		);
	}

	/////////////////////////////////////////////////////////////
	// Form handlers.
	/////////////////////////////////////////////////////////////

	/**
	 * Handle webhook test action.
	 *
	 * @param array $response The current response array.
	 * @param array $data     The data posted to the settings page.
	 *
	 * @return array
	 */
	protected function handle_test_webhook( $response = array(), $data = array() ) {
		try {
			$result = $this->api->verify_connection( $this->webhooks->get_webhook_url() );

			if ( is_wp_error( $result ) ) {
				throw new Exception( $result->get_error_message() );
			}

			$response['alert'] = $this->get_success_alert(
				esc_html_x(
					'Your website has received a webhook, confirming it supports external requests needed for Facebook Lead Ads.',
					'Facebook Lead Ads',
					'uncanny-automator'
				),
				esc_html_x( 'Webhook test successful', 'Facebook Lead Ads', 'uncanny-automator' )
			);

		} catch ( Exception $e ) {
			$response['success'] = false;
			$response['alert']   = $this->get_error_alert(
				$e->getMessage(),
				esc_html_x( 'Webhook test failed', 'Facebook Lead Ads', 'uncanny-automator' )
			);
		}

		return $response;
	}

	/**
	 * Handle page refresh/verification action.
	 *
	 * Verifies the page connection status and returns updated row data.
	 * Used both by the queue (on page load) and manual refresh button.
	 *
	 * @param array $response The current response array.
	 * @param array $data     The data posted to the settings page.
	 *
	 * @return array
	 */
	protected function handle_refresh_page( $response = array(), $data = array() ) {
		$page_id  = $this->maybe_get_posted_row_id( $data );
		$is_queue = ! empty( $data['is_queue'] );

		// Validate page ID.
		if ( empty( $page_id ) ) {
			return array(
				'success' => false,
				'alert'   => $this->get_error_alert(
					esc_html_x( 'Page ID is required.', 'Facebook Lead Ads', 'uncanny-automator' )
				),
			);
		}

		// Verify the page connection (force fresh check) - this also caches the status.
		$status = $this->helpers->verify_page_connection( $page_id, true );

		// Get status string for the verified page.
		$verified_status = is_wp_error( $status )
			? $status->get_error_message()
			: $status;

		// Build ALL rows with their current statuses.
		$pages = $this->helpers->get_pages_credentials();
		$rows  = array();

		foreach ( $pages as $page ) {
			$current_page_id = (string) $page['id'];

			if ( $current_page_id === (string) $page_id ) {
				// This is the page we just verified - use fresh status.
				$rows[] = $this->get_page_table_row( $page, $verified_status, false );

				continue;
			}

			// Other pages - get cached status only (don't trigger fetch).
			$cached_status = $this->helpers->get_cached_page_status( $current_page_id );
			$rows[]        = $this->get_page_table_row( $page, $cached_status, false );
		}

		// Return ALL rows with their current statuses.
		$response = array(
			'success' => true,
			'data'    => $rows,
		);

		// Show alert only for manual refresh (not queue).
		if ( ! $is_queue ) {
			if ( is_wp_error( $status ) ) {
				$response['alert'] = $this->get_error_alert(
					$status->get_error_message(),
					esc_html_x( 'Page verification failed', 'Facebook Lead Ads', 'uncanny-automator' )
				);
			} else {
				$response['alert'] = $this->get_success_alert(
					sprintf(
						// translators: %s: Status message.
						esc_html_x( 'Page status: %s', 'Facebook Lead Ads', 'uncanny-automator' ),
						esc_html( $status )
					),
					esc_html_x( 'Page verified', 'Facebook Lead Ads', 'uncanny-automator' )
				);
			}
		}

		return $response;
	}

	/**
	 * Cleanup after disconnect.
	 *
	 * @param array $response The current response array.
	 * @param array $data     The posted data.
	 *
	 * @return array
	 */
	protected function after_disconnect( $response = array(), $data = array() ) {
		// Clear page status cache.
		delete_transient( $this->helpers->get_const( 'PAGE_STATUS_TRANSIENT_KEY' ) );

		return $response;
	}
}
