<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator;

/**
 * Class WhatsApp_Helpers
 *
 * @package Uncanny_Automator
 */
class WhatsApp_Helpers {

	const GRAPH_URL = 'https://graph.facebook.com/debug_token';

	const CLIENT = 'automator_whatsapp_client';

	const API_ENDPOINT = 'v2/whatsapp';

	const WEBHOOK_KEY = 'uap_active_campaign_webhook_key';

	protected $whatsapp_settings = '';

	/**
	 * The options.
	 *
	 * @var mixed The options.
	 */
	public $options;

	/**
	 * Webhook endpoint.
	 *
	 * @var string
	 */
	public $webhook_endpoint;

	/**
	 * Set the options.
	 *
	 * @param WhatsApp_Helpers $options
	 */
	public function setOptions( Whatsapp_Helpers $options ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid

		$this->options = $options;

	}

	public function __construct() {

		// Disconnect.
		add_action( 'wp_ajax_automator_whatsapp_disconnect', array( $this, 'disconnect' ) );

		// Regenerate webhoook.
		add_action( 'wp_ajax_whatsapp-regenerate-webhook-key', array( $this, 'regenerate_webhook_key_ajax' ) );

		// The webhook's rest api endpoint.
		add_action( 'rest_api_init', array( $this, 'init_webhook' ) );

		// Flush expired transients.
		add_action( 'automator_whatsapp_flush_transient', array( $this, 'flush_transient' ) );

		// Message templates dropdown.
		add_action( 'wp_ajax_automator_whatsapp_list_message_templates', array( $this, 'list_message_templates' ) );

		// Message templates fields retrieval.
		add_Action( 'wp_ajax_automator_whatsapp_retrieve_template', array( $this, 'retrieve_template' ) );

		// Load the settings page.
		require_once __DIR__ . '/../settings/settings-whatsapp.php';

		// Webhook endpoint.
		$this->webhook_endpoint = apply_filters( 'automator_meta_webhook_endpoint', '/whatsapp', $this );

		$this->whatsapp_settings = new WhatsApp_Settings( $this );

	}

	/**
	 * Delete expired transient.
	 */
	public function flush_transient( $name = '' ) {

		delete_transient( $name );

	}

	/**
	 * Method is_webhook_response_valid.
	 *
	 * Checks if the incoming data from WhatsApp is valid or not valid.
	 *
	 * @param array $response The incoming data parsed as JSON.
	 *
	 * @return boolean True if data is okay. Otherwise, false.
	 */
	public function is_webhook_response_valid( $response = array() ) {

		$is_valid = true;

		// Chech if incoming message.
		if ( isset( $response['entry'][0]['changes'][0]['value']['messages'] ) ) {

			$message = $response['entry'][0]['changes'][0]['value']['messages'][0];

			return $this->validate_incoming_message( $message );

		}

		// Otherwise, assume sending message.
		if (
			! isset( $response['entry'] ) ||
			! isset( $response['entry'][0]['id'] ) ||
			! isset( $response['entry'][0]['changes'] ) ||
			! isset( $response['entry'][0]['changes'][0] ) ||
			! isset( $response['entry'][0]['changes'][0]['value']['metadata'] ) ||
			! isset( $response['entry'][0]['changes'][0]['value']['statuses'][0] )
		) {

			$is_valid = false;

		}

		return $is_valid;

	}

	public function validate_incoming_message( $message ) {
		return true;
	}

