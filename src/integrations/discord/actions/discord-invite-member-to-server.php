<?php
namespace Uncanny_Automator\Integrations\Discord;

use Exception;

/**
 * Class DISCORD_INVITE_MEMBER_TO_SERVER
 *
 * @package Uncanny_Automator
 */
class DISCORD_INVITE_MEMBER_TO_SERVER extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Prefix for action code / meta.
	 *
	 * @var string
	 */
	public $prefix = 'DISCORD_INVITE_MEMBER_TO_SERVER';

	/**
	 * Server meta key.
	 *
	 * @var string
	 */
	private $server_key;

	/**
	 * Set up action.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->helpers    = array_shift( $this->dependencies );
		$this->server_key = $this->helpers->get_constant( 'ACTION_SERVER_META_KEY' );

		$this->set_integration( 'DISCORD' );
		$this->set_action_code( $this->prefix . '_CODE' );
		$this->set_action_meta( $this->prefix . '_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/discord/' ) );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				// translators: %1$s Server name, %2$s recipient email
				esc_attr_x( 'Send an invitation to join {{a server:%1$s}} to {{an email:%2$s}}', 'Discord', 'uncanny-automator' ),
				$this->server_key . ':' . $this->get_action_meta(),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Send an invitation to join {{a server}} to {{an email}}', 'Discord', 'uncanny-automator' ) );
		$this->set_action_tokens(
			array(
				'SERVER_ID'    => array(
					'name' => esc_html_x( 'Server ID', 'Discord', 'uncanny-automator' ),
					'type' => 'text',
				),
				'SERVER_NAME'  => array(
					'name' => esc_html_x( 'Server name', 'Discord', 'uncanny-automator' ),
					'type' => 'text',
				),
				'CHANNEL_NAME' => array(
					'name' => esc_html_x( 'Channel name', 'Discord', 'uncanny-automator' ),
					'type' => 'text',
				),
				'INVITE_URL'   => array(
					'name' => esc_html_x( 'Invite URL', 'Discord', 'uncanny-automator' ),
					'type' => 'text',
				),
			),
			$this->get_action_code()
		);
	}

	/**
	 * Define options
	 *
	 * @return array
	 */
	public function options() {

		// Replaceable codes for email body.
		$available_codes = apply_filters(
			'automator_discord_invite_tokens',
			array(
				'{{invite_url}}',
				'{{server_name}}',
				'{{channel_name}}',
				'{{site_name}}',
			)
		);

		return array(
			$this->helpers->get_server_select_config( $this->server_key ),
			$this->helpers->get_server_channel_select_config( 'CHANNEL', $this->server_key ),
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => esc_html_x( 'Email', 'Discord', 'uncanny-automator' ),
				'input_type'  => 'email',
				'required'    => true,
				'description' => esc_html_x( 'Enter an email recipient to send the invite link to.', 'Discord', 'uncanny-automator' ),
			),
			array(
				'option_code'   => 'SUBJECT',
				'label'         => esc_html_x( 'Subject', 'Discord', 'uncanny-automator' ),
				'input_type'    => 'text',
				'required'      => true,
				'default_value' => esc_html_x( 'Join our Discord server!', 'Discord', 'uncanny-automator' ),
			),
			array(
				'option_code'   => 'EMAILFROM',
				'label'         => esc_html_x( 'From', 'Discord', 'uncanny-automator' ),
				'input_type'    => 'email',
				'required'      => true,
				'default_value' => '{{admin_email}}',
			),
			array(
				'option_code'   => 'REPLYTO',
				'label'         => esc_html_x( 'Reply to', 'Discord', 'uncanny-automator' ),
				'input_type'    => 'email',
				'required'      => true,
				'default_value' => '{{admin_email}}',
			),
			array(
				'option_code'               => 'EMAILBODY',
				'label'                     => esc_html_x( 'Email body', 'Discord', 'uncanny-automator' ),
				'input_type'                => 'textarea',
				'supports_fullpage_editing' => true,
				'supports_tinymce'          => true,
				'required'                  => true,
				// phpcs:disable
				'default_value'             => wp_kses(
					_x(
						"Hi, <br />You've been invited to join our Discord server! <br /><br />Click the link below to join and get started:<br />{{invite_url}}<br /><br />Thanks, <br />The {{site_name}} team",
						'Discord',
						'uncanny-automator'
					),
					array(
						'br' => array(),
					)
				),
				'description'               => sprintf(
					// translators: %s Available token codes
					_x( 'Use following tokens in email:<br />%s', 'Discord', 'uncanny-automator' ),
					join( '<br />', $available_codes )
				),
				// phpcs:enable
			),
			array(
				'option_code'   => 'EXPIRATION',
				'label'         => esc_html_x( 'Expiration', 'Discord', 'uncanny-automator' ),
				'input_type'    => 'int',
				'default_value' => '0',
				'min_number'    => 0,
				'max_number'    => 604800,
				'description'   => esc_html_x( 'The time in seconds before the invite link expires. Set to 0 for no expiration, 86400 (24 hours) or a maximum of 604800 (7 days)', 'Discord', 'uncanny-automator' ),
			),
			array(
				'option_code'   => 'USE_COUNT',
				'label'         => esc_html_x( 'Use count', 'Discord', 'uncanny-automator' ),
				'input_type'    => 'int',
				'default_value' => '0',
				'min_number'    => 0,
				'max_number'    => 100,
				'description'   => esc_html_x( 'The maximum number of uses up to 100 or 0 for unlimited.', 'Discord', 'uncanny-automator' ),
			),
			array(
				'option_code' => 'UNIQUE_URL',
				'label'       => esc_html_x( 'Unique URL', 'Discord', 'uncanny-automator' ),
				'input_type'  => 'checkbox',
				'is_toggle'   => true,
				'description' => esc_html_x( 'When enabled, a new URL will be generated (useful for creating many unique one time use invites)', 'Discord', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 * @throws Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		// Required fields - throws error if not set and valid.
		$server_id = $this->helpers->get_server_id_from_parsed( $parsed, $this->server_key );

		// Required email fields.
		$email_to   = $this->get_valid_email( $parsed, $this->get_action_meta(), esc_html_x( 'Email', 'Discord', 'uncanny-automator' ) );
		$email_from = $this->get_valid_email( $parsed, 'EMAILFROM', esc_html_x( 'From', 'Discord', 'uncanny-automator' ) );
		$reply_to   = $this->get_valid_email( $parsed, 'REPLYTO', esc_html_x( 'Reply to', 'Discord', 'uncanny-automator' ) );
		$subject    = $this->helpers->get_text_value_from_parsed( $parsed, 'SUBJECT', esc_html_x( 'Email subject is required', 'Discord', 'uncanny-automator' ) );
		$email_body = isset( $action_data['meta']['EMAILBODY'] ) ? $action_data['meta']['EMAILBODY'] : '';
		if ( empty( $email_body ) ) {
			throw new Exception( esc_html_x( 'Email body is required', 'Discord', 'uncanny-automator' ) );
		}

		// Required channel ID - throws error if not set and valid.
		$channel_id = $this->helpers->get_channel_id_from_parsed( $parsed, 'CHANNEL', $server_id );

		// Make request for invite URL.
		$invite_url = $this->get_invite_url( $parsed, $server_id, $channel_id );

		// Prepare token variables.
		$server_name  = $parsed[ $this->server_key . '_readable' ];
		$channel_name = $this->helpers->get_channel_name_token_value(
			$parsed['CHANNEL_readable'],
			$channel_id,
			$server_id
		);

		// Hydrate tokens.
		$this->hydrate_tokens(
			array(
				'SERVER_ID'    => $server_id,
				'SERVER_NAME'  => $server_name,
				'CHANNEL_NAME' => $channel_name,
				'INVITE_URL'   => $invite_url,
			)
		);

		// Prepare the email body tokens.
		$email_body = str_ireplace( '{{invite_url}}', $invite_url, $email_body );
		$email_body = str_ireplace( '{{server_name}}', $server_name, $email_body );
		$email_body = str_ireplace( '{{channel_name}}', $channel_name, $email_body );
		$email_body = str_ireplace( '{{site_name}}', get_bloginfo( 'name' ), $email_body );
		// Parse any additional tokens.
		$email_body = Automator()->parse->text( $email_body, $recipe_id, $user_id, $args );
		// Parse any shortcodes.
		$email_body = do_shortcode( $email_body );
		// Prepare headers.
		$headers = array(
			'From: ' . $email_from,
			'Reply-To: ' . $reply_to,
			'Content-Type: text/html; charset=UTF-8',
		);
		$headers = apply_filters( 'automator_discord_invite_email_headers', $headers, $this );

		// Send the email.
		$mailed = wp_mail( $email_to, $subject, $email_body, $headers );

		if ( ! $mailed ) {
			throw new Exception( esc_html_x( 'Error sending email.', 'Discord', 'uncanny-automator' ) );
		}

		return true;
	}

	/**
	 * Get valid email.
	 *
	 * @param array $parsed
	 *
	 * @return string
	 * @throws Exception
	 */
	private function get_valid_email( $parsed, $key, $field_name ) {
		$error = sprintf(
			// translators: %s Field name ( Email, Reply to, From, etc. )
			esc_html_x( '%s field is required.', 'Discord', 'uncanny-automator' ),
			$field_name
		);
		$email = $this->helpers->get_text_value_from_parsed( $parsed, $key, $error );

		if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			$error = sprintf(
				// translators: %s Field name ( Email, Reply to, From, etc. )
				esc_html_x( '%s field is not a valid email.', 'Discord', 'uncanny-automator' ),
				$field_name
			);
			throw new Exception( esc_html( $error ) );
		}

		return $email;
	}

	/**
	 * Get invite URL.
	 *
	 * @param array $parsed
	 * @param string $server_id
	 * @param string $channel_id
	 *
	 * @return string
	 * @throws Exception
	 */
	private function get_invite_url( $parsed, $server_id, $channel_id ) {

		// Validate the expiration.
		$expiration = absint( $this->get_parsed_meta_value( 'EXPIRATION', 0 ) );
		if ( $expiration < 0 || $expiration > 604800 ) {
			throw new Exception( esc_html_x( 'Expiration must be between 0 and 604800 seconds', 'Discord', 'uncanny-automator' ) );
		}

		// Validate the use count.
		$use_count = absint( $this->get_parsed_meta_value( 'USE_COUNT', 0 ) );
		if ( $use_count < 0 || $use_count > 100 ) {
			throw new Exception( esc_html_x( 'Use count must be between 0 and 100', 'Discord', 'uncanny-automator' ) );
		}

		// Prepare the body.
		$body = array(
			'action'     => 'create_channel_invite',
			'channel_id' => $channel_id,
			'max_age'    => $expiration,
			'max_uses'   => $use_count,
			'unique'     => $this->helpers->get_bool_value( $this->get_parsed_meta_value( 'UNIQUE_URL', false ) ),
		);

		// Send the message.
		$response = $this->helpers->api()->api_request( $body, $action_data, $server_id );
		$error    = esc_html_x( 'Error generating invite URL.', 'Discord', 'uncanny-automator' );

		// Check for errors.
		$status_code = isset( $response['statusCode'] ) ? absint( $response['statusCode'] ) : 0;
		if ( 200 !== $status_code ) {
			throw new Exception( esc_html( $error ) );
		}

		$code = isset( $response['data']['code'] ) ? $response['data']['code'] : '';

		if ( empty( $code ) ) {
			throw new Exception( esc_html( $error ) );
		}

		return 'https://discord.gg/' . $code;
	}
}
