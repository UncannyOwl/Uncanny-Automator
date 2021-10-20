<?php

namespace Uncanny_Automator;

use Firebase\JWT\JWT;

global $zoom_webinar_token_renew;

/**
 * Class Zoom_Webinar_Helpers
 * @package Uncanny_Automator
 */
class Zoom_Webinar_Helpers {

	/**
	 * @var Zoom_Webinar_Helpers
	 */
	public $options;
	/**
	 * @var Zoom_Webinar_Helpers
	 */
	public $pro;
	/**
	 * @var Zoom_Webinar_Helpers
	 */
	public $setting_tab;
	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Zoom_Webinar_Helpers constructor.
	 */
	public function __construct() {

		// Selectively load options
		if ( method_exists( '\Uncanny_Automator\Automator_Helpers_Recipe', 'maybe_load_trigger_options' ) ) {
			$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
		} else {
			$this->load_options = true;
		}

		$this->setting_tab = 'zoom_webinar_api';

		$this->automator_api = AUTOMATOR_API_URL . 'v2/zoom';

		add_filter( 'uap_settings_tabs', array( $this, 'add_zoom_api_settings' ), 15 );
		add_action( 'init', array( $this, 'zoom_oauth_save' ), 200 );

		// Disconnect wp-ajax action.
		add_action( 'wp_ajax_uap_automator_zoom_webinar_api_disconnect', array( $this, 'disconnect' ), 10 );
	}

	/**
	 * @param Zoom_Webinar_Helpers $options
	 */
	public function setOptions( Zoom_Webinar_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Zoom_Webinar_Pro_Helpers $pro
	 */
	public function setPro( \Uncanny_Automator_Pro\Zoom_Webinar_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}


	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function get_webinars( $label = null, $option_code = 'ZOOMWEBINARS', $args = array() ) {

		if ( ! $label ) {
			$label = __( 'Webinar', 'uncanny-automator' );
		}

		$args = wp_parse_args(
			$args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => __( 'Any Webinar', 'uncanny-automator' ),
			)
		);

		$token        = key_exists( 'token', $args ) ? $args['token'] : true;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = array();

		$client = $this->get_client();

		if ( ! $client || empty( $client['access_token'] ) ) {
			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		// API register call
		$response = wp_remote_post(
			$this->automator_api,
			array(
				'body' =>
					array(
						'action'       => 'get_webinars',
						'access_token' => $client['access_token'],
						'page_number'  => 1,
						'page_size'    => 1000,
						'type'         => 'upcoming',
					),
			)
		);

		if ( ! is_wp_error( $response ) ) {

			$response_code = wp_remote_retrieve_response_code( $response );

			if ( $response_code === 200 ) {

				$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

				if ( count( $response_body['data']['webinars'] ) > 0 ) {

					foreach ( $response_body['data']['webinars'] as $key => $webinar ) {

						$options[] = array(
							'value' => $webinar['id'],
							'text' => $webinar['topic']
						);

					}
				}
			}
		}

		

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $options,
		);

		return apply_filters( 'uap_option_zoom_get_webinars', $option );
	}