	public function is_timestamp_acceptable( $wa_timestamp = 0, $acceptable_interval = 10 ) {

		$wp_current_datetime = current_time( 'mysql' );

		$wp_current_datetime_object = new \DateTime( $wp_current_datetime, new \DateTimeZone( Automator()->get_timezone_string() ) );

		automator_log(
			array(
				'$wp_current_time'            => $wp_current_datetime,
				'$wp_current_datetime_object' => $wp_current_datetime_object,
			),
			'WhatsApp: Method is_timestamp_acceptable params',
			AUTOMATOR_DEBUG_MODE,
			'whatsapp-params'
		);

		if ( false === $wp_current_datetime_object ) {

			return false;

		}
		// Set the timezone to UTC.
		$wp_current_datetime_object->setTimezone( new \DateTimeZone( 'UTC' ) );
		// Get the timestamp.
		$wp_current_datetime_utc = strtotime( $wp_current_datetime_object->format( 'Y-m-d H:i:s' ) );

		automator_log(
			array(
				'calculated'           => absint( $wp_current_datetime_utc - $wa_timestamp ),
				'$acceptable_interval' => $acceptable_interval,
			),
			'WhatsApp: Method is_timestamp_acceptable params',
			AUTOMATOR_DEBUG_MODE,
			'whatsapp-processed'
		);
		// Compare if it was recently accepted.
		return absint( $wp_current_datetime_utc - $wa_timestamp ) <= $acceptable_interval;

	}

	/**
	 * Method extract_response.
	 *
	 * @thows \Exception
	 *
	 * @return array The extracted data from the Webhook.
	 */
	public function extract_response( $response = array() ) {

		if ( ! $this->is_webhook_response_valid( $response ) ) {

			throw new \Exception( 'Malformed data.', 403 );

		}

		if ( isset( $response['entry'][0]['changes'][0]['value']['messages'] ) ) {

			return $this->extract_receiving_message_response( $response );

		}

		return $this->extract_sending_message_response( $response );

	}

	/**
	 * Method extract_receiving_message_response.
	 *
	 * Extracts the incoming webhook data from message received status.
	 *
	 * @param array $response The response from Meta.
	 *
	 * @return array The incoming webhook data.
	 */
	public function extract_receiving_message_response( $response = array() ) {

		$timestamp = $response['entry'][0]['changes'][0]['value']['messages'][0]['timestamp'];

		/**
		 * Prevent spammy WhatsApp webhook. This is an old issue where WhatsApp repeatedly sends the webhook payload to the URL.
		 * We have to do this to safe guard the users and not let WhatsApp spam their logs with incoming webhooks.
		 * By default, its enabled.
		 *
		 * When the Trigger is erratically firing,
		 * try increasing the acceptable interval first via 'automator_whatsapp_acceptable_interval'
		 *
		 * @default int 5 - Five seconds.
		 * @filter automator_whatsapp_acceptable_interval
		 **/
		$acceptable_interval = apply_filters( 'automator_whatsapp_acceptable_interval', 5, $response, $this );

		/**
		 * Disable timestamp validation.
		 *
		 * Otherwise, you may completely disable the timestamp validation.
		 *
		 * @default true
		 * @filter automator_whatsapp_timestamp_validation_enabled
		 */
		$timestamp_validation = apply_filters( 'automator_whatsapp_timestamp_validation_enabled', true, $response, $this );

		if ( true === $timestamp_validation && ! $this->is_timestamp_acceptable( $timestamp, $acceptable_interval ) ) {
			throw new \Exception( 'Stale data: WhatsApp Bug. Do not process.' );
		}

		$default = array(
			'from'      => '',
			'wamid'     => 0,
			'body'      => '',
			'timestamp' => '',
		);

		$message = $response['entry'][0]['changes'][0]['value']['messages'][0];

		$text_body = $this->extract_message( $message );

		$args = array(
			'from'      => $message['from'],
			'wamid'     => $message['id'],
			'body'      => $text_body,
			'timestamp' => $timestamp,
			'_response' => $response, // Send the whole response to the Trigger.
		);

		do_action( 'automator_whatsapp_webhook_message_received', $args );

		return wp_parse_args( $args, $default );

	}

