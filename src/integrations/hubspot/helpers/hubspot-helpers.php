<?php

namespace Uncanny_Automator;

/**
 * Class Hubspot_Helpers
 *
 * @package Uncanny_Automator
 */
class Hubspot_Helpers {


	/**
	 * @var Hubspot_Helpers
	 */
	public $options;

	/**
	 * @var Hubspot_Helpers
	 */
	public $setting_tab;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Hubspot_Helpers constructor.
	 */
	public function __construct() {

		$this->setting_tab   = 'hubspot_api';
		$this->automator_api = AUTOMATOR_API_URL . 'v2/hubspot';

		add_filter( 'automator_settings_tabs', array( $this, 'add_hubspot_api_settings' ), 15 );
		add_action( 'init', array( $this, 'capture_oauth_tokens' ), 100, 3 );
		add_action( 'init', array( $this, 'disconnect' ), 100, 3 );
		add_filter( 'automator_after_settings_extra_buttons', array( $this, 'hubspot_connect_html' ), 10, 3 );

	}

	/**
	 * @param Hubspot_Helpers $options
	 */
	public function setOptions( Hubspot_Helpers $options ) { // phpcs:ignore
		$this->options = $options;
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
	public function add_hubspot_api_settings( $tabs ) {

		if ( $this->display_settings_tab() ) {

			$tab_url                    = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-settings&tab=' . $this->setting_tab;
			$tabs[ $this->setting_tab ] = array(
				'name'           => __( 'HubSpot', 'uncanny-automator' ),
				'title'          => __( 'HubSpot account settings', 'uncanny-automator' ),
				'description'    => sprintf( '<p>%s</p>', __( 'Connecting to HubSpot requires signing into your account to link it to Automator. To get started, click the "Connect an account" button below or the "Disconnect account" button if you need to disconnect or connect a new account. Uncanny Automator can only connect to a single HubSpot account at one time. (It is not possible to set some recipes up under one account and then switch accounts, all recipes are mapped to the account selected on this page and existing recipes may break if they were set up under another account.)', 'uncanny-automator' ) ) . $this->user_info(),
				'settings_field' => 'uap_automator_hubspot_api_settings',
				'wp_nonce_field' => 'uap_automator_hubspot_api_nonce',
				'save_btn_name'  => 'uap_automator_hubspot_api_save',
				'save_btn_title' => __( 'Save settings', 'uncanny-automator' ),
				'fields'         => array(),
			);

		}

		return $tabs;
	}

	/**
	 * @param $content
	 * @param $active
	 * @param $tab
	 *
	 * @return false|mixed|string
	 */
	public function hubspot_connect_html( $content, $active, $tab ) {

		if ( 'hubspot_api' === $active ) {

			$tab_url = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-settings&tab=' . $this->setting_tab;

			$hubspot_client = $this->get_client();

			if ( $hubspot_client ) {
				$button_text  = __( 'Disconnect account', 'uncanny-automator' );
				$button_class = 'uo-disconnect-button';
				$button_url   = $tab_url . '&disconnect=1';
			} else {
				$nonce      = wp_create_nonce( 'automator_hubspot_api_authentication' );
				$plugin_ver = AUTOMATOR_PLUGIN_VERSION;
				$api_ver    = '1.0';

				$action       = 'authorization_request';
				$redirect_url = rawurlencode( $tab_url );
				$button_url   = $this->automator_api . "?action={$action}&redirect_url={$redirect_url}&nonce={$nonce}&api_ver={$api_ver}&plugin_ver={$plugin_ver}";
				$button_text  = __( 'Connect an account', 'uncanny-automator' );
				$button_class = 'uo-connect-button';
			}

			ob_start();

			?>

			<a href="<?php echo esc_url( $button_url ); ?>" class="uo-settings-btn uo-settings-btn--secondary <?php echo esc_attr( $button_class ); ?>">
			<?php
			echo esc_attr( $button_text );
			?>
			</a>

			<style>
				.uo-hubspot-user-info {
					display: flex;
					align-items: center;
					margin: 20px 0 0;
				}

				.uo-hubspot-user-info__handle {
					font-weight: 700;
					color: #212121;
				}

				button[name="uap_automator_hubspot_api_save"] {
					display: none;
				}

				.uo-connect-button {
					color: #fff;
					background-color: #4fb840;
				}

				.uo-disconnect-button {
					color: #fff;
					background-color: #f58933;
				}
			</style>
			<?php
			$content = ob_get_contents();
			ob_end_clean();
		}

		return $content;
	}

	/**
	 *
	 * @return array $tokens
	 */
	public function get_client() {

		$tokens = get_option( '_automator_hubspot_settings', array() );

		if ( empty( $tokens['access_token'] ) || empty( $tokens['refresh_token'] ) ) {
			return false;
		}

		return $tokens;
	}

	/**
	 * store_client
	 *
	 * @param  mixed $tokens
	 * @return void
	 */
	public function store_client( $tokens ) {

		$tokens['stored_at'] = time();

		update_option( '_automator_hubspot_settings', $tokens );

		delete_transient( '_automator_hubspot_token_info' );

		return $tokens;
	}

	/**
	 * Capture tokens returned by Automator API.
	 *
	 * @return mixed
	 */
	public function capture_oauth_tokens() {

		if ( automator_filter_input( 'tab' ) !== $this->setting_tab ) {
			return;
		}

		$automator_message = automator_filter_input( 'automator_api_message' );

		if ( empty( $automator_message ) ) {
			return;
		}

		$nonce = wp_create_nonce( 'automator_hubspot_api_authentication' );

		$tokens = (array) Automator_Helpers_Recipe::automator_api_decode_message( $automator_message, $nonce );

		$redirect_url = admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-settings&tab=' . $this->setting_tab );

		if ( $tokens ) {
			$this->store_client( $tokens );
			$redirect_url .= '&connect=1';
		} else {
			$redirect_url .= '&connect=2';
		}

		wp_safe_redirect( $redirect_url );

		die;
	}

	/**
	 * disconnect
	 *
	 * @return void
	 */
	public function disconnect() {

		if ( automator_filter_input( 'tab' ) !== $this->setting_tab ) {
			return;
		}

		if ( ! automator_filter_has_var( 'disconnect' ) ) {
			return;
		}

		delete_transient( '_automator_hubspot_token_info' );
		delete_option( '_automator_hubspot_settings' );

		$redirect_url = admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-settings&tab=' . $this->setting_tab );

		wp_safe_redirect( $redirect_url );

		die;
	}

	/**
	 * maybe_refresh_token
	 *
	 * @param  mixed $tokens
	 * @return void
	 */
	public function maybe_refresh_token( $tokens ) {

		$expiration_timestamp = $tokens['stored_at'] + $tokens['expires_in'];

		// Check if token will expire in the next minute
		if ( time() > $expiration_timestamp - MINUTE_IN_SECONDS ) {
			// Token is expired or will expire soon, refresh it
			return $this->api_refresh_token( $tokens );
		}

		return $tokens;
	}

	/**
	 * extract_data
	 *
	 * @param  mixed $response
	 * @return void
	 */
	public function extract_data( $response ) {

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['data'] ) ) {
			return false;
		}

