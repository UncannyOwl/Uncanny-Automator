<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

/**
 * Class Facebook_Helpers
 *
 * @package Uncanny_Automator
 */
class Facebook_Groups_Helpers {

	/**
	 * The Pro helpers options object.
	 *
	 * @var string|object
	 */
	public $pro = '';

	/**
	 * The helpers options object.
	 *
	 * @var string|object
	 */
	public $options = '';

	/**
	 * The prefix for all our wp_ajax endpoints.
	 *
	 * @var string
	 */
	const AJAX_PREFIX = 'automator_integration_facebook_group_capture_token';

	/**
	 * The wp_options table key for selecting the integration options.
	 *
	 * @var string
	 */
	const OPTION_KEY = '_uncannyowl_facebook_group_settings';

	/**
	 * The public API edge.
	 *
	 * @var string
	 */
	const API_ENDPOINT = 'v2/facebook-group';

	/**
	 * Set the options.
	 *
	 * @param Facebook_Groups_Helpers $options
	 */
	public function setOptions( Facebook_Groups_Helpers $options ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->options = $options;
	}

	/**
	 * Set pro method.
	 *
	 * @param Facebook_Groups_Pro_Helpers $pro
	 */
	public function setPro( \Uncanny_Automator_Pro\Facebook_Groups_Pro_Helpers $pro ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->pro = $pro;
	}

	public function __construct() {

		// Capturing the OAuth Token and user id.
		add_action( 'wp_ajax_' . self::AJAX_PREFIX, array( $this, self::AJAX_PREFIX ), 10 );

		// Add a disconnect button.
		add_action( 'wp_ajax_' . self::AJAX_PREFIX . '_disconnect', array( $this, self::AJAX_PREFIX . '_disconnect' ) );

		// Add an ajax endpoint for listing groups.
		add_action( 'wp_ajax_ua_facebook_group_list_groups', array( $this, 'list_groups' ) );

		require_once __DIR__ . '/../settings/settings-facebook-groups.php';

		new Facebook_Group_Settings( $this );

	}

	/**
	 * Get the login dialoag uri.
	 *
	 * @return string The login dialog uri.
	 */
	public function get_login_dialog_uri() {

		return add_query_arg(
			array(
				'action'   => 'login_dialog',
				'nonce'    => wp_create_nonce( self::OPTION_KEY ),
				'user_url' => rawurlencode( admin_url( 'admin-ajax.php' ) . '?action=' . self::AJAX_PREFIX . '_token_capture' ),
			),
			AUTOMATOR_API_URL . self::API_ENDPOINT
		);

	}

	/**
	 * Endpoint wp_ajax callback. List all groups.
	 *
	 * @return void
	 */
	public function list_groups() {

		// Nonce verification.
		if ( ! wp_verify_nonce( automator_filter_input( 'nonce', INPUT_POST ), 'uncanny_automator' ) ) {
			wp_send_json(
				array(
					'success' => false,
					'message' => esc_html__( 'Nonce authentication error', 'uncanny-automator' ),
					'items'   => null,
				)
			);
		}

		// Try serving the cached list of groups.
		$saved_groups = get_transient( 'ua_facebook_group_items' );

		if ( false !== $saved_groups && ! empty( $saved_groups ) ) {

			wp_send_json(
				array(
					'success' => true,
					'message' => '',
					'items'   => $saved_groups,
				)
			);

		}

		// Otherwise, request from the API.
		$settings = get_option( self::OPTION_KEY );

		$body = array(
			'action'       => 'list_groups',
			'access_token' => isset( $settings['user']['token'] ) ? $settings['user']['token'] : '',
			'user_id'      => isset( $settings['user']['id'] ) ? $settings['user']['id'] : '',
		);

		try {

			$response = $this->api_request( $body, null );

			if ( ! isset( $response['data']['data'] ) ) {
				throw new \Exception( 'Facebook API has responded with empty data', 400 );
			}

			$items = array();

			foreach ( $response['data']['data']  as $group ) {
				$items[] = array(
					'id'   => $group['id'],
					'text' => $group['name'],
				);
			}

			if ( ! empty( $items ) ) {
				// Cache the list of groups.
				set_transient( 'ua_facebook_group_items', $items, MINUTE_IN_SECONDS * 5 );
				// Then save to options table.
				update_option( 'ua_facebook_group_saved_groups', $items );
			}

			wp_send_json(
				array(
					'success' => true,
					'message' => esc_html__( 'Groups has been successfully fetched.' ),
					'items'   => $items,
				)
			);

		} catch ( \Exception $e ) {

			wp_send_json(
				array(
					'success' => false,
					'message' => sprintf( 'Request failed with error code: %d [%s]', $e->getCode(), $e->getMessage() ),
					'items'   => null,
				)
			);

		}

	}

