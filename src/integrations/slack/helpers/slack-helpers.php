<?php

namespace Uncanny_Automator;

/**
 * Class Slack_Helpers
 *
 * @package Uncanny_Automator
 */
class Slack_Helpers {

	/**
	 * The API endpoint address.
	 *
	 * @var API_ENDPOINT The endpoint adress.
	 */
	const API_ENDPOINT = 'v2/slack';
	public $options;
	public $pro;
	public $load_options = true;
	public $api_integration_url;
	public $scope;

	/**
	 * The setting's ID.
	 *
	 * @var string $setting_tab
	 */
	public $setting_tab = 'slack_api';

	/**
	 * The settings page URL.
	 *
	 * @var string $settings_page_url
	 */
	public $settings_page_url = '';

	/**
	 * The scope.
	 *
	 * @var string $slack_scope
	 */
	public $slack_scope = '';

	/**
	 * Slack_Helpers constructor.
	 */
	public function __construct() {

		$this->settings_page_url = add_query_arg(
			array(
				'post_type'   => 'uo-recipe',
				'page'        => 'uncanny-automator-config',
				'tab'         => 'premium-integrations',
				'integration' => $this->setting_tab,
			),
			admin_url( 'edit.php' )
		);

		// Set the Slack scope
		$this->slack_scope = implode(
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

		add_action( 'init', array( $this, 'capture_oauth_tokens' ), AUTOMATOR_APP_INTEGRATIONS_PRIORITY );
		add_action( 'init', array( $this, 'disconnect' ), AUTOMATOR_APP_INTEGRATIONS_PRIORITY );

		$this->load_settings();
	}

	/**
	 * Load the settings
	 *
	 * @return void
	 */
	private function load_settings() {
		include_once __DIR__ . '/../settings/settings-slack.php';
		new Slack_Settings( $this );
	}

	/**
	 * Method setOptions
	 *
	 * @param Slack_Helpers $options
	 *
	 * @return void
	 */
	public function setOptions( Slack_Helpers $options ) { // phpcs:ignore
		$this->options = $options;
	}

	/**
	 * Method setPro
	 *
	 * @param Slack_Helpers $pro
	 */
	public function setPro( \Uncanny_Automator_Pro\Slack_Pro_Helpers $pro ) { // phpcs:ignore
		$this->pro = $pro;
	}

	/**
	 * Method get_slack_client
	 *
	 * @return array $tokens
	 */
	public function get_slack_client() {

		$tokens = automator_get_option( '_uncannyowl_slack_settings', array() );

		// A patch to address the change in api_decode_message
		// We need to recursively convert the array into an object here
		$tokens = json_decode( wp_json_encode( $tokens ) );

		if ( empty( $tokens ) ) {
			throw new \Exception( 'Slack is not connected' );
		}

		return $tokens;
	}

	/**
	 * Method maybe_customize_bot
	 *
	 * @param array $mesage
	 *
	 * @return array $mesage
	 */
	public function maybe_customize_bot( $message ) {

		$settings_bot_name = automator_get_option( 'uap_automator_slack_api_bot_name', '' );

		if ( empty( $message['username'] ) && ! empty( $settings_bot_name ) ) {
			$message['username'] = automator_get_option( 'uap_automator_slack_api_bot_name', '' );
		}

		$settings_bot_icon = automator_get_option( 'uap_automator_alck_api_bot_icon', '' );

		if ( empty( $message['icon_url'] ) && ! empty( $settings_bot_icon ) ) {

			$message['icon_url'] = automator_get_option( 'uap_automator_alck_api_bot_icon', '' );
		}

		return apply_filters( 'uap_slack_maybe_customize_bot', $message );
	}

	/**
	 * Method get_slack_channels
	 *
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function get_slack_channels( $label = null, $option_code = 'SLACKCHANNEL', $args = array() ) {

		if ( ! $label ) {
			$label = esc_html_x( 'Slack channel', 'Slack', 'uncanny-automator' );
		}

		$is_ajax                  = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field             = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point                = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$description              = key_exists( 'description', $args ) ? $args['description'] : esc_html_x( 'Make sure that the bot is added to the selected channel!', 'Slack', 'uncanny-automator' );
		$custom_value_description = key_exists( 'custom_value_description', $args ) ? $args['custom_value_description'] : null;
		$supports_custom_value    = key_exists( 'supports_custom_value', $args ) ? $args['supports_custom_value'] : false;
		$supports_tokens          = key_exists( 'supports_tokens', $args ) ? $args['supports_tokens'] : null;
		$placeholder              = key_exists( 'placeholder', $args ) ? $args['placeholder'] : null;

		$option = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'description'              => $description,
			'input_type'               => 'select',
			'required'                 => true,
			'is_ajax'                  => $is_ajax,
			'fill_values_in'           => $target_field,
			'endpoint'                 => $end_point,
			'options'                  => $this->api_get_channels(),
			'supports_tokens'          => apply_filters( 'uap_option_' . $option_code . '_select_field', $supports_tokens ),
			'custom_value_description' => $custom_value_description,
			'supports_custom_value'    => $supports_custom_value,
			'placeholder'              => $placeholder,
			'integration'              => 'SLACK',
		);

		return apply_filters( 'uap_option_get_slack_channels', $option );
	}

	/**
	 * Method get_slack_users
	 *
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function get_slack_users( $label = null, $option_code = 'SLACKUSERS', $args = array() ) {

		if ( ! $label ) {
			$label = esc_html_x( 'Slack user', 'Slack', 'uncanny-automator' );
		}

		$is_ajax                  = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field             = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point                = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$description              = key_exists( 'description', $args ) ? $args['description'] : null;
		$custom_value_description = key_exists( 'custom_value_description', $args ) ? $args['custom_value_description'] : null;
		$supports_custom_value    = key_exists( 'supports_custom_value', $args ) ? $args['supports_custom_value'] : false;
		$supports_tokens          = key_exists( 'supports_tokens', $args ) ? $args['supports_tokens'] : null;
		$placeholder              = key_exists( 'placeholder', $args ) ? $args['placeholder'] : null;

		$option = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'description'              => $description,
			'input_type'               => 'select',
			'required'                 => true,
			'is_ajax'                  => $is_ajax,
			'fill_values_in'           => $target_field,
			'endpoint'                 => $end_point,
			'options'                  => $this->api_get_users(),
			'supports_tokens'          => apply_filters( 'uap_option_' . $option_code . '_select_field', $supports_tokens ),
			'custom_value_description' => $custom_value_description,
			'supports_custom_value'    => $supports_custom_value,
			'placeholder'              => $placeholder,
			'integration'              => 'SLACK',
		);

		return apply_filters( 'uap_option_get_slack_users', $option );
	}

	/**
	 * Method chat_post_message
	 *
	 * @param $message
	 *
	 * @return array|\WP_Error
	 */
	public function chat_post_message( $message, $action = null ) {

		$body = array(
			'action'  => 'post_message',
			'message' => $this->maybe_customize_bot( $message ),
		);

		$body = apply_filters( 'uap_slack_chat_post_message', $body );

		$response = $this->api_call( $body, $action );

		return $response;
	}

	/**
	 * Method conversations_create
	 *
	 * @param $channel
	 *
	 * @return array|\WP_Error
	 */
	public function conversations_create( $channel_name, $action = null ) {

		$body = array(
			'action' => 'create_conversation',
			'name'   => substr( sanitize_title( $channel_name ), 0, 79 ),
		);

		$body = apply_filters( 'uap_slack_conversations_create', $body );

		$response = $this->api_call( $body, $action );

		return $response;
	}

	/**
	 * Method textarea_field
	 *
	 * @param string $option_code
	 * @param string $label
	 * @param bool $tokens
	 * @param string $type
	 * @param string $default_value
	 * @param bool $required
	 * @param string $description
	 * @param string $placeholder
	 *
	 * @return mixed
	 */
	public function textarea_field( $option_code = 'TEXT', $label = null, $tokens = true, $type = 'text', $default_value = null, $required = true, $description = '', $placeholder = null ) {

		if ( ! $label ) {
			$label = esc_html_x( 'Text', 'Slack', 'uncanny-automator' );
		}

		if ( ! $description ) {
			$description = '';
		}

		if ( ! $placeholder ) {
			$placeholder = '';
		}

		$option = array(
			'option_code'       => $option_code,
			'label'             => $label,
			'description'       => $description,
			'placeholder'       => $placeholder,
			'input_type'        => $type,
			'supports_tokens'   => $tokens,
			'required'          => $required,
			'default_value'     => $default_value,
			'supports_tinymce'  => false,
			'supports_markdown' => true,
		);

		return apply_filters( 'uap_option_text_field', $option );
	}

	/**
	 * Method api_get_channels
	 *
	 * @return void
	 */
	public function api_get_channels() {

		try {

			$body = array(
				'action' => 'get_users_conversations',
				'types'  => 'public_channel,private_channel',
			);

			$response = $this->api_call( $body );

			$options[] = array(
				'value' => '-1',
				'text'  => esc_html_x( 'Select a channel', 'Slack', 'uncanny-automator' ),
			);

			foreach ( $response['data']['channels'] as $channel ) {
				if ( $channel['is_private'] ) {
					$options[] = array(
						'value' => $channel['id'],
						'text'  => 'Private: ' . $channel['name'],
					);
				} else {
					$options[] = array(
						'value' => $channel['id'],
						'text'  => $channel['name'],
					);
				}
			}
		} catch ( \Exception $e ) {
			$options[] = array(
				'value' => '',
				'text'  => esc_html_x( 'Something went wrong when fetching channels. Please try again later.', 'Slack', 'uncanny-automator' ),
			);
		}

		return $options;
	}

	/**
	 * Method api_get_users
	 *
	 * @return void
	 */
	public function api_get_users() {

		try {

			$body = array(
				'action' => 'get_users',
			);

			$response = $this->api_call( $body );

			$options[] = array(
				'value' => '',
				'text'  => esc_html_x( 'Select a user', 'Slack', 'uncanny-automator' ),
			);

			foreach ( $response['data']['members'] as $member ) {
				$options[] = array(
					'value' => $member['id'],
					'text'  => $member['name'],
				);
			}
		} catch ( \Exception $e ) {
			$options[] = array(
				'value' => '',
				'text'  => esc_html_x( 'Something went wrong when fetching users. Please try again later.', 'Slack', 'uncanny-automator' ),
			);
		}

		return $options;
	}

	/**
	 * Returns the link to connect to Slack
	 *
	 * @return string The link to connect the site
	 */
	public function get_connect_url( $redirect_url = '' ) {
		// Check if there is a custom redirect URL defined, otherwise, use the default one
		$redirect_url = ! empty( $redirect_url ) ? $redirect_url : $this->settings_page_url;

		// Define the parameters of the URL
		$parameters = array(
			'nonce'        => wp_create_nonce( 'automator_slack_api_authentication' ),
			'plugin_ver'   => AUTOMATOR_PLUGIN_VERSION,
			'api_ver'      => '2.0',
			'action'       => 'slack_authorization_request',
			'redirect_url' => rawurlencode( $redirect_url ),
			'scope'        => $this->slack_scope,
		);

		// Return the URL
		return add_query_arg(
			$parameters,
			AUTOMATOR_API_URL . self::API_ENDPOINT
		);
	}

	/**
	 * Returns the link to disconnect Slack
	 *
	 * @return string The link to disconnect the site
	 */
	public function get_disconnect_url() {
		// Define the parameters of the URL
		$parameters = array(
			// Parameter used to detect the request
			'disconnect' => '1',
		);

		// Return the URL
		return add_query_arg(
			$parameters,
			$this->settings_page_url
		);
	}

	/**
	 * Method is_current_settings_tab
	 *
	 * @return void
	 */
	public function is_current_settings_tab() {

		if ( 'uo-recipe' !== automator_filter_input( 'post_type' ) ) {
			return false;
		}

		if ( 'uncanny-automator-config' !== automator_filter_input( 'page' ) ) {
			return false;
		}

		if ( 'premium-integrations' !== automator_filter_input( 'tab' ) ) {
			return false;
		}

		if ( automator_filter_input( 'integration' ) !== $this->setting_tab ) {
			return;
		}

		return true;
	}

	/**
	 * Captures oauth tokens after the redirect from Slack
	 */
	public function capture_oauth_tokens() {

		if ( ! $this->is_current_settings_tab() ) {
			return;
		}

		// Check if the API returned the tokens
		// If this exists, then we can assume the user is trying to connect his account
		$automator_api_response = automator_filter_input( 'automator_api_message' );

		if ( empty( $automator_api_response ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Parse the tokens
		$tokens = Automator_Helpers_Recipe::automator_api_decode_message(
			$automator_api_response,
			wp_create_nonce( 'automator_slack_api_authentication' )
		);

		$connect = 2;

		// Check is the parsed tokens are valid
		if ( $tokens ) {
			automator_update_option( '_uncannyowl_slack_settings', $tokens );
			$connect = 1;
		}

		// Reload
		wp_safe_redirect(
			add_query_arg(
				array(
					'connect' => $connect,
				),
				$this->settings_page_url
			)
		);

		die;
	}

	/**
	 * Method disconnect
	 *
	 * @return void
	 */
	public function disconnect() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! $this->is_current_settings_tab() ) {
			return;
		}

		if ( '1' === automator_filter_input( 'disconnect' ) ) {
			// Delete the saved data
			automator_delete_option( '_uncannyowl_slack_settings' );

			// Reload the page
			wp_safe_redirect( $this->settings_page_url );
		}
	}

	/**
	 * Method api_call
	 *
	 * @param mixed $body
	 * @param mixed $action
	 *
	 * @return void
	 */
	public function api_call( $body, $action = null ) {

		$client = $this->get_slack_client();

		$body['token']  = $client->access_token;
		$body['client'] = $client;

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => $body,
			'action'   => $action,
		);

		$response = Api_Server::api_call( $params );

		$this->check_for_errors( $response );

		return $response;
	}

