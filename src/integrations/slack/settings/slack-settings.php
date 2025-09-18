<?php

namespace Uncanny_Automator\Integrations\Slack;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Uncanny_Automator\Settings\OAuth_App_Integration;

/**
 * Class Slack_Settings
 *
 * @package Uncanny_Automator
 *
 * @property Slack_App_Helpers $helpers
 */
class Slack_Settings extends App_Integration_Settings {

	use OAuth_App_Integration;

	/**
	 * The bot name
	 *
	 * @var string
	 */
	private $bot_name;

	/**
	 * The bot icon url
	 *
	 * @var string
	 */
	private $bot_icon;

	////////////////////////////////////////////////////////////
	// Required abstract method
	////////////////////////////////////////////////////////////

	/**
	 * Get formatted account information for connected user info display
	 *
	 * @return array
	 */
	protected function get_formatted_account_info() {
		$account = $this->helpers->get_account_info();
		return array(
			'avatar_type'    => 'text',
			'avatar_value'   => strtoupper( $account['name'][0] ),
			'main_info'      => sprintf(
				// translators: %1$s The name of the Slack channel
				esc_html_x( '%1$s (workspace)', 'Slack', 'uncanny-automator' ),
				esc_html( $account['name'] )
			),
			'main_info_icon' => true,
			'additional'     => sprintf(
				// translators: 1. ID
				esc_html_x( 'ID: %1$s', 'Slack', 'uncanny-automator' ),
				$account['id']
			),
		);
	}

	////////////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////////////

	/**
	 * Set additonal non-standard properties.
	 *
	 * @return void
	 */
	public function set_disconnected_properties() {
		$this->oauth_action       = 'slack_authorization_request';
		$this->show_connect_arrow = true;
	}

	/**
	 * Set connected properties.
	 *
	 * @return void
	 */
	public function set_connected_properties() {
		// Get bot name and icon from uap_options if set.
		$this->bot_name = $this->helpers->get_bot_name();
		$this->bot_icon = $this->helpers->get_bot_icon();

		// Set custom assets for our Bot Preview.
		$this->set_css( '/slack/settings/assets/style.css' );
		$this->set_js( '/slack/settings/assets/script.js' );
	}

	/**
	 * Register connected options.
	 *
	 * @return void
	 */
	public function register_connected_options() {
		$this->register_option( $this->helpers->get_const( 'BOT_NAME' ) );
		$this->register_option( $this->helpers->get_const( 'BOT_ICON' ) );
	}

	/**
	 * Maybe filter OAuth args.
	 *
	 * @param array $args - The args to filter.
	 * @param array $data - The data from rest request.
	 *
	 * @return array $args
	 */
	protected function maybe_filter_oauth_args( $args, $data = array() ) {
		$args['api_ver'] = '2.0';
		// REVIEW : Why not set on server?
		$args['scope'] = implode(
			',',
			array(
				'channels:read',
				'groups:read',
				'channels:manage',
				'groups:write',
				'chat:write',
				'users:read',
				'chat:write.customize',
			)
		);

		return $args;
	}

	/**
	 * Validate integration credentials.
	 *
	 * @param array $credentials - payload from the OAuth response.
	 *
	 * @return array
	 */
	public function validate_integration_credentials( $credentials ) {
		// Empty checks already happen in abstract, overriding here to omit standard vault signature check.

		// Recursively convert the array into an object.
		$credentials = json_decode( wp_json_encode( $credentials ) );
		return $credentials;
	}

	////////////////////////////////////////////////////////////
	// Abstract Templating methods
	////////////////////////////////////////////////////////////

	/**
	 * Output main disconnected content.
	 *
	 * @return void - Outputs the generated HTML.
	 */
	public function output_main_disconnected_content() {
		// Output the standard disconnected header with description
		$this->output_disconnected_header(
			esc_html_x( 'Integrate your WordPress site directly with Slack. Send messages to Slack channels or users when users make a purchase, fill out a form, complete a course, or complete any combination of supported triggers.', 'Slack', 'uncanny-automator' )
		);

		// Output available recipe items.
		$this->output_available_items();
	}

	/**
	 * Output main connected content.
	 *
	 * @return void
	 */
	public function output_main_connected_content() {
		$this->output_bot_setup();
		$this->output_panel_separator();
		// Output the single account message
		$this->alert_html(
			array(
				'type'    => 'info',
				'heading' => esc_html_x( 'Uncanny Automator only supports connecting to one Slack workspace.', 'Slack', 'uncanny-automator' ),
				'content' => esc_html_x( 'If you create recipes and then change the connected Slack workspace, your previous recipes may no longer work.', 'Slack', 'uncanny-automator' ),
			)
		);
	}

	////////////////////////////////////////////////////////////
	// Integration Templating methods
	////////////////////////////////////////////////////////////

