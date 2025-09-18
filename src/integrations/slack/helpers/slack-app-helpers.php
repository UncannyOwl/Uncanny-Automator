<?php

namespace Uncanny_Automator\Integrations\Slack;

use Uncanny_Automator\App_Integrations\App_Helpers;
use Exception;

/**
 * Class Slack_Helpers
 *
 * @package Uncanny_Automator
 *
 * @property Slack_API_Caller $api
 */
class Slack_App_Helpers extends App_Helpers {

	/**
	 * The bot name uap_option key.
	 *
	 * @var string
	 */
	const BOT_NAME = 'uap_automator_slack_api_bot_name';

	/**
	 * The bot icon uap_option key.
	 *
	 * @var string
	 */
	const BOT_ICON = 'uap_automator_alck_api_bot_icon';

	////////////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////////////

	/**
	 * Set the properties for the Slack integration.
	 *
	 * @return void
	 */
	public function set_properties() {
		$this->set_credentials_option_name( '_uncannyowl_slack_settings' );
	}

	/**
	 * Validate credentials.
	 *
	 * @param array $credentials The credentials.
	 * @param array $args Optional arguments.
	 *
	 * @return array $credentials
	 * @throws Exception If the credentials are not valid.
	 */
	public function validate_credentials( $credentials, $args = array() ) {
		if ( empty( $credentials ) ) {
			throw new Exception( 'Slack is not connected' );
		}
		return $credentials;
	}

	/**
	 * Get the user account info.
	 *
	 * @return array $info
	 */
	public function get_account_info() {
		try {
			$credentials = $this->get_credentials();
		} catch ( Exception $e ) {
			$credentials = array();
		}

		$info = isset( $credentials->team ) ? $credentials->team : (object) array();

		return array(
			// Get the Slack workspace name
			'name' => $info->name ?? '',
			// Get the Slack workspace ID
			'id'   => $info->id ?? '',
		);
	}

	////////////////////////////////////////////////////////////
	// Integration specific methods
	////////////////////////////////////////////////////////////

	/**
	 * Get the bot name
	 *
	 * @return string
	 */
	public function get_bot_name() {
		return automator_get_option( self::BOT_NAME, '' );
	}

	/**
	 * Get the bot icon
	 *
	 * @return string
	 */
	public function get_bot_icon() {
		return automator_get_option( self::BOT_ICON, '' );
	}

	/**
	 * Method maybe_customize_bot
	 *
	 * @param array $mesage
	 *
	 * @return array $mesage
	 */
	public function maybe_customize_bot( $message ) {

		$name = $this->get_bot_name();
		if ( empty( $message['username'] ) && ! empty( $name ) ) {
			$message['username'] = $name;
		}

		$icon = $this->get_bot_icon();
		if ( empty( $message['icon_url'] ) && ! empty( $icon ) ) {
			$message['icon_url'] = $icon;
		}

		return apply_filters( 'uap_slack_maybe_customize_bot', $message );
	}

	/**
	 * Get the configuration for the channel select field.
	 *
	 * @return array
	 */
	public function get_channel_select_config() {
		return array(
			'option_code'            => 'SLACKCHANNEL',
			'label'                  => esc_attr_x( 'Channel', 'Slack', 'uncanny-automator' ),
			'input_type'             => 'select',
			'required'               => true,
			'supports_custom_value'  => true,
			'show_label_in_sentence' => false,
			'description'            => esc_attr_x( 'Make sure that the bot is added to the selected channel!', 'Slack', 'uncanny-automator' ),
			'ajax'                   => array(
				'endpoint' => 'automator_slack_get_channels',
				'event'    => 'on_load',
			),
		);
	}

	/**
	 * Get channel options ajax handler.
	 *
	 * @return void
	 */
	public function get_channel_options_ajax() {
		Automator()->utilities->verify_nonce();
		$options = $this->api->get_channel_options();
		wp_send_json(
			array(
				'success' => true,
				'options' => $options,
			)
		);
	}

	/**
	 * Get the configuration for the message textarea field.
	 *
	 * @return array
	 */
	public function get_message_textarea_config() {
		return array(
			'option_code'           => 'SLACKMESSAGE',
			'label'                 => esc_attr_x( 'Message', 'Slack', 'uncanny-automator' ),
			'input_type'            => 'textarea',
			'supports_custom_value' => true,
			'required'              => true,
			'placeholder'           => esc_attr_x( 'Enter the message', 'Slack', 'uncanny-automator' ),
			'description'           => esc_attr_x( '* Markdown is supported', 'Slack', 'uncanny-automator' ),
			'supports_markdown'     => true,
		);
	}

	/**
	 * Get the configuration for the bot name field.
	 *
	 * @return array
	 */
	public function get_bot_name_config() {
		return array(
			'option_code'           => 'BOT_NAME',
			'label'                 => esc_attr_x( 'Bot name', 'Slack', 'uncanny-automator' ),
			'input_type'            => 'text',
			'supports_custom_value' => true,
			'required'              => false,
			'default'               => $this->get_bot_name(),
		);
	}

	/**
	 * Get the configuration for the bot icon field.
	 *
	 * @return array
	 */
	public function get_bot_icon_config() {
		return array(
			'option_code'           => 'BOT_ICON',
			'label'                 => esc_attr_x( 'Bot icon', 'Slack', 'uncanny-automator' ),
			'input_type'            => 'url',
			'supports_custom_value' => true,
			'required'              => false,
			'default'               => $this->get_bot_icon(),
			'description'           => esc_attr_x( 'Enter the URL of the image you wish to share. The image must be publicly accessible and at minimum 512x512 pixels and at maximum 1024x1024 pixels.', 'Slack', 'uncanny-automator' ),
		);
	}

	/**
	 * Get joinable channels select options ajax handler.
	 *
	 * @return void
	 */
	public function get_joinable_channel_options_ajax() {
		Automator()->utilities->verify_nonce();
		wp_send_json(
			array(
				'success' => true,
				'options' => $this->api->get_joinable_channel_options(),
			)
		);
	}

	/**
	 * Get users select options ajax handler.
	 *
	 * @return void
	 */
	public function get_user_options_ajax() {
		Automator()->utilities->verify_nonce();
		wp_send_json(
			array(
				'success' => true,
				'options' => $this->api->get_user_options(),
			)
		);
	}
}
