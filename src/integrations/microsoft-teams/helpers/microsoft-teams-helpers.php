<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Microsoft_Teams;

use Uncanny_Automator\Api_Server;
use WP_REST_Response;
/**
 * Class Microsoft_Teams_Helpers
 *
 * @package Uncanny_Automator
 */
class Microsoft_Teams_Helpers {

	/**
	 * The helpers options object.
	 *
	 * @var string|object
	 */
	public $options = '';

	/**
	 * Settings tab id
	 *
	 * @var string|object
	 */
	public $settings_tab = 'microsoft-teams';

	/**
	 * The wp_options table key for selecting the integration options.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'automator_microsoft_teams_credentials';

	/**
	 * The public API edge.
	 *
	 * @var string
	 */
	const API_ENDPOINT = 'v2/microsoft-teams';

	/**
	 * The public API edge.
	 *
	 * @var string
	 */
	const WEBHOOK = '/microsoft-teams/';

	/**
	 * The nonce.
	 *
	 * @var string
	 */
	const NONCE = 'automator_microsoft_teams_api_authentication';

	/**
	 * The stored nonce for incoming API requests.
	 *
	 * @var string
	 */
	const API_SECRET = 'automator_microsoft_teams_api_secret';
	/**
	 * Get settings page url.
	 *
	 * @return mixed
	 */
	public function get_settings_page_url() {
		return add_query_arg(
			array(
				'post_type'   => 'uo-recipe',
				'page'        => 'uncanny-automator-config',
				'tab'         => 'premium-integrations',
				'integration' => $this->settings_tab,
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * rest_api_endpoint
	 *
	 * @return void
	 */
	public function rest_api_endpoint() {

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			self::WEBHOOK,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'process_rest_call' ),
				'permission_callback' => array( $this, 'validate_rest_call' ),
			)
		);
	}


	/**
	 * get_webhook_url
	 *
	 * @return string
	 */
	public static function get_webhook_url() {
		$url = get_rest_url() . AUTOMATOR_REST_API_END_POINT . self::WEBHOOK;
		return $url;
	}

	/**
	 * get_api_secret
	 *
	 * @return string
	 */
	public function get_api_secret() {

		$secret = automator_get_option( self::API_SECRET, false );

		if ( false === $secret ) {
			$secret = wp_create_nonce( self::NONCE );
			automator_update_option( self::API_SECRET, $secret );
		}

		return $secret;
	}

	/**
	 * validate_rest_call
	 *
	 * @param  mixed $request
	 * @return bool
	 */
	public function validate_rest_call( $request ) {

		$body = $request->get_body_params();

		if ( empty( $body['nonce'] ) ) {
			return false;
		}

		return password_verify( $this->get_api_secret(), $body['nonce'] );
	}

	/**
	 * process_rest_call
	 *
	 * @param  mixed $request
	 * @return WP_REST_Response
	 */
	public function process_rest_call( $request ) {

		$body = $request->get_body_params();

		$message = esc_html_x( 'Something went wrong while capturing the tokens', 'Microsoft Teams', 'uncanny-automator' );
		$code    = 400;

		try {

			if ( empty( $body['automator_api_message'] ) ) {
				throw new \Exception( esc_html_x( 'Automator message was not found', 'Microsoft Teams', 'uncanny-automator' ), 400 );
			}

			if ( 'authorization_request' === $body['action'] ) {
				$message = $this->capture_oauth_tokens( $body['automator_api_message'] );
				$code    = 201;
			}
		} catch ( \Exception $e ) {
			$message = $e->getMessage();
			$code    = $e->getCode();
		}

		return new WP_REST_Response( $message, $code );
	}

	/**
	 * get_client
	 *
	 * @return array $client
	 */
	public function get_client() {

		$client = (array) automator_get_option( self::OPTION_KEY, false );

		if ( empty( $client['access_token'] ) || empty( $client['refresh_token'] ) ) {
			throw new \Exception( 'Microsoft is not connected' );
		}

		$client = $this->maybe_refresh_token( $client );

		return $client;
	}

