<?php
/**
 * Settings page for Facebook Lead Ads integration.
 *
 * Provides functionalities for managing the settings, connections, and credentials of the
 * Facebook Lead Ads integration within Uncanny Automator.
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator\Integrations\Facebook_Lead_Ads;

use Uncanny_Automator\Integrations\Facebook_Lead_Ads\Helpers\Facebook_Lead_Ads_Helpers;
use Uncanny_Automator\Integrations\Facebook_Lead_Ads\Utilities\Connections_Manager;
use Uncanny_Automator\Integrations\Facebook_Lead_Ads\Utilities\Credentials_Manager;

/**
 * Manages the settings page for Facebook Lead Ads integration.
 *
 * @package Uncanny_Automator\Integrations\Facebook_Lead_Ads
 */
class Facebook_Lead_Ads_Settings extends \Uncanny_Automator\Settings\Premium_Integration_Settings {

	/**
	 * Indicates if the account is connected.
	 *
	 * @var bool
	 */
	protected $is_account_connected = false;

	/**
	 * URL for disconnecting the account.
	 *
	 * @var string
	 */
	protected $disconnect_url = '';

	/**
	 * URL for connecting the account.
	 *
	 * @var string
	 */
	protected $connect_url = '';

	/**
	 * Handles connection management.
	 *
	 * @var Connections_Manager
	 */
	protected $connections = null;

	/**
	 * Renders HTML partials.
	 *
	 * @var Html_Partial_Renderer
	 */
	protected $html_renderer = null;

	/**
	 * Manages API credentials.
	 *
	 * @var Credentials_Manager
	 */
	protected $credentials = null;

	/**
	 * Sets up the properties of the settings page.
	 *
	 * Initializes integration settings, assigns dependencies, and sets the initial status.
	 *
	 * @return void
	 */
	public function set_properties() {

		$this->set_id( 'facebook_lead_ads' );
		$this->set_icon( 'FACEBOOK_LEAD_ADS' );
		$this->set_name( 'Facebook Lead Ads' );

		$this->connections   = $this->dependencies[0];
		$this->html_renderer = $this->dependencies[1];
		$this->credentials   = $this->dependencies[2];

		$this->set_status( $this->connections->has_connection() ? 'success' : '' );

		$this->html_renderer->set_html_partial_root_path( trailingslashit( __DIR__ ) );

		if ( automator_filter_has_var( 'error_message' ) ) {
			$this->display_errors( automator_filter_input( 'error_message' ) );
		}
	}

	/**
	 * Updates the settings and verifies the API credentials.
	 *
	 * Displays a success alert upon successful connection.
	 *
	 * @return void
	 */
	public function settings_updated() {

		$this->add_alert(
			array(
				'type'    => 'success',
				'heading' => esc_html_x( 'Connection established', 'Facebook_Lead_Ads', 'uncanny-automator' ),
				'content' => esc_html_x( 'You are successfully connected.', 'Facebook_Lead_Ads', 'uncanny-automator' ),
			)
		);
	}

	/**
	 * Displays error messages as alerts.
	 *
	 * @param mixed $error_message The error message to display.
	 * @return void
	 */
	public function display_errors( $error_message ) {

		$this->add_alert(
			array(
				'type'    => 'error',
				'heading' => esc_html_x( 'An error exception has occurred', 'Facebook_Lead_Ads', 'uncanny-automator' ),
				'content' => $error_message,
			)
		);
	}

	/**
	 * Outputs the settings panel.
	 *
	 * Displays the main settings panel with top, content, and bottom sections.
	 *
	 * @return void
	 */
	public function output_panel() {

		if ( 'mock' === automator_filter_input( 'mode' ) ) {
			include trailingslashit( __DIR__ ) . 'views/mock.html';
			return;
		}

		$has_connection = Facebook_Lead_Ads_Helpers::create_connection_manager()->has_connection();
		?>
		<div class="uap-settings-panel">
			<div class="uap-settings-panel-top">
				<?php $this->output_panel_top(); ?>
				<?php $this->display_alerts(); ?>
				<div class="uap-settings-panel-content">
					<?php $this->output_panel_content(); ?>
				</div>
			</div>
			<div class="uap-settings-panel-bottom" <?php echo esc_attr( ! $has_connection ? 'has-arrow' : '' ); ?>>
				<?php $this->output_panel_bottom(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Outputs the main content of the panel.
	 *
	 * Loads required assets and renders the HTML partial for the panel content.
	 *
	 * @return string HTML content.
	 */
	public function output_panel_content() {

		// Loads CSS files.
		$this->load_css( '/facebook-lead-ads/settings/assets/style.css' );

		// Loads JS files.
		$this->load_js( '/facebook-lead-ads/settings/assets/test-connection.js' );
		$this->load_js( '/facebook-lead-ads/settings/assets/pages.js', '_fbla-pages' );

		$args = array(
			'has_connection' => $this->connections->has_connection(),
			'credentials'    => $this->credentials->get_credentials(),
		);

		$this->html_renderer->render_html_partial( 'views/output-content-partial.php', $args );
	}

	/**
	 * Outputs the bottom-left content of the panel.
	 *
	 * Renders the partial for the bottom-left content using connection and user data.
	 *
	 * @return string HTML content.
	 */
	public function output_panel_bottom_left() {

		$credentials = ( new Credentials_Manager() )->get_credentials();
		$fb_user     = $credentials['user'] ?? null;

		$args = array(
			'connection_url' => Facebook_Lead_Ads_Helpers::get_connect_url(),
			'has_connection' => $this->connections->has_connection(),
			'user'           => $fb_user,
		);

		$this->html_renderer->render_html_partial( 'views/output-content-bottom-left.php', $args );
	}

	/**
	 * Outputs the bottom-right content of the panel.
	 *
	 * Renders the partial for the bottom-right content using connection and disconnect URL data.
	 *
	 * @return string HTML content.
	 */
	public function output_panel_bottom_right() {

		$args = array(
			'disconnect_url' => Facebook_Lead_Ads_Helpers::get_disconnect_url(),
			'has_connection' => $this->connections->has_connection(),
		);

		$this->html_renderer->render_html_partial( 'views/output-content-bottom-right.php', $args );
	}
}
