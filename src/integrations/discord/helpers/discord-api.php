<?php

namespace Uncanny_Automator\Integrations\Discord;

use Uncanny_Automator\Api_Server;

/**
 * Class Discord_Api
 *
 * @package Uncanny_Automator
 */
class Discord_Api {

	/**
	 * Helpers
	 *
	 * @var Discord_Helpers
	 */
	protected $helpers;

	/**
	 * The public API edge.
	 *
	 * @var string
	 */
	const API_ENDPOINT = 'v2/discord';

	/**
	 * __construct
	 *
	 * @param  mixed $helpers
	 * @return void
	 */
	public function __construct( $helpers ) {
		$this->helpers = $helpers;
	}

	/**
	 * API request.
	 *
	 * @param array $body
	 * @param array $action_data
	 * @param int $server_id
	 *
	 * @return array
	 */
	public function api_request( $body, $action_data = null, $server_id = null ) {

		// Append credentials to the body.
		$body['credentials'] = $this->get_api_request_credentials( $server_id );

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => $body,
			'action'   => $action_data,
		);

		$response = Api_Server::api_call( $params );

		$this->check_for_errors( $response, $server_id );

		return $response;
	}

	/**
	 * Get API request credentials.
	 *
	 * @param mixed null || int - $server_id
	 *
	 * @return string - JSON encoded credentials
	 * @throws Exception - If server credentials are invalid
	 */
	private function get_api_request_credentials( $server_id = null ) {

		// All credentials.
		$data        = $this->helpers->get_credentials();
		$credentials = array();

		// Server Bot credentials.
		if ( ! is_null( $server_id ) ) {

			if ( ! isset( $data[ $server_id ] ) ) {
				throw new \Exception( 'Server credentials are invalid' );
			}

			$data                  = $data[ $server_id ];
			$credentials['is_bot'] = true;
		}

		$credentials['discord_id']      = $data['discord_id'];
		$credentials['vault_signature'] = $data['vault_signature'];

		return wp_json_encode( $credentials );
	}

	/**
	 * Check for errors.
	 *
	 * @param  array $response
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function check_for_errors( $response, $server_id ) {

		// Check for error.
		if ( ! empty( $response['error'] ) ) {
			throw new \Exception( esc_html( $response['error'] ) );
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
								$message .= $error_item['message'];
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

				throw new \Exception( esc_html( $message ) );
			}

			throw new \Exception( 'Discord authorization error' );
		}
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
		$key              = $this->helpers->get_constant( 'SERVERS' );
		$existing_servers = automator_get_option( $key, array() );

		// If we have the servers cached, return them.
		if ( ! empty( $existing_servers ) && ! $refresh ) {
			return $existing_servers;
		}

		$body = array(
			'action' => 'get_servers',
		);

		try {
			$response = $this->api_request( $body );
			$data     = $response['data'];
			$servers  = array();
			foreach ( $data as $server ) {
				$existing            = isset( $existing_servers[ $server['id'] ] ) ? $existing_servers[ $server['id'] ] : array();
				$server['connected'] = ! empty( $existing['connected'] ) ? $existing['connected'] : false;
				$server['channels']  = ! empty( $existing['channels'] ) ? $existing['channels'] : array();
				$server['roles']     = ! empty( $existing['roles'] ) ? $existing['roles'] : array();
				// Set allowed channel types.
				$server['allowed_channel_types'] = $this->set_allowed_channel_types( $server );
				$servers[ $server['id'] ]        = $server;
			}
			automator_update_option( $key, $servers, false );
			return $servers;
		} catch ( \Exception $e ) {
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

		$key      = $this->helpers->get_constant( 'SERVERS' );
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
		$body = array(
			'action' => 'get_server_channels',
		);

		try {
			$response = $this->api_request( $body, null, $server_id );
			if ( ! isset( $response['data'] ) ) {
				return array();
			}

			// Format and cache the results.
			$servers[ $server_id ]['channels'] = $this->helpers->format_select_results( $response['data'] );
			automator_update_option( $key, $servers, false );
			return $servers[ $server_id ]['channels'];
		} catch ( \Exception $e ) {
			return array();
		}
	}

	/**
	 * Get server members.
	 *
	 * @param int $server_id
	 * @param bool $refresh
	 */
	public function get_server_members( $server_id, $refresh = false ) {
		if ( empty( $server_id ) ) {
			return array();
		}

		$key     = 'DISCORD_MEMBERS_' . $server_id;
		$members = automator_get_option( $key, array() );
		$members = ! empty( $members )
			? $this->helpers->decrypt_data( $members, $server_id, 'members' )
			: array();

		// If we have the members cached, return them.
		if ( ! $refresh ) {
			if ( ! empty( $members ) ) {
				return $members;
			}
		}

		// Get the members from the API.
		$body = array(
			'action' => 'get_server_members',
		);

		try {
			$response = $this->api_request( $body, null, $server_id );
			if ( ! isset( $response['data'] ) ) {
				return array();
			}
			if ( ! isset( $response['data']['members'] ) ) {
				return array();
			}

			// Encrypt the members data.
			$members           = $response['data']['members'];
			$encrypted_members = $this->helpers->encrypt_data( $members, $server_id, 'members' );
			automator_update_option( $key, $encrypted_members, false );

			return $members;
		} catch ( \Exception $e ) {
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

		$key     = $this->helpers->get_constant( 'SERVERS' );
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
		$body = array(
			'action' => 'get_server_roles',
		);

		try {
			$response = $this->api_request( $body, null, $server_id );
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
		} catch ( \Exception $e ) {
			return array();
		}
	}

	/**
	 * Get allowed channel types for a server.
	 *
	 * @param int $server_id
	 * @param bool $refresh
	 *
	 * @return array
	 */
	public function get_allowed_channel_types_for_server( $server_id, $refresh = false ) {
		$key = $this->helpers->get_constant( 'SERVERS' );

		// Get Servers data
		$servers = $refresh
			? $this->get_servers( true )
			: automator_get_option( $key, array() );

		$server = isset( $servers[ $server_id ] )
			? $servers[ $server_id ]
			: array();

		return isset( $server['allowed_channel_types'] )
			? $server['allowed_channel_types']
			: $this->set_allowed_channel_types( $server );
	}

	/**
	 * Set allowed channel types for a server.
	 *
	 * @param array $server
	 *
	 * @return array - Allowed channel type IDs.
	 */
	public function set_allowed_channel_types( $server ) {
		$features = isset( $server['features'] ) ? $server['features'] : array();
		$types    = array( 0, 2, 4 ); // Text, Voice, Category - available for all servers.

		// Bail if the server does not have the COMMUNITY feature.
		if ( ! in_array( 'COMMUNITY', $features, true ) ) {
			return $types;
		}

		// Additional permission required with channel ID.
		$config = array(
			'NEWS'            => 5, // Announcement
			'STAGE_INSTANCES' => 13, // Stage
			'FORUMS'          => 15, // Forum
			'MEDIA_CHANNEL'   => 16, // Media
		);

		foreach ( $config as $feature => $type ) {
			if ( in_array( $feature, $features, true ) ) {
				$types[] = $type;
			}
		}

		return $types;
	}

	/**
	 * Get endpoint.
	 *
	 * @return string
	 */
	public function get_api_endpoint() {
		return self::API_ENDPOINT;
	}
}
