<?php

namespace Uncanny_Automator\Integrations\Discord;

use Uncanny_Automator\App_Integrations\Api_Caller;
use Exception;

/**
 * Class Discord_Api
 *
 * @package Uncanny_Automator
 *
 * @property Discord_App_Helpers $helpers
 */
class Discord_Api_Caller extends Api_Caller {

	/**
	 * Actions that may run against the connected account instead of a server's bot grant.
	 *
	 * Every other action is server-scoped, so an action added without a server id fails
	 * closed rather than silently sending the account credential in the bot's place.
	 * 'disconnect' is dual-mode — it targets the account when called with a null server.
	 *
	 * @var string[]
	 */
	const ACCOUNT_SCOPED_ACTIONS = array( 'get_servers', 'get_user_info', 'disconnect' );

	////////////////////////////////////////////////////////////
	// Abstract override methods
	////////////////////////////////////////////////////////////

	/**
	 * Prepare credentials for use in API requests.
	 *
	 * @param array $credentials The raw credentials from options to prepare.
	 * @param array $args        Additional arguments that may be needed for preparation.
	 *
	 * @return array - The prepared credentials for user or Bot requests.
	 */
	public function prepare_request_credentials( $credentials, $args ) {

		// Set incoming credentials as all data.
		$data = $credentials;
		// Prepare fresh credentials for use in the request.
		$credentials = array();
		// Check if there is a server ID in the args.
		$server_id = $args['server_id'] ?? null;

		// If there is a server ID, validate and get the server credentials.
		if ( ! is_null( $server_id ) ) {
			// Check if the server ID is set.
			if ( ! isset( $data[ $server_id ] ) ) {
				throw new Exception( 'Server credentials are invalid' );
			}
			// Reset the data to the server specific credentials.
			$data = $data[ $server_id ];
			// Set the bot flag.
			$credentials['is_bot'] = true;
		}

		// Configure the vault ID and signature.
		$credentials['discord_id']      = $data['discord_id'];
		$credentials['vault_signature'] = $data['vault_signature'];

		// Return the prepared credentials.
		return wp_json_encode( $credentials );
	}

	/**
	 * Recover the server scope of a resent request from its stored body.
	 *
	 * Discord picks its credential per request — server-scoped actions carry a
	 * specific server's bot grant, the rest carry the connected account — and the
	 * server id travels in $args, which a replayed body does not record. A
	 * server-scoped action's stored credential names the server it went to, so
	 * take the scope from there; an unresolvable server throws in
	 * prepare_request_credentials(), which leaves the original credential in place.
	 *
	 * @param array $body The stored request body being replayed.
	 *
	 * @return array
	 */
	protected function resend_credential_args( $body ) {

		$action = $body['action'] ?? '';

		// Account-scoped actions re-resolve unscoped, as they were sent.
		if ( in_array( $action, self::ACCOUNT_SCOPED_ACTIONS, true ) ) {
			return array();
		}

		$stored = json_decode( (string) $body[ $this->get_credential_request_key() ], true );

		return array( 'server_id' => $stored['discord_id'] ?? '' );
	}

	/**
	 * Check for errors.
	 *
	 * @param array $response The response.
	 * @param array $args     The arguments.
	 *
	 * @return void
	 * @throws Exception If an error occurs
	 */
	public function check_for_errors( $response, $args = array() ) {

		// Get server_id from args if provided
		$server_id = isset( $args['server_id'] ) ? $args['server_id'] : null;

		// Check for error.
		if ( ! empty( $response['error'] ) ) {
			throw new Exception( esc_html( $response['error'] ) );
		}
		// Check for 400 level status codes.
		$status = isset( $response['statusCode'] ) ? $response['statusCode'] : 0;
		if ( $status >= 400 && $status < 500 ) {
			// Get message and code if available.
			if ( isset( $response['data'] ) && isset( $response['data']['message'] ) ) {
				$code = isset( $response['data']['code'] ) ? $response['data']['code'] : 0;
				// Generate a message.
				$message = ! empty( $code )
					? $response['data']['code'] . ': ' . $response['data']['message']
					: $response['data']['message'];

				// Check for additional error data.
				if ( isset( $response['data']['errors'] ) ) {
					$errors = $response['data']['errors'];
					// Loop through errors and append to message.
					foreach ( $errors as $error ) {
						// Loop through error types.
						foreach ( $error as $type => $error_data ) {
							// Loop through error data.
							foreach ( $error_data as $error_item ) {
								$message .= ' ';
								$code     = isset( $error_item['code'] ) ? $error_item['code'] : '';
								$message .= ! empty( $code ) ? $code . ': ' : '';
								$message .= $error_item['message'] ?? '';
							}
						}
					}
				}

				// Check for specific Permission related code.
				if ( 50013 === absint( $code ) && ! empty( $server_id ) ) {
					$message = sprintf(
						// translators: Missing Permissions error message %s is the link to the knowledge base article.
						esc_html_x( "Missing permissions: It looks like your bot doesn't have the required permissions to complete this action. To resolve please follow the steps outlined in [this guide](%s) and try again.", 'Discord', 'uncanny-automator' ),
						'https://automatorplugin.com/knowledge-base/troubleshooting-discord-permissions/'
					);
				}

				throw new Exception( esc_html( $message ) );
			}

			throw new Exception( 'Discord authorization error' );
		}
	}

