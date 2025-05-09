<?php
namespace Uncanny_Automator\Integrations\Facebook_Lead_Ads\Helpers;

use Exception;
use Uncanny_Automator\Automator_Helpers_Recipe;
use Uncanny_Automator\Integrations\Facebook_Lead_Ads\Utilities\Client;
use Uncanny_Automator\Integrations\Facebook_Lead_Ads\Utilities\Connections_Manager;
use Uncanny_Automator\Integrations\Facebook_Lead_Ads\Utilities\Credentials_Manager;
use Uncanny_Automator\Integrations\Facebook_Lead_Ads\Utilities\Page_Connection_Verifier;
use Uncanny_Automator\Integrations\Facebook_Lead_Ads\Utilities\Rest_Api;

/**
 * Provides helper methods for the Facebook Lead Ads integration.
 *
 * This class includes methods for managing connections, handling forms, generating URLs,
 * and interacting with Facebook Lead Ads APIs.
 *
 * @package Uncanny_Automator\Integrations\Facebook_Lead_Ads\Helpers
 */
class Facebook_Lead_Ads_Helpers {

	/**
	 * The API endpoint address for Facebook Lead Ads.
	 *
	 * @var string
	 */
	const API_ENDPOINT = 'v2/facebook-lead-ads';

	/**
	 * The nonce for the connection process.
	 *
	 * @var string
	 */
	const CONNECTION_NONCE = 'automator_facebook_lead_ads_connection_nonce';

	/**
	 * Handles Facebook Lead Ads forms.
	 *
	 * Verifies the nonce, retrieves forms from Facebook, and sends a JSON response
	 * with the results or errors.
	 *
	 * @return void
	 */
	public static function forms_handler() {

		Automator()->utilities->verify_nonce();

		$client              = new Client();
		$credentials_manager = new Credentials_Manager();

		$field_values      = automator_filter_input_array( 'values', INPUT_POST );
		$page_id           = absint( $field_values['FB_LEAD_ADS_META'] ?? 0 );
		$page_access_token = $credentials_manager->get_page_access_token( $page_id );

		$forms = $client->get_forms( $page_id, $page_access_token );

		// Handle errors.
		if ( is_wp_error( $forms ) ) {
			wp_send_json(
				array(
					'success' => false,
					'error'   => 'An error has occurred while fetching the forms: ' . $forms->get_error_message(),
				)
			);
		}

		// Handle success.
		$forms   = (array) $forms['data']['data'] ?? array();
		$options = array();

		foreach ( $forms as $form ) {
			$options[] = array(
				'text'  => $form['name'],
				'value' => $form['id'],
			);
		}

		wp_send_json(
			array(
				'success' => true,
				'options' => $options,
			)
		);
	}