	/**
	 * For registering user to webinar action method.
	 *
	 * @param string $user_id
	 * @param string $webinar_key
	 *
	 * @return array
	 */
	public function register_user( $user_id, $webinar_key ) {

		$user = get_userdata( $user_id );

		if ( is_wp_error( $user ) ) {
			return array(
				'result'  => false,
				'message' => __( 'Zoom user not found.', 'uncanny-automator' ),
			);

		}

		$customer_first_name = $user->first_name;
		$customer_last_name  = $user->last_name;
		$customer_email      = $user->user_email;

		if ( ! empty( $customer_email ) ) {
			$customer_email_parts = explode( '@', $customer_email );
			$customer_first_name  = empty( $customer_first_name ) ? $customer_email_parts[0] : $customer_first_name;
			$customer_last_name   = empty( $customer_last_name ) ? $customer_email_parts[0] : $customer_last_name;
		}

		$client = $this->get_client();

		if ( ! $client || empty( $client['access_token'] ) ) {
			return array(
				'result'  => false,
				'message' => __( 'Zoom Webinar credentials have expired.', 'uncanny-automator' ),
			);
		}

		$response = wp_remote_post(
			$this->automator_api,
			array(
				'body' =>
					array(
						'action'       => 'register_webinar_user',
						'access_token' => $client['access_token'],
						'webinar_key'  => $webinar_key,
						'first_name'   => $customer_first_name,
						'last_name'    => $customer_last_name,
						'email'        => $customer_email,
					),
			)
		);

		if ( ! is_wp_error( $response ) ) {

			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( 201 === wp_remote_retrieve_response_code( $response ) ) {

				if ( isset( $body['data']['join_url'] ) ) {
					return array(
						'result'  => true,
						'message' => __( 'Successfully registered', 'uncanny-automator' ),
					);
				}
			} else {

				$error = '';

				if ( isset( $body['data']['message'] ) ) {
					$error = $body['data']['message'];
				} elseif ( isset( $body['error']['description'] ) ) {
					$error = $body['error']['description'];
				}

				return array(
					'result'  => false,
					'message' => __( $error, 'uncanny-automator' ),
				);
			}
		}

		return array(
			'result'  => false,
			'message' => __( 'WordPress was not able to communicate with Zoom API.', 'uncanny-automator' ),
		);
	}

	/**
	 * For registering user to webinar action method.
	 *
	 * @param string $user_id
	 * @param string $webinar_key
	 *
	 * @return array
	 */
	public function register_userless( $user, $webinar_key ) {

		$customer_email       = $user['EMAIL'];
		$customer_email_parts = explode( '@', $customer_email );

		$customer_first_name = empty( $user['FIRSTNAME'] ) ? $customer_email_parts[0] : $user['FIRSTNAME'];
		$customer_last_name  = empty( $user['LASTNAME'] ) ? $customer_email_parts[0] : $user['LASTNAME'];

		$client = $this->get_client();

		if ( ! $client || empty( $client['access_token'] ) ) {
			return array(
				'result'  => false,
				'message' => __( 'Zoom Webinar credentials have expired.', 'uncanny-automator' ),
			);
		}

		$response = wp_remote_post(
			$this->automator_api,
			array(
				'body' =>
					array(
						'action'       => 'register_webinar_user',
						'access_token' => $client['access_token'],
						'webinar_key'  => $webinar_key,
						'first_name'   => $customer_first_name,
						'last_name'    => $customer_last_name,
						'email'        => $customer_email,
					),
			)
		);

		if ( ! is_wp_error( $response ) ) {

			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( 201 === wp_remote_retrieve_response_code( $response ) ) {

				if ( isset( $body['data']['join_url'] ) ) {

					return array(
						'result'  => true,
						'message' => __( 'Successfully registered', 'uncanny-automator' ),
					);
				}
			} else {

				$error = '';

				if ( isset( $body['data']['message'] ) ) {
					$error = $body['data']['message'];
				} elseif ( isset( $body['error']['description'] ) ) {
					$error = $body['error']['description'];
				}

				return array(
					'result'  => false,
					'message' => __( $error, 'uncanny-automator' ),
				);
			}
		}

		return array(
			'result'  => false,
			'message' => __( 'WordPress was not able to communicate with Zoom API.', 'uncanny-automator' ),
		);
	}

	/**
	 * For un-registering user to webinar action method.
	 *
	 * @param string $email
	 * @param string $webinar_key
	 *
	 * @return array
	 */
	public function unregister_user( $email, $webinar_key ) {

		$client = $this->get_client();

		if ( ! $client || empty( $client['access_token'] ) ) {
			return array(
				'result'  => false,
				'message' => __( 'Zoom Webinar credentails have expired.', 'uncanny-automator' ),
			);
		}

		$response = wp_remote_post(
			$this->automator_api,
			array(
				'body' =>
					array(
						'action'       => 'unregister_webinar_user',
						'access_token' => $client['access_token'],
						'webinar_key'  => $webinar_key,
						'email'        => $email,
					),
			)
		);

		if ( ! is_wp_error( $response ) ) {

			if ( 201 === wp_remote_retrieve_response_code( $response ) || 204 === wp_remote_retrieve_response_code( $response ) ) {

				return array(
					'result'  => true,
					'message' => __( 'Successfully registered', 'uncanny-automator' ),
				);

			} else {

				$body = json_decode( wp_remote_retrieve_body( $response ), true );

				$error = '';

				if ( isset( $body['data']['message'] ) ) {
					$error = $body['data']['message'];
				} elseif ( isset( $body['error']['description'] ) ) {
					$error = $body['error']['description'];
				}

				return array(
					'result'  => false,
					'message' => __( $error, 'uncanny-automator' ),
				);
			}
		}

		return array(
			'result'  => false,
			'message' => __( 'WordPress was not able to communicate with Zoom API.', 'uncanny-automator' ),
		);
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

		return ! empty( $this->get_client() );
	}

