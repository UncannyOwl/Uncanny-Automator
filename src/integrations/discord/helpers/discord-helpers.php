<?php // phpcs:ignoreFile PHPCompatibility.Operators.NewOperators.t_coalesceFound
// phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Discord;

use Exception;
use WP_Error;
use Uncanny_Automator\Api_Server;
use Uncanny_Automator\Automator_Helpers_Recipe;

/**
 * Class Discord_Helpers
 *
 * @package Uncanny_Automator
 */
class Discord_Helpers {

	/**
	 * Settings tab id
	 *
	 * @var string|object
	 */
	public $settings_tab = 'discord';

	/**
	 * Credentials options key.
	 *
	 * @var string
	 */
	const CREDENTIALS = 'automator_discord_credentials';

	/**
	 * Servers options key.
	 * 
	 * @var string
	 */
	const SERVERS = 'automator_discord_servers';

	/**
	 * Server field action meta key.
	 *
	 * @var string
	 */
	const ACTION_SERVER_META_KEY = 'DISCORD_SERVER';

	/**
	 * Discord user mapping meta key.
	 *
	 * @var string
	 */
	const DISCORD_USER_MAPPING_META_KEY = 'automator_discord_member_id';

	/**
	 * Cache key for verified members.
	 *
	 * @var string
	 */
	const VERIFIED_MEMBERS_CACHE_KEY = 'automator_discord_verified_members';

	/**
	 * Get api instance.
	 * 
	 * @return Discord_Api
	 */
	public function api() {
		static $api = null;
		if ( null === $api ) {
			$api = new Discord_Api( $this );
		}
		return $api;
	}