	/**
	 * Captures the user access token and connects to Facebook.
	 *
	 * Retrieves the token, fetches page access tokens, and updates connection settings.
	 *
	 * @return void
	 */
	public static function capture_token_handler() {

		$nonce = automator_filter_input( 'nonce' );
		$data  = automator_filter_input( 'automator_api_message' );

		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $nonce, self::CONNECTION_NONCE ) ) {
			wp_die( 'You are not authorized to access this page.' );
		}

		$credentials_manager = new Credentials_Manager();
		$connections_manager = self::create_connection_manager();

		try {

			$data = self::decode_token_data( $data );
			$args = self::build_connection_args( $data );
			$connections_manager->connect( $args );

			$page_access_tokens = self::fetch_page_access_tokens();

			if ( is_wp_error( $page_access_tokens ) ) {
				self::handle_page_access_token_error( $page_access_tokens );
			}

			$args['pages_access_tokens'] = $page_access_tokens['data']['data'] ?? array();

			$connections_manager->connect( $args );

			self::finalize_connection( $credentials_manager );

		} catch ( Exception $e ) {
			self::handle_connection_exception( $e );
		}
	}

	/**
	 * Decodes the token data from the API message.
	 *
	 * @param string $data The data to decode.
	 * @param string $nonce The nonce to verify.
	 *
	 * @return array Decoded token data.
	 */
	private static function decode_token_data( $data ) {
		return Automator_Helpers_Recipe::automator_api_decode_message( $data, wp_create_nonce( self::CONNECTION_NONCE ) );
	}

	/**
	 * Builds the connection arguments from the token data.
	 *
	 * @param array $data The decoded token data.
	 * @return array Connection arguments.
	 */
	private static function build_connection_args( array $data ) {

		return array(
			'user_access_token' => $data['user_token'] ?? '',
			'vault_signatures'  => $data['vault_signatures'] ?? '',
			'user'              => $data['user'] ?? '',
		);
	}

	/**
	 * Fetches page access tokens using Client.
	 *
	 * @return array|WP_Error Page access tokens or WP_Error on failure.
	 */
	private static function fetch_page_access_tokens() {
		return( new Client() )->get_page_access_tokens();
	}

	/**
	 * Handles an error in fetching page access tokens.
	 *
	 * @param WP_Error $error The error encountered.
	 * @return void
	 */
	private static function handle_page_access_token_error( $error ) {
		self::settings_redirect(
			array(
				'error_message' => $error->get_error_message(),
			)
		);
	}

	/**
	 * Finalizes the connection process by verifying credentials.
	 *
	 * @param Credentials_Manager $credentials_manager The credentials manager instance.
	 * @return void
	 */
	private static function finalize_connection( $credentials_manager ) {

		if ( ! is_a( $credentials_manager, Credentials_Manager::class ) ) {
			$error_message = 'missing_credentials_manager_class';
			self::settings_redirect(
				array(
					'error_message' => $error_message,
				)
			);
		}

		if ( $credentials_manager->has_user_credentials() && $credentials_manager->has_pages_credentials() ) {
			self::settings_redirect();
		}

		self::settings_redirect(
			array(
				'error_message' => 'missing_credentials',
			)
		);
	}

	/**
	 * Handles exceptions during the connection process.
	 *
	 * @param Exception $e The exception encountered.
	 * @return void
	 */
	private static function handle_connection_exception( Exception $e ) {

		self::settings_redirect(
			array(
				'error_message' => $e->getMessage(),
			)
		);
	}

	/**
	 * Checks the connection handler.
	 *
	 * Verifies connection details and sends a JSON response.
	 *
	 * @return void
	 */
	public static function check_connection_handler() {

		Automator()->utilities->verify_nonce();

		$data = (array) json_decode( file_get_contents( 'php://input', true ) );
		$url  = $data['site_url'] ?? '';

		$endpoint_url = ! empty( $url ) ? $url : rest_url( Rest_Api::REST_NAMESPACE . Rest_Api::VERIFICATION_REST_ROUTE );

		$connections_manager = self::create_connection_manager();
		$connection          = $connections_manager->verify_connection( $endpoint_url );

		wp_send_json( $connection );
	}

	/**
	 * Creates and returns a connection manager instance.
	 *
	 * @return Connections_Manager Connection manager instance.
	 */
	public static function create_connection_manager() {

		$credentials_manager = new Credentials_Manager();

		return new Connections_Manager(
			$credentials_manager,
			new Page_Connection_Verifier( $credentials_manager )
		);
	}

	/**
	 * Handles the disconnection process.
	 *
	 * Disconnects from Facebook Lead Ads and redirects to settings.
	 *
	 * @return void
	 */
	public static function disconnect_handler() {

		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( automator_filter_input( 'nonce' ), 'automator_facebook_lead_ads_disconnect_nonce' ) ) {
			wp_die( 'You are not authorized to access this page.' );
		}

		$connections_manager = self::create_connection_manager();
		$connections_manager->disconnect();

		self::settings_redirect();
	}

	/**
	 * Retrieves a list of pages.
	 *
	 * Returns an array of page options in the format {text, value}.
	 *
	 * @return array[] Array of pages with text and value keys.
	 */
	public static function get_pages() {

		$credentials_manager = new Credentials_Manager();

		if ( ! $credentials_manager->has_pages_credentials() ) {
			return array();
		}

		$pages = $credentials_manager->get_pages_credentials();

		return self::format_pages_options( $pages );
	}

	/**
	 * Formats a list of pages into options.
	 *
	 * Each page must have a 'name' and 'id' key. Pages missing these keys are skipped.
	 *
	 * @param array $pages List of pages to format.
	 * @return array[] Formatted pages as an array of options with text and value keys.
	 */
	private static function format_pages_options( array $pages ) {

		$options = array();

		foreach ( $pages as $page ) {
			if ( isset( $page['name'], $page['id'] ) ) {
				$options[] = array(
					'text'  => $page['name'],
					'value' => $page['id'],
				);
			}
		}

		return $options;
	}

	/**
	 * Redirects to the settings page with optional query arguments.
	 *
	 * @param array $query_arg Optional. Query arguments to append to the URL.
	 * @return void
	 */
	private static function settings_redirect( array $query_arg = array() ) {
		wp_safe_redirect( add_query_arg( $query_arg, self::get_settings_page_url() ) );
		exit;
	}

	/**
	 * Returns the URL for the settings page.
	 *
	 * @return string Settings page URL.
	 */
	public static function get_settings_page_url() {

		$query_args = array(
			'post_type'   => 'uo-recipe',
			'page'        => 'uncanny-automator-config',
			'tab'         => 'premium-integrations',
			'integration' => 'facebook_lead_ads',
		);

		return add_query_arg( $query_args, admin_url( 'edit.php' ) );
	}

	/**
	 * Generates the connection URL.
	 *
	 * @return string Connection URL.
	 */
	public static function get_connect_url() {

		$query_args = array(
			'action'       => 'authorization',
			'nonce'        => wp_create_nonce( 'automator_facebook_lead_ads_connection_nonce' ),
			'user_url'     => get_site_url(),
			'user_api_url' => Rest_Api::get_listener_endpoint_url(),
			'plugin_ver'   => AUTOMATOR_PLUGIN_VERSION,
		);

		$connection = self::create_connection_manager();

		if ( $connection->site_has_basic_auth() ) {
			$creds = wp_json_encode(
				array(
					'basic_auth_username' => AUTOMATOR_FACEBOOK_LEAD_ADS_BASIC_AUTH_USERNAME,
					'basic_auth_password' => AUTOMATOR_FACEBOOK_LEAD_ADS_BASIC_AUTH_PASSWORD,
				)
			);

			$query_args['basic_auth'] = rawurlencode(
				base64_encode( $creds ) // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			);
		}

		return add_query_arg( $query_args, AUTOMATOR_API_URL . self::API_ENDPOINT );
	}

	/**
	 * Generates the disconnection URL.
	 *
	 * @return string Disconnection URL.
	 */
	public static function get_disconnect_url() {

		$query_args = array(
			'action' => 'automator_integration_facebook_lead_ads_disconnect',
			'nonce'  => wp_create_nonce( 'automator_facebook_lead_ads_disconnect_nonce' ),
		);

		return add_query_arg( $query_args, admin_url( 'admin-ajax.php' ) );
	}

	/**
	 * Handles the check page connection process.
	 *
	 * @return void
	 */
	public static function check_page_connection_handler() {

		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( automator_filter_input( 'nonce' ), 'fbLeadsNonce' ) ) {
			wp_die( 'You are not authorized to access this page.' );
		}

		$connection = self::create_connection_manager();

		$status_code = 200;
		$bool_force  = false;

		$force      = automator_filter_input( 'force' );
		$bool_force = false;

		if ( 'true' === $force ) {
			$bool_force = true;
		}

		$status = $connection->verify_page_connection(
			automator_filter_input( 'page_id' ),
			$bool_force
		);

		if ( is_wp_error( $status ) ) {
			$status_code = 400;
			$status      = $status->get_error_message();
		}

		wp_send_json(
			array(
				'status' => $status,
			),
			$status_code
		);
	}
}
