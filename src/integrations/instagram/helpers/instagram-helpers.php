<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator;

/**
 * Class Instagram_Pro_Helpers
 *
 * @package Uncanny_Automator
 */
class Instagram_Helpers {


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

	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );

		$this->fb_endpoint_uri = AUTOMATOR_API_URL . 'v2/facebook';

		$this->wp_ajax_action = 'automator_integration_instagram_capture_token';

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

	public function automator_integration_instagram_capture_token_fetch_instagram_accounts() {

		if ( wp_verify_nonce( automator_filter_input( 'nonce', INPUT_POST ), 'uncanny_automator' ) ) {

			$options_fb_pages_key = '_uncannyowl_facebook_pages_settings';

			$page_id = automator_filter_input( 'page_id', INPUT_POST );

			$facebook_pages = get_option( $options_fb_pages_key );

			$access_token = '';

			foreach ( $facebook_pages as $page ) {
				if ( $page['value'] === $page_id ) {
					$access_token = $page['page_access_token'];
				}
			}

			$remote = wp_remote_post(
				$this->fb_endpoint_uri,
				array(
					'body' => array(
						'action'       => 'page-list-ig-account',
						'access_token' => $access_token,
						'page_id'      => $page_id,
					),
				)
			);

			if ( ! is_wp_error( $remote ) ) {

				$ig_response = json_decode( wp_remote_retrieve_body( $remote ) );

				foreach ( $facebook_pages as $key => $page ) {
					if ( $page['value'] === $page_id ) {
						if ( isset( $ig_response->data ) ) {
							$facebook_pages[ $key ]['ig_account'] = $ig_response->data;
						}
					}
				}

				// Update the option.
				update_option( $options_fb_pages_key, $facebook_pages );

				wp_send_json( $ig_response );
			}
		}

		die;

	}

	public function get_ig_accounts() {

		$ig_accounts      = array();
		$fb_options_pages = get_option( self::FB_OPTIONS_KEY );

		if ( is_array( $fb_options_pages ) ) {
			foreach ( $fb_options_pages as $page ) {
				if ( isset( $page['ig_account']->data ) && is_array( $page['ig_account']->data ) ) {
					foreach ( $page['ig_account']->data as $ig_account ) {
						$ig_accounts[ $page['value'] ] = $ig_account->username;
					}
				}
			}
		}

		return $ig_accounts;

	}

	public function get_user_page_connected_ig( $page_id = 0 ) {

		$options_pages = get_option( '_uncannyowl_facebook_pages_settings' );

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

		$remote = wp_remote_post(
			$this->fb_endpoint_uri,
			array(
				'body' => array(
					'action'       => 'list-user-pages',
					'access_token' => $settings['user']['token'],
				),
			)
		);

		$pages = array();

		if ( ! is_wp_error( $remote ) ) {

			$response = wp_remote_retrieve_body( $remote );

			$response = json_decode( $response );

			$status = isset( $response->response ) ? $response->response : '';

			$message = isset( $response->message ) ? $response->message : '';

			if ( 200 === $status ) {

				foreach ( $response->pages as $page ) {
					$pages[] = array(
						'value'             => $page->id,
						'text'              => $page->name,
						'tasks'             => $page->tasks,
						'page_access_token' => $page->page_access_token,
					);
				}

				$message = esc_html__( 'Pages are fetched successfully', 'automator-pro' );

				// Save the pages.
				update_option( '_uncannyowl_facebook_pages_settings', $pages );
			}
		} else {
			$message = $remote->get_error_message();
			$status  = 500;
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
				'post_type'          => 'uo-recipe',
				'page'               => 'uncanny-automator-config',
				'tab'                => 'premium-integrations',
				'integration'        => 'facebook-pages',
				'automator_minimal'  => filter_input( INPUT_GET, 'automator_minimal', FILTER_DEFAULT ),
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

}