	/**
	 * @param mixed[] $message;
	 *
	 * @return string Returns the message json string.
	 */
	protected function extract_message( $message ) {

		$type = isset( $message['type'] ) ? $message['type'] : 'text';

		switch ( $type ) {

			case 'text':
				// Return the text body.
				return isset( $message['text']['body'] ) ? $message['text']['body'] : '';
			case 'image':
				$caption          = isset( $message['image']['caption'] ) ? $message['image']['caption'] : '';
				$image_id         = isset( $message['image']['id'] ) ? $message['image']['id'] : '';
				$image_id_caption = sprintf( '(%1$s) %2$s', $image_id, $caption );
				// Return the image id + caption.
				return apply_filters( 'automator_whatsapp_image_caption', $image_id_caption, $message );
			case 'button':
				$button_text = isset( $message['button']['text'] ) ? $message['button']['text'] : '';
				// Return the button text.
				return apply_filters( 'automator_whatsapp_button_text', $button_text, $message );
			default:
				// Otherwise, just return the type and message ID for now.
				$default = sprintf( '(%s) %s', $type, $message['id'] );
				return apply_filters( 'automator_whatsapp_default_type_message_return', $default, $message );
		}

	}

	/**
	 * Method extract_sending_message_response.
	 *
	 * Extracts the incoming webhook data from message sending status.
	 *
	 * @param array $response The response from Meta.
	 *
	 * @return array The incoming webhook data.
	 */
	public function extract_sending_message_response( $response ) {

		$timestamp = $response['entry'][0]['changes'][0]['value']['statuses'][0]['timestamp'];

		// Prevent spammy WhatsApp webhook.
		if ( ! $this->is_timestamp_acceptable( $timestamp ) ) {
			throw new \Exception( 'Stale data: WhatsApp Bug. Do not process.' );
		}

		$default = array(
			'to'        => '',
			'from'      => '',
			'wamid'     => 0,
			'object'    => 'unknown',
			'status'    => '',
			'errors'    => array(
				'code'  => null,
				'title' => '',
			),
			'entry_id'  => 0,
			'timestamp' => 0,
		);

		// The $response is already validated in method `is_webhook_response_valid`. Assume they are all valid at this point.
		$args = array(
			'to'        => $response['entry'][0]['changes'][0]['value']['statuses'][0]['recipient_id'],
			'from'      => $response['entry'][0]['changes'][0]['value']['metadata']['display_phone_number'],
			'object'    => 'whatsapp',
			'status'    => $response['entry'][0]['changes'][0]['value']['statuses'][0]['status'],
			'wamid'     => $response['entry'][0]['changes'][0]['value']['statuses'][0]['id'],
			'entry_id'  => $response['entry'][0]['id'],
			'timestamp' => $timestamp,
			'errors'    => $this->extract_errors( $response ),
		);

		return wp_parse_args( $args, $default );

	}

	public function extract_errors( $response = array() ) {

		$error = array(
			'code'    => null,
			'message' => '',
		);

		if ( isset( $response['entry'][0]['changes'][0]['value']['statuses'][0]['errors'] ) ) {

			$error['code'] = $response['entry'][0]['changes'][0]['value']['statuses'][0]['errors'][0]['code'];

			$error['message'] = $response['entry'][0]['changes'][0]['value']['statuses'][0]['errors'][0]['title'];

		}

		return $error;

	}

	/**
	 * Completes the action based on WhatsApp incoming webhook data.
	 *
	 * @param array $action_data
	 * @param array $incoming_data
	 *
	 * @return void
	 */
	public function complete_action( $action_data = array(), $incoming_data = array() ) {

		$completed = 1;

		$error_message = '';

		// Get the meta.
		if ( 'failed' === $incoming_data['status'] ) {

			$recipe_error = Automator()->db->action->get_error_message( $action_data['recipe_log_id'] );

			if ( ! empty( $recipe_error ) ) {

				$completed = 2;

				$error_message = implode( ' - ', array_values( $incoming_data['errors'] ) ) . '<br>';

				// Skip awaiting error message.
				if ( 10 !== intval( $recipe_error->completed ) && $error_message !== $recipe_error->error_message ) {
					// Append the error message so previous error message wont get overwritten.
					$error_message .= $recipe_error->error_message;
				}
			}
		}

		Automator()->db->action->mark_complete( $action_data['action_id'], $action_data['recipe_log_id'], $completed, $error_message );

		Automator()->db->recipe->mark_complete( $action_data['recipe_log_id'], $completed );

		// e.g. `automator_whatsapp_message_delivery_failed`.
		do_action( 'automator_whatsapp_message_delivery_' . $incoming_data['status'], $incoming_data );
		do_action( 'automator_whatsapp_message_status', $incoming_data, $incoming_data['status'] );

	}

