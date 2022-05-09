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

		// Load the settings page.
		require_once __DIR__ . '/../settings/settings-facebook.php';

		new Facebook_Settings( $this );

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

		$facebook_options_user  = get_option( self::OPTION_KEY, array() );
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

		$settings = array(
			'user' => array(
				'id'    => filter_input( INPUT_GET, 'fb_user_id', FILTER_SANITIZE_NUMBER_INT ),
				'token' => filter_input( INPUT_GET, 'fb_user_token', FILTER_SANITIZE_STRING ),
			),
		);

		$error_status = filter_input( INPUT_GET, 'status', FILTER_DEFAULT );

		if ( 'error' === $error_status ) {
			wp_safe_redirect( $this->get_settings_page_uri() . '&status=error' );
			exit;
		}

		// Only update the record when there is a valid user.
		if ( isset( $settings['user']['id'] ) && isset( $settings['user']['token'] ) ) {
			// Updates the option value to settings.
			update_option( self::OPTION_KEY, $settings );
			// Delete any settings left.
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
			delete_option( self::OPTION_KEY );
			delete_option( '_uncannyowl_facebook_pages_settings' );
			delete_transient( 'uo-fb-transient-user-connected' );
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

		if ( wp_verify_nonce( automator_filter_input( 'nonce', INPUT_POST ), 'uncanny_automator' ) ) {

			$existing_page_settings = get_option( '_uncannyowl_facebook_pages_settings' );

			$error_message = '';

			if ( false !== $existing_page_settings ) {

				if ( empty( $existing_page_settings ) ) {
					$error_message = esc_html__( 'There are no pages found.', 'uncanny-automator' );
				}

				wp_send_json(
					array(
						'status'        => 200,
						'message'       => __( 'Successful', 'automator-pro' ),
						'pages'         => $existing_page_settings,
						'error_message' => $error_message,
					)
				);

			} else {
				$pages = $this->fetch_pages_from_api();
				wp_send_json( $pages );
			}
		}

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
			update_option( '_uncannyowl_facebook_pages_settings', $pages );

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
	 * Retrieve the connected user.
	 *
	 * @return array|mixed
	 */
	public function get_user_connected() {

		$graph = get_option( self::OPTION_KEY );

		$response = array(
			'user_id' => 0,
			'picture' => false,
			'name'    => false,
		);

		if ( ! empty( $graph ) ) {
			$response = $this->transient_get_user_connected( $graph['user']['id'], $graph['user']['token'] );
		}

		return $response;
	}

	/**
	 * Retrieve the connected user from the transient.
	 *
	 * @param $user_id
	 * @param $token
	 *
	 * @return array|mixed
	 */
	private function transient_get_user_connected( $user_id, $token ) {

		$response = array(
			'user_id' => 0,
			'name'    => '',
			'picture' => '',
		);

		$transient_key = 'uo-fb-transient-user-connected';

		$transient_user_connected = get_transient( $transient_key );

		if ( false !== $transient_user_connected ) {
			return $transient_user_connected;
		}

		$request = wp_remote_get(
			'https://graph.facebook.com/v11.0/' . $user_id,
			array(
				'body' => array(
					'access_token' => $token,
					'fields'       => 'id,name,picture',
				),
			)
		);

		$graph_response = wp_remote_retrieve_body( $request );

		if ( ! is_wp_error( $graph_response ) ) {

			$graph_response = json_decode( $graph_response );

			$response['user_id'] = isset( $graph_response->id ) ? $graph_response->id : '';
			$response['name']    = isset( $graph_response->name ) ? $graph_response->name : '';
			$response['picture'] = isset( $graph_response->picture->data->url ) ? $graph_response->picture->data->url : '';

			set_transient( $transient_key, $response, DAY_IN_SECONDS );

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
	 * @return string
	 */
	public function get_user_page_access_token( $page_id ) {

		$options_pages = get_option( '_uncannyowl_facebook_pages_settings' );

		if ( ! empty( $options_pages ) ) {
			foreach ( $options_pages as $page ) {
				if ( $page['value'] === $page_id ) {
					return $page['page_access_token'];
				}
			}
		}

		throw new \Exception( __( 'Facebook is not connected', 'uncanny-automator' ) );
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
			'timeout'  => 10,
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
			'timeout'  => 10,
		);

		return Api_Server::api_call( $params );
	}

	public function check_for_errors( $response ) {

		if ( isset( $response['data']['error']['message'] ) ) {
			throw new \Exception( $response['data']['error']['message'], $response['statusCode'] );
		}
	}

	/**
	 * Method log_action_error
	 *
	 * @param $response
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 *
	 * @return void
	 */
	public function complete_with_error( $error_msg, $user_id, $action_data, $recipe_id ) {
		$action_data['do-nothing']           = true;
		$action_data['complete_with_errors'] = true;
		Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_msg );
	}


}