	/**
	 * integration_status
	 *
	 * @return string
	 */
	public function integration_status() {

		try {
			$is_user_connected = $this->get_client();
		} catch ( \Exception $e ) {
			$is_user_connected = false;
		}

		return $is_user_connected ? 'success' : '';
	}

	/**
	 * maybe_refresh_token
	 *
	 * @param  array $token
	 * @return array
	 */
	public function maybe_refresh_token( $client ) {

		$token_expires_at = absint( $client['issued_at'] ) + absint( $client['expires_in'] );

		// Refresh one minute before expiration
		$token_expires_at = $token_expires_at - 60;

		if ( $token_expires_at < time() ) {
			$client = $this->refresh_token( $client );
		}

		return $client;
	}

	/**
	 * refresh_token
	 *
	 * @param  array $token
	 * @return array
	 */
	public function refresh_token( $client ) {

			$body['client'] = wp_json_encode( $client );
			$body['action'] = 'refresh_token';

			$params = array(
				'endpoint' => self::API_ENDPOINT,
				'body'     => $body,
			);

			$response = Api_Server::api_call( $params );

			if ( ! empty( $response['data']['error'] ) && 'invalid_client' === $response['data']['error'] ) {
				$this->remove_credentials();
				throw new \Exception( esc_html_x( 'Microsoft Teams client is invalid. Please reconnect or contact support.', 'Microsoft Teams', 'uncanny-automator' ) );
			}

			$client = $response['data'];

			$this->store_token( $client );

			return $client;
	}

	/**
	 * store_token
	 *
	 * @param  array $client
	 * @return void
	 */
	public function store_token( $client ) {

		if ( empty( $client['access_token'] ) || empty( $client['refresh_token'] ) || empty( $client['expires_in'] ) ) {
			throw new \Exception( 'Missing credentials' );
		}

		$client['issued_at'] = time();

		automator_update_option( self::OPTION_KEY, $client );
	}

	/**
	 * get_auth_url
	 *
	 * @return string
	 */
	public function get_auth_url() {

		// Define the parameters of the URL
		$parameters = array(
			'nonce'        => password_hash( $this->get_api_secret(), PASSWORD_DEFAULT ),
			'action'       => 'authorization_request',
			'redirect_url' => rawurlencode( $this->get_settings_page_url() ),
			'webhook_url'  => $this->get_webhook_url(),
			'plugin_ver'   => AUTOMATOR_PLUGIN_VERSION,
		);

		// Return the URL
		return add_query_arg(
			$parameters,
			AUTOMATOR_API_URL . self::API_ENDPOINT
		);
	}

