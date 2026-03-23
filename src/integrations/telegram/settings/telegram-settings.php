<?php

namespace Uncanny_Automator\Integrations\Telegram;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Uncanny_Automator\Settings\Premium_Integration_Webhook_Settings;
use Exception;

/**
 * Class Telegram_Settings
 *
 * @package Uncanny_Automator
 *
 * @property Telegram_App_Helpers $helpers
 * @property Telegram_Api_Caller $api
 * @property Telegram_Webhooks $webhooks
 */
class Telegram_Settings extends App_Integration_Settings {

	use Premium_Integration_Webhook_Settings;

	/**
	 * Register disconnected options
	 *
	 * @return void
	 */
	public function register_disconnected_options() {
		// Register the bot secret option for manual auth
		$this->register_option( $this->helpers->get_const( 'BOT_SECRET_OPTION' ) );
	}

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
	 * Handle authorization (Manual Auth)
	 *
	 * @param array $response
	 * @param array $options
	 *
	 * @return array
	 */
	public function authorize_account( $response, $options ) {
		try {
			// Get bot secret from form data.
			$bot_secret = $options[ $this->helpers->get_const( 'BOT_SECRET_OPTION' ) ] ?? '';
			if ( empty( $bot_secret ) ) {
				throw new Exception( esc_html_x( 'Bot secret is required', 'Telegram', 'uncanny-automator' ) );
			}

			// Verify the token and get bot info.
			$this->api->verify_bot_token();

			// Success
			$this->register_success_alert(
				esc_html_x( 'You have successfully connected your Telegram bot', 'Telegram', 'uncanny-automator' )
			);

		} catch ( Exception $e ) {
			// Clear stored account info on failure
			$this->helpers->delete_account_info();

			$this->register_error_alert(
				sprintf(
					// translators: %s: Error message
					esc_html_x( 'Failed to connect to your Telegram bot: %s', 'Telegram', 'uncanny-automator' ),
					esc_html( $e->getMessage() )
				)
			);
		}

		return $response;
	}

	/**
	 * Get formatted account info for connected state
	 *
	 * @return array
	 */
	protected function get_formatted_account_info() {
		$bot_info = $this->helpers->get_account_info();

		if ( empty( $bot_info ) ) {
			return array();
		}

		$bot_name     = $bot_info['first_name'] ?? '';
		$bot_username = $bot_info['username'] ?? '';

		// Get first letter for avatar
		$avatar_text = '';
		if ( ! empty( $bot_name ) ) {
			$avatar_text = strtoupper( substr( $bot_name, 0, 1 ) );
		}

		return array(
			'avatar_type'  => 'text',
			'avatar_value' => $avatar_text,
			'main_info'    => $bot_name,
			'additional'   => ! empty( $bot_username )
				? sprintf(
					// translators: %s: Bot username
					esc_html_x( 'Bot username: %s', 'Telegram', 'uncanny-automator' ),
					esc_html( $bot_username )
				)
				: '',
		);
	}

	/**
	 * Determine webhook action to be taken before save.
	 *
	 * @param array $response Current response
	 * @param array $data Posted data
	 *
	 * @return array
	 */
	protected function before_save_settings( $response = array(), $data = array() ) {
		// Get current and posted values.
		$current_webhook_enabled = (bool) $this->webhooks->get_webhooks_enabled_status();
		$posted_webhook_enabled  = (bool) $this->get_data_option( $this->webhooks->get_webhooks_enabled_option_name(), $data );

		// Determine action to be taken after save.
		if ( empty( $current_webhook_enabled ) && ! empty( $posted_webhook_enabled ) ) {
			// Case 1: Enabling webhooks.
			$response['telegram_webhook_action'] = 'enable';
		} elseif ( ! empty( $current_webhook_enabled ) && ! $posted_webhook_enabled ) {
			// Case 2: Disabling webhooks.
			$response['telegram_webhook_action'] = 'disable';
		} else {
			// Case 3: No change.
			$response['telegram_webhook_action'] = 'none';
		}

		return $response;
	}

	/**
	 * Execute webhook action after save.
	 *
	 * @param array $response Current response
	 * @param array $options The saved options
	 *
	 * @return array
	 */
	protected function after_save_settings( $response = array(), $options = array() ) {
		// Get the action to be taken after save.
		$action = $response['telegram_webhook_action'] ?? 'none';
		unset( $response['telegram_webhook_action'] );

		switch ( $action ) {
			case 'enable':
				$response = $this->handle_webhook_enable( $response );
				break;
			case 'disable':
				$response = $this->handle_webhook_disable( $response );
				break;
			case 'none':
			default:
				$response['alert'] = $this->get_info_alert(
					sprintf(
						// translators: %s is the integration name
						esc_html_x( 'No changes were made to the %s triggers settings.', 'Telegram', 'uncanny-automator' ),
						esc_html( $this->get_name() )
					)
				);
				break;
		}

		return $response;
	}