	////////////////////////////////////////////////////////////
	// Integration specific methods
	////////////////////////////////////////////////////////////

	/**
	 * Makes a Discord API request with server context
	 *
	 * @param array|string $body The request body or action string
	 * @param array|null $action_data Action data for logging/retriggering
	 * @param string|null $server_id The Discord server ID to include in the request
	 *
	 * @return array The API response
	 * @throws Exception If a server-scoped action was called without a server
	 */
	public function discord_request( $body, $action_data = null, $server_id = null ) {

		$action = is_string( $body ) ? $body : ( $body['action'] ?? '' );

		// Without a server the account credential would go out in the bot's place.
		if ( is_null( $server_id ) && ! in_array( $action, self::ACCOUNT_SCOPED_ACTIONS, true ) ) {
			throw new Exception( esc_html( 'A Discord server is required for the ' . $action . ' request' ) );
		}

		$args = array(
			'server_id' => $server_id,
		);
		return $this->api_request( $body, $action_data, $args );
	}

	/**
	 * Get all servers for a connected user.
	 *
	 * @param  bool $refresh
	 *
	 * @return array
	 */
	public function get_servers( $refresh = false ) {
		// Get the servers from the cache.
		$key              = $this->helpers->get_const( 'SERVERS' );
		$existing_servers = automator_get_option( $key, array() );

		// If we have the servers cached, return them.
		if ( ! empty( $existing_servers ) && ! $refresh ) {
			return $existing_servers;
		}

		try {
			$response = $this->discord_request( 'get_servers' );
			$data     = $response['data'];
			$servers  = array();
			foreach ( $data as $server ) {
				$existing            = isset( $existing_servers[ $server['id'] ] ) ? $existing_servers[ $server['id'] ] : array();
				$server['connected'] = ! empty( $existing['connected'] ) ? $existing['connected'] : false;
				$server['channels']  = ! empty( $existing['channels'] ) ? $existing['channels'] : array();
				$server['roles']     = ! empty( $existing['roles'] ) ? $existing['roles'] : array();
				// Set allowed channel types.
				$server['allowed_channel_types'] = $this->helpers->set_allowed_channel_types( $server );
				$servers[ $server['id'] ]        = $server;
			}
			automator_update_option( $key, $servers, false );
			return $servers;
		} catch ( Exception $e ) {
			return array();
		}
	}

	/**
	 * Get server channels.
	 *
	 * @param int $server_id
	 * @param bool $refresh
	 */
	public function get_server_channels( $server_id, $refresh = false ) {

		if ( empty( $server_id ) ) {
			return array();
		}

		$key      = $this->helpers->get_const( 'SERVERS' );
		$servers  = automator_get_option( $key, array() );
		$channels = isset( $servers[ $server_id ] )
			? $servers[ $server_id ]['channels']
			: array();

		// If we have the channels cached, return them.
		if ( ! $refresh ) {
			if ( ! empty( $channels ) ) {
				return $channels;
			}
		}

		// Get the channels from the API.
		try {
			$response = $this->discord_request( 'get_server_channels', null, $server_id );
			if ( ! isset( $response['data'] ) ) {
				return array();
			}

			// Format and cache the results.
			$servers[ $server_id ]['channels'] = $this->helpers->format_select_results( $response['data'] );
			automator_update_option( $key, $servers, false );
			return $servers[ $server_id ]['channels'];
		} catch ( Exception $e ) {
			return array();
		}
	}

	/**
	 * Get server roles.
	 *
	 * @param int $server_id
	 * @param bool $refresh
	 */
	public function get_server_roles( $server_id, $refresh = false ) {

		if ( empty( $server_id ) ) {
			return array();
		}

		$key     = $this->helpers->get_const( 'SERVERS' );
		$servers = automator_get_option( $key, array() );
		$roles   = isset( $servers[ $server_id ] )
			? $servers[ $server_id ]['roles']
			: array();

		// If we have the roles cached, return them.
		if ( ! $refresh ) {
			if ( ! empty( $roles ) ) {
				return $roles;
			}
		}

		// Get the channels from the API.

		try {
			$response = $this->discord_request( 'get_server_roles', null, $server_id );
			if ( ! isset( $response['data'] ) ) {
				return array();
			}

			// Filter out managed roles ( bots )
			$roles = array_filter(
				$response['data'],
				function ( $role ) {
					return empty( $role['managed'] );
				}
			);

			// Format and cache the results.
			$servers[ $server_id ]['roles'] = $this->helpers->format_select_results( $roles );
			automator_update_option( $key, $servers, false );
			return $servers[ $server_id ]['roles'];
		} catch ( Exception $e ) {
			return array();
		}
	}

	/**
	 * Get user information.
	 *
	 * @param string $user_id
	 *
	 * @return array
	 */
	public function get_user_info( $user_id ) {
		if ( empty( $user_id ) ) {
			return array();
		}

		$body = array(
			'action'  => 'get_user_info',
			'user_id' => $user_id,
		);

		try {
			$response = $this->api_request( $body );
			if ( ! isset( $response['data'] ) ) {
				return array();
			}
			return $response['data'];
		} catch ( \Exception $e ) {
			return array();
		}
	}
}