	/**
	 * Create and retrieve a disconnect url for Microsoft Teams.
	 *
	 * @return string The disconnect uri.
	 */
	public function get_disconnect_url() {

		return add_query_arg(
			array(
				'action' => 'automator_microsoft_teams_disconnect_user',
				'nonce'  => wp_create_nonce( self::NONCE ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * is_current_settings_tab
	 *
	 * @return bool
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

		if ( automator_filter_input( 'integration' ) !== $this->settings_tab ) {
			return;
		}

		return true;
	}

	/**
	 * capture_oauth_tokens
	 *
	 * @param  mixed $message
	 * @return string
	 */
	public function capture_oauth_tokens( $message ) {

		$credentials = json_decode( $message, true );

		$this->store_token( $credentials );

		$user = $this->get_user();

		if ( empty( $user['userPrincipalName'] ) ) {
			throw new \Exception( esc_html_x( 'Something went wrong', 'Microsoft Teams', 'uncanny-automator' ), 400 );
		}

		return 'ok';
	}

	/**
	 * disconnect
	 *
	 * @return void
	 */
	public function disconnect() {

		if ( wp_verify_nonce( filter_input( INPUT_GET, 'nonce', FILTER_UNSAFE_RAW ), self::NONCE ) ) {

			$this->remove_credentials();
		}

		wp_safe_redirect( $this->get_settings_page_url() );

		exit;
	}

	/**
	 * remove_credentials
	 *
	 * @return void
	 */
	public function remove_credentials() {
		// There is no need to revoke access because the token will expire in an hour
		automator_delete_option( self::OPTION_KEY );
		automator_delete_option( self::API_SECRET );
		delete_transient( 'automator_microsoft_teams_user' );
	}

	/**
	 * get_user
	 *
	 * @return array
	 */
	public function get_user() {

		$transient = 'automator_microsoft_teams_user';

		$user = get_transient( $transient );

		if ( ! empty( $user ) ) {
			return $user;
		}

		try {

			$body = array(
				'action' => 'user_info',
			);

			$response = $this->api_request( $body );

			if ( ! isset( $response['data']['userPrincipalName'] ) ) {
				throw new \Exception( 'Unable to fetch user info' );
			}

			$user = $response['data'];

			set_transient( $transient, $user, 60 * 60 * 24 );

		} catch ( \Exception $th ) {
			$user = array();
		}

		return $user;
	}

	/**
	 * api_request
	 *
	 * @param  mixed $body
	 * @param  mixed $action_data
	 * @return array
	 */
	public function api_request( $body, $action_data = null ) {

		$body['client'] = wp_json_encode( $this->get_client() );

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => $body,
			'action'   => $action_data,
		);

		$response = Api_Server::api_call( $params );

		return $response;
	}

	/**
	 * user_teams_options
	 *
	 * @return array
	 */
	public function user_teams_options() {

		try {

			$teams = $this->get_user_teams();

			if ( empty( $teams['value'] ) ) {
				throw new \Exception( esc_html_x( 'No teams were found', 'Microsoft Teams', 'uncanny-automator' ) );
			}

			foreach ( $teams['value'] as $team ) {
				$options[] = array(
					'value' => $team['id'],
					'text'  => $team['displayName'],
				);
			}
		} catch ( \Exception $e ) {
			$options[] = array(
				'value' => '',
				'text'  => $e->getMessage(),
			);
		}

		return $options;
	}

	/**
	 * get_user_teams
	 *
	 * @return array
	 */
	public function get_user_teams() {

		$body = array(
			'action' => 'user_teams',
		);

		$response = $this->api_request( $body );

		return $response['data'];
	}

	/**
	 * create_channel
	 *
	 * @param  array $channel
	 * @param  string $team_id
	 * @return array
	 */
	public function create_channel( $channel, $team_id, $action_data ) {

		$body = array(
			'action'  => 'create_channel',
			'channel' => wp_json_encode( $channel ),
			'team_id' => $team_id,
		);

		$response = $this->api_request( $body, $action_data );

		return $response['data'];
	}

	/**
	 * channel_type_options
	 *
	 * @return array
	 */
	public function channel_type_options() {

		$channel_types = array(
			'standard' => esc_html_x( 'Standard', 'Microsoft Teams', 'uncanny-automator' ),
			'private'  => esc_html_x( 'Private', 'Microsoft Teams', 'uncanny-automator' ),
			'shared'   => esc_html_x( 'Shared', 'Microsoft Teams', 'uncanny-automator' ),
		);

		$channel_types = apply_filters( 'automator_microsoft_teams_channel_types', $channel_types );

		return automator_array_as_options( $channel_types );
	}

	/**
	 * ajax_get_team_members_options
	 *
	 * @return array
	 */
	public function ajax_get_team_members_options() {

		Automator()->utilities->ajax_auth_check();

		try {

			$team = automator_filter_input( 'value', INPUT_POST );

			$members = $this->get_team_members( $team );

			if ( empty( $members['value'] ) ) {
				throw new \Exception( esc_html_x( 'No members were found', 'Microsoft Teams', 'uncanny-automator' ) );
			}

			foreach ( $members['value'] as $member ) {

				$me = $this->get_user();

				if ( $member['userId'] === $me['id'] ) {
					continue;
				}

				$options[] = array(
					'value' => $member['userId'],
					'text'  => $member['displayName'],
				);
			}
		} catch ( \Exception $e ) {
			$options[] = array(
				'value' => '',
				'text'  => $e->getMessage(),
			);
		}

		wp_send_json( $options );

		die();
	}

	/**
	 * get_team_members
	 *
	 * @param  string $team_id
	 * @return array
	 */
	public function get_team_members( $team_id ) {

		$body = array(
			'action'  => 'get_team_members',
			'team_id' => $team_id,
		);

		$response = $this->api_request( $body );

		return $response['data'];
	}

	/**
	 * member_message
	 *
	 * @param  string $member
	 * @param  string $message
	 * @return array
	 */
	public function member_message( $member, $message, $action_data ) {

		$body = array(
			'action'    => 'member_message',
			'member_id' => $member,
			'message'   => $message,
		);

		$response = $this->api_request( $body, $action_data );

		$this->check_for_errors( $response );

		return $response['data'];
	}

	/**
	 * teams_specializations_options
	 *
	 * @return array
	 */
	public function teams_specializations_options() {

		$specializations = array(
			'standard'                               => esc_html_x( 'Standard', 'Microsoft Teams', 'uncanny-automator' ),
			'educationClass'                         => esc_html_x( 'Education - Class Team', 'Microsoft Teams', 'uncanny-automator' ),
			'educationStaff'                         => esc_html_x( 'Education - Staff Team', 'Microsoft Teams', 'uncanny-automator' ),
			'educationProfessionalLearningCommunity' => esc_html_x( 'Education - Professional Learning Community', 'Microsoft Teams', 'uncanny-automator' ),
		);

		$specializations = apply_filters( 'automator_microsoft_teams_specializations', $specializations );

		return automator_array_as_options( $specializations );
	}

	/**
	 * create_team
	 *
	 * @param  array $team
	 * @return array
	 */
	public function create_team( $team, $action_data ) {

		$body = array(
			'action' => 'create_team',
			'team'   => wp_json_encode( $team ),
		);

		$response = $this->api_request( $body, $action_data );

		$this->check_for_errors( $response );

		return $response;
	}

	/**
	 * channel_message
	 *
	 * @param  string $channel
	 * @param  string $message
	 * @return array
	 */
	public function channel_message( $team_id, $channel_id, $message, $action_data ) {

		$body = array(
			'action'     => 'channel_message',
			'team_id'    => $team_id,
			'channel_id' => $channel_id,
			'message'    => $message,
		);

		$response = $this->api_request( $body, $action_data );

		$this->check_for_errors( $response );

		return $response['data'];
	}

	/**
	 * ajax_get_team_channels_options
	 *
	 * @return array
	 */
	public function ajax_get_team_channels_options() {

		Automator()->utilities->ajax_auth_check();

		try {

			$team = automator_filter_input( 'value', INPUT_POST );

			$channels = $this->get_team_channels( $team );

			if ( empty( $channels['value'] ) ) {
				throw new \Exception( esc_html_x( 'No channels were found', 'Microsoft Teams', 'uncanny-automator' ) );
			}

			foreach ( $channels['value'] as $channel ) {

				$options[] = array(
					'value' => $channel['id'],
					'text'  => $channel['displayName'],
				);
			}
		} catch ( \Exception $e ) {
			$options[] = array(
				'value' => '',
				'text'  => $e->getMessage(),
			);
		}

		wp_send_json( $options );

		die();
	}

	/**
	 * get_team_channels
	 *
	 * @return array
	 */
	public function get_team_channels( $team_id ) {

		$body = array(
			'action'  => 'team_channels',
			'team_id' => $team_id,
		);

		$response = $this->api_request( $body );

		return $response['data'];
	}

	/**
	 * check_for_errors
	 *
	 * @param  mixed $response
	 * @return void
	 */
	public function check_for_errors( $response ) {

		if ( isset( $response['data']['error'] ) ) {
			throw new \Exception( esc_html( $response['data']['error'] ), 400 );
		}
	}
}