	/**
	 * @param $tabs
	 *
	 * @return mixed
	 */
	public function add_zoom_api_settings( $tabs ) {

		if ( ! $this->display_settings_tab() ) {
			return $tabs;
		}

		$tab_url = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-settings&tab=' . $this->setting_tab;

		$tabs[ $this->setting_tab ] = array(
			'name'           => __( 'Zoom Webinar', 'uncanny-automator' ),
			'title'          => __( 'Zoom Webinar API settings', 'uncanny-automator' ),
			'description'    => sprintf(
									'<p>%1$s</p>',
									sprintf(
										__( "Connecting to Zoom requires setting up a JWT application and getting 2 values from inside your account. It's really easy, we promise! Visit %1\$s for simple instructions.", 'uncanny-automator' ),
										'<a href="' . automator_utm_parameters( 'https://automatorplugin.com/knowledge-base/zoom/', 'settings', 'zoom_webinar-kb_article' ) . '" target="_blank">https://automatorplugin.com/knowledge-base/zoom/</a>'
									)
								) . $this->get_user_info(),
			'is_pro'         => false,
			'settings_field' => 'uap_automator_zoom_webinar_api_settings',
			'wp_nonce_field' => 'uap_automator_zoom_webinar_api_nonce',
			'save_btn_name'  => 'uap_automator_zoom_webinar_api_save',
			'save_btn_title' => __( 'Save API details', 'uncanny-automator' ),
			'fields'         => array(
				'uap_automator_zoom_webinar_api_consumer_key'    => array(
					'title'       => __( 'API key:', 'uncanny-automator' ),
					'type'        => 'text',
					'css_classes' => '',
					'placeholder' => '',
					'default'     => '',
					'required'    => true,
					'custom_atts' => array( 'autocomplete' => 'off' ),
				),
				'uap_automator_zoom_webinar_api_consumer_secret' => array(
					'title'       => __( 'API secret:', 'uncanny-automator' ),
					'type'        => 'password',
					'css_classes' => '',
					'placeholder' => '',
					'default'     => '',
					'required'    => true,
					'custom_atts' => array( 'autocomplete' => 'off' ),
				),
			),
		);

		return $tabs;
	}

