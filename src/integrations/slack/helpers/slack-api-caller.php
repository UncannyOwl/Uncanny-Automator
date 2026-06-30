<?php

namespace Uncanny_Automator\Integrations\Slack;

use Uncanny_Automator\App_Integrations\Api_Caller;
/**
 * Class Slack_Api_Caller
 *
 * @package Uncanny_Automator
 *
 * @property Slack_App_Helpers $helpers
 */
class Slack_Api_Caller extends Api_Caller {

	/**
	 * Send a message to a Slack channel or user.
	 *
	 * @param array $message The message to send.
	 * @param array $action_data Optional action data for logging.
	 *
	 * @return array The API response.
	 */
	public function chat_post_message( $message, $action_data = null ) {
		$body = array(
			'action'  => 'post_message',
			'message' => $this->maybe_customize_bot( $message ),
		);

		$body = apply_filters( 'uap_slack_chat_post_message', $body );

		return $this->slack_request( $body, $action_data );
	}

	/**
	 * Customize bot name and icon if set in settings.
	 *
	 * @param array $message The message to customize.
	 *
	 * @return array The customized message.
	 */
	private function maybe_customize_bot( $message ) {
		$bot_name = $this->helpers->get_bot_name();
		if ( empty( $message['username'] ) && ! empty( $bot_name ) ) {
			$message['username'] = $bot_name;
		}

		$bot_icon = $this->helpers->get_bot_icon();
		if ( empty( $message['icon_url'] ) && ! empty( $bot_icon ) ) {
			$message['icon_url'] = $bot_icon;
		}

		return apply_filters( 'uap_slack_maybe_customize_bot', $message );
	}

	/**
	 * Create a new Slack channel.
	 *
	 * @param string $channel_name The name of the channel to create.
	 * @param array $action_data Optional action data for logging.
	 *
	 * @return array The API response.
	 */
	public function conversations_create( $channel_name, $action_data = null ) {
		$body = array(
			'action' => 'create_conversation',
			'name'   => substr( sanitize_title( $channel_name ), 0, 79 ),
		);

		$body = apply_filters( 'uap_slack_conversations_create', $body );

		return $this->slack_request( $body, $action_data );
	}

	/**
	 * Get a list of Slack channels.
	 *
	 * @return array List of channels.
	 */
	public function get_channel_options() {
		try {
			$body = array(
				'action' => 'get_users_conversations',
				'types'  => 'public_channel,private_channel',
			);

			$response = $this->slack_request( $body );

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
	 * Join a Slack channel.
	 *
	 * @param string $channel_id The channel ID to join.
	 * @param array $action_data Optional action data for logging.
	 *
	 * @return array The API response.
	 */
	public function conversations_join( $channel_id, $action_data = null ) {
		$body = array(
			'action' => 'join_conversation',
			'channel' => $channel_id,
		);

		$body = apply_filters( 'uap_slack_conversations_join', $body );

		return $this->slack_request( $body, $action_data );
	}

	/**
	 * Invite a user to a Slack channel.
	 *
	 * @param string $channel_id The channel ID to invite the user to.
	 * @param string $user_id The user ID to invite.
	 * @param array $action_data Optional action data for logging.
	 *
	 * @return array The API response.
	 */
	public function conversations_invite( $channel_id, $user_id, $action_data = null ) {
		$body = array(
			'action' => 'invite_conversation',
			'channel' => $channel_id,
			'users' => $user_id,
		);

		$body = apply_filters( 'uap_slack_conversations_invite', $body );

		return $this->slack_request( $body, $action_data );
	}

	/**
	 * Get a list of joinable Slack channels (public channels not already joined).
	 *
	 * @return array List of joinable channels.
	 */
	public function get_joinable_channel_options() {
		try {
			$body = array(
				'action' => 'get_joinable_conversations',
				'types'  => 'public_channel',
			);

			$response = $this->slack_request( $body );

			$options[] = array(
				'value' => '-1',
				'text'  => esc_html_x( 'Select a channel to join', 'Slack', 'uncanny-automator' ),
			);

			foreach ( $response['data']['channels'] as $channel ) {
				$options[] = array(
					'value' => $channel['id'],
					'text'  => '#' . $channel['name'],
				);
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
	 * Get a list of Slack users.
	 *
	 * @return array List of users.
	 */
	public function get_user_options() {

		$options = array();

		try {

			$response = $this->slack_request( 'get_users' );

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
	 * Makes a Slack API request with credential override.
	 * Temporary workaround until we start to utilize vault
	 *
	 * @param array|string $body The request body or action string
	 * @param array|null $action_data Action data for logging/retriggering
	 *
	 * @return array The API response
	 */
	public function slack_request( $body, $action_data = null ) {

		// If the body is a string, convert it to an array with the action key.
		if ( is_string( $body ) ) {
			$body = array( 'action' => $body );
		}

		// Set our credentials in a legacy manner.
		$args = array(
			'exclude_credentials' => true,
		);

		$credentials    = $this->helpers->get_credentials();
		$body['token']  = $credentials->access_token;
		$body['client'] = $credentials;

		// Use the abstract api_request method to make the request.
		return $this->api_request( $body, $action_data, $args );
	}

	/**
	 * Refresh Slack's bespoke credential keys on a log resend.
	 *
	 * Slack bakes the bare token plus the whole credentials object straight into
	 * the body (bypassing the base credential_request_key injection), so the
	 * default refresh would miss them. Re-resolve the current credentials and
	 * overwrite both keys so a resent request uses the connection that exists now.
	 *
	 * @param array $body The stored request body being replayed.
	 *
	 * @return array
	 */
	protected function replace_resend_credentials( $body ) {

		$credentials = $this->helpers->get_credentials();

		if ( array_key_exists( 'token', $body ) && isset( $credentials->access_token ) ) {
			$body['token'] = $credentials->access_token;
		}

		if ( array_key_exists( 'client', $body ) ) {
			$body['client'] = $credentials;
		}

		return parent::replace_resend_credentials( $body );
	}

	/**
	 * check_for_errors
	 *
	 * @param mixed $response
	 *
	 * @return void
	 */
	public function check_for_errors( $response, $args = array() ) {

		// The API class makes sure the [data] is always there.
		$data = $response['data'] ?? array();

		if ( ! empty( $data['ok'] ) && true === $data['ok'] ) {
			return;
		}

		$error = esc_html_x( 'Unknown Slack API error occurred.', 'Slack', 'uncanny-automator' );

		if ( ! empty( $data['error'] ) ) {
			$error = esc_html_x( 'Slack API returned an error:', 'Slack', 'uncanny-automator' ) . $data['error'];
		} elseif ( ! empty( $response['error'] ) && is_string( $response['error'] ) ) {
			// Platform exception path: Api_Server::maybe_throw_exception already
			// threw error.description, re-passed here as a string. Surface it
			// instead of the generic fallback.
			$error = $response['error'];
		}

		throw new \Exception( esc_html( $error ), absint( $response['statusCode'] ) );
	}
}
