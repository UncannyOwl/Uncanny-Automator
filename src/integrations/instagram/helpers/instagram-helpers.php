<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator;

/**
 * Class Instagram_Pro_Helpers
 *
 * @package Uncanny_Automator
 */
class Instagram_Helpers {

	/**
	 * The API endpoint address. Instagram is using Facebook Endpoint.
	 *
	 * @var API_ENDPOINT The endpoint adress.
	 */
	const API_ENDPOINT = 'v2/facebook';

	/**
	 * Instagram Pro Helpers.
	 *
	 * @var Instagram_Pro_Helpers
	 */
	public $pro;

	/**
	 * The options.
	 *
	 * @var $options
	 */
	public $options = '';

	/**
	 * Load options.
	 *
	 * @var bool
	 */
	public $load_options;

	const FB_OPTIONS_KEY = '_uncannyowl_facebook_pages_settings';

	const OPTION_KEY = '_uncannyowl_instagram_settings';

	/**
	 * The wp_ajax callback method.
	 *
	 * @var string $wp_ajax_action
	 */
	public $wp_ajax_action = 'automator_integration_instagram_capture_token';

	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );

		// Add a fetch user pages action.
		add_action(
			"wp_ajax_{$this->wp_ajax_action}_fetch_user_pages",
			array(
				$this,
				sprintf( '%s_fetch_user_pages', $this->wp_ajax_action ),
			)
		);

		// Add get instagram action.
		add_action(
			"wp_ajax_{$this->wp_ajax_action}_fetch_instagram_accounts",
			array(
				$this,
				sprintf( '%s_fetch_instagram_accounts', $this->wp_ajax_action ),
			)
		);

		// Load the settings page.
		require_once __DIR__ . '/../settings/settings-instagram.php';

		new Instagram_Settings( $this );

	}

	/**
	 * Set Options
	 *
	 * @param Instagram_Helpers $options
	 */
	public function setOptions( Instagram_Helpers $options ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->options = $options;
	}

	/**
	 * Set Pro
	 *
	 * @param Instagram_Helpers $pro
	 */
	public function setPro( Instagram_Helpers $pro ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->pro = $pro;
	}

	/**
	 * Fetches the user pages from Automator api to user's website using his token.
	 *
	 * @return void Sends json formatted data to client.
	 */
	public function automator_integration_instagram_capture_token_fetch_user_pages() {

		if ( wp_verify_nonce( automator_filter_input( 'nonce', INPUT_POST ), 'uncanny_automator' ) ) {

			$existing_page_settings = get_option( self::FB_OPTIONS_KEY );

			if ( false !== $existing_page_settings ) {

				wp_send_json(
					array(
						'status'  => 200,
						'message' => __( 'Successful', 'automator-pro' ),
						'pages'   => $existing_page_settings,
					)
				);

			} else {

				$pages = $this->fetch_pages_from_api();

				wp_send_json( $pages );

			}
		}

	}

	/**
	 * Method automator_integration_instagram_capture_token_fetch_instagram_accounts.
	 *
	 * Callback method to fetch connected pages business instagram accounts. The response will include all the
	 * Instagram business account connected and then validate to check if it has permission or not during fetch.
	 * This request also update the Facebook settings to include the IG data.
	 *
	 * @return wp_json response.
	 */
	public function automator_integration_instagram_capture_token_fetch_instagram_accounts() {

		if ( wp_verify_nonce( automator_filter_input( 'nonce', INPUT_POST ), 'uncanny_automator' ) ) {

			$page_id = automator_filter_input( 'page_id', INPUT_POST );

			$facebook_pages = get_option( self::FB_OPTIONS_KEY );

			$access_token = '';

			foreach ( $facebook_pages as $page ) {

				if ( $page['value'] === $page_id ) {

					$access_token = $page['page_access_token'];

				}
			}

			try {

				$body = array(
					'action'       => 'page-list-ig-account',
					'access_token' => $access_token,
					'page_id'      => $page_id,
				);

				$ig_response = $this->api_request( $body, null );

				foreach ( $facebook_pages as $key => $page ) {

					if ( $page['value'] === $page_id ) {

						if ( isset( $ig_response['data'] ) ) {

							// Convert $ig_response['data'] to make sure existing integrations don't break.
							$facebook_pages[ $key ]['ig_account'] = json_decode( wp_json_encode( $ig_response['data'] ) );

							// Validate IG if the user has given sufficient permission during OAuth dialog.
							if ( isset( $ig_response['data']['data'][0] ) ) {

								$connection = $this->get_business_account_connection_data( $ig_response['data']['data'][0] );

								// Save ig_connection index.
								$facebook_pages[ $key ]['ig_connection'] = $connection;

								// Send response as 'connected'.
								$ig_response['data']['data'][0]['ig_connection'] = $connection;

							}
						}
					}
				}

				update_option( self::FB_OPTIONS_KEY, $facebook_pages );

				wp_send_json( $ig_response );

			} catch ( \Exception $e ) {

				wp_send_json(
					array(
						'status'  => 400,
						'message' => $e->getMessage(),
					)
				);

			}
		}

		die;

	}

	/**
	 * Method get_business_account_connection_data.
	 *
	 * Analyze the response to check if there is an associated business account.
	 *
	 * @return array The connection with is_connected and message keys.
	 */
	public function get_business_account_connection_data( $ig_response ) {

		$connection = array(
			'is_connected' => true,
			'message'      => '',
		);

		if ( empty( $ig_response['instagram_business_account'] ) ) {
			$connection = array(
				'is_connected' => false,
				'message'      => esc_html__( 'There was an error connecting to your Instagram account.  Please ensure you check the box next to the desired Instagram account during the authentication process.', 'uncanny-automator' ),
			);
		}

		return $connection;

	}

	public function get_ig_accounts() {

		$ig_accounts = array();

		$fb_options_pages = get_option( self::FB_OPTIONS_KEY );

		if ( is_array( $fb_options_pages ) ) {

			foreach ( $fb_options_pages as $page ) {

				if ( isset( $page['ig_account'] ) && ! empty( $page['ig_account'] ) ) {

					foreach ( $page['ig_account'] as $ig_account ) {

						$ig_account = (array) end( $ig_account );

						$ig_accounts[ $page['value'] ] = $ig_account['username'];

					}
				}
			}
		}

		return $ig_accounts;

	}

	public function get_user_page_connected_ig( $page_id = 0 ) {

		$options_pages = get_option( self::FB_OPTIONS_KEY );

		if ( ! empty( $options_pages ) ) {

			foreach ( $options_pages as $page ) {

				if ( $page['value'] === $page_id ) {

					return $page;

				}
			}
		}

		return '';

	}

	public function fetch_pages_from_api() {

		$settings = get_option( '_uncannyowl_facebook_settings' );

		$body = array(
			'action'       => 'list-user-pages',
			'access_token' => $settings['user']['token'],
		);

		$status = 200;

		try {

			$request = $this->api_request( $body, null );

			if ( 200 !== $request['statusCode'] ) {
				throw new \Exception(
					esc_html__( 'Error fetching pages.', 'uncanny-automator' ),
					absint( $request['statusCode'] ) // Pass the status code.
				);
			}

			if ( ! isset( $request['data']['data'] ) || empty( $request['data']['data'] ) ) {
				throw new \Exception(
					esc_html__( 'No data found.', 'uncanny-automator' ),
					404 // Invoke 404 status code.
				);
			}

			foreach ( $request['data']['data'] as $page ) {

				$pages[] = array(
					'value'             => $page['id'],
					'text'              => $page['name'],
					'tasks'             => $page['tasks'],
					'page_access_token' => $page['access_token'],
				);

			}

			$message = esc_html__( 'Pages are fetched successfully', 'automator-pro' );

			// Save the pages.
			update_option( '_uncannyowl_facebook_pages_settings', $pages );

		} catch ( \Exception $e ) {

			$message = $e->getMessage();

			$status = $e->getCode();

		}

		$response = array(
			'status'  => $status,
			'message' => $message,
			'pages'   => $pages,
		);

		return $response;

	}

	public function is_user_connected() {

		$settings = get_option( self::FB_OPTIONS_KEY );

		if ( ! $settings || empty( $settings ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Create and retrieve a Facebook Pages settings URI.
	 *
	 * @return string.
	 */
	public function get_facebook_pages_settings_url() {
		return add_query_arg(
			array(
				'post_type'                    => 'uo-recipe',
				'page'                         => 'uncanny-automator-config',
				'tab'                          => 'premium-integrations',
				'integration'                  => 'facebook-pages',
				'automator_minimal'            => filter_input( INPUT_GET, 'automator_minimal', FILTER_DEFAULT ),
				'automator_hide_settings_tabs' => filter_input( INPUT_GET, 'automator_hide_settings_tabs', FILTER_DEFAULT ),
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Create and retrieve a Facebook OAuth dialog URI.
	 *
	 * @return string.
	 */
	public function get_facebook_pages_oauth_dialog_uri() {
		return add_query_arg(
			array(
				'action'   => 'facebook_authorization_request',
				'nonce'    => wp_create_nonce( '_uncannyowl_facebook_settings' ),
				'user_url' => rawurlencode( admin_url( 'admin-ajax.php' ) . '?action=' . $this->wp_ajax_action ),
			),
			AUTOMATOR_API_URL . 'v2/facebook'
		);
	}

	/**
	 * Method api_request
	 *
	 * @param $params
	 *
	 * @return void
	 */
	public function api_request( $body = array(), $action = null ) {

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => $body,
			'action'   => $action,
			'timeout'  => 60, // Add generous timeout limit for slow Instagram endpoint.
		);

		$response = Api_Server::api_call( $params );

		return $response;

	}

}