	/**
	 * Action when settings added, it will redirect user to 3rd party for OAuth connect.
	 */
	public function zoom_oauth_save() {

		if ( isset( $_POST['uap_automator_zoom_webinar_api_nonce'] ) && wp_verify_nonce( $_POST['uap_automator_zoom_webinar_api_nonce'], 'uap_automator_zoom_webinar_api_nonce' ) ) {

			if ( isset( $_POST['uap_automator_zoom_webinar_api_consumer_key'] ) && ! empty( $_POST['uap_automator_zoom_webinar_api_consumer_key'] ) && isset( $_POST['uap_automator_zoom_webinar_api_consumer_secret'] ) && ! empty( $_POST['uap_automator_zoom_webinar_api_consumer_secret'] ) ) {

				update_option( 'uap_automator_zoom_webinar_api_consumer_key', $_POST['uap_automator_zoom_webinar_api_consumer_key'] );
				update_option( 'uap_automator_zoom_webinar_api_consumer_secret', $_POST['uap_automator_zoom_webinar_api_consumer_secret'] );
				delete_transient( 'uap_automator_zoom_webinar_api_user_info' );

				$client = $this->refresh_token();

				// Check if token is working fine or not.
				$response = wp_remote_post(
					$this->automator_api,
					array(
						'body' =>
							array(
								'action'       => 'get_webinars',
								'access_token' => $client['access_token'],
								'page_number'  => 1,
								'page_size'    => 300,
								'type'         => 'upcoming',
							),
					)
				);

				if ( is_wp_error( $response ) ) {
					$error_msg = implode( ', ', $response->get_error_messages() );
					wp_safe_redirect( admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-settings&tab=' . $this->setting_tab . '&connect=' . $error_msg ) );
				} else {

					$status_code = wp_remote_retrieve_response_code( $response );

					// Check for a meeting API call if not 200 then its wrong pair.
					if ( $status_code !== 200 ) {

						$body = json_decode( wp_remote_retrieve_body( $response ), true );

						delete_option( '_uncannyowl_zoom_webinar_settings' );

						$error_msg = ! empty( $body['data']['message'] ) ? $body['data']['message'] : '2';

						wp_safe_redirect( admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-settings&tab=' . $this->setting_tab . '&connect=' . $error_msg ) );

					} else {

						wp_safe_redirect( admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-settings&tab=' . $this->setting_tab . '&connect=1' ) );

					}
				}

				die;
			}
		}
	}

	/**
	 * Get the connected user from Zoom API.
	 *
	 * @return string The HTML of the user.
	 */
	public function get_user_info() {

		ob_start();

		$zoom_user = $this->api_get_user_info();

		// Bail out if zoom user if empty.
		if ( false === $zoom_user ) {
			return;
		}
		?>

		<?php $this->get_inline_style(); ?>

		<p>
		<div class="uo-zoom-user-info">

			<div class="uo-zoom-user-info__avatar">
				<?php if ( ! isset( $zoom_user->pic_url ) ) : ?>
					<div class="uo-zoom-user-info-placeholder-avatar">
						<span class="dashicons dashicons-admin-users"></span>
					</div>
				<?php else : ?>
					<img width="32" src="<?php echo esc_url( $zoom_user->pic_url ); ?>"
						 alt="<?php esc_attr_e( 'Profile Pic', 'uncanny-automator' ); ?>"/>
				<?php endif; ?>
			</div>

			<div class="uo-zoom-user-info__email">
				<?php echo esc_html( $zoom_user->email ); ?>
			</div>

			<div class="uo-zoom-user-info__name">
				<?php echo esc_html( sprintf( '(%s %s)', ! empty( $zoom_user->first_name ) ? $zoom_user->first_name : '', $zoom_user->last_name ? $zoom_user->first_name : '' ) ); ?>
			</div>

		</div><!--.uo-zoom-user-info-->
		</p>
		<p>
			<?php
			$disconnect_uri = add_query_arg(
				array(
					'action' => 'uap_automator_zoom_webinar_api_disconnect',
					'nonce'  => wp_create_nonce( 'uap_automator_zoom_webinar_api_disconnect' ),
				),
				admin_url( 'admin-ajax.php' )
			);
			?>
			<a class="uo-settings-btn uo-settings-btn--error" href="<?php echo esc_url( $disconnect_uri ); ?>"
			   title="<?php esc_attr_e( 'Disconnect', 'uncanny-automator' ); ?>">
				<?php esc_html_e( 'Disconnect', 'uncanny-automator' ); ?>
			</a>
			<br/>
		</p>
		<?php

		return ob_get_clean();
	}

	/**
	 * Returns the zoom user from transient or from zoom api.
	 *
	 * @return mixed The zoom user if tokens are available. Otherwise, false.
	 */
	public function api_get_user_info() {

		$transient_key = 'uap_automator_zoom_webinar_api_user_info';

		$saved_user_info = get_transient( $transient_key );

		if ( false !== $saved_user_info ) {
			return $saved_user_info;
		}

		$client = $this->get_client();

		if ( ! $client || empty( $client['access_token'] ) ) {
			return false;
		}

		$response = wp_remote_post(
			$this->automator_api,
			array(
				'body' =>
					array(
						'action'       => 'get_user',
						'access_token' => $client['access_token'],
					),
			)
		);

		if ( ! is_wp_error( $response ) ) {
			$status_code = wp_remote_retrieve_response_code( $response );

			if ( 200 === $status_code ) {
				$response_body = json_decode( wp_remote_retrieve_body( $response ) );
				set_transient( $transient_key, $response_body->data, WEEK_IN_SECONDS );
	
				return $response_body->data;
			}
		}

		return false;

	}

	/**
	 * Outputs inline style to format the html return of user info.
	 *
	 * @return void.
	 */
	public function get_inline_style() {
		?>
		<style>
			.uo-settings-content-description a.uo-settings-btn--error {
				color: #e94b35;
			}

			.uo-settings-content-description a.uo-settings-btn--error:focus,
			.uo-settings-content-description a.uo-settings-btn--error:active,
			.uo-settings-content-description a.uo-settings-btn--error:hover {
				color: #fff;
			}

			.uo-zoom-user-info {
				display: flex;
				align-items: center;
				margin: 20px 0 0;
			}

			.uo-zoom-user-info__avatar {
				background: #fff;
				box-shadow: 0 2px 5px 0 rgb(0 0 0 / 10%);
				border-radius: 32px;
				height: 32px;
				overflow: hidden;
				width: 32px;
				text-align: center;
				display: inline-flex;
				align-items: center;
				justify-content: center;
				color: #2D8CFF;
				margin-right: 5px;
			}

			.uo-zoom-user-info__avatar img {
				border-radius: 32px;
			}

			.uo-zoom-user-info__name {
				opacity: 0.75;
				margin-left: 5px;
			}
		</style>
		<?php
	}

	/**
	 * get_client
	 *
	 * @return void
	 */
	public function get_client() {

		$client = get_option( '_uncannyowl_zoom_webinar_settings' );

		if ( ! $client || empty( $client['access_token'] ) ) {
			return false;
		}

		if ( empty( $client['expires'] ) || $client['expires'] < time() ) {
			return $this->refresh_token();
		}

		return $client;

	}

	/**
	 * refresh_token
	 *
	 * @param array $client
	 *
	 * @return void
	 */
	public function refresh_token() {

		$client = array();

		// Get the API key and secret
		$consumer_key    = trim( get_option( 'uap_automator_zoom_webinar_api_consumer_key', '' ) );
		$consumer_secret = trim( get_option( 'uap_automator_zoom_webinar_api_consumer_secret', '' ) );

		if ( empty( $consumer_key ) || empty( $consumer_secret ) ) {
			return false;
		}

		// Set the token expiration to 1 minute as recommended in the docuemntation
		$client['expires'] = time() + 60;

		$payload = array(
			'iss' => $consumer_key,
			'exp' => $client['expires'],
		);

		// Generate the access token using the JWT library
		$token = JWT::encode( $payload, $consumer_secret );

		$client['access_token']  = $token;
		$client['refresh_token'] = $token;

		// Cache it in settings
		update_option( '_uncannyowl_zoom_webinar_settings', $client );

		return $client;
	}

	/**
	 * Disconnect the user from the Zoom API.
	 *
	 * @return void.
	 */
	public function disconnect() {

		if ( wp_verify_nonce( filter_input( INPUT_GET, 'nonce', FILTER_DEFAULT ), 'uap_automator_zoom_webinar_api_disconnect' ) ) {

			delete_option( 'uap_automator_zoom_webinar_api_consumer_key' );
			delete_option( 'uap_automator_zoom_webinar_api_consumer_secret' );

			delete_option( '_uncannyowl_zoom_webinar_settings_version' );
			delete_option( '_uncannyowl_zoom_webinar_settings' );

			delete_transient( '_uncannyowl_zoom_webinar_settings' );
			delete_transient( 'uap_automator_zoom_webinar_api_user_info' );

		}

		$return_uri = add_query_arg(
			array(
				'post_type' => 'uo-recipe',
				'page'      => 'uncanny-automator-settings',
				'tab'       => 'zoom_webinar_api',
			),
			admin_url( 'edit.php' )
		);

		wp_safe_redirect( $return_uri );

		exit;

	}
}