	/**
	 * Handle webhook enable.
	 *
	 * @param array $response Current response
	 *
	 * @return array
	 */
	private function handle_webhook_enable( $response ) {
		try {
			$this->webhooks->register_telegram_webhook();

			$response['alert'] = $this->get_success_alert(
				sprintf(
					// translators: %s is the integration name
					esc_html_x( '%s triggers are now enabled.', 'Telegram', 'uncanny-automator' ),
					esc_html( $this->get_name() )
				)
			);
		} catch ( Exception $e ) {
			// Disable webhooks since registration failed.
			$this->webhooks->store_webhooks_enabled_status( false );
			$this->register_error_alert(
				sprintf(
					// translators: %1$s is the integration name, %2$s is the error message
					esc_html_x( 'Failed to enable %1$s triggers: %2$s', 'Telegram', 'uncanny-automator' ),
					esc_html( $this->get_name() ),
					esc_html( $e->getMessage() )
				)
			);
			$response['reload'] = true;
		}
		return $response;
	}

	/**
	 * Handle webhook disable.
	 *
	 * @param array $response Current response
	 *
	 * @return array
	 */
	private function handle_webhook_disable( $response ) {
		try {
			$this->webhooks->delete_telegram_webhook();
			$response['alert'] = $this->get_warning_alert(
				sprintf(
					// translators: %s is the integration name
					esc_html_x( '%s triggers are now disabled.', 'Telegram', 'uncanny-automator' ),
					esc_html( $this->get_name() )
				)
			);
		} catch ( Exception $e ) {
			// Re-enable webhooks since deletion failed.
			$this->webhooks->store_webhooks_enabled_status( true );
			$this->register_error_alert(
				sprintf(
					// translators: %1$s is the integration name, %2$s is the error message
					esc_html_x( 'Failed to disable %1$s triggers: %2$s', 'Telegram', 'uncanny-automator' ),
					esc_html( $this->get_name() ),
					esc_html( $e->getMessage() )
				)
			);
			$response['reload'] = true;
		}

		return $response;
	}

	/**
	 * Called before disconnect to clean up webhooks
	 *
	 * @param array $response The current response array
	 * @param array $data The posted data
	 *
	 * @return array Modified response array
	 */
	protected function before_disconnect( $response = array(), $data = array() ) {
		// Delete webhook when disconnecting
		$this->webhooks->delete_telegram_webhook();
		// Clear out registered channels
		$this->helpers->delete_channel_data();

		return $response;
	}

	///////////////////////////////////////////////////////////
	// Templating methods
	///////////////////////////////////////////////////////////

	/**
	 * Output main disconnected content (Manual Auth)
	 *
	 * @return void
	 */
	public function output_main_disconnected_content() {
		// Disconnected header.
		$this->output_disconnected_header(
			esc_html_x(
				'Connect your WordPress site to Telegram to run automations when messages are received and send Telegram messages in your recipes.',
				'Telegram',
				'uncanny-automator'
			)
		);

		// Available triggers/actions
		$this->output_available_items();

		// Setup instructions
		$this->alert_html(
			array(
				'heading' => esc_html_x( 'Setup instructions', 'Telegram', 'uncanny-automator' ),
				'content' => sprintf(
					// translators: %s: Knowledge Base article link
					esc_html_x( 'Connecting to Telegram requires creating a Telegram bot and retrieving an HTTP access token value (a.k.a. "Bot secret"). Visit our %s for instructions.', 'Telegram', 'uncanny-automator' ),
					$this->get_escaped_link(
						automator_utm_parameters( 'https://automatorplugin.com/knowledge-base/telegram/', 'settings', 'telegram-kb_article' ),
						esc_html_x( 'Knowledge Base article', 'Telegram', 'uncanny-automator' )
					)
				),
			)
		);

		// Bot secret input field
		$this->text_input_html(
			array(
				'id'       => $this->helpers->get_const( 'BOT_SECRET_OPTION' ),
				'value'    => automator_get_option( $this->helpers->get_const( 'BOT_SECRET_OPTION' ), '' ),
				'label'    => esc_html_x( 'Bot secret', 'Telegram', 'uncanny-automator' ),
				'required' => true,
				'class'    => 'uap-spacing-top',
			)
		);
	}

	/**
	 * Output main connected content
	 *
	 * @return void
	 */
	public function output_main_connected_content() {
		// Output standard single account message.
		$this->output_single_account_message(
			esc_html_x( 'You can only connect to a Telegram bot for which you have read and write access.', 'Telegram', 'uncanny-automator' )
		);

		// Output webhook settings with switch and conditional content.
		$this->output_webhook_settings( esc_html_x( 'Enable webhooks', 'Telegram', 'uncanny-automator' ) );

		// Output chat channels manager.
		$this->output_registered_channels();

		// Output registration instructions.
		$this->output_registration_instructions();
	}