	/**
	 * Endpoint wp_ajax callback. Capture user token.
	 *
	 * @return void.
	 */
	public function automator_integration_facebook_group_capture_token() {

		$settings = array(
			'user' => array(
				'id'    => filter_input( INPUT_GET, 'fb_user_id', FILTER_SANITIZE_NUMBER_INT ),
				'token' => filter_input( INPUT_GET, 'fb_user_token', FILTER_SANITIZE_STRING ),
			),
		);

		$error_status = filter_input( INPUT_GET, 'status', FILTER_DEFAULT );

		if ( 'error' === $error_status ) {
			wp_safe_redirect( $this->get_settings_page_url() . '&status=error' );
			exit;
		}

		delete_transient( 'uo-fb-group-transient-user-connected' );

		// Only update the record when there is a valid user.
		if ( isset( $settings['user']['id'] ) && isset( $settings['user']['token'] ) ) {
			// Updates the option value to settings.
			update_option( self::OPTION_KEY, $settings );
		}

		wp_safe_redirect( $this->get_settings_page_url() . '&connection=new' );

		exit;

	}

	/**
	 * WordPress ajax endpoint for disconnecting the Facebook User.
	 *
	 * @return void wp_die with error message if there is an error. Otherwise, redirects to the settings page.
	 */
	public function automator_integration_facebook_group_capture_token_disconnect() {

		if ( wp_verify_nonce( filter_input( INPUT_GET, 'nonce', FILTER_DEFAULT ), self::OPTION_KEY ) ) {

			delete_option( self::OPTION_KEY );

			delete_transient( 'uo-fb-group-transient-user-connected' );

			wp_safe_redirect( $this->get_settings_page_url() );

			exit;

		}

		wp_die( esc_html__( 'Nonce Verification Failed', 'uncanny-automator' ) );

	}

