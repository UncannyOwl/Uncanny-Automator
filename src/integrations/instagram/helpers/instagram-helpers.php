<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator;

/**
 * Class Instagram_Pro_Helpers
 *
 * @package Uncanny_Automator
 */
class Instagram_Helpers {


	/**
	 * @var Instagram_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var $options
	 */
	public $options = '';

	/**
	 * @var $settings_tab
	 */
	public $setting_tab;

	/**
	 * @var bool
	 */
	public $load_options;

	const FB_OPTIONS_KEY = '_uncannyowl_facebook_pages_settings';

	const OPTION_KEY = '_uncannyowl_instagram_settings';

	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );

		$this->setting_tab = 'instagram_api';

		$this->fb_endpoint_uri = AUTOMATOR_API_URL . 'v2/facebook';

		$this->wp_ajax_action = 'automator_integration_instagram_capture_token';

		// Allow overwrite in wp-config.php.
		if ( DEFINED( 'UO_AUTOMATOR_DEV_FB_ENDPOINT_URL' ) ) {
			$this->fb_endpoint_uri = UO_AUTOMATOR_DEV_FB_ENDPOINT_URL;
		}

		// Adds new section to tab.
		add_filter( 'automator_settings_tabs', array( $this, 'add_instagram_api_settings' ), 15 );

		// Add a fetch user pages action.
		add_action( "wp_ajax_{$this->wp_ajax_action}_fetch_user_pages", array(
			$this,
			sprintf( '%s_fetch_user_pages', $this->wp_ajax_action ),
		) );

		// Add get instagram action.
		add_action( "wp_ajax_{$this->wp_ajax_action}_fetch_instagram_accounts", array(
			$this,
			sprintf( '%s_fetch_instagram_accounts', $this->wp_ajax_action ),
		) );

	}

	/**
	 * @param Instagram_Helpers $options
	 */
	public function setOptions( Instagram_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Instagram_Helpers $pro
	 */
	public function setPro( Instagram_Helpers $pro ) {
		$this->pro = $pro;
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
	public function add_instagram_api_settings( $tabs ) {

		if ( $this->display_settings_tab() ) {

			$tabs[ $this->setting_tab ] = array(
				'name'           => __( 'Instagram', 'uncanny-automator' ),
				'title'          => __( 'Instagram account settings', 'uncanny-automator' ),
				'description'    => $this->get_tab_content(),
				'settings_field' => 'uap_automator_instagram_api_settings',
				'wp_nonce_field' => 'uap_automator_instagram_api_nonce',
				'save_btn_name'  => 'uap_automator_instagram_api_save',
				'save_btn_title' => __( 'Save settings', 'uncanny-automator' ),
				'fields'         => array(),
			);

		}

		return $tabs;

	}

	public function get_tab_content() {

		ob_start();

		$message = $this->get_inline_style();

		$tab = filter_input( 1, 'tab', 513 );

		if ( 'instagram_api' !== $tab ) {
			return $message;
		}

		if ( $this->is_user_connected() ) : ?>

			<?php
			$message .= sprintf(
				'<p class="uo-ig-alert">%s</p><p>%s</p>',
				__( 'You have successfully linked your Facebook Pages with Automator App.', 'uncanny-automator' ),
				__(
					'Click on the refresh icon on each corresponding Facebook Page that you have previously linked 
						to the Automator app to get the associated Instagram business account.',
					'uncanny-automator'
				)
			);
			?>

            <h4><?php esc_html_e( 'Facebook pages', 'uncanny-automator' ); ?></h4>

            <div id="uo-user-ig-pages">
                <p>
                    <span class="dashicons dashicons-image-rotate uo-preloader-rotate"></span>
					<?php esc_html_e( 'Please wait while we fetch the Facebook Pages that you have linked to Automator App...', 'uncanny-automator' ); ?>
                </p>
            </div>

			<?php
			$this->get_inline_js();

		else :

			$message .= sprintf(
				'<p class="uo-ig-alert--error">%s</p><p>%s</p>',
				__( 'Facebook Pages is not linked to the Automator App.', 'uncanny-automator' ),
				__(
					'Instagram requires a Professional or Business Account 
					connected to your Facebook Page to work. Go to the Facebook settings tab to link your Facebook pages with the Automator App.',
					'uncanny-automator'
				)
			);

			?>
			<?php
			$fb_settings_uri = add_query_arg(
				array(
					'post_type'          => 'uo-recipe',
					'page'               => 'uncanny-automator-settings',
					'tab'                => 'facebook_api',
					'minimal'            => filter_input( INPUT_GET, 'minimal', FILTER_DEFAULT ),
					'hide_settings_tabs' => filter_input( INPUT_GET, 'hide_settings_tabs', FILTER_DEFAULT ),
				),
				admin_url( 'edit.php' )
			);
			?>
            <p>
                <a
                        href="<?php echo esc_url( $fb_settings_uri ); ?>"
                        title="<?php esc_attr_e( 'Go to Facebook settings tab', 'uncanny-automator' ); ?>"
                        class="uo-settings-btn uo-settings-btn--primary">
					<?php esc_html_e( 'Go to Facebook settings tab', 'uncanny-automator' ); ?>
                </a>
            </p>
		<?php

		endif;

		$message .= ob_get_clean();

		return $message;
	}


	/**
	 * Fetches the user pages from Automator api to user's website using his token.
	 *
	 * @return void Sends json formatted data to client.
	 */
	public function automator_integration_instagram_capture_token_fetch_user_pages() {

		if ( wp_verify_nonce( filter_input( INPUT_GET, 'nonce', FILTER_DEFAULT ), self::OPTION_KEY ) ) {

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

		if ( wp_verify_nonce( filter_input( INPUT_GET, 'nonce', FILTER_DEFAULT ), self::OPTION_KEY ) ) {

			$options_fb_pages_key = '_uncannyowl_facebook_pages_settings';

			$page_id = filter_input( INPUT_GET, 'page_id', FILTER_DEFAULT );

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
						$facebook_pages[ $key ]['ig_account'] = $ig_response->data;
					}
				}

				// Update the option.
				update_option( $options_fb_pages_key, $facebook_pages );

				wp_send_json( $ig_response );
			}
		}

		die;

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

	private function get_inline_style() {
		ob_start();
		?>
        <style>

            .uo-ig-alert,
            .uo-ig-alert--error {
                padding: 10px 20px;
                background: #f9f9f9;
                border-left: 2px solid #0790e8;
            }

            .uo-ig-alert--error {
                border-left-color: #e94b35;
            }

            @keyframes uo-preloader-rotate {
                to {
                    transform: rotate(-360deg);
                }
            }

            .uo-preloader-rotate {
                animation: uo-preloader-rotate 0.75s linear infinite;
            }

            .uo-ig-account-connected-item {
                display: flex;
                align-items: center;
                font-weight: 600;
                background: #fffce2;
            }

            .uo-ig-account-connected-item span {
                padding: 0 7.5px;
            }

            .uo-settings-content-footer,
            button[name="uap_automator_instagram_api_save"] {
                display: none;
            }

            #uo-user-ig-pages > p.error {
                color: #e94b35;
            }

            span.uo-ig-pages-item-id {
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

            span.uo-ig-pages-item-task {
                color: #20831c;
                position: relative;
                top: 2.5px;
            }

            #uo-user-ig-pages > ul > li > a {
                margin-right: 15px;
            }

            #uo-user-ig-pages > ul > li {
                display: flex;
                align-items: center;
            }

            #uo-user-ig-pages > ul > li > a.uo-ig-pages-item-title {
                display: block;
                width: 200px;
            }

            .uo-ig-account-connected {
                width: 265px;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }

            .uo-ig-account-connected a:active,
            .uo-ig-account-connected a:focus {
                outline: none;
                box-shadow: none;
            }

            span.dashicons-image-rotate {
                color: #757575;
                font-size: 14px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
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

                var uo_get_ig_account_card = function (user_id, picture, username) {

                    var ig_account_html = '<div data-user-id="' + user_id + '" class="uo-ig-account-connected-item">';
                    ig_account_html += '<img onerror="jQuery(this).hide();" width="32" src="' + picture + '" />';
                    ig_account_html += '<span>' + username + '</span>';
                    ig_account_html += '</div>';

                    return ig_account_html;

                }

                var uo_load_facebook_pages = function () {

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
                                    console.log(page);
                                    $li += '<li>';
                                    $li += '<span class="uo-ig-pages-item-id">' + page.value + '</span>';
                                    $li += '<a class="uo-ig-pages-item-title" href="https://facebook.com/' + page.value + '" target="_blank">' + page.text + '</a>';
                                    $li += '<div class="uo-ig-account-connected">';
                                    if (page.ig_account) {
                                        $.each(page.ig_account.data, function (i, ig_account) {
                                            if (ig_account) {
                                                $li += uo_get_ig_account_card(ig_account.id, ig_account.profile_pic, ig_account.username);
                                            }
                                        });
                                    } else {
                                        $li += '<?php esc_html_e( 'No Instagram accounts connected', 'uncanny-automator' ); ?>';
                                    }
                                    $li += '&nbsp; <a data-page-id=' + page.value + ' class="uo-ig-pages-item-btn uo-ig-pages-item-get-instagram-accounts-btn" href="#">' + '<span class="dashicons dashicons-image-rotate"></span>' + '</a>';
                                    $li += '</div>';
                                    $li += '</li>';

                                });

                                $('#uo-user-ig-pages').html('<ul>' + $li + '</ul>');

                            } else {
                                $('#uo-user-ig-pages > p').html(response.message).addClass('error');
                            }

                        },
                        error: function (xhr, status, error) {
                            $('#uo-user-ig-pages > p').html(status + ': ' + error).addClass('error');
                        }
                    });
                }

                uo_load_facebook_pages();

                $('#uo-user-ig-pages').on('click', 'a.uo-ig-pages-item-btn', function (e) {
                    e.preventDefault();
                    $(this).find('span.dashicons-image-rotate').addClass('uo-preloader-rotate');
                    var target = $(this);
                    $.ajax({
                        dataType: 'json',
                        url: url,
                        data: {
                            action: '<?php echo esc_html( "{$this->wp_ajax_action}_fetch_instagram_accounts" ); ?>',
                            page_id: target.attr('data-page-id'),
                            nonce: '<?php echo esc_html( wp_create_nonce( self::OPTION_KEY ) ); ?>'
                        },
                        success: function (igResponse) {

                            var ig_account_html = '';

                            if (200 === igResponse.statusCode) {

                                var igResponseData = igResponse.data.data;

                                if (igResponseData.length >= 1) {

                                    $.each(igResponseData, function (index, ig_account) {
                                        ig_account_html += '<div class="uo-ig-account-connected-item">';
                                        ig_account_html += '<img width="32" src="' + ig_account.profile_pic + '" />';
                                        ig_account_html += '<span>' + ig_account.username + '</span>';
                                        ig_account_html += '</div>';
                                    });

                                    target.parent().html(ig_account_html);

                                } else {
                                    target.parent().css('color', '#ff7c00');
                                    target.parent().html('<?php esc_html_e( 'No Instagram Business or Professional account connected.', 'uncanny-automator' ); ?>');
                                }

                            } else {
                                target.parent().css('color', '#ff3b00');
                                target.parent().html('<?php esc_html_e( 'Could not find any Business/Professional Instagram account connected to the page.', 'uncanny-automator' ); ?>');
                            }
                        },
                        error: function (xhr, status, error) {
                            target.html(status + ': ' + error).removeClass('uo-settings-btn--primary').addClass('uo-settings-btn--error');
                        }
                    });
                });

            });
        </script>
		<?php
	}
}