	/**
	 * Output webhook content.
	 *
	 * @return void
	 */
	public function output_webhook_content() {
		$this->output_webhook_instructions(
			array(
				'heading'  => esc_attr_x( 'With enabled webhooks :', 'Telegram', 'uncanny-automator' ),
				'sections' => array(
					array(
						'type'  => 'steps',
						'items' => array(
							esc_html_x(
								'Telegram triggers will automatically receive messages and trigger your recipes.',
								'Telegram',
								'uncanny-automator'
							),
							esc_html_x(
								'You may register your channels and chats for easy use in your recipes.',
								'Telegram',
								'uncanny-automator'
							),
						),
					),
					$this->get_webhook_regeneration_button(
						array(
							'label'           => esc_attr_x( 'Refresh webhook connection', 'Telegram', 'uncanny-automator' ),
							'confirm_heading' => esc_attr_x( 'Refresh webhook connection', 'Telegram', 'uncanny-automator' ),
							'confirm_content' => esc_attr_x( 'This will re-register your webhook with Telegram. This may help fix issues with registration buttons or triggers not working.', 'Telegram', 'uncanny-automator' ),
							'confirm_button'  => esc_attr_x( 'Refresh webhook', 'Telegram', 'uncanny-automator' ),
						)
					),
				),
			)
		);
	}

	/**
	 * Output registration instructions.
	 *
	 * @return void
	 */
	private function output_registration_instructions() {

		$registration_token = $this->webhooks->generate_registration_token();

		// If no token, bot is not connected.
		if ( false === $registration_token ) {
			return;
		}

		// Get bot username.
		$bot_info     = $this->helpers->get_account_info();
		$bot_username = $bot_info['username'];

		// Output registration instructions.
		$this->output_webhook_instructions(
			array(
				'heading'  => esc_attr_x( 'How to Register Your Channel/Chat', 'Telegram', 'uncanny-automator' ),
				'class'    => 'uap-spacing-top',
				'sections' => array(
					array(
						'type'  => 'steps',
						'items' => array(
							esc_html_x( 'Ensure you have webhooks enabled above', 'Telegram', 'uncanny-automator' ),
							sprintf(
								// translators: %1$s: bot username, %2$s: strong tag, %3$s: /strong tag
								esc_html_x( 'Add the bot %1$s to your channel/chat as an %2$sadmin%3$s', 'Telegram', 'uncanny-automator' ),
								esc_html( $bot_username ),
								'<strong>',
								'</strong>'
							),
							esc_html_x( 'Copy the registration command below and paste it in your channel', 'Telegram', 'uncanny-automator' ),
							esc_html_x( 'Click the "Register This Channel" button that appears in your channel', 'Telegram', 'uncanny-automator' ),
						),
					),
					array(
						'type'   => 'field',
						'config' => array(
							'value'             => '/start ' . $registration_token,
							'label'             => esc_attr_x( 'Registration command', 'Telegram', 'uncanny-automator' ),
							'disabled'          => true,
							'copy-to-clipboard' => true,
							'class'             => 'uap-spacing-top uap-spacing-bottom',
						),
					),
					array(
						'type'    => 'text',
						'content' => '<em>' . esc_html_x( "That's it! The bot will automatically detect it's in a channel and send the registration button directly there.", 'Telegram', 'uncanny-automator' ) . '</em>',
					),
				),
			)
		);
	}

	/**
	 * Output registered channels list
	 *
	 * @return void
	 */
	private function output_registered_channels() {
		$channels = $this->helpers->get_channel_data();

		if ( empty( $channels ) ) {
			return;
		}

		// Channels subtitle.
		$this->output_panel_subtitle(
			esc_html_x( 'Registered Channels / Chats', 'Telegram', 'uncanny-automator' ),
			'uap-spacing-top'
		);

		// Channels description.
		$this->output_subtle_panel_paragraph(
			esc_html_x( 'The following channels and chats are registered for use in your recipes:', 'Telegram', 'uncanny-automator' )
		);

		$this->output_channels_table( $channels );
	}

	/**
	 * Output the channels table
	 *
	 * @param array $channels The channels to output
	 *
	 * @return void - Outputs HTML directly
	 */
	private function output_channels_table( $channels ) {
		// Define the columns for the table.
		$columns = array(
			array(
				'header' => esc_html_x( 'Name', 'Telegram', 'uncanny-automator' ),
				'key'    => 'name',
			),
			array(
				'header' => esc_html_x( 'Type', 'Telegram', 'uncanny-automator' ),
				'key'    => 'type',
			),
			array(
				'header' => esc_html_x( 'Action', 'Telegram', 'uncanny-automator' ),
				'key'    => 'action',
			),
		);

		// Format the data using shared method.
		$table_data = $this->format_channels_for_table( $channels );

		$this->output_settings_table( $columns, $table_data );
	}