	/**
	 * integration_status
	 *
	 * @return string
	 */
	public function integration_status() {

		try {
			$client       = $this->get_slack_client();
			$is_connected = true;
		} catch ( \Exception $e ) {
			$client       = array();
			$is_connected = false;
		}

		return $is_connected ? 'success' : '';
	}

	/**
	 * check_for_errors
	 *
	 * @param mixed $response
	 *
	 * @return void
	 */
	public function check_for_errors( $response ) {

		// The API class makes sure the [data] is always there.
		$data = $response['data'];

		if ( ! empty( $data['ok'] ) && true === $data['ok'] ) {
			return;
		}

		$error = esc_html_x( 'Unknown Slack API error occurred.', 'Slack', 'uncanny-automator' );

		if ( ! empty( $data['error'] ) ) {
			$error = esc_html_x( 'Slack API returned an error:', 'Slack', 'uncanny-automator' ) . $data['error'];
		}

		throw new \Exception( esc_html( $error ), absint( $response['statusCode'] ) );
	}

	/**
	 * bot_name_field
	 *
	 * @return array
	 */
	public function bot_name_field() {
		return Automator()->helpers->recipe->field->text(
			array(
				'option_code' => 'BOT_NAME',
				'input_type'  => 'text',
				'required'    => false,
				'label'       => esc_html_x( 'Bot name', 'Slack', 'uncanny-automator' ),
				'default'     => automator_get_option( 'uap_automator_slack_api_bot_name', '' ),
			)
		);
	}

	/**
	 * bot_icon_field
	 *
	 * @return array
	 */
	public function bot_icon_field() {
		return Automator()->helpers->recipe->field->text(
			array(
				'option_code' => 'BOT_ICON',
				'input_type'  => 'url',
				'required'    => false,
				'label'       => esc_html_x( 'Bot icon', 'Slack', 'uncanny-automator' ),
				'default'     => automator_get_option( 'uap_automator_alck_api_bot_icon', '' ),
				'description' => esc_html_x( 'Enter the URL of the image you wish to share. The image must be publicly accessible and at minimum 512x512 pixels and at maximum 1024x1024 pixels.', 'Slack', 'uncanny-automator' ),
			)
		);
	}
}