	/**
	 * Output the bot setup.
	 *
	 * @return void - Outputs the generated HTML.
	 */
	public function output_bot_setup() {
		?>
		<div class="uap-slack-fields">
			<div class="uap-slack-fields-left">
				<?php
				// Output Bot fields.
				$this->output_bot_fields();
				?>
			</div><!-- .uap-slack-fields-left -->
			<div class="uap-slack-fields-right">
				<?php
				// Output Bot preview.
				$this->output_bot_preview();
				?>
			</div><!-- .uap-slack-fields-right -->
		</div>
		<?php
	}

	/**
	 * Output the bot fields.
	 *
	 * @return void
	 */
	public function output_bot_fields() {
		$this->output_panel_subtitle(
			esc_html_x( 'Bot setup', 'Slack', 'uncanny-automator' )
		);

		$this->text_input_html(
			array(
				'id'    => 'uap_automator_slack_api_bot_name',
				'value' => $this->bot_name,
				'label' => esc_html_x( 'Bot name', 'Slack', 'uncanny-automator' ),
				'class' => 'uap-spacing-top',
			)
		);
		$this->text_input_html(
			array(
				'id'          => 'uap_automator_alck_api_bot_icon',
				'value'       => $this->bot_icon,
				'label'       => esc_html_x( 'Bot icon', 'Slack', 'uncanny-automator' ),
				'helper'      => esc_html_x( 'The bot icon should be a minimum of 512x512 pixels, but no larger than 1024x1024 pixels.', 'Slack', 'uncanny-automator' ),
				'placeholder' => esc_html_x( 'https://...', 'Slack', 'uncanny-automator' ),
				'class'       => 'uap-spacing-top',
			)
		);
	}

	/**
	 * Output the bot preview.
	 *
	 * @return void - Outputs the generated HTML.
	*/
	public function output_bot_preview() {

		// Get name or use default.
		$bot_name = ! empty( $this->bot_name )
			? $this->bot_name
			: 'Uncanny Automator';

		// The Slack icon. This is a preview, and it's not really the icon Automator sends to Slack
		// The one sent is bigger (this is 80x80, the one sent is 1024x1024)
		$default_icon = plugins_url( 'assets/slack-avatar-2x.png', __FILE__ );

		// Get icon or use default.
		$bot_icon = ! empty( $this->bot_icon )
			? $this->bot_icon
			: $default_icon;

		// Output subtitle.
		$this->output_panel_subtitle(
			esc_html_x( 'Preview', 'Slack', 'uncanny-automator' )
		);

		// Add wrapper for the preview generator.
		echo '<div id="uap-slack-preview-generator" data-icon="' . esc_url( $default_icon ) . '">';

		// Output the light and dark mode previews.
		$this->output_preview_html( 'light', $bot_name, $bot_icon );
		$this->output_preview_html( 'dark', $bot_name, $bot_icon );

		// Close the preview generator wrapper.
		echo '</div>';
	}

	/**
	 * Generate the preview HTML for a specific mode.
	 *
	 * @param string $mode The preview mode ('light' or 'dark').
	 * @param string $bot_name The bot name.
	 * @param string $bot_icon The bot icon.
	 *
	 * @return void - Outputs the generated HTML.
	 */
	private function output_preview_html( $mode, $bot_name, $bot_icon ) {
		$is_dark    = 'dark' === $mode;
		$mode_class = $is_dark ? ' uap-slack-preview--dark' : '';
		$icon_id    = sprintf( 'uap-slack-preview-%s-icon', $mode );
		$name_id    = sprintf( 'uap-slack-preview-%s-name', $mode );

		?>
		<div class="uap-slack-preview<?php echo esc_attr( $mode_class ); ?> uap-spacing-top">
			<div class="uap-slack-preview-avatar">
				<img src="<?php echo esc_url( $bot_icon ); ?>" id="<?php echo esc_attr( $icon_id ); ?>">
			</div>
			<div class="uap-slack-preview-details">
				<span class="uap-slack-preview-details__name" id="<?php echo esc_attr( $name_id ); ?>">
					<?php echo esc_html( $bot_name ); ?>
				</span>
				<span class="uap-slack-preview-details__tag">
					<?php echo esc_html_x( 'APP', 'Slack', 'uncanny-automator' ); ?>
				</span>
				<span class="uap-slack-preview-details__date">
					<?php echo esc_attr( date_i18n( 'g:i A' ) ); ?>
				</span>
			</div>
			<div class="uap-slack-preview-body">
				<?php echo esc_html_x( 'Hello, world!', 'Slack', 'uncanny-automator' ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * After save settings.
	 *
	 * @param array $response The response array.
	 * @param array $options The options array.
	 *
	 * @return array
	 */
	protected function after_save_settings( $response = array(), $options = array() ) {
		// Add success message to response.
		$response['alert'] = $this->get_success_alert(
			esc_html_x( 'Slack settings updated', 'Slack', 'uncanny-automator' ),
		);
		return $response;
	}
}