		return $body['data'];
	}

	/**
	 * api_refresh_token
	 *
	 * @param  mixed $tokens
	 * @return void
	 */
	public function api_refresh_token( $tokens ) {

		$args = array(
			'body' => array(
				'action' => 'refresh_token',
				'client' => wp_json_encode( $tokens ),
			),
		);

		$response = wp_remote_post( $this->automator_api, $args );

		$data = $this->extract_data( $response );

		if ( empty( $data['access_token'] ) ) {
			return false;
		}

		$tokens = $this->store_client( $data );

		return $tokens;

	}

	/**
	 * Displays the hubspot handle of the user in settings description.
	 *
	 * @return string The hubspot handle html.
	 */
	public function user_info() {

		if ( ! $this->get_client() ) {
			return '';
		}

		$token_info = $this->api_token_info();

		if ( ! $token_info ) {
			return '';
		}

		ob_start();
		?>

		<div class="uo-hubspot-user-info">
		<?php if ( isset( $token_info['user'] ) ) : ?>
			<div class="uo-hubspot-user-info__handle">
			<?php echo esc_html( $token_info['user'] ); ?>
			</div>
		<?php endif; ?>
		</div>

		<?php

		return ob_get_clean();
	}

	/**
	 * api_token_info
	 *
	 * @return void
	 */
	public function api_token_info() {

		$token_info = get_transient( '_automator_hubspot_token_info' );

		if ( ! $token_info ) {

			$params = array(
				'action' => 'access_token_info',
			);

			$response = $this->api_request( $params );

			$token_info = $this->extract_data( $response );

			if ( ! $token_info ) {
				return false;
			}

			set_transient( '_automator_hubspot_token_info', $token_info, DAY_IN_SECONDS );
		}

		return $token_info;
	}

	/**
	 * create_contact
	 *
	 * @param  mixed $email
	 * @return void
	 */
	public function create_contact( $properties, $update = true ) {

		$action = 'create_contact';

		if ( $update ) {
			$action = 'create_or_update_contact';
		}

		$params = array(
			'action'     => $action,
			'properties' => wp_json_encode( $properties ),
		);

		$response = $this->api_request( $params );

		return $response;
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
	public function log_action_error( $response, $user_id, $action_data, $recipe_id ) {

		// log error when no token found.
		$error_msg = __( 'API error: ', 'uncanny-automator' );

		if ( isset( $response['data']['status'] ) && 'error' === $response['data']['status'] ) {
			$error_msg .= ' ' . $response['data']['message'];
		}

		$action_data['do-nothing']           = true;
		$action_data['complete_with_errors'] = true;
		Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_msg );
	}

	/**
	 * Method api_request
	 *
	 * @param $params
	 *
	 * @return void
	 */
	public function api_request( $params ) {

		$params = apply_filters( 'automator_hubspot_api_request_params', $params );

		$client = $this->get_client();

		$client = $this->maybe_refresh_token( $client );

		if ( ! $client ) {
			return false;
		}

		$body = array(
			'client'     => $client,
			'api_ver'    => '2.0',
			'plugin_ver' => InitializePlugin::PLUGIN_VERSION,
		);

		$body = array_merge( $body, $params );

		$response = wp_remote_post(
			$this->automator_api,
			array(
				'method'  => 'POST',
				'body'    => $body,
				'timeout' => 15,
			)
		);

		$response = apply_filters( 'automator_hubspot_api_response', $response );

		return $response;
	}

	/**
	 * get_fields
	 *
	 * @return void
	 */
	public function get_fields( $exclude = array() ) {

		$fields = array(
			array(
				'value' => '',
				'text'  => __( 'Select a field', 'uncanny-automator' ),
			),
		);

		$request_params = array(
			'action' => 'get_fields',
		);

		$response = $this->api_request( $request_params );

		if ( is_wp_error( $response ) ) {

			$error_msg = implode( ', ', $response->get_error_messages() );
			automator_log( 'WordPress was unable to communicate with HubSpot and returned an error: ' . $error_msg );

			return $fields;

		} else {

			$json_data = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $json_data && 200 === intval( $json_data['statusCode'] ) ) {
				foreach ( $json_data['data'] as $field ) {

					if ( in_array( $field['name'], $exclude, true ) ) {
						continue;
					}

					if ( $field['readOnlyValue'] ) {
						continue;
					}

					$fields[] = array(
						'value' => $field['name'],
						'text'  => $field['label'],
					);
				}
			} else {
				automator_log( $json_data );
			}
		}

		return $fields;
	}



	/**
	 * get_lists
	 *
	 * @return void
	 */
	public function get_lists() {

		$options[] = array(
			'value' => '',
			'text'  => __( 'Select a list', 'uncanny-automator' ),
		);

		$params = array(
			'action' => 'get_lists',
		);

		$response = $this->api_request( $params );

		if ( is_wp_error( $response ) ) {

			$error_msg = implode( ', ', $response->get_error_messages() );
			automator_log( 'WordPress was unable to communicate with HubSpot and returned an error: ' . $error_msg );

			return $options;

		} else {

			$json_data = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $json_data && 200 === intval( $json_data['statusCode'] ) ) {

				foreach ( $json_data['data']['lists'] as $list ) {

					if ( 'STATIC' !== $list['listType'] ) {
						continue;
					}

					$options[] = array(
						'value' => $list['listId'],
						'text'  => $list['name'],
					);
				}
			} else {
				automator_log( $json_data );
			}
		}

		return apply_filters( 'automator_hubspot_options_get_lists', $options );
	}

	/**
	 * add_contact_to_list
	 *
	 * @param  mixed $email
	 * @return void
	 */
	public function add_contact_to_list( $list, $email ) {

		$params = array(
			'action' => 'add_contact_to_list',
			'email'  => $email,
			'list'   => $list,
		);

		$response = $this->api_request( $params );

		return $response;
	}

	/**
	 * remove_contact_from_list
	 *
	 * @param  mixed $list
	 * @param  mixed $email
	 * @return void
	 */
	public function remove_contact_from_list( $list, $email ) {
		$params = array(
			'action' => 'remove_contact_from_list',
			'email'  => $email,
			'list'   => $list,
		);

		$response = $this->api_request( $params );

		return $response;
	}

}