	/**
	 * Is connected.
	 *
	 * @return bool
	 */
	public function is_connected() {
		try {
			$this->get_credentials();
			return true;
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Integration status.
	 *
	 * @return string
	 */
	public function integration_status() {
		return $this->is_connected() ? 'success' : '';
	}

	/**
	 * Get credentials.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_credentials() {

		$credentials = automator_get_option( self::CREDENTIALS, array() );

		if ( empty( $credentials['discord_id'] ) || empty( $credentials['vault_signature'] ) ) {
			throw new Exception( 'Discord is not connected' );
		}

		return $credentials;
	}

	/**
	 * Store credentials
	 *
	 * @param mixed $credentials
	 *
	 * @return int
	 */
	public function store_credentials( $credentials ) {

		$data = automator_get_option( self::CREDENTIALS, array() );

		// Saving bot credentials.
		if ( isset( $credentials['bot'] ) ) {
			$data[ $credentials['discord_id'] ] = array(
				'discord_id'      => $credentials['discord_id'],
				'vault_signature' => $credentials['vault_signature']
			);
			$this->update_server_connected_status( $credentials['discord_id'], time() );
		}

		// Saving user credentials.
		if ( isset( $credentials['user'] ) ) {
			// Encrypt the user data for Discord policy compliance
			$encrypted_user = $this->encrypt_data( $credentials['user'], $credentials['discord_id'], 'user' );
			
			$data['user']            = $encrypted_user;
			$data['discord_id']      = $credentials['discord_id'];
			$data['vault_signature'] = $credentials['vault_signature'];
		}

		automator_update_option( self::CREDENTIALS, $data );

		return 1;
	}

	/**
	 * Update server connected status.
	 * 
	 * @param string $server_id
	 * @param int    $timestamp - Unix timestamp or 0
	 * 
	 * @return void
	 */
	public function update_server_connected_status( $server_id, $timestamp ) {
		$servers = automator_get_option( self::SERVERS, array() );
		$servers[ $server_id ]['connected'] = $timestamp;
		automator_update_option( self::SERVERS, $servers, false );
	}

	/**
	 * Get server by id.
	 *
	 * @param string $server_id
	 *
	 * @return array
	 */
	public function get_server_by_id( $server_id ) {
		$servers = automator_get_option( self::SERVERS, array() );
		return $servers[ $server_id ] ?? array();
	}

	/**
	 * Remove credentials.
	 *
	 * @return void
	 */
	public function remove_credentials() {
		// Store servers data.
		$servers = automator_get_option( self::SERVERS, array() );

		// Loop through servers.
		foreach ( $servers as $server_id => $server ) {
			// Delete server members cache.
			$key = 'DISCORD_MEMBERS_' . $server_id;
			automator_delete_option( $key );

			// Skip if not connected.
			if ( ! isset( $server['connected'] ) || empty( $server['connected'] ) ) {
				continue;
			}

			// Delete connected Bot from server and vault.
			$this->disconnect( $server_id );
		}

		// Delete account vault.
		$this->disconnect( null );

		// Delete options.
		automator_delete_option( self::CREDENTIALS );
		automator_delete_option( self::SERVERS );

	}

	/**
	 * Disconnect ( server or account )
	 * If server ID is provided it will remove bot from the server.
	 * Vault details will be removed for either account or server.
	 * 
	 * @param string $server_id
	 * 
	 * @return void
	 */
	public function disconnect( $server_id = null ) {
		try {
			$this->api()->api_request( array('action'=> 'disconnect' ), null, $server_id );
		} catch ( Exception $e ) {
			// Do nothing
		}
	}

	/**
	 * Get user info.
	 *
	 * @return array
	 */
	public function get_user_info() {
		try {
			$credentials = $this->get_credentials();
			$user        = $this->decrypt_data( $credentials['user'], $credentials['discord_id'], 'user' );
			$user['id']  = $credentials['discord_id'];
			return $user;
		}
		catch ( Exception $e ) {
			return array();
		}
	}

	/**
	 * Get server UI select config.
	 *
	 * @return array
	 */
	public function get_server_select_config() {
		return array(
			'option_code'            => self::ACTION_SERVER_META_KEY,
			'label'                  => _x( 'Server', 'Discord', 'uncanny-automator' ),
			'input_type'             => 'select',
			'options'                => array(),
			'required'               => true,
			'supports_custom_value'  => false,
			'show_label_in_sentence' => false,
			'relevant_tokens'        => array(),
			'ajax'                   => array(
				'endpoint'      => 'automator_discord_get_servers',
				'event'         => 'on_load',
			)
		);
	}

	/**
	 * Get servers via Ajax for select.
	 *
	 * @return array
	 */
	public function get_servers_ajax() {
		Automator()->utilities->verify_nonce();
		$servers  = $this->api()->get_servers( $this->is_ajax_refresh() );
		$options = $this->format_select_results( $servers );

		// Add empty option if more than one server.
		if ( count( $servers ) > 1 ) {
			$empty = array(
				'value' => '',
				'text'  => _x( 'Select a server', 'Discord', 'uncanny-automator' ),
			);
			array_unshift( $options, $empty );
		}

		wp_send_json( array(
			'success' => true,
			'options' => $options,
		) );
	}

	/**
	 * Get server ID from parsed.
	 *
	 * @param  array $parsed
	 * @param  string $meta_key
	 * @return mixed
	 * @throws Exception
	 */
	public function get_server_id_from_parsed( $parsed, $meta_key ) {
		$required_error = esc_html_x( 'Server ID is required', 'Discord', 'uncanny-automator' );
		return $this->get_text_value_from_parsed( $parsed, $meta_key, $required_error );
	}

	/**
	 * Get server channel select config.
	 *
	 * @param string $option_code
	 * @param string $server_key
	 * @param bool $required
	 *
	 * @return array
	 */
	public function get_server_channel_select_config( $option_code, $server_key, $args = array() ) {
		$config = array(
			'option_code'              => $option_code,
			'label'                    => _x( 'Channel', 'Discord', 'uncanny-automator' ),
			'input_type'               => 'select',
			'options'                  => array(),
			'required'                 => true,
			'supports_custom_value'    => true,
			'custom_value_description' => _x( 'Enter a channel ID ( eg: 1317134385290947584 )', 'Discord', 'uncanny-automator' ),
			'show_label_in_sentence'   => true,
			'relevant_tokens'          => array(),
			'ajax'                     => array(
				'endpoint'      => 'automator_discord_get_server_channels',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( $server_key ),
			),
		);

		return ! empty( $args ) ? wp_parse_args( $args, $config ) : $config;
	}

	/**
	 * Get server channels via Ajax for select.
	 *
	 * @return array
	 */
	public function get_server_channels_ajax() {
		Automator()->utilities->verify_nonce();
		$server_id = $this->get_server_id_from_post();
		$channels = $this->api()->get_server_channels( $server_id, $this->is_ajax_refresh() );
		$empty    = array(
			'value' => '',
			'text'  => _x( 'Select a channel', 'Discord', 'uncanny-automator' ),
		);

		// Add empty option.
		array_unshift( $channels, $empty );

		wp_send_json( array(
			'success' => true,
			'options' => $channels,
		) );
	}

	/**
	 * Get channel ID from parsed.
	 *
	 * @param  array $parsed
	 * @param  string $meta_key
	 * @param  string $server_id
	 * 
	 * @return mixed
	 * @throws Exception
	 */
	public function get_channel_id_from_parsed( $parsed, $meta_key, $server_id ) {
		$required_error = esc_html_x( 'Channel is required', 'Discord', 'uncanny-automator' );
		$channel_id     =  $this->get_text_value_from_parsed( $parsed, $meta_key, $required_error );

		// Check if the channel is in the server.
		if ( ! $this->get_server_channel_by_id( $server_id, $channel_id ) ) {
			throw new Exception( esc_html_x( 'Channel not found in the selected server', 'Discord', 'uncanny-automator' ) );
		}

		return $channel_id;
	}

	/**
	 * Get channel name token value.
	 *
	 * @param string $channel_name
	 * @param string $channel_id
	 * @param string $server_id
	 *
	 * @return string
	 */
	public function get_channel_name_token_value( $channel_name, $channel_id, $server_id ) {
		// If custom value was not used, return the parsed channel name.
		if ( ! $this->is_token_custom_value_text( $channel_name ) ) {
			return $channel_name;
		}
		// Get channel from server.
		$channel = $this->get_server_channel_by_id( $server_id, $channel_id );
		// Return channel name or '-' if not found.
		return $channel ? $channel['text'] : '-';
	}

	/**
	 * Get server channel by ID.
	 *
	 * @param string $server_id
	 * @param string $channel_id
	 *
	 * @return array|false
	 */
	private function get_server_channel_by_id( $server_id, $channel_id ) {
		$channels = $this->api()->get_server_channels( $server_id );
		$channel  = array_values( wp_list_filter( $channels, array( 'value' => $channel_id ) ) );
		return ! empty( $channel ) ? $channel[0] : false;
	}

	/**
	 * Get server members select config.
	 *
	 * @param string $option_code
	 *
	 * @return array
	 */
	public function get_verified_members_select_config( $option_code ) {
		return array(
			'option_code'              => $option_code,
			'label'                    => _x( 'Member', 'Discord', 'uncanny-automator' ),
			'input_type'               => 'select',
			'options'                  => array(),
			'required'                 => true,
			'supports_custom_value'    => true,
			'custom_value_description' => _x( 'Enter a member ID ( snowflake eg: 1423695857943239309 )', 'Discord', 'uncanny-automator' ),
			'show_label_in_sentence'   => true,
			'relevant_tokens'          => array(),
			'description'              => _x( 'Members that authenticated their WP Account are listed here', 'Discord', 'uncanny-automator' ),
			'ajax'                     => array(
				'endpoint' => 'automator_discord_get_verified_members',
				'event'    => 'on_load',
			),
		);
	}

	/**
	 * Get member ID from parsed.
	 *
	 * @param array $parsed
	 * @param string $meta_key
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function get_member_id_from_parsed( $parsed, $meta_key ) {
		$required_error = esc_html_x( 'Member is required', 'Discord', 'uncanny-automator' );
		return $this->get_text_value_from_parsed( $parsed, $meta_key, $required_error );
	}

	/**
	 * Get verified members via Ajax for select.
	 *
	 * @return array
	 */
	public function get_verified_members_ajax() {
		Automator()->utilities->verify_nonce();
		
		// Use object cache unless refresh requested.
		$members = false;
		if ( ! $this->is_ajax_refresh() ) {
			$members = wp_cache_get( self::VERIFIED_MEMBERS_CACHE_KEY, 'automator' );
		}
		
		if ( false === $members ) {
			// Get WordPress users with Discord mapping.
			$users = get_users(
				array(
					'meta_key'     => self::DISCORD_USER_MAPPING_META_KEY,
					'meta_value'   => '',
					'meta_compare' => '!=',
				)
			);

			$members = array();
			foreach ( $users as $user ) {
				$discord_id = get_user_meta( $user->ID, self::DISCORD_USER_MAPPING_META_KEY, true );
				if ( ! empty( $discord_id ) ) {
					$members[] = array(
						'text'  => $user->display_name . ' (' . $user->user_email . ')',
						'value' => $discord_id,
					);
				}
			}
			
			// Cache the results for 5 minutes (300 seconds).
			wp_cache_set( self::VERIFIED_MEMBERS_CACHE_KEY, $members, 'automator', 300 );
		}

		wp_send_json(
			array(
				'success' => true,
				'options' => $members,
			)
		);
	}

	/**
	 * Get member username token value.
	 *
	 * @param string $member_id
	 *
	 * @return string
	 */
	public function get_member_username_token_value( $member_id ) {
		// Get user info from Discord API.
		$user = $this->api()->get_user_info( $member_id );
		
		if ( ! empty( $user ) && isset( $user['username'] ) ) {
			return $user['username'];
		}

		return '-';
	}

	/**
	 * Get server roles select config.
	 * 
	 * @param string $option_code
	 * @param string $server_key
	 * @param array $args
	 * 
	 * @return array
	 */
	public function get_server_roles_select_config( $option_code, $server_key, $args = array() ) {
		$config = array(
			'option_code'              => $option_code,
			'label'                    => _x( 'Role', 'Discord', 'uncanny-automator' ),
			'input_type'               => 'select',
			'options'                  => array(),
			'required'                 => true,
			'supports_custom_value'    => true,
			'custom_value_description' => _x( 'Enter a role ID ( eg: 1317134385290947584 )', 'Discord', 'uncanny-automator' ),
			'show_label_in_sentence'   => true,
			'relevant_tokens'          => array(),
			'ajax'                     => array(
				'endpoint'      => 'automator_discord_get_server_roles',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( $server_key ),
			),
		);
		return ! empty( $args ) ? wp_parse_args( $args, $config ) : $config;
	}

	/**
	 * Get server roles via Ajax for select.
	 *
	 * @return array
	 */
	public function get_server_roles_ajax() {
		Automator()->utilities->verify_nonce();
		$server_id = $this->get_server_id_from_post();
		$roles    = $this->api()->get_server_roles( $server_id, $this->is_ajax_refresh() );

		wp_send_json( array(
			'success' => true,
			'options' => $roles,
		) );
	}

	/**
	 * Get role ID from parsed.
	 *
	 * @param array $parsed
	 * @param string $meta_key
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function get_role_id_from_parsed( $parsed, $meta_key ) {
		$required_error = esc_html_x( 'Role is required', 'Discord', 'uncanny-automator' );
		return $this->get_text_value_from_parsed( $parsed, $meta_key, $required_error );
	}

	/**	
	 * Get role name token value.
	 *
	 * @param string $role_name
	 * @param int $role_id
	 * @param int $server_id
	 *
	 * @return string
	 */
	public function get_role_name_token_value( $role_name, $role_id, $server_id ) {
		// If custom value was not used, return the parsed role name.
		if ( ! $this->is_token_custom_value_text( $role_name ) ) {
			return $role_name;
		}

		// Check against the existing server list of roles.
		$roles = $this->api()->get_server_roles( $server_id, false );
		$role  = array_values( wp_list_filter( $roles, array( 'value' => $role_id ) ) );

		return $role[0]['text'] ?? '-';
	}

	/**
	 * Get allowed channel types via Ajax for select.
	 *
	 * @return array
	 */
	public function get_allowed_channel_types_ajax() {
		Automator()->utilities->verify_nonce();
		$server_id = $this->get_server_id_from_post();
		$ids      = $this->api()->get_allowed_channel_types_for_server( $server_id );

		// Filter allowed types.
		$types = $this->get_channel_types();
		$types = array_filter(
			$types,
			function( $type ) use ( $ids ) {
				return in_array( $type['value'], $ids );
			}
		);

		wp_send_json( array(
			'success' => true,
			'options' => $types,
		) );
	}

	/**
	 * Get channel types.
	 *
	 * @return array
	 */
	public function get_channel_types() {
		return array(
			array(
				'value' => 0,
				'text'  => _x( 'Text', 'Discord', 'uncanny-automator' ),
			),
			array(
				'value' => 2,
				'text'  => _x( 'Voice', 'Discord', 'uncanny-automator' ),
			),
			array(
				'value' => 4,
				'text'  => _x( 'Category', 'Discord', 'uncanny-automator' ),
			),
			array(
				'value' => 5,
				'text'  => _x( 'Announcement', 'Discord', 'uncanny-automator' ),
			),
			array(
				'value' => 13,
				'text'  => _x( 'Stage', 'Discord', 'uncanny-automator' ),
			),
			array(
				'value' => 15,
				'text'  => _x( 'Forum', 'Discord', 'uncanny-automator' ),
			),
			array(
				'value' => 16,
				'text'  => _x( 'Media', 'Discord', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Get message from parsed.
	 * 
	 * @param array $parsed
	 * @param string $meta_key
	 * 
	 * @return string
	 * @throws Exception
	 */
	public function get_message_from_parsed( $parsed, $meta_key ) {

		$required_error = esc_html_x( 'Message is required', 'Discord', 'uncanny-automator' );
		if ( ! isset( $parsed[ $meta_key ] ) ) {
			throw new Exception( esc_html( $required_error ) );
		}

		// Sanitize the message while preserving line breaks
		$message = sanitize_textarea_field( $parsed[ $meta_key ] );

		// Remove invalid Unicode characters while preserving line breaks
		$message = preg_replace( '/[^\x{0000}-\x{10FFFF}\n]/u', '', $message );

		if ( empty( $message ) ) {
			throw new Exception( esc_html( $required_error ) );
		}

		return $message;
	}

	/**
	 * Get meta value from parsed.
	 *
	 * @param array $parsed
	 * @param string $meta_key
	 * @param string $error
	 *
	 * @return string
	 * @throws Exception
	 */
	public function get_text_value_from_parsed( $parsed, $meta_key, $error ) {
		if ( ! isset( $parsed[ $meta_key ] ) ) {
			throw new Exception( esc_html( $error ) );
		}

		$value = sanitize_text_field( $parsed[ $meta_key ] );

		if ( empty( $value ) ) {
			throw new Exception( esc_html( $error ) );
		}

		return $value;
	}

	/**
	 * Get server ID from $_POST.
	 *
	 * @return string
	 */
	public function get_server_id_from_post() {
		$values = automator_filter_has_var( 'values', INPUT_POST ) 
			? automator_filter_input_array( 'values', INPUT_POST ) 
			: array();

		return isset( $values[ self::ACTION_SERVER_META_KEY ] ) 
			? sanitize_text_field( wp_unslash( $values[ self::ACTION_SERVER_META_KEY ] ) ) 
			: '';
	}

	/**
	 * Get bool value.
	 *
	 * @param mixed $value
	 *
	 * @return bool
	 */
	public function get_bool_value( $value ) {
		return filter_var( strtolower( $value ), FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Check if the request is an AJAX refresh.
	 *
	 * @return bool
	 */
	public function is_ajax_refresh() {
		$context = automator_filter_has_var( 'context', INPUT_POST ) ? automator_filter_input( 'context', INPUT_POST ) : '';
		return 'refresh-button' === $context;
	}

	/**
	 * Format select results.
	 *
	 * @param array $results
	 * @param string $value_key
	 * @param string $label_key
	 *
	 * @return array
	 */
	public function format_select_results( $results, $value_key = 'id', $label_key = 'name' ) {
		return array_values( array_map(
			function( $result ) use ( $value_key, $label_key ) {
				return array(
					'value' => $result[ $value_key ],
					'text'  => $result[ $label_key ],
				);
			},
			$results
		) );
	}

	/**
	 * Check if the value is a custom value text.
	 *
	 * @param string $string_to_check
	 *
	 * @return bool
	 */
	private function is_token_custom_value_text( $string_to_check ) {
		return $string_to_check === esc_attr__( 'Use a token/custom value', 'uncanny-automator' );
	}

	/**
	 * Get the mapped Discord member ID for a user.
	 *
	 * @param int $user_id
	 *
	 * @return string|false
	 */
	public function get_mapped_wp_user_discord_id( $user_id ) {
		$member_id = get_user_meta( $user_id, self::DISCORD_USER_MAPPING_META_KEY, true );
		return ! empty( $member_id ) ? $member_id : false;
	}

	/**
	 * Clear the verified members cache.
	 *
	 * @return bool
	 */
	public function clear_verified_members_cache() {
		return wp_cache_delete( self::VERIFIED_MEMBERS_CACHE_KEY, 'automator' );
	}

	/**
	 * Encrypt data for Discord policy compliance.
	 *
	 * @param array  $data
	 * @param int    $id
	 * @param string $type
	 *
	 * @return string
	 */
	public function encrypt_data( $data, $id, $type ) {
		// Serialize data and generate random IV.
		$serialized = serialize( $data );
		$iv         = random_bytes( 16 );
		
		// Create unique key using ID, salt, type, and IV
		$key = hash( 'sha256', $id . NONCE_SALT . $type . $iv );
		
		// XOR encrypt with repeating key (handles any data size)
		$encrypted = $serialized ^ str_repeat( $key, ceil( strlen( $serialized ) / strlen( $key ) ) );
		
		// Return IV + encrypted data as base64
		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypt data encrypted by encrypt_data().
	 *
	 * @param string $encrypted_data
	 * @param int    $id
	 * @param string $type
	 *
	 * @return array
	 */
	public function decrypt_data( $encrypted_data, $id, $type ) {
		// Handle empty or invalid input
		if ( empty( $encrypted_data ) ) {
			return array();
		}

		// If the data is already an array (unencrypted), return it directly
		if ( is_array( $encrypted_data ) ) {
			return $encrypted_data;
		}

		// Ensure we have a string for base64_decode
		if ( ! is_string( $encrypted_data ) ) {
			return array();
		}

		// Decode and validate minimum length (16 bytes for IV)
		$decoded = base64_decode( $encrypted_data );
		if ( false === $decoded || strlen( $decoded ) < 16 ) {
			return array();
		}

		// Extract IV and encrypted data
		$iv        = substr( $decoded, 0, 16 );
		$encrypted = substr( $decoded, 16 );
		
		// Recreate the same unique key used for encryption
		$key = hash( 'sha256', $id . NONCE_SALT . $type . $iv );
		
		// XOR decrypt with repeating key
		$decrypted = $encrypted ^ str_repeat( $key, ceil( strlen( $encrypted ) / strlen( $key ) ) );
		
		// Unserialize and return original data
		$data = unserialize( $decrypted );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Get class constant.
	 * 
	 * @param string $constant
	 * 
	 * @return mixed
	 */
	public function get_constant( $constant ) {
		return constant( 'self::' . $constant );
	}
}