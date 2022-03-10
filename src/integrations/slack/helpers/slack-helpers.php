<?php

namespace Uncanny_Automator;

/**
 * Class Slack_Helpers
 *
 * @package Uncanny_Automator
 */
class Slack_Helpers {

	/**
	 * @var Slack_Helpers
	 */
	public $options;

	/**
	 * @var Slack_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * The URL of the API for this integration
	 *
	 * @var String
	 */
	public static $api_integration_url;

	/**
	 * The Slack scope
	 *
	 * @var String
	 */
	public static $scope;

	/**
	 * Slack_Helpers constructor.
	 */
	public function __construct() {
		self::$api_integration_url = AUTOMATOR_API_URL . 'v2/slack';
		$this->load_settings();
	}

	/**
	 * Load the settings
	 * 
	 * @return void
	 */
	private function load_settings() {
		include_once __DIR__ . '/../settings/settings-slack.php';
	}

	/**
	 * @param Slack_Helpers $options
	 */
	public function setOptions( Slack_Helpers $options ) { // phpcs:ignore
		$this->options = $options;
	}

	/**
	 * @param Slack_Helpers $pro
	 */
	public function setPro( \Uncanny_Automator_Pro\Slack_Pro_Helpers $pro ) { // phpcs:ignore
		$this->pro = $pro;
	}

	/**
	 * Checks whether this integration is connected
	 *
	 * @return boolean True if it's connected
	 */
	public static function get_is_connected() {
		return ! empty( self::get_slack_client() );
	}

	/**
	 *
	 * @return array $tokens
	 */
	public static function get_slack_client() {
		$tokens = get_option( '_uncannyowl_slack_settings', array() );

		if ( empty( $tokens ) ) {
			return false;
		}

		return $tokens;
	}

	/**
	 * @param array $mesage
	 *
	 * @return array $mesage
	 */
	public function maybe_customize_bot( $message ) {

		$bot_name = get_option( 'uap_automator_slack_api_bot_name' );

		if ( ! empty( $bot_name ) ) {
			$message['username'] = $bot_name;
		}

		$bot_icon = get_option( 'uap_automator_alck_api_bot_icon' );

		if ( ! empty( $bot_icon ) ) {
			$message['icon_url'] = $bot_icon;
		}

		return apply_filters( 'uap_slack_maybe_customize_bot', $message );

	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function get_slack_channels( $label = null, $option_code = 'SLACKCHANNEL', $args = array() ) {

		if ( ! $label ) {
			$label = __( 'Slack channel', 'uncanny-automator' );
		}

		$args = wp_parse_args(
			$args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => __( 'Any channel', 'uncanny-automator' ),
			)
		);

		$is_ajax                  = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field             = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point                = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$description              = key_exists( 'description', $args ) ? $args['description'] : __( 'Make sure that the bot is added to the selected channel!', 'uncanny-automator' );
		$custom_value_description = key_exists( 'custom_value_description', $args ) ? $args['custom_value_description'] : null;
		$supports_custom_value    = key_exists( 'supports_custom_value', $args ) ? $args['supports_custom_value'] : false;
		$supports_tokens          = key_exists( 'supports_tokens', $args ) ? $args['supports_tokens'] : null;
		$placeholder              = key_exists( 'placeholder', $args ) ? $args['placeholder'] : null;
		$options                  = array();

		$options[] = array(
			'value' => '-1',
			'text'  => __( 'Select a channel', 'uncanny-automator' ),
		);

		$client = self::get_slack_client();

		$response = wp_remote_get( self::$api_integration_url . '?action=get_conversations&types=public_channel,private_channel&token=' . $client->access_token, $args );

		$body = null;

		$data = false;
		if ( is_array( $response ) && ! is_wp_error( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ) );
			$data = $body->data;
		}

		if ( $data && $data->ok ) {

			foreach ( $data->channels as $channel ) {
				if ( $channel->is_private ) {
					$options[] = array(
						'value' => $channel->id,
						'text'  => 'Private: ' . $channel->name,
					);
				} else {
					$options[] = array(
						'value' => $channel->id,
						'text'  => $channel->name,
					);
				}
			}
		}