	/**
	 * Handle webhook URL regeneration (refresh webhook configuration)
	 *
	 * @param array $response - The current response array
	 * @param array $data - The data posted to the settings page.
	 *
	 * @return array
	 */
	protected function handle_webhook_url_regeneration( $response = array(), $data = array() ) {
		try {
			// Re-register the webhook with current settings.
			$result            = $this->webhooks->register_telegram_webhook();
			$response['alert'] = array(
				'type'    => 'success',
				'heading' => esc_html_x( 'Webhook refreshed', 'Telegram', 'uncanny-automator' ),
				'content' => esc_html_x( 'Your webhook configuration has been successfully refreshed with Telegram.', 'Telegram', 'uncanny-automator' ),
			);

		} catch ( Exception $e ) {
			$response['alert'] = array(
				'type'    => 'error',
				'heading' => esc_html_x( 'Webhook refresh failed', 'Telegram', 'uncanny-automator' ),
				'content' => $e->getMessage(),
			);
		}

		return $response;
	}

	/**
	 * Handle channel removal
	 *
	 * @param array $response - The current response array
	 * @param array $data - The data posted to the settings page.
	 *
	 * @return array
	 */
	protected function handle_channel_remove( $response = array(), $data = array() ) {
		try {
			$channel_id = $this->maybe_get_posted_row_id( $data );

			if ( empty( $channel_id ) ) {
				throw new Exception( esc_html_x( 'Channel ID is required.', 'Telegram', 'uncanny-automator' ) );
			}

			// Get current channels.
			$channels = $this->helpers->get_channel_data();

			// Store channel name for success message.
			$channel_name = $channels[ $channel_id ]['title'] ?? esc_html_x( 'Unknown', 'Telegram', 'uncanny-automator' );

			// Remove the channel.
			unset( $channels[ $channel_id ] );

			// Save updated channels.
			$this->helpers->set_channel_data( $channels );

			// Return updated table data for instant UI update.
			$response['data'] = $this->format_channels_for_table( $channels );

			// Show success alert.
			$response['alert'] = array(
				'type'    => 'success',
				'heading' => esc_html_x( 'Channel removed', 'Telegram', 'uncanny-automator' ),
				'content' => sprintf(
					// translators: %s is the channel name.
					esc_html_x( '%s has been removed successfully.', 'Telegram', 'uncanny-automator' ),
					esc_html( $channel_name )
				),
			);

		} catch ( Exception $e ) {
			$response['alert'] = array(
				'type'    => 'error',
				'heading' => esc_html_x( 'Channel removal failed', 'Telegram', 'uncanny-automator' ),
				'content' => $e->getMessage(),
			);
		}

		return $response;
	}

	/**
	 * Format channels data for table display
	 *
	 * @param array $channels
	 *
	 * @return array
	 */
	private function format_channels_for_table( $channels ) {
		// Ensure we have a sequential array.
		$channels = array_values( $channels );

		// Format the data for the table component.
		return array_map(
			function ( $channel ) {
				return array(
					'id'      => $channel['value'],
					'columns' => array(
						'name'   => array(
							'options' => array(
								array(
									'type' => 'text',
									'data' => $channel['title'] ?? esc_html_x( 'Unknown', 'Telegram', 'uncanny-automator' ),
								),
							),
						),
						'type'   => array(
							'options' => array(
								array(
									'type' => 'text',
									'data' => ucfirst( $channel['type'] ?? 'unknown' ),
								),
							),
						),
						'action' => array(
							'options' => array(
								array(
									'type' => 'button',
									// phpcs:disable WordPress.Arrays.MultipleStatementAlignment
									'data' => array(
										'name'                      => 'automator_action',
										'value'                     => 'channel_remove',
										'label'                     => esc_html_x( 'Remove', 'Telegram', 'uncanny-automator' ),
										'color'                     => 'secondary',
										'type'                      => 'submit',
										'row-submission'            => true,
										'needs-confirmation'        => true,
										'confirmation-heading'      => esc_html_x( 'Remove Channel', 'Telegram', 'uncanny-automator' ),
										'confirmation-content'      => esc_html_x( 'Are you sure you want to remove this channel? You can always register it again later.', 'Telegram', 'uncanny-automator' ),
										'confirmation-button-label' => esc_html_x( 'Yes, remove channel', 'Telegram', 'uncanny-automator' ),
									),
									// phpcs:enable WordPress.Arrays.MultipleStatementAlignment
								),
							),
						),
					),
				);
			},
			$channels
		);
	}
}