	/**
	 * Get the settings page URL.
	 *
	 * @return string
	 */
	public function get_settings_page_url() {

		return add_query_arg(
			array(
				'post_type'   => 'uo-recipe',
				'page'        => 'uncanny-automator-config',
				'tab'         => 'premium-integrations',
				'integration' => 'facebook-groups',
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Get the disconnect URL for the Facebook User.
	 *
	 * @return string The URL to use for disconnecting the Facebook User to Facebook Groups..
	 */
	public function get_disconnect_url() {

		return add_query_arg(
			array(
				'action' => self::AJAX_PREFIX . '_disconnect',
				'nonce'  => wp_create_nonce( self::OPTION_KEY ),
			),
			admin_url( 'admin-ajax.php' )
		);

	}

	/**
	 * Check if the user is connected.
	 *
	 * @return bool
	 */
	public function is_user_connected() {

		$settings = get_option( self::OPTION_KEY );

		$user_connected = $this->get_user_connected();

		return $settings && ( isset( $user_connected ) && ! empty( $user_connected['user_id'] ) );

	}

	/**
	 * Get the user connected.
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
	 * Get the connected user via transient.
	 *
	 * @param $user_id
	 * @param $token
	 *
	 * @return array|mixed
	 */
	public function transient_get_user_connected( $user_id, $token ) {

		$response = array(
			'user_id' => 0,
			'name'    => '',
			'picture' => '',
		);

		$transient_key = 'uo-fb-group-transient-user-connected';

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

			// Cache the request with 1 day lifetime.
			set_transient( $transient_key, $response, DAY_IN_SECONDS );

		}

		return $response;
	}

	/**
	 * Get the user access token from wp_options table.
	 *
	 * @return string The user access token.
	 */
	public function get_user_access_token() {

		$option = get_option( self::OPTION_KEY );

		return isset( $option['user']['token'] ) ? $option['user']['token'] : '';

	}

	/**
	 * Get the list of users groups saved in wp_options table.
	 *
	 * @return array $items The items saved in wp_options table.
	 */
	public function get_saved_groups() {

		$saved_groups = get_option( 'ua_facebook_group_saved_groups' );

		$items = array();

		if ( ! empty( $saved_groups ) ) {
			foreach ( $saved_groups as $group ) {
				$items[ $group['id'] ] = $group['text'];
			}
		}

		return $items;

	}

	/**
	 * The buttons for Verify App Install and help.
	 *
	 * @param string $action_meta The action meta.
	 * @param string $support_link The support link.
	 *
	 * @return array The buttons config.
	 */
	public function buttons( $action_meta, $support_link = 'https://automatorplugin.com/knowledge-base/' ) {
		return array(
			array(
				'show_in'     => $action_meta,
				'text'        => esc_attr__( 'Help', 'uncanny-automator' ),
				'css_classes' => 'uap-btn uap-btn--transparent',
				'on_click'    => 'function(){ window.open( "' . esc_url_raw( $support_link ) . '", "_blank" ); }',
			),
			array(
				'show_in'     => $action_meta,
				'text'        => esc_attr__( 'Verify app installation', 'uncanny-automator' ),
				'css_classes' => 'uap-btn uap-btn--primary',
				'on_click'    => $this->click_handler( $action_meta ),
			),
		);
	}

	/**
	 * Get the groups dropdown selector field.
	 *
	 * @param $action_meta string The action meta.
	 *
	 * @return array The group dropdown field array.
	 */
	public function get_groups_field( $action_meta = '' ) {
		return array(
			'option_code'              => $action_meta,
			'label'                    => esc_attr__( 'Facebook Group', 'uncanny-automator' ),
			'description'              => esc_attr__( 'The group you select must have the "Uncanny Automator" app installed. Click on the Verify app installation button below to confirm.', 'uncanny-automator' ),
			'input_type'               => 'select',
			'required'                 => true,
			'options'                  => $this->get_saved_groups(),
			'custom_value_description' => esc_html__( 'Group ID', 'uncanny-automator' ),
		);
	}

	/**
	 * The click handler for "Verify App Install" button.
	 *
	 * @todo: This function should be move to its own js file in the future.
	 * @param $action_meta string The action meta.
	 *
	 * @return void
	 */
	public function click_handler( $action_meta = '' ) {
		ob_start();
		?>
		<script>
			function ($button, data, modules) {

				$button.addClass('uap-btn--loading uap-btn--disabled');

				// Get the notices container
				let $noticesContainer = jQuery('.item[data-id="' + data.item.id + '"] .item-options__notices');

				// Get the ID of the selected group.
				let selected_group_id = data.values.<?php echo esc_js( $action_meta ); ?>;

				// Begin AJAX Request.
				jQuery.ajax({
					method: 'POST',
					url: '<?php echo esc_url( AUTOMATOR_API_URL . self::API_ENDPOINT ); ?>',
					data: {
						action: 'verify_app_install',
						group_id: selected_group_id
					},
					success: function (response) {

						let isFound = false;

						// Remove loading animation from the button
						$button.removeClass('uap-btn--loading uap-btn--disabled');

						if ( response.data.data.length >= 1 ) {
							isFound = true;
						}

						if ( isFound ) {
							$noticesContainer.html( '<div class="item-options__notice item-options__notice--success">' + '<?php echo esc_html__( 'Successfully validated the group.', 'uncanny-automator' ); ?>' + '</div>' );
							return;
						}

						$noticesContainer.html( '<div class="item-options__notice item-options__notice--warning">'+ '<?php echo esc_html__( 'The Uncanny Automator Facebook Group app is not installed on the selected group.', 'uncanny-automator' ); ?>' + ' <a href="https://automatorplugin.com/knowledge-base/facebook-groups/" target="_blank">Learn more</a>.</div>' );

						return;

					},

					error: function (response, message, details ) {

						$noticesContainer.html( '<div class="item-options__notice item-options__notice--error">' +response.responseText+ '</div>' );

						$button.removeClass('uap-btn--loading uap-btn--disabled');

					},

				});
			}
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Method api_request
	 *
	 * @param $params
	 *
	 * @return void
	 */
	public function api_request( $body, $action_data = null ) {

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => $body,
			'action'   => $action_data,
			'timeout'  => apply_filters( 'automator_integration_facebook_groups_api_request_timeout', 10 ), // Apply generous 15 seconds timeout.
		);

		$response = Api_Server::api_call( $params );

		$this->check_for_errors( $response );

		return $response;

	}

	/**
	 * Check for common errors. Used in the api_request method.
	 *
	 * @param $response array The response from API call.
	 *
	 * @throws \Exception.
	 */
	public function check_for_errors( $response ) {

		if ( isset( $response['data']['error']['message'] ) ) {

			throw new \Exception( $response['data']['error']['message'], $response['statusCode'] );

		}
	}

}
