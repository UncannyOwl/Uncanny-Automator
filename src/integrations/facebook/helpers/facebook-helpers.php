<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

/**
 * Class Facebook_Helpers
 *
 * @package Uncanny_Automator
 */
class Facebook_Helpers {

	/**
	 * The API endpoint address.
	 *
	 * @var API_ENDPOINT The endpoint adress.
	 */
	const API_ENDPOINT = 'v2/facebook';

	/**
	 * The options.
	 *
	 * @var $options
	 */
	public $options = '';

	/**
	 * Load options.
	 *
	 * @var mixed $load_options
	 */
	public $load_options;

	/**
	 * The endpoint uri.
	 *
	 * @var $fb_endpoint_uri
	 */
	public $fb_endpoint_uri = '';

	protected $setting_tab = '';

	protected $wp_ajax_action = '';

	protected $pro = null;

	/**
	 * The option key.
	 *
	 * @var string
	 */
	const OPTION_KEY = '_uncannyowl_facebook_settings';

	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );

		$this->setting_tab = 'facebook_api';

		$this->fb_endpoint_uri = AUTOMATOR_API_URL . 'v2/facebook';

		$this->wp_ajax_action = 'automator_integration_facebook_capture_token';

		// Capturing the OAuth Token and user id.
		add_action( "wp_ajax_{$this->wp_ajax_action}", array( $this, $this->wp_ajax_action ), 10 );

		// Add a disconnect button.
		add_action(
			"wp_ajax_{$this->wp_ajax_action}_disconnect",
			array(
				$this,
				sprintf( '%s_disconnect', $this->wp_ajax_action ),
			)
		);

		// Add a fetch user pages action.
		add_action(
			"wp_ajax_{$this->wp_ajax_action}_fetch_user_pages",
			array(
				$this,
				sprintf( '%s_fetch_user_pages', $this->wp_ajax_action ),
			)
		);

		// Defer loading of settings page to current_screen so we can check if its recipe page.
		add_action(
			'current_screen',
			function() {
				// Load the settings page.
				require_once __DIR__ . '/../settings/settings-facebook.php';
				new Facebook_Settings( $this );
			}
		);

		// Fixes the credentials that are sent into our API when the re-send button if clicked.
		add_filter( 'automator_facebook_api_call', array( $this, 'resend_with_current_credentials' ) );

	}

	/**
	 * Resend the credentials with current values stored in the db.
	 *
	 * @param array $params The action parameters.
	 *
	 * @return array The action parameters.
	 */
	public function resend_with_current_credentials( $params ) {

		// Bail when request is not coming from the re-send button.
		if ( empty( $params['resend'] ) ) {
			return $params;
		}

		// Bail when access token is empty.
		if ( empty( $params['body']['access_token'] ) || empty( $params['body']['page_id'] ) ) {
			return $params;
		}

		$access_token = $this->get_user_page_access_token( $params['body']['page_id'] );

		if ( ! empty( $access_token ) ) {
			$params['body']['access_token'] = $access_token;
		}

		return $params;

	}

	/**
	 * The facebook helper.
	 *
	 * @param Facebook_Helpers $options
	 */
	public function setOptions( Facebook_Helpers $options ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->options = $options;
	}

	/**
	 * The facebook helpers pro.
	 *
	 * @param Facebook_Helpers $pro
	 */
	public function setPro( Facebook_Helpers $pro ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->pro = $pro;
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

		return $this->has_connection_data();
	}

	/**
	 * Check if the 3rd-party integration has any connection api stored.
	 *
	 * @return boolean.
	 */
	public function has_connection_data() {

		$facebook_options_user = get_option( self::OPTION_KEY, array() );

		$facebook_options_pages = get_option( '_uncannyowl_facebook_pages_settings', array() );

		if ( ! empty( $facebook_options_user ) && ! empty( $facebook_options_pages ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Capture the user token and id.
	 */
	public function automator_integration_facebook_capture_token() {

		if ( ! current_user_can( 'manage_options' ) ) {

			wp_safe_redirect( $this->get_settings_page_uri() . '&status=error' );

			exit;

		}

		if ( ! wp_verify_nonce( automator_filter_input( 'state' ), self::OPTION_KEY ) ) {

			wp_safe_redirect( $this->get_settings_page_uri() . '&status=error' );

			exit;

		}

		$settings = array(
			'user' => array(
				'id'    => absint( automator_filter_input( 'fb_user_id' ) ),
				'token' => automator_filter_input( 'fb_user_token' ),
			),
		);

		$error_status = filter_input( INPUT_GET, 'status', FILTER_DEFAULT );

		if ( 'error' === $error_status ) {

			wp_safe_redirect( $this->get_settings_page_uri() . '&status=error' );

			exit;

		}

		// Only update the record when there is a valid user.
		if ( isset( $settings['user']['id'] ) && isset( $settings['user']['token'] ) ) {

			// Append user info to settings option.
			$settings['user-info'] = $this->get_user_information( $settings['user']['id'], $settings['user']['token'] );

			// Updates the option value to settings.
			update_option( self::OPTION_KEY, $settings, true );

			// Updates the option value to settings.
			update_option( self::OPTION_KEY, $settings, true );

			// Delete any user info left.
			delete_option( '_uncannyowl_facebook_pages_settings' );

		}

		wp_safe_redirect( $this->get_settings_page_uri() . '&connection=new' );

		exit;

	}

	/**
	 * Disconnects the user account from Facebook by deleting the access tokens.
	 * It actually doesn't disconnect the Facebook account but rather prevent it from accessing the API.
	 *
	 * @return void.
	 */
	public function automator_integration_facebook_capture_token_disconnect() {

		if ( wp_verify_nonce( filter_input( INPUT_GET, 'nonce', FILTER_DEFAULT ), self::OPTION_KEY ) ) {

			$this->remove_credentials();

			wp_safe_redirect( $this->get_settings_page_uri() );

			exit;

		}

		wp_die( esc_html__( 'Nonce Verification Failed', 'uncanny-automator' ) );

	}

	/**
	 * Fetches the user pages from Automator api to user's website using his token.
	 *
	 * @return void Sends json formatted data to client.
	 */
	public function automator_integration_facebook_capture_token_fetch_user_pages() {

		if ( ! wp_verify_nonce( automator_filter_input( 'nonce', INPUT_POST ), 'uncanny_automator' ) ) {

			wp_die( 'Invalid nonce', 403 );

		}

		$existing_pages = get_option( '_uncannyowl_facebook_pages_settings', false );

		if ( ! empty( $existing_pages ) ) {

			$response = array(
				'status'  => 200,
				'message' => '',
				'pages'   => $existing_pages,
			);

			wp_send_json( $response );

		}

		$pages = $this->fetch_pages_from_api();

		wp_send_json( $pages );

	}


	/**
	 * Retrieve the users pages.
	 *
	 * @return array
	 */
	public function fetch_pages_from_api() {

		$settings = get_option( self::OPTION_KEY );

		$message = '';

		$pages = array();

		$status = 200;

		try {

			// Throw error if access token is empty.
			if ( ! isset( $settings['user']['token'] ) ) {
				// Invoke 403 status code.
				throw new \Exception( esc_html__( 'Forbidden. User access token is required but empty.', 'uncanny-automator' ), 403 );
			}

			// Request from API.
			$request = $this->api_request_null(
				array(
					'action'       => 'list-user-pages',
					'access_token' => $settings['user']['token'],
				)
			);

			// Throw error if status code is invalid.
			if ( 200 !== $request['statusCode'] ) {
				throw new \Exception( esc_html__( 'Invalid status code.', 'uncanny-automator' ), $request['statusCode'] );
			}

			foreach ( $request['data']['data'] as $page ) {
				$pages[] = array(
					'value'             => $page['id'],
					'text'              => $page['name'],
					'tasks'             => $page['tasks'],
					'page_access_token' => $page['access_token'],
				);
			}

			$message = esc_html__( 'Pages are fetched successfully', 'uncanny-automator' );

			// Save the option.
			update_option( '_uncannyowl_facebook_pages_settings', $pages, true );

		} catch ( \Exception $e ) {

			// Assign the exception code as status code.
			$status = $e->getCode();

			// Assign the exception message as the message.
			$message = $e->getMessage();

		}

		$response = array(
			'status'  => $status,
			'message' => $message,
			'pages'   => $pages,
		);

		return $response;

	}

	/**
	 * Create and retrieve the settings page uri.
	 *
	 * @return string
	 */
	public function get_settings_page_uri() {

		return add_query_arg(
			array(
				'post_type'   => 'uo-recipe',
				'page'        => 'uncanny-automator-config',
				'tab'         => 'premium-integrations',
				'integration' => 'facebook-pages',
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Check if user is already connected or not.
	 *
	 * @return bool
	 */
	public function is_user_connected() {

		$settings = get_option( self::OPTION_KEY );

		if ( ! $settings || empty( $settings ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Create and retrieve the disconnect url.
	 *
	 * @return string
	 */
	public function get_disconnect_url() {

		return add_query_arg(
			array(
				'action' => $this->wp_ajax_action . '_disconnect',
				'nonce'  => wp_create_nonce( self::OPTION_KEY ),
			),
			admin_url( 'admin-ajax.php' )
		);

	}

	/**
	 * Create and retrieve a url that will be passed to API to create an OAuth url.
	 *
	 * @return string
	 */
	public function get_login_dialog_uri() {

		return add_query_arg(
			array(
				'action'   => 'facebook_authorization_request',
				'nonce'    => wp_create_nonce( self::OPTION_KEY ),
				'user_url' => rawurlencode( admin_url( 'admin-ajax.php' ) . '?action=' . $this->wp_ajax_action ),
			),
			$this->fb_endpoint_uri
		);

	}

	/**
	 * Get the user connected.
	 *
	 * Gets called on the settings page.
	 *
	 * @return array|mixed
	 */
	public function get_user_connected() {

		// Bail out if we dont need user request.
		if ( ! $this->is_user_request_needed() ) {
			return false;
		}

		return get_option( self::OPTION_KEY );

	}

	/**
	 * Retrieves user information.
	 *
	 * @param $user_id
	 * @param $token
	 *
	 * @return array|mixed
	 */
	public function get_user_information( $user_id, $token ) {

		try {

			$params = array(
				'action'       => 'get_user',
				'user_id'      => $user_id,
				'access_token' => $token,
			);

			$response = $this->api_request_null( $params );

			$response['user_id'] = isset( $response['data']['id'] ) ? $response['data']['id'] : '';
			$response['name']    = isset( $response['data']['name'] ) ? $response['data']['name'] : '';
			$response['picture'] = isset( $response['data']['picture']['data']['url'] )
				? $response['data']['picture']['data']['url'] :
				'';

		} catch ( \Exception $e ) {

			// Reset the connection if something is wrong.
			$this->remove_credentials();

			wp_safe_redirect( $this->get_settings_page_uri() . '&status=error' );

			die;

		}

		return $response;

	}


	/**
	 * Retrieve the users pages from wp_options table.
	 *
	 * @return array
	 */
	public function get_user_pages_from_options_table() {

		$pages = array();

		$options_pages = get_option( '_uncannyowl_facebook_pages_settings' );

		foreach ( $options_pages as $page ) {
			$pages[] = array(
				'value' => $page['value'],
				'text'  => $page['text'],
			);
		}

		return $pages;

	}

	/**
	 * Get the user page access tokens.
	 *
	 * @param string $page_id
	 *
	 * @return string
	 */
	public function get_user_page_access_token( $page_id ) {

		$options_pages = (array) get_option( '_uncannyowl_facebook_pages_settings', array() );

		if ( ! empty( $options_pages ) ) {
			foreach ( $options_pages as $page ) {
				// These are both strings.
				if ( $page['value'] === $page_id ) {
					return $page['page_access_token'];
				}
			}
		}

		// Details for debugging.
		$details = array(
			'selected_page_id' => $page_id,
			'options_pages'    => $this->redact_page_access_token( $options_pages ),
		);

		throw new \Exception(
			sprintf(
				/* translators: Error exception message */
				esc_html_x(
					'Unable to locate a valid access token for the specified Facebook Page. Please edit the recipe and ensure that you have selected the correct Facebook page, then resave the action. Additionally, you can attempt to reconnect the account by navigating to Automator > App Integrations > Facebook Pages. %s',
					'Facebook pages',
					'uncanny-automator'
				),
				wp_json_encode( $details )
			),
			400
		);
	}

	/**
	 * Redacts the page access token for privacy.
	 *
	 * @since 5.2
	 *
	 * @param array $options_pages
	 *
	 * @return mixed[] The options_pages value
	 */
	private function redact_page_access_token( $options_pages = array() ) {

		$redacted = array();

		if ( ! empty( $options_pages ) ) {
			foreach ( $options_pages as $page ) {
				$redacted[] = array(
					'value'             => isset( $page['value'] ) ? $page['value'] : null,
					'text'              => isset( $page['text'] ) ? $page['text'] : null,
					'page_access_token' => substr( $page['page_access_token'], 0, 10 ) . '-<redacted>',
				);
			}
		}

		return $redacted;
	}

	/**
	 * Get the endpoint url.
	 *
	 * @return string
	 */
	public function get_endpoint_url() {

		return $this->fb_endpoint_uri;

	}

	/**
	 * Method api_request
	 *
	 * @param $params
	 *
	 * @return void
	 */
	public function api_request( $page_id, $body, $action_data = null ) {

		$access_token = $this->get_user_page_access_token( $page_id );

		$body['access_token'] = $access_token;

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => $body,
			'action'   => $action_data,
			'timeout'  => 30,
		);

		$response = Api_Server::api_call( $params );

		$this->check_for_errors( $response );

		return $response;

	}

	/**
	 * Method api_request_null.
	 *
	 * @param $body
	 *
	 * @return void
	 */
	public function api_request_null( $body = array() ) {

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => $body,
			'action'   => null,
			'timeout'  => 30,
		);

		return Api_Server::api_call( $params );
	}

	public function check_for_errors( $response ) {

		if ( isset( $response['data']['error']['message'] ) ) {
			throw new \Exception( $response['data']['error']['message'], $response['statusCode'] );
		}
	}

	/**
	 * Wrapper for completing the action with err message.
	 *
	 * @param $response
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 *
	 * @return void
	 */
	public function complete_with_error( $error_msg, $user_id, $action_data, $recipe_id ) {
		$action_data['complete_with_errors'] = true;
		Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_msg );
	}

	/**
	 * Determines if user request is needed or not.
	 *
	 * @return bool True if user info request is needed. Returns false, otherwise.
	 */
	private function is_user_request_needed() {

		// Bail immediately if on front-end screen.
		if ( ! is_admin() ) {
			return false;
		}

		// Checks if on Facebook Groups settings (e.g. Premium Integrations).
		$is_facebook_pages_settings = 'uncanny-automator-config' === automator_filter_input( 'page' );

		// Determine if user is connecting Facebook groups.
		$is_capturing_token = wp_doing_ajax()
			&& automator_filter_input( 'action' ) === $this->wp_ajax_action;

		// Checks if from recipe edit page.
		$current_screen           = get_current_screen();
		$is_automator_recipe_page = isset( $current_screen->id )
			&& 'uo-recipe' === $current_screen->id
			&& 'edit' === automator_filter_input( 'action' );

		return $is_facebook_pages_settings || $is_capturing_token || $is_automator_recipe_page;

	}

	/**
	 * Removes credentials and attempts from options table.
	 *
	 * @return bool True. always.
	 */
	private function remove_credentials() {

		// Delete the credentials
		delete_option( self::OPTION_KEY );
		// Delete settings info.
		delete_option( '_uncannyowl_facebook_pages_settings' );

		return true;
	}

}
