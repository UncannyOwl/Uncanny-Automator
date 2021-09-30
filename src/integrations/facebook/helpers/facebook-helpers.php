<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

/**
 * Class Facebook_Helpers
 * @package Uncanny_Automator
 */
class Facebook_Helpers {

	/**
	 * @var $options
	 */
	public $options = '';

	/**
	 * @var $settings_tab
	 */
	public $setting_tab;

	/**
	 * @var mixed $load_options
	 */
	public $load_options;

	/**
	 * @var $fb_endpoint_uri
	 */
	public $fb_endpoint_uri = '';

	const OPTION_KEY = '_uncannyowl_facebook_settings';

	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );

		$this->setting_tab = 'facebook_api';

		$this->fb_endpoint_uri = AUTOMATOR_API_URL . 'v2/facebook';

		$this->wp_ajax_action = 'automator_integration_facebook_capture_token';

		// Allow overwrite in wp-config.php.
		if ( DEFINED( 'UO_AUTOMATOR_DEV_FB_ENDPOINT_URL' ) ) {
			$this->fb_endpoint_uri = UO_AUTOMATOR_DEV_FB_ENDPOINT_URL;
		}

		// Adds new section to tab.
		add_filter( 'automator_settings_tabs', array( $this, 'add_facebook_api_settings' ), 15 );

		// Adds new button to settings tab.
		add_filter( 'automator_after_settings_extra_buttons', array( $this, 'facebook_connect_button_html' ), 10, 3 );

		// Capturing the OAuth Token and user id.
		add_action( "wp_ajax_{$this->wp_ajax_action}", array( $this, $this->wp_ajax_action ), 10 );

		// Add a disconnect button.
		add_action( "wp_ajax_{$this->wp_ajax_action}_disconnect", array(
			$this,
			sprintf( '%s_disconnect', $this->wp_ajax_action ),
		) );

		// Add a fetch user pages action.
		add_action( "wp_ajax_{$this->wp_ajax_action}_fetch_user_pages", array(
			$this,
			sprintf( '%s_fetch_user_pages', $this->wp_ajax_action ),
		) );

	}

	/**
	 * @param Facebook_Helpers $options
	 */
	public function setOptions( Facebook_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Facebook_Helpers $pro
	 */
	public function setPro( Facebook_Helpers $pro ) {
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

		$facebook_options_user  = get_option( '_uncannyowl_facebook_settings', array() );
		$facebook_options_pages = get_option( '_uncannyowl_facebook_pages_settings', array() );

		if ( ! empty( $facebook_options_user ) && ! empty( $facebook_options_pages ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Callback method to `automator_settings_tabs` that displays our Facebook Settings.
	 *
	 * @return $tabs All existing tabs.
	 */
	public function add_facebook_api_settings( $tabs ) {

		if ( $this->display_settings_tab() ) {

			$tabs[ $this->setting_tab ] = array(
				'name'           => __( 'Facebook', 'uncanny-automator' ),
				'title'          => __( 'Facebook account settings', 'uncanny-automator' ),
				'description'    => $this->get_tab_content(),
				'settings_field' => 'uap_automator_facebook_api_settings',
				'wp_nonce_field' => 'uap_automator_facebook_api_nonce',
				'save_btn_name'  => 'uap_automator_facebook_api_save',
				'save_btn_title' => __( 'Save settings', 'uncanny-automator' ),
				'fields'         => array(),
			);

		}

		return $tabs;

	}

	/**
	 * Callback method to `automator_after_settings_extra_buttons`. Adds button to our newly created tab.
	 *
	 * @return string The button html.
	 */
	public function facebook_connect_button_html( $content, $active, $tab ) {

		if ( $this->setting_tab === $active ) {
			$content = $this->get_fb_settings_content();
		}

		return $content;

	}

	/**
	 * The tab content.
	 *
	 * @return string the HTML content of the tab.
	 */
	public function get_tab_content() {

		ob_start();

		$message = '';

		$message .= $this->get_inline_style();

		$message .= sprintf(
			'<p>%s</p><p>%s</p>',
			__(
				'Connecting to Automator Facebook API requires 
                    that you connect your existing Facebook Account with Automator App. 
                    You must grant Automator App an access to the Facebook Pages and the Instagram Business Account (if you need Instagram later) 
                    that you manage in order for Automator to work properly.',
				'uncanny-automator'
			),
			__(
				"Click the 'Connect Facebook Pages' to get started and click on the 'Disconnect Facebook Pages' to disconnect your Facebook account.",
				'uncanny-automator'
			)
		);

		$tab = filter_input( 1, 'tab', 513 );

		if ( 'facebook_api' !== $tab ) {
			return $message;
		}

		$error_status = filter_input( INPUT_GET, 'status', FILTER_DEFAULT );

		if ( 'error' === $error_status ) {
			$message .= '<div class="error error-message">' . __( 'An error was encountered while authenticating. Permission is denied.', 'uncanny-automator' ) . '</div>';
		}

		if ( $this->is_user_connected() ) : ?>

			<?php $user = $this->get_user_connected(); ?>

			<?php if ( isset( $user['user_id'] ) && ! empty( isset( $user['user_id'] ) ) ) : ?>

                <h4>
					<?php esc_html_e( 'Facebook Account', 'automator-pro' ); ?>
                </h4>

                <div class="uo-fb-connected-account">

					<?php $fb_profile_link = '#'; // Dont show facebook profile. ?>

                    <a class="uo-fb-connected-account__user-card" href="<?php echo esc_url( $fb_profile_link ); ?>"
                       title="<?php echo esc_attr( $user['name'] ); ?>">
                        <img alt="<?php echo esc_attr( $user['name'] ); ?>" width="24"
                             src="<?php echo esc_url( $user['picture'] ); ?>"/>
						<?php echo esc_html( $user['name'] ); ?>
                    </a>

                </div>

			<?php endif; ?>

            <h4>
				<?php esc_html_e( 'Linked pages', 'uncanny-automator' ); ?>
            </h4>

            <div id="uo-user-fb-pages">
                <p>
                    <span class="dashicons dashicons-image-rotate uo-preloader-rotate"></span>
					<?php esc_html_e( 'Please wait while we fetch the Facebook Pages that you have linked to Automator App...', 'uncanny-automator' ); ?>
                </p>
            </div>

            <p>
                <span class="dashicons dashicons-info-outline"
                      style="font-size: 14px; position: relative; top: 3.25px;"></span>
				<?php esc_html_e( 'Click on the Change Account Settings button to re-connect your Facebook Account and Facebook Pages.', 'uncanny-automator' ); ?>
            </p>
            <a title="<?php esc_attr_e( 'Change Account Settings', 'uncanny-automator' ); ?>"
               href="<?php echo esc_url( $this->get_login_dialog_uri() ); ?>"
               class="uo-settings-btn uo-settings-btn--secondary">
				<?php esc_html_e( 'Change Account Settings', 'uncanny-automator' ); ?>
            </a>

			<?php
			$this->get_inline_js();
			$message .= ob_get_clean();

		endif;

		return $message;
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

		wp_safe_redirect( $this->get_settings_page_uri() );

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

		if ( wp_verify_nonce( filter_input( INPUT_GET, 'nonce', FILTER_DEFAULT ), self::OPTION_KEY ) ) {

			$existing_page_settings = get_option( '_uncannyowl_facebook_pages_settings' );

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

			$status = isset( $response->statusCode ) ? $response->statusCode : '';

			$message = isset( $response->data->error->message ) ? $response->data->error->message : '';

			if ( 200 === $status ) {

				foreach ( $response->data->data as $page ) {

					$pages[] = array(
						'value'             => $page->id,
						'text'              => $page->name,
						'tasks'             => $page->tasks,
						'page_access_token' => $page->access_token,
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

	public function get_settings_page_uri() {

		return add_query_arg(
			array(
				'post_type' => 'uo-recipe',
				'page'      => 'uncanny-automator-settings',
				'tab'       => 'facebook_api',
			),
			admin_url( 'edit.php' )
		);
	}

	public function is_user_connected() {

		$settings = get_option( self::OPTION_KEY );

		if ( ! $settings || empty( $settings ) ) {
			return false;
		}

		return true;
	}

	private function get_fb_settings_content() {
		ob_start();
		?>
		<?php if ( $this->is_user_connected() ) : ?>
            <a href="<?php echo esc_url( $this->get_disconnect_url() ); ?>"
               class="uo-settings-btn uo-settings-btn--error">
				<?php esc_html_e( 'Disconnect Facebook Pages' ); ?>
            </a>
		<?php else : ?>
            <a href="<?php echo esc_url( $this->get_login_dialog_uri() ); ?>"
               class="uo-settings-btn uo-settings-btn--secondary facebook-setting-btn">
                <span class="dashicons dashicons-facebook"></span>
				<?php esc_html_e( 'Connect Facebook Pages' ); ?>
            </a>
		<?php endif; ?>

		<?php
		return ob_get_clean();
	}

	public function get_disconnect_url() {

		return add_query_arg(
			array(
				'action' => $this->wp_ajax_action . '_disconnect',
				'nonce'  => wp_create_nonce( self::OPTION_KEY ),
			),
			admin_url( 'admin-ajax.php' )
		);

	}

	private function get_login_dialog_uri() {

		return add_query_arg(
			array(
				'action'   => 'facebook_authorization_request',
				'nonce'    => wp_create_nonce( self::OPTION_KEY ),
				'user_url' => rawurlencode( admin_url( 'admin-ajax.php' ) . '?action=' . $this->wp_ajax_action ),
			),
			$this->fb_endpoint_uri
		);

	}

	private function get_inline_style() {
		ob_start();
		?>
        <style>
            @keyframes uo-preloader-rotate {
                to {
                    transform: rotate(-360deg);
                }
            }

            .uo-preloader-rotate {
                animation: uo-preloader-rotate 0.75s linear infinite;
            }

            span.dashicons-image-rotate {
                color: #757575;
                font-size: 14px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            button[name="uap_automator_facebook_api_save"] {
                display: none;
            }

            .facebook-setting--user {
                margin-right: 15px;
            }

            .facebook-setting-btn:hover,
            .facebook-setting-btn {
                background-color: #2d88ff;
                color: #fff;
                border: 0 none;
                padding: 6px 15px 8px;
                box-shadow: none;
                font-weight: 600;
            }

            .facebook-setting-btn:hover {
                outline: 1px dashed #7bb4fd;
                outline-offset: 3px;
                outline-width: 1px;
            }

            .facebook-setting-btn .dashicons-facebook {
                position: relative;
                top: -1px;
                left: -6px;
                font-size: 24px;
                opacity: 0.95;
            }

            #uo-user-fb-pages > p.error {
                color: #e94b35;
            }

            span.uo-fb-pages-item-id {
                border-radius: 3px;
                font-size: 12px;
                padding: 2px;
                text-align: center;
                width: 115px;
                display: inline-block;
                border: 2px dashed #fff27d;
                margin-right: 10px;
                background: #fffce2;
            }

            span.uo-fb-pages-item-task {
                color: #20831c;
                position: relative;
                top: 2.5px;
            }

            #uo-user-fb-pages > ul > li > a {
                margin-right: 15px;
            }

            .uo-fb-connected-account .uo-fb-connected-account__user-card {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                max-width: 200px;
            }

            .uo-fb-connected-account .uo-fb-connected-account__user-card > img {
                margin-right: 10px;
                border-radius: 50px;
            }
        </style>
		<?php
		return ob_get_clean();
	}

	private function get_inline_js() {
		?>
        <script>
            jQuery(document).ready(function ($) {
                'use strict';
                var url = "<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>";
                $.ajax({
                    dataType: 'json',
                    url: url,
                    data: {
                        action: '<?php echo esc_html( "{$this->wp_ajax_action}_fetch_user_pages" ); ?>',
                        nonce: '<?php echo esc_html( wp_create_nonce( self::OPTION_KEY ) ); ?>'
                    },
                    success: function (response) {

                        if (200 === response.status) {

                            var $li = "";

                            $.each(response.pages, function (i, page) {

                                $li += '<li>';
                                $li += '<span class="uo-fb-pages-item-id">' + page.value + '</span>';
                                $li += '<a href="https://facebook.com/' + page.value + '" target="_blank">' + page.text + '</a>';

                                /*$.each( page.tasks, function(i, task){
									$li += '<span class="uo-fb-pages-item-task enabled">' + task + '</span>';
								});*/
                                $li += '<span class="uo-fb-pages-item-task enabled"><span class="dashicons dashicons-yes"></span></span>';

                                $li += '</li>';
                            });

                            $('#uo-user-fb-pages').html('<ul>' + $li + '</ul>');
                        } else {
                            $('#uo-user-fb-pages > p').html(response.message).addClass('error');
                        }

                    },
                    error: function (e, message) {
                        $('#uo-user-fb-pages > p').html(message).addClass('error');
                    }
                });
            });
        </script>
		<?php
	}

	private function get_user_connected() {

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

		return '';
	}

	public function get_endpoint_url() {

		return $this->fb_endpoint_uri;

	}

}
