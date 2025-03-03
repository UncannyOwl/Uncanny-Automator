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
			$data['user']            = $credentials['user'];
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
			$user        = $credentials['user'];
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
			'option_code'            => $option_code,
			'label'                  => _x( 'Channel', 'Discord', 'uncanny-automator' ),
			'input_type'             => 'select',
			'options'                => array(),
			'required'               => true,
			'supports_custom_value'  => false,
			'show_label_in_sentence' => true,
			'relevant_tokens'        => array(),
			'ajax'                   => array(
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
		$channels = $this->api()->get_server_channels( $server_id );
		$channel  = wp_list_filter( $channels, array( 'value' => $channel_id ) );

		if ( empty( $channel ) ) {
			throw new Exception( esc_html_x( 'Channel not found in the selected server', 'Discord', 'uncanny-automator' ) );
		}

		return $channel_id;
	}

	/**
	 * Get server members select config.
	 *
	 * @param string $option_code
	 * @param string $server_key
	 *
	 * @return array
	 */
	public function get_server_members_select_config( $option_code, $server_key ) {
		return array(
			'option_code'            => $option_code,
			'label'                  => _x( 'Member', 'Discord', 'uncanny-automator' ),
			'input_type'             => 'select',
			'options'                => array(),
			'required'               => true,
			'supports_custom_value'  => false,
			'show_label_in_sentence' => true,
			'relevant_tokens'        => array(),
			'ajax'                   => array(
				'endpoint'      => 'automator_discord_get_server_members',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( $server_key ),
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
	 * Get server members via Ajax for select.
	 *
	 * @return array
	 */
	public function get_server_members_ajax() {
		Automator()->utilities->verify_nonce();
		$server_id = $this->get_server_id_from_post();
		$members  = $this->api()->get_server_members( $server_id, $this->is_ajax_refresh() );

		wp_send_json( array(
			'success' => true,
			'options' => $members,
		) );
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
			'option_code'            => $option_code,
			'label'                  => _x( 'Role', 'Discord', 'uncanny-automator' ),
			'input_type'             => 'select',
			'options'                => array(),
			'required'               => true,
			'supports_custom_value'  => false,
			'show_label_in_sentence' => true,
			'relevant_tokens'        => array(),
			'ajax'                   => array(
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

		// Sanitize the message
		$message = sanitize_text_field( $parsed[ $meta_key ] );

		// Escape HTML entities
		$message = esc_html( $message );

		// Remove invalid Unicode characters
		$message = preg_replace( '/[^\x{0000}-\x{10FFFF}]/u', '', $message );

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