	/**
	 * Completes the recipe depending on incoming WhatsApp data.
	 *
	 * @param array $response The incoming webhook data from WhatsApp.
	 *
	 * @return void
	 */
	public function complete_recipe( $response ) {

		try {

			$incoming_data = $this->extract_response( $response );

			$action_data = $this->get_action_data_by_wamid( $incoming_data['wamid'] );

			// Bail out if meta is empty.
			if ( empty( $action_data['meta'] ) ) {
				automator_log( $action_data, 'WhatsApp: Bailed out. The $action_data["meta"] was empty.', AUTOMATOR_DEBUG_MODE, 'whatsapp' );
				return;
			}

			$this->complete_action( $action_data, $incoming_data );

		} catch ( \Exception $e ) { //phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Do nothing for now.
			automator_log( $e->getMessage(), 'WhatsApp: An Exception has occured', AUTOMATOR_DEBUG_MODE, 'whatsapp' );

		}

	}

	public function get_action_data_by_wamid( $wamid = '' ) {

		global $wpdb;

		// Can be written as Utility.
		$wamid = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}uap_action_log_meta WHERE meta_key = %s AND meta_value = %s",
				'whatsapp_wamid',
				$wamid
			)
		);

		if ( empty( $wamid ) ) {
			return array();
		}

		// Can be written as Utility.
		$meta = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}uap_action_log_meta WHERE meta_key = %s AND automator_action_log_id = %d AND automator_action_id = %d",
				'whatsapp_meta',
				$wamid->automator_action_log_id,
				$wamid->automator_action_id
			)
		);

		$action_meta = json_decode( $meta->meta_value );

		return array(
			'action_id'     => $wamid->automator_action_id,
			'recipe_log_id' => $action_meta->recipe_log_id,
			'meta'          => $action_meta,
		);

	}

	public function init_webhook() {

		if ( $this->is_connected() ) {

			register_rest_route(
				AUTOMATOR_REST_API_END_POINT,
				$this->webhook_endpoint,
				array(
					'methods'             => array( 'POST', 'GET' ),
					'callback'            => array( $this, 'webhook_callback' ),
					'permission_callback' => array( $this, 'validate_webhook' ),
				)
			);
		}

	}

	/**
	 * Validate the incoming webhook
	 *
	 * @param  mixed $request
	 * @return void
	 */
	public function validate_webhook( $request ) {

		$user_agent = $request->get_header( 'user_agent' ); //

		$allowed_user_agents = array(
			'facebookexternalua',
			'facebookplatform/1.0 (+http://developers.facebook.com)',
		);

		// Fail if user agent is not from Facebook.
		if ( ! in_array( $user_agent, $allowed_user_agents, true ) ) {
			return false;
		}

		$secret = automator_get_option( 'automator_whatsapp_secret', null );

		$x_hub_signature = $request->get_header( 'x-hub-signature' );

		if ( ! empty( $x_hub_signature ) ) {
			$is_payload_geniune = hash_equals(
				explode( '=', $x_hub_signature )[1],
				hash_hmac( 'sha256', $request->get_body(), $secret )
			);
			// Recommended: Check if $is_payload_geniune is true or false.
		}

		$query_params = $request->get_query_params();

		if ( ! isset( $query_params['key'] ) ) {
			return false;
		}

		$actual_key = $this->get_webhook_key();

		if ( $actual_key !== $query_params['key'] ) {

			return false;

		}

		return true;

	}

	/**
	 * Callback method to our REST API endpoint.
	 *
	 * @param mixed $request The incoming webhook data.
	 *
	 * @return {HTTP 200 Ok}
	 */
	public function webhook_callback( $request ) {

		// Hub challenge verification.
		if ( ! empty( $request->get_param( 'hub_challenge' ) ) ) {

			if ( $request->get_param( 'hub_verify_token' ) !== automator_get_option( self::WEBHOOK_KEY, false ) ) {

				wp_send_json_error( null, 403 );

			}

			echo esc_html( $request->get_param( 'hub_challenge' ) );

			exit;

		}

		$this->complete_recipe( $request->get_params() );

		// Send 200 'Ok' status.
		http_response_code( 200 );

		exit;

	}

	/**
	 * The ajax endpoint
	 *
	 * @return void
	*/
	public function regenerate_webhook_key_ajax() {

		$this->regenerate_webhook_key();

		$uri = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-config&tab=premium-integrations&integration=whatsapp';

		wp_safe_redirect( $uri );

		exit;

	}

	/**
	 * Generate webhook key.
	 *
	 * @return void
	 */
	public function regenerate_webhook_key() {

		$new_key = md5( uniqid( wp_rand(), true ) );

		update_option( self::WEBHOOK_KEY, $new_key );

		return $new_key;

	}

	/**
	 * Retrieve the webhook key.
	 *
	 * @return void
	 */
	public function get_webhook_key() {

		$webhook_key = automator_get_option( self::WEBHOOK_KEY, false );

		if ( false === $webhook_key ) {

			$webhook_key = $this->regenerate_webhook_key();

		}

		return $webhook_key;
	}

	/**
	 * Get the webhook uri.
	 *
	 * @return void
	 */
	public function get_webhook_url() {

		return urldecode(
			add_query_arg(
				array(
					'key' => $this->get_webhook_key(),
				),
				get_rest_url() . AUTOMATOR_REST_API_END_POINT . $this->webhook_endpoint
			)
		);

	}

	public function get_disconnect_url() {

		return add_query_arg(
			array(
				'action' => 'automator_whatsapp_disconnect',
				'nonce'  => wp_create_nonce( 'automator_whatsapp_disconnect' ),
			),
			admin_url( 'admin-ajax.php' )
		);

	}

	public function disconnect() {

		// Delete the message template dropdown transient.
		delete_transient( $this->get_dropdown_transient_key() );

		// Remove the client.
		delete_option( self::CLIENT );

		// Remove phone id value.
		delete_option( WhatsApp_Settings::PHONE_ID );

		// Remove access token value.
		delete_option( WhatsApp_Settings::ACCESS_TOKEN );

		// Remove business account ID
		delete_option( WhatsApp_Settings::BUSINESS_ID );

		wp_safe_redirect(
			add_query_arg(
				array( 'disconnected' => 'yes' ),
				$this->whatsapp_settings->get_settings_url()
			)
		);

		die;

	}

	public function verify_token( $access_token = '' ) {

		$response = wp_remote_get(
			add_query_arg(
				array(
					'access_token' => $access_token,
					'input_token'  => $access_token,
				),
				/**
				 * We're validating the provided access token to the Facebook Graph directly.
				 * At this point, the user is not connected and they would use their own application.
				 * The debug_token endpoint is a generic endpoint which would work for all of the Meta API services.
				 *
				 * We can move this to our API and provide an access token debugger service. (e.g /v2/meta?action=token_debugger)
				 **/
				self::GRAPH_URL
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \Exception( $response->get_error_message(), 400 );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		// Check for any errors.
		if ( ! empty( $data['error']['message'] ) ) {
			throw new \Exception( 'Meta OAuthException: ' . $data['error']['message'], 403 );
		}

		// Check for missing scopes.
		if ( $this->has_missing_scopes( $data['data'] ) ) {
			throw new \Exception( __( 'The provided access token contains missing permissions. Make sure both whatsapp_business_management and whatsapp_business_messaging permissions are included.' ), 400 );
		}

		return array(
			'data' => $data,
		);

	}

	public function get_client() {

		$option = automator_get_option( self::CLIENT, array() );

		return ! empty( $option['data']['data'] ) ? $option['data']['data'] : array();

	}

	public function has_missing_scopes( $client = array() ) {

		if ( empty( $client ) || empty( $client['scopes'] ) ) {
			return false;
		}

		$required_scopes = array(
			'whatsapp_business_management',
			'whatsapp_business_messaging',
		);

		// Would return false if either one of the required scopes is missing.
		return count( array_intersect( $client['scopes'], $required_scopes ) ) < 2;

	}

	public function get_phone_number_id() {

		return absint( automator_get_option( WhatsApp_Settings::PHONE_ID, 0 ) );

	}

	public function get_access_token() {

		return trim( automator_get_option( WhatsApp_Settings::ACCESS_TOKEN, '' ) );

	}

	public function is_connected() {

		return ! empty( $this->get_client() ) && ! $this->has_missing_scopes( $this->get_client() );

	}

	public function list_message_templates() {

		Automator()->utilities->ajax_auth_check();

		if ( false !== get_transient( $this->get_dropdown_transient_key() ) ) {

			wp_send_json( get_transient( $this->get_dropdown_transient_key() ) );

		}

		$dropdown_values = array();

		try {

			$body = array(
				'action'       => 'list_template',
				'business_id'  => automator_get_option( WhatsApp_Settings::BUSINESS_ID ),
				'access_token' => $this->get_access_token(),
			);

			$response = $this->api_call( $body, null );

			$dropdown_values = $this->interpret_whatsapp_response_as_dropdown( $response );

		} catch ( \Exception $e ) {

			wp_send_json(
				array(
					array(
						'text'  => 'Error: ' . $e->getCode() . ': ' . $e->getMessage(),
						'value' => $e->getCode(),
					),
				)
			);

		}

		wp_send_json( $dropdown_values );

	}

	public function get_dropdown_transient_key() {
		return 'automator_whatsapp_message_templates_dropdown_' . automator_get_option( WhatsApp_Settings::BUSINESS_ID, '' );
	}

	public function retrieve_template() {

		Automator()->utilities->ajax_auth_check();

		list( $template, $language ) = explode( '|', automator_filter_input( 'template', INPUT_POST ) );

		try {

			$body = array(
				'action'       => 'list_template',
				'business_id'  => get_option( WhatsApp_Settings::BUSINESS_ID ),
				'access_token' => $this->get_access_token(),
			);

			$response = $this->api_call( $body, null );

			if ( ! empty( $response['data']['data'] ) ) {
				foreach ( $response['data']['data'] as $data ) {
					if ( $template === $data['name'] && $language === $data['language'] ) {
						wp_send_json( $data );
					}
				}
			}

			// Delete the transient.
			delete_transient( $this->get_dropdown_transient_key() );

			wp_send_json_error(
				array(
					'message' => __( 'Cannot find the structure for the selected template. Please refresh the page and try again later.', 'uncanny_automator' ),
				),
				400
			);

		} catch ( \Exception $e ) {

			wp_send_json_error(
				array(
					'message' => strtr(
						__( 'An unexpected error has with status code [{{status_code}}] has occured. Message: {{error_message}}', 'uncanny_automator' ),
						array(
							'{{status_code}}'   => $e->getCode(),
							'{{error_message}}' => $e->getMessage(),
						)
					),
				),
				$e->getCode()
			);

		}

		die;

	}

	public function interpret_whatsapp_response_as_dropdown( $response ) {

		$list = array();

		if ( ! empty( $response['data']['data'] ) ) {

			foreach ( $response['data']['data'] as $data ) {

				if ( 'APPROVED' === $data['status'] ) {
					$list[] = array(
						'text'  => sprintf( '%1$s (%2$s)', $data['name'], $data['language'] ),
						'value' => $data['name'] . '|' . $data['language'],
					);
				}
			}
		}

		set_transient( $this->get_dropdown_transient_key(), $list, MINUTE_IN_SECONDS * 60 );

		return $list;
	}

	/**
	 * Method api_call
	 *
	 * @param  array $body The request body form-data.
	 * @param  array $action The Automator Action parameters.
	 *
	 * @return array API response.
	 */
	public function api_call( $body, $action = null ) {

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => $body,
			'action'   => $action,
			'timeout'  => 10,
		);

		$response = Api_Server::api_call( $params );

		if ( 200 !== $response['statusCode'] ) {
			throw new \Exception( $params['endpoint'] . ' failed' );
		}

		return $response;

	}

}