		$option = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'description'              => $description,
			'input_type'               => 'select',
			'required'                 => true,
			'is_ajax'                  => $is_ajax,
			'fill_values_in'           => $target_field,
			'endpoint'                 => $end_point,
			'options'                  => $options,
			'supports_tokens'          => apply_filters( 'uap_option_' . $option_code . '_select_field', $supports_tokens ),
			'custom_value_description' => $custom_value_description,
			'supports_custom_value'    => $supports_custom_value,
			'placeholder'              => $placeholder,
			'integration'              => 'SLACK',
		);

		return apply_filters( 'uap_option_get_slack_channels', $option );

	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function get_slack_users( $label = null, $option_code = 'SLACKUSERS', $args = array() ) {

		if ( ! $label ) {
			$label = __( 'Slack user', 'uncanny-automator' );
		}

		$args = wp_parse_args(
			$args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => __( 'Any user', 'uncanny-automator' ),
			)
		);

		$is_ajax                  = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field             = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point                = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$description              = key_exists( 'description', $args ) ? $args['description'] : null;
		$custom_value_description = key_exists( 'custom_value_description', $args ) ? $args['custom_value_description'] : null;
		$supports_custom_value    = key_exists( 'supports_custom_value', $args ) ? $args['supports_custom_value'] : false;
		$supports_tokens          = key_exists( 'supports_tokens', $args ) ? $args['supports_tokens'] : null;
		$placeholder              = key_exists( 'placeholder', $args ) ? $args['placeholder'] : null;
		$options                  = array();

		$options[] = array(
			'value' => '-1',
			'text'  => __( 'Select a channel', 'uncanny-automator' ),
		);

		if ( Automator()->helpers->recipe->load_helpers ) {

			$options = get_transient( 'automator_get_slack_users' );

			if ( false === $options ) {

				$client = self::get_slack_client();

				$response = wp_remote_get( self::$api_integration_url . '?action=get_users&token=' . $client->access_token );

				$body = null;

				if ( is_array( $response ) && ! is_wp_error( $response ) ) {
					$body = json_decode( wp_remote_retrieve_body( $response ) );
					$data = $body->data;

					if ( $data && $data->ok ) {
						foreach ( $data->members as $member ) {
							$options[] = array(
								'value' => $member->id,
								'text'  => $member->name,
							);
						}
					}
				}

				set_transient( 'automator_get_slack_users', $options, 60 );

			}
		}

		$option = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'description'              => $description,
			'input_type'               => 'select',
			'required'                 => true,
			'is_ajax'                  => $is_ajax,
			'fill_values_in'           => $target_field,
			'endpoint'                 => $end_point,
			'options'                  => $options,
			'supports_tokens'          => apply_filters( 'uap_option_' . $option_code . '_select_field', $supports_tokens ),
			'custom_value_description' => $custom_value_description,
			'supports_custom_value'    => $supports_custom_value,
			'placeholder'              => $placeholder,
			'integration'              => 'SLACK',
		);

		return apply_filters( 'uap_option_get_slack_users', $option );

	}

	/**
	 * @param $message
	 *
	 * @return array|\WP_Error
	 */
	public function chat_post_message( $message ) {

		$args = array();

		$client = self::get_slack_client();

		$args['body'] = array(
			'action'  => 'post_message',
			'message' => $this->maybe_customize_bot( $message ),
			'token'   => $client->access_token,
		);

		$args = apply_filters( 'uap_slack_chat_post_message', $args );

		$response = wp_remote_post( self::$api_integration_url, $args );

		return $response;
	}

	/**
	 * @param $channel
	 *
	 * @return array|\WP_Error
	 */
	public function conversations_create( $channel_name ) {

		$args = array();

		$client = self::get_slack_client();

		$args['body'] = array(
			'action' => 'create_conversation',
			'name'   => substr( sanitize_title( $channel_name ), 0, 79 ),
			'token'  => $client->access_token,
		);

		$args = apply_filters( 'uap_slack_conversations_create', $args );

		$response = wp_remote_post( self::$api_integration_url, $args );

		return $response;
	}

	/**
	 * @param string $option_code
	 * @param string $label
	 * @param bool $tokens
	 * @param string $type
	 * @param string $default
	 * @param bool
	 * @param string $description
	 * @param string $placeholder
	 *
	 * @return mixed
	 */
	public function textarea_field( $option_code = 'TEXT', $label = null, $tokens = true, $type = 'text', $default = null, $required = true, $description = '', $placeholder = null ) {

		if ( ! $label ) {
			$label = __( 'Text', 'uncanny-automator' );
		}

		if ( ! $description ) {
			$description = '';
		}

		if ( ! $placeholder ) {
			$placeholder = '';
		}

		$option = array(
			'option_code'      => $option_code,
			'label'            => $label,
			'description'      => $description,
			'placeholder'      => $placeholder,
			'input_type'       => $type,
			'supports_tokens'  => $tokens,
			'required'         => $required,
			'default_value'    => $default,
			'supports_tinymce' => false,
		);

		return apply_filters( 'uap_option_text_field', $option );

	}
}
