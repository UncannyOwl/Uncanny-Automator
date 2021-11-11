<?php

namespace Uncanny_Automator;

/**
 * Class Slack_Helpers
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
	 * @var Slack_Helpers
	 */
	public $setting_tab;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Slack_Helpers constructor.
	 */
	public function __construct() {

		$this->setting_tab   = 'slack_api';
		$this->automator_api = AUTOMATOR_API_URL . 'v2/slack';
		$this->scope         = 'channels:read,groups:read,channels:manage,groups:write,chat:write,users:read,chat:write.customize';

		add_filter( 'uap_settings_tabs', array( $this, 'add_slack_api_settings' ), 15 );
		add_action( 'init', array( $this, 'capture_oauth_tokens' ), 100, 3 );
		add_filter( 'automator_after_settings_extra_buttons', array( $this, 'slack_connect_html' ), 10, 3 );

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
	 *
	 * @return array $tokens
	 */
	public function get_slack_client() {
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
	 * @param array  $args
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

		$client = $this->get_slack_client();

		$response = wp_remote_get( $this->automator_api . '?action=get_conversations&types=public_channel,private_channel&token=' . $client->access_token, $args );

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
	 * @param array  $args
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

				$client = $this->get_slack_client();

				$response = wp_remote_get( $this->automator_api . '?action=get_users&token=' . $client->access_token );

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

		$client = $this->get_slack_client();

		$args['body'] = array(
			'action'  => 'post_message',
			'message' => $this->maybe_customize_bot( $message ),
			'token'   => $client->access_token,
		);

		$args = apply_filters( 'uap_slack_chat_post_message', $args );

		$response = wp_remote_post( $this->automator_api, $args );

		return $response;
	}

	/**
	 * @param $channel
	 *
	 * @return array|\WP_Error
	 */
	public function conversations_create( $channel_name ) {

		$args = array();

		$client = $this->get_slack_client();

		$args['body'] = array(
			'action' => 'create_conversation',
			'name'   => substr( sanitize_title( $channel_name ), 0, 79 ),
			'token'  => $client->access_token,
		);

		$args = apply_filters( 'uap_slack_conversations_create', $args );

		$response = wp_remote_post( $this->automator_api, $args );

		return $response;
	}

	/**
	 * @param string $option_code
	 * @param string $label
	 * @param bool   $tokens
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

	/**
	 * Check if the settings tab should display.
	 *
	 * @return boolean.
	 */
	public function display_settings_tab() {

		if ( Automator()->utilities->has_valid_license() ) {
			return true;
		}

		if ( Automator()->utilities->is_from_modal_action() ) {
			return true;
		}

		return ! empty( $this->get_slack_client() );
	}

	/**
	 * @param $tabs
	 *
	 * @return mixed
	 */
	public function add_slack_api_settings( $tabs ) {

		if ( $this->display_settings_tab() ) {
			$tab_url                    = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-settings&tab=' . $this->setting_tab;
			$tabs[ $this->setting_tab ] = array(
				'name'           => __( 'Slack', 'uncanny-automator' ),
				'title'          => __( 'Slack account settings', 'uncanny-automator' ),
				'description'    => sprintf( '<p>%s</p>', __( 'Connecting to Slack requires signing into your account to link it to Automator. To get started, click the "Connect an account" button below or the "Disconnect account" button if you need to disconnect or connect a new account. Uncanny Automator can only connect to a single Slack account at one time. (It is not possible to set some recipes up under one account and then switch accounts, all recipes are mapped to the account selected on this page and existing recipes may break if they were set up under another account.)', 'uncanny-automator' ) ) . $this->get_user_info(),
				'is_pro'         => false,
				'settings_field' => 'uap_automator_slack_api_settings',
				'wp_nonce_field' => 'uap_automator_slack_api_nonce',
				'save_btn_name'  => 'uap_automator_slack_api_save',
				'save_btn_title' => __( 'Save settings', 'uncanny-automator' ),
				'fields'         => array(
					'uap_automator_slack_api_bot_name' => array(
						'title'       => __( 'Bot name:', 'uncanny-automator' ),
						'type'        => 'text',
						'css_classes' => '',
						'placeholder' => 'Leave blank for default name',
						'default'     => '',
						'required'    => false,
					),
					'uap_automator_alck_api_bot_icon'  => array(
						'title'       => __( 'Bot icon:', 'uncanny-automator' ),
						'type'        => 'text',
						'css_classes' => '',
						'placeholder' => 'Leave blank for default icon',
						'default'     => '',
						'required'    => false,
					),
				),
			);
		}

		return $tabs;
	}

	/**
	 * @param $content
	 * @param $active
	 * @param $tab
	 *
	 * @return false|mixed|string
	 */
	public function slack_connect_html( $content, $active, $tab ) {

		if ( 'slack_api' === $active ) {

			$tab_url = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-settings&tab=' . $this->setting_tab;

			$slack_client = $this->get_slack_client();

			if ( $slack_client ) {
				$button_text  = __( 'Disconnect account', 'uncanny-automator' );
				$button_class = 'uo-disconnect-button';
				$button_url   = $tab_url . '&disconnect=1';
			} else {
				$nonce        = wp_create_nonce( 'automator_slack_api_authentication' );
				$plugin_ver   = InitializePlugin::PLUGIN_VERSION;
				$api_ver      = '1.0';
				$scope        = $this->scope;
				$action       = 'slack_authorization_request';
				$redirect_url = rawurlencode( $tab_url );
				$button_url   = $this->automator_api . "?action={$action}&scope={$scope}&redirect_url={$redirect_url}&nonce={$nonce}&api_ver={$api_ver}&plugin_ver={$plugin_ver}";
				$button_text  = __( 'Connect an account', 'uncanny-automator' );
				$button_class = 'uo-connect-button';
			}

			ob_start();
			?>

			<a href="<?php echo esc_url( $button_url ); ?>" class="uo-settings-btn uo-settings-btn--secondary <?php echo esc_attr( $button_class ); ?>">
				<?php echo esc_attr( $button_text ); ?>
			</a>

			<style>
				.uoa-slack-settings {
					display: flex;
					align-items: center;
					margin: 15px 0 15px 0;
					font-weight: 700;
				}

				.uoa-slack-settings__team-icon {
					margin-right: 10px;
				}

				.uoa-slack-settings__team-name {
					color: #400d40;
				}

				.uo-connect-button {
					color: #fff;
					background-color: #4fb840;
				}

				.uo-disconnect-button {
					color: #fff;
					background-color: #f58933;
				}
			</style>
			<?php
			$content = ob_get_contents();
			ob_end_clean();
		}

		return $content;
	}

	/**
	 * Captures the OAuthentication tokens.
	 *
	 * @return void.
	 */
	public function capture_oauth_tokens() {

		$tab = automator_filter_input( 'tab' );

		if ( ! empty( $tab ) && $tab === $this->setting_tab ) {

			if ( ! empty( automator_filter_input( 'automator_api_message' ) ) ) {
				$tokens = Automator_Helpers_Recipe::automator_api_decode_message( automator_filter_input( 'automator_api_message' ), wp_create_nonce( 'automator_slack_api_authentication' ) );

				if ( $tokens ) {
					update_option( '_uncannyowl_slack_settings', $tokens );
					wp_safe_redirect( admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-settings&tab=' . $this->setting_tab . '&connect=1' ) );
					die;
				} else {
					wp_safe_redirect( admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-settings&tab=' . $this->setting_tab . '&connect=2' ) );
					die;
				}
			} elseif ( ! empty( automator_filter_input( 'disconnect' ) ) ) {
				delete_option( '_uncannyowl_slack_settings' );
				wp_safe_redirect( admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-settings&tab=' . $this->setting_tab ) );
				die;
			}
		}
	}

	/**
	 * Displays the user info.
	 *
	 * @return void
	 */
	public function get_user_info() {

		ob_start();
		/**
		 * @var object $slack_client
		 */
		$slack_client = $this->get_slack_client();

		// Bailout if client is not set.
		if ( empty( $slack_client ) ) {
			return;
		}
		?>
		<div class="uoa-slack-settings">
			<div class="uoa-slack-settings__team-icon">
				<img width="24" src="<?php echo esc_url( plugin_dir_url( __DIR__ ) . 'img/slack-icon.svg' ); ?>" alt="" />
			</div>
			<div class="uoa-slack-settings__team-name">
				<?php echo esc_html( $slack_client->team->name ); ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

}
