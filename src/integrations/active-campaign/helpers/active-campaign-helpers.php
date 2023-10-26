<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator;

use Exception;
use Uncanny_Automator\Api_Server;
use Uncanny_Automator\Utilities\Automator_Http_Response_Code;
use WP_Error;

/**
 * Class Active_Campaign_Helpers
 *
 * @package Uncanny_Automator
 */
class Active_Campaign_Helpers {

	/**
	 * The API endpoint address.
	 *
	 * @var API_ENDPOINT The endpoint adress.
	 */
	const API_ENDPOINT = 'v2/active-campaign';

	/**
	 * The options.
	 *
	 * @var mixed The options.
	 */
	public $options;

	/**
	 * The trigger options.
	 *
	 * @var mixed The trigger options.
	 */
	public $load_options;

	/**
	 * Webhook url.
	 *
	 * @var string
	 */
	public $webhook_url;

	/**
	 * Webhook endpoint.
	 *
	 * @var string
	 */
	public $webhook_endpoint;

	/**
	 * The settings tab id.
	 *
	 * @var string $setting_tab
	 */
	public $setting_tab = 'active-campaign';

	/**
	 * The tab URL.
	 *
	 * @var string $tab_url
	 */
	public $tab_url = '';

	/**
	 * Active_Campaign_helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );

		$this->tab_url = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-config&tab=premium-integrations&integration=' . $this->setting_tab;

		// Add the ajax endpoints.
		add_action( 'wp_ajax_active-campaign-list-tags', array( $this, 'list_tags' ) );
		add_action( 'wp_ajax_active-campaign-list-tags-triggers', array( $this, 'list_tags_triggers' ) );
		add_action( 'wp_ajax_active-campaign-list-contacts', array( $this, 'list_contacts' ) );
		add_action( 'wp_ajax_active-campaign-list-retrieve', array( $this, 'list_retrieve' ) );
		add_action( 'wp_ajax_active-campaign-disconnect', array( $this, 'disconnect' ) );

		add_action( 'wp_ajax_active-campaign-regenerate-webhook-key', array( $this, 'regenerate_webhook_key_ajax' ) );
		add_action( 'wp_ajax_active-campaign-sync-data', array( $this, 'ac_sync_data' ) );

		$this->webhook_endpoint = apply_filters( 'automator_active_campaign_webhook_endpoint', '/active-campaign', $this );

		add_action( 'rest_api_init', array( $this, 'init_webhook' ) );

		add_action( 'add_option_uap_active_campaign_settings_timestamp', array( $this, 'settings_updated' ), 9999 );
		add_action( 'update_option_uap_active_campaign_settings_timestamp', array( $this, 'settings_updated' ), 9999 );

		$this->load_settings_tab();
	}


	/**
	 * Checks if webhook is enabled or not. We need to support both 'on' and 1 values for backwards compatibility.
	 *
	 * @return void
	 */
	public function is_webhook_enabled() {

		$webhook_enabled_option = automator_get_option( 'uap_active_campaign_enable_webhook', false );

		// The get_option can return string or boolean sometimes.
		if ( 'on' === $webhook_enabled_option || 1 == $webhook_enabled_option ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
			return true;
		}

		return false;
	}

	public function load_settings_tab() {
		// Load the settings page.
		require_once __DIR__ . '/../settings/settings-active-campaign.php';

		new Active_Campaign_Settings( $this );
	}

	public function integration_status() {

		if ( ! $this->has_connection_data() ) {
			return '';
		}

		$users = get_option( 'uap_active_campaign_connected_user', array() );

		if ( empty( $users[0]['email'] ) ) {
			return '';
		}

		return 'success';
	}

	/**
	 * Set the options.
	 *
	 * @param Active_Campaign_helpers $options
	 */
	public function setOptions( Active_Campaign_helpers $options ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->options = $options;
	}

	/**
	 * Checks if the user has valid license in pro or free version.
	 *
	 * @return boolean.
	 */
	public function has_valid_license() {

		$has_pro_license  = false;
		$has_free_license = false;

		$free_license_status = automator_get_option( 'uap_automator_free_license_status' );
		$pro_license_status  = automator_get_option( 'uap_automator_pro_license_status' );

		if ( defined( 'AUTOMATOR_PRO_FILE' ) && 'valid' === $pro_license_status ) {
			$has_pro_license = true;
		}

		if ( 'valid' === $free_license_status ) {
			$has_free_license = true;
		}

		return $has_free_license || $has_pro_license;

	}

	/**
	 * Checks if screen is from the modal action popup or not.
	 *
	 * @return boolean.
	 */
	public function is_from_modal_action() {

		$minimal = filter_input( INPUT_GET, 'automator_minimal', FILTER_DEFAULT );

		$hide_settings_tabs = filter_input( INPUT_GET, 'automator_hide_settings_tabs', FILTER_DEFAULT );

		return ! empty( $minimal ) && ! empty( $hide_settings_tabs ) && ! empty( $hide_settings_tabs );
	}

	/**
	 * Check if the 3rd-party integration has any connection api stored.
	 *
	 * @return boolean.
	 */
	public function has_connection_data() {

		$settings_url = get_option( 'uap_active_campaign_api_url', '' );
		$settings_key = get_option( 'uap_active_campaign_api_key', '' );

		if ( empty( $settings_key ) || empty( $settings_url ) ) {
			return false;
		}

		return true;
	}

	public function list_retrieve() {

		Automator()->utilities->ajax_auth_check();

		$lists = get_transient( 'ua_ac_list_group' );

		if ( false === $lists ) {
			$lists = $this->sync_lists( false );
		}

		wp_send_json( $lists );

	}


	/**
	 * Lists all available tags.
	 *
	 * Syncs the tag in case the transients has expired.
	 *
	 * @return void
	 */
	public function list_tags() {

		Automator()->utilities->ajax_auth_check();

		$lists = get_transient( 'ua_ac_tag_list' );

		if ( false === $lists ) {
			$lists = $this->sync_tags( false );
		}

		wp_send_json( $lists );

	}

	/**
	 * Lists all available tags for triggers. Adds `Any` option.
	 *
	 * Syncs the tag in case the transients has expired.
	 *
	 * @return void
	 */
	public function list_tags_triggers() {

		Automator()->utilities->ajax_auth_check();

		$any_option = array(
			array(
				'text'  => esc_html__( 'Any tag' ),
				'value' => -1,
			),
		);

		$tags = get_transient( 'ua_ac_tag_list' );

		if ( false === $tags ) {
			$tags = $this->sync_tags( false );
		}

		if ( empty( $tags ) || ! is_array( $tags ) ) {
			return $any_option;
		}

		/**
		 * Assigns the Tag's text as Tag's value.
		 *
		 * @see $this->get_tags()
		 */
		$tags = array_map(
			function( $tag ) {
				$tag['value'] = $tag['text'];
				return $tag;
			},
			$tags
		);

		wp_send_json( array_merge( $any_option, $tags ) );

	}

	public function list_contacts() {

		Automator()->utilities->ajax_auth_check();

		$saved_contact_list = get_transient( 'ua_ac_contact_list' );

		if ( false !== $saved_contact_list ) {
			wp_send_json( $saved_contact_list );
		}

		try {
			$body = array(
				'action' => 'list_contacts',
			);

			$response = $this->api_request( $body );

			if ( empty( $response['data']['contacts'] ) ) {
				throw new \Exception( 'The account has no contacts' );
			}

			$contact_items = array();

			foreach ( $response['data']['contacts'] as $contact ) {
				$contact_items[] = array(
					'value' => $contact['id'],
					'text'  => sprintf(
						'%s (%s)',
						implode( ' ', array( $contact['firstName'], $contact['lastName'] ) ), // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						$contact['email']
					),
				);
			}

			set_transient( 'ua_ac_contact_list', $contact_items, HOUR_IN_SECONDS );
			wp_send_json( $contact_items );

		} catch ( \Exception $e ) {
			wp_send_json(
				array(
					array(
						'text'  => $e->getMessage(),
						'value' => 0,
					),
				)
			);
		}

		wp_send_json( array() );
	}

	/**
	 * Removes all option. Automatically disconnects the account.
	 */
	public function disconnect() {

		delete_option( 'uap_active_campaign_api_url' );
		delete_option( 'uap_active_campaign_api_key' );
		delete_option( 'uap_active_campaign_settings_timestamp' );
		delete_transient( 'uap_active_campaign_connected_user' );
		delete_option( 'uap_active_campaign_connected_user' );
		delete_option( 'uap_active_campaign_enable_webhook' );
		delete_option( 'uap_active_campaign_webhook_key' );

		$uri = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-config&tab=premium-integrations&integration=active-campaign';
		wp_safe_redirect( $uri );

		exit;

	}

	/**
	 * Get the saved user info from wp_options.
	 *
	 * @return mixed the connection data.
	 */
	public function get_connected_users() {

		$account_url = get_option( 'uap_active_campaign_api_url', false );
		$api_key     = get_option( 'uap_active_campaign_api_key', false );
		$users       = false;

		if ( empty( $account_url ) || empty( $api_key ) ) {
			throw new \Exception( __( 'ActiveCampaign is not connected', 'uncanny-automator' ) );
		}

		if ( ! wp_http_validate_url( $account_url ) ) {
			throw new \Exception( __( 'The account URL is not a valid URL', 'uncanny-automator' ) );
		}

		$params = array(
			'method'  => 'GET',
			'url'     => sprintf( '%s/api/3/users', esc_url( $account_url ) ),
			'headers' => array(
				'Api-token' => $api_key,
				'Accept'    => 'application/json',
			),
		);

		$response = Api_Server::call( $params );

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			throw new \Exception( __( 'Error validating the credentials', 'uncanny-automator' ) );
		}

		$response = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $response['users'] ) ) {
			throw new \Exception( __( 'User was not found', 'uncanny-automator' ) );
		}

		update_option( 'uap_active_campaign_connected_user', $response['users'] );

		return $response['users'];

	}

	/**
	 * Get the user by email.
	 *
	 * @param string $email The email of the contact.
	 *
	 * @return array The contact data.
	 */
	public function get_user_by_email( $email = '' ) {

		$body = array(
			'action' => 'get_contact_by_email',
			'email'  => $email,
		);

		$response = $this->api_request( $body );

		if ( empty( $response['data']['contacts'] ) ) {

			$message = sprintf(
				_x( 'ActiveCampaign has responded with status code: %1$d (%3$s) &mdash; %2$s', 'ActiveCampaign', 'uncanny-automator' ),
				$response['statusCode'],
				sprintf( 'The contact %s does not exist in ActiveCampaign', $email ),
				Automator_Http_Response_Code::text( $response['statusCode'] )
			);

			throw new \Exception( $message, 404 );

		}

		return array_shift( $response['data']['contacts'] );

	}

	/**
	 * get_email_id
	 *
	 * @param  string $email
	 * @return string $id
	 */
	public function get_email_id( $email ) {

		$contact = $this->get_user_by_email( $email );

		if ( empty( $contact['id'] ) ) {
			throw new \Exception( "Contact ID wasn't found" );
		}

		return $contact['id'];
	}

	/**
	 * The ajax endpoint
	 *
	 * @return void
	 */
	public function regenerate_webhook_key_ajax() {
		$this->regenerate_webhook_key();
		$uri = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-config&tab=premium-integrations&integration=active-campaign';
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

		update_option( 'uap_active_campaign_webhook_key', $new_key );

		return $new_key;

	}

	/**
	 * Retrieve the webhook key.
	 *
	 * @return void
	 */
	public function get_webhook_key() {

		$webhook_key = get_option( 'uap_active_campaign_webhook_key', false );

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

		return $this->webhook_endpoint . '?key=' . $this->get_webhook_key();

	}

	/**
	 * Ajax callback function to get all tags and list from AC.
	 *
	 * @return void
	 */
	public function ac_sync_data() {

		$tags           = $this->sync_tags();
		$lists          = $this->sync_lists();
		$contact_fields = $this->sync_contact_fields();

		$response = array(
			'success'                  => true,
			'messages'                 => _x( 'Tags, lists, and custom fields has been successfully refreshed', 'ActiveCampaign', 'uncanny-automator' ),
			'is_tags_synced'           => true,
			'is_lists_synced'          => true,
			'is_contact_fields_synced' => true,
		);

		$errors = array();

		// Push lists errors.
		if ( is_wp_error( $lists ) ) {
			$errors[] = $lists->get_error_message();
			// Mark as false.
			$response['is_lists_synced'] = false;
		}

		// Push tags errors.
		if ( is_wp_error( $tags ) ) {
			$errors[] = $tags->get_error_message();
			// Mark as false.
			$response['is_tags_synced'] = false;
		}

		// Push contact fields.
		if ( is_wp_error( $contact_fields ) ) {
			$errors[] = $contact_fields->get_error_message();
			// Mark as false.
			$response['is_contact_fields_synced'] = false;
		}

		if ( ! empty( $errors ) ) {

			$error_html = '<ul style="list-style-position: outside">';

			foreach ( $errors as $error ) {
				$error_html .= '<li>' . esc_html( $error ) . '</li>';
			}

			$error_html .= '</ul>';

			$response['messages'] = nl2br( $error_html );
		}

		wp_send_json( $response, 200 );

	}

	/**
	 * Get the tags.
	 *
	 * Warning! This function will return tag names as the option values, because the incoming webhooks return names and not ids as well.
	 * It also doesn't cache the results, so it should only be used in postponed options load.
	 *
	 * @deprecated 4.10
	 *
	 * @return array The list of tags.
	 */
	public function get_tags() {

		$tag_items = array();

		try {

			$body = array(
				'action' => 'list_tags',
			);

			$response = $this->api_request( $body );

			if ( ! empty( $response['data']['tags'] ) ) {

				foreach ( $response['data']['tags'] as $tag ) {
					$tag_items[] = array(
						'value' => $tag['tag'],
						'text'  => $tag['tag'],
					);
				}
			}
		} catch ( \Exception $e ) {
			$tag_items[] = array(
				'value' => 0,
				'text'  => $e->getMessage(),
			);
		}

		return $tag_items;
	}

	public function get_tag_options( $code, $any = false ) {

		$tags_dropdown = array(
			'option_code'           => $code,
			'label'                 => __( 'Tag', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'is_ajax'               => true,
			'endpoint'              => 'active-campaign-list-tags-triggers',
			'supports_custom_value' => true,
			'options'               => array(),
		);

		return array(
			'options' => array(
				$tags_dropdown,
			),
		);
	}

	/**
	 * Initialize the incoming webhook if it's enabled
	 *
	 * @return void
	 */
	public function init_webhook() {

		if ( $this->is_webhook_enabled() && $this->has_connection_data() ) {
			register_rest_route(
				AUTOMATOR_REST_API_END_POINT,
				$this->webhook_endpoint,
				array(
					'methods'             => array( 'POST' ),
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

		$query_params = $request->get_query_params();

		if ( ! isset( $query_params['key'] ) ) {
			return false;
		}

		$actual_key = $this->get_webhook_key();
		if ( $actual_key !== $query_params['key'] ) {
			return false;
		}

		//Active campaign doesn't provide any means to validate their calls. We have submitted a feature requests here:
		//https://ideas.activecampaign.com/ideas/AC-I-19435

		return true;
	}

	/**
	 * This function will fire for valid incoming webhook calls
	 *
	 * @param  mixed $request
	 * @return void
	 */
	public function webhook_callback( $request ) {

		$body = $request->get_body_params();

		do_action( 'automator_active_campaign_webhook_received', $body );

		exit;

	}

	/**
	 * Get all active campaign tags.
	 *
	 * @return mixed Boolean false if not successful. Otherwise, array list of the active campaign tags.
	 */
	public function sync_tags( $should_verify_nonce = true ) {

		if ( $should_verify_nonce ) {
			if ( ! wp_verify_nonce( automator_filter_input( 'nonce', INPUT_POST ), 'uncanny_automator' ) ) {
				return new WP_Error( 403, 'Forbidden. Invalid nonce.' );
			}
		}

		$settings_url = get_option( 'uap_active_campaign_api_url', '' );
		$settings_key = get_option( 'uap_active_campaign_api_key', '' );

		if ( empty( $settings_url ) || empty( $settings_key ) ) {
			return new WP_Error( 403, 'Invalid ActiveCampaign URL or Api key' );
		}

		$offset         = 0;
		$limit          = 100;
		$has_items      = true;
		$available_tags = array();

		while ( $has_items ) {

			$response = wp_safe_remote_get(
				$settings_url . '/api/3/tags?limit=' . $limit . '&offset=' . $offset,
				array(
					'headers' => array(
						'Api-token' => $settings_key,
					),
				)
			);

			// Logs wp related errors.
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$status_code = wp_remote_retrieve_response_code( $response );

			$response = (array) json_decode( wp_remote_retrieve_body( $response ), true );

			// Logs generic http error response.
			if ( 200 !== $status_code ) {

				$error_message = sprintf(
					'ActiveCampaign API has responded with status code %d (%s) while syncing list tags ',
					$status_code,
					Automator_Http_Response_Code::text( $status_code )
				);

				if ( empty( $response ) ) {
					$error_message .= '&mdash; Try reconnecting your ActiveCampaign account and try again if the issue persists.';
				}

				return new \WP_Error( $status_code, $error_message );
			}

			if ( isset( $response['tags'] ) ) {
				foreach ( $response['tags'] as $tag ) {
					$available_tags[ $tag['id'] ] = $tag['tag'];
				}
			}

			if ( empty( $response['tags'] ) || count( $response['tags'] ) < $limit ) {
				$has_items = false;
			}

			$offset += $limit;

		}

		asort( $available_tags );

		$tag_items = array();

		foreach ( $available_tags as $value => $text ) {
			$tag_items[] = array(
				'value' => $value,
				'text'  => $text,
			);
		}

		set_transient( 'ua_ac_tag_list', $tag_items, HOUR_IN_SECONDS );

		return $tag_items;
	}

	/**
	 * Get all active campaign contact fields.
	 *
	 * @param bool $should_verify_nonce
	 *
	 * @return mixed[]|WP_Error
	 */
	public function sync_contact_fields( $should_verify_nonce = true ) {

		$user = get_option( 'uap_active_campaign_connected_user', false );

		if ( empty( $user ) ) {
			return new WP_Error( 404, 'Cannot initiate request. Option key uap_active_campaign_connected_user is empty.' );
		}

		if ( $should_verify_nonce ) {
			if ( ! wp_verify_nonce( automator_filter_input( 'nonce', INPUT_POST ), 'uncanny_automator' ) ) {
				return new WP_Error( 403, 'Forbidden. Invalid nonce.' );
			}
		}

		$settings_url = get_option( 'uap_active_campaign_api_url', '' );
		$settings_key = get_option( 'uap_active_campaign_api_key', '' );

		if ( empty( $settings_url ) || empty( $settings_key ) ) {
			return new WP_Error( 403, 'Forbidden. Invalid nonce.' );
		}

		$offset           = 0;
		$limit            = 100;
		$has_items        = true;
		$available_fields = array();

		while ( $has_items ) {

			$response = wp_safe_remote_get(
				$settings_url . '/api/3/fields?limit=' . $limit . '&offset=' . $offset,
				array(
					'headers' => array(
						'Api-token' => $settings_key,
					),
				)
			);

			// Logs wp related errors.
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$status_code = wp_remote_retrieve_response_code( $response );

			$response = (array) json_decode( wp_remote_retrieve_body( $response ), true );

			// Logs generic http error response.
			if ( 200 !== $status_code ) {

				$error_message = sprintf(
					'ActiveCampaign API has responded with status code %d (%s) while syncing contact fields ',
					$status_code,
					Automator_Http_Response_Code::text( $status_code )
				);

				if ( empty( $response ) ) {
					$error_message .= '&mdash; Try reconnecting your ActiveCampaign account and try again if the issue persists.';
				}

				return new \WP_Error( $status_code, $error_message );

			}

			$action_fields = array();

			// Bail out if not set.
			$field_options = array();

			// Get all options.
			if ( isset( $response['fieldOptions'] ) ) { //phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				foreach ( $response['fieldOptions'] as $option ) { //phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					$field_options[ $option['id'] ] = $option;
				}
			}

			// Get all the fields and assign the options.
			if ( isset( $response['fields'] ) ) {

				foreach ( $response['fields'] as $field ) {

					$options = false;

					if ( ! empty( $field['options'] ) ) {
						foreach ( $field['options'] as $field_option ) {
							if ( isset( $field_options[ $field_option ] ) ) {
								$options[] = $field_options[ $field_option ];
							}
						}
					}

					$available_fields[ $field['id'] ] = array(
						'type'          => $field['type'],
						'title'         => $field['title'],
						'description'   => $field['descript'],
						'is_required'   => $field['isrequired'],
						'default_value' => $field['defval'],
						'options'       => $options,
					);

					$action_fields[] = array(
						'type'    => $field['type'],
						'postfix' => '_CUSTOM_FIELD_' . $field['id'],
					);

				}
			}

			if ( empty( $response['fields'] ) || count( $response['fields'] ) < $limit ) {
				$has_items = false;
			}

			$offset += $limit;

		}

		set_transient( 'ua_ac_contact_fields_list', $available_fields, HOUR_IN_SECONDS );
		set_transient( 'ua_ac_contact_fields_list_action_fields', $action_fields, HOUR_IN_SECONDS );

		return $available_fields;

	}

	/**
	 * Get all the list from active campaign.
	 *
	 * @param bool $should_verify_nonce
	 *
	 * @return mixed[]|\WP_Error
	 */
	public function sync_lists( $should_verify_nonce = true ) {

		if ( $should_verify_nonce ) {
			if ( ! wp_verify_nonce( automator_filter_input( 'nonce', INPUT_POST ), 'uncanny_automator' ) ) {
				return new WP_Error( 403, 'Forbidden. Invalid nonce.' );
			}
		}

		$settings_url = get_option( 'uap_active_campaign_api_url', '' );
		$settings_key = get_option( 'uap_active_campaign_api_key', '' );

		if ( empty( $settings_url ) || empty( $settings_key ) ) {
			return new WP_Error( 403, 'Invalid ActiveCampaign URL or Api key.' );
		}

		$offset          = 0;
		$limit           = 100;
		$has_items       = true;
		$available_lists = array();

		while ( $has_items ) {

			$response = wp_safe_remote_get(
				$settings_url . '/api/3/lists?limit=' . $limit . '&offset=' . $offset,
				array(
					'headers' => array(
						'Api-token' => $settings_key,
					),
				)
			);

			// Returns the instance of WP_Error if there is a WordPress related error.
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$status_code = wp_remote_retrieve_response_code( $response );

			$response = (array) json_decode( wp_remote_retrieve_body( $response ), true );

			// Return an instance of WP_Error if ActiveCampaign failed to sync the list.
			if ( 200 !== $status_code ) {

				$error_message = sprintf(
					'ActiveCampaign API has responded with status code %d (%s) while syncing list fields ',
					$status_code,
					Automator_Http_Response_Code::text( $status_code )
				);

				if ( empty( $response ) ) {
					$error_message .= '&mdash; Try reconnecting your ActiveCampaign account and try again if the issue persists.';
				}
				return new WP_Error( $status_code, $error_message );
			}

			if ( isset( $response['lists'] ) ) {
				foreach ( $response['lists'] as $list ) {
					$available_lists[ $list['id'] ] = $list['name'];
				}
				if ( count( $response['lists'] ) < $limit ) {
					$has_items = false;
				}
			}

			$offset += $limit;

		}

		asort( $available_lists );

		$list_items = array();

		foreach ( $available_lists as $value => $text ) {
			if ( ! empty( $text ) ) {
				$list_items[] = array(
					'value' => $value,
					'text'  => $text,
				);
			}
		}

		if ( ! empty( $list_items ) ) {
			set_transient( 'ua_ac_list_group', $list_items, HOUR_IN_SECONDS );
		}

		return $list_items;

	}

	/**
	 * Get the sync button labels.
	 *
	 * @return array The button labels.
	 */
	public function get_sync_btn_label() {
		return array(
			'default'  => esc_html__( 'Refresh available tags, lists, and custom fields', 'uncanny-automator' ),
			'syncing'  => esc_html__( 'Connecting', 'uncanny-automator' ),
			'working'  => esc_html__( 'Syncing tags, lists, and custom fields', 'uncanny-automator' ),
			'complete' => esc_html__( 'Complete', 'uncanny-automator' ),
		);
	}

	/**
	 * Returns the custom fields from ActiveCampaign.
	 *
	 * @return array The fields.
	 */
	public function get_custom_fields( $prefix = '' ) {

		$custom_fields = get_transient( 'ua_ac_contact_fields_list' );

		$fields = array();

		// Transform AC fields into automator.
		$field_adapter = array(
			'text'     => 'text',
			'textarea' => 'textarea',
			'date'     => 'date',
			'radio'    => 'radio',
			'datetime' => 'text',
			'checkbox' => 'select',
			'listbox'  => 'select',
			'dropdown' => 'select',
			'hidden'   => 'text',
		);

		// Placeholders.
		$placeholder = array(
			'datetime' => esc_html__( 'mm/dd/yyyy hh:mm', 'uncanny-automator' ),
		);

		// Add the custom fields.
		if ( false !== $custom_fields && ! empty( $custom_fields ) ) {

			foreach ( $custom_fields as $id => $custom_field ) {

				$options = array();

				if ( ! empty( $custom_field['options'] ) ) {
					foreach ( $custom_field['options'] as $option ) {
						$options[ $option->label ] = $option->label;
					}
				}

				$args = array(
					'option_code'           => $prefix . '_CUSTOM_FIELD_' . $id,
					'label'                 => $custom_field['title'],
					'input_type'            => $field_adapter[ $custom_field['type'] ],
					'default_value'         => $custom_field['default_value'],
					'required'              => (bool) $custom_field['is_required'],
					'placeholder'           => isset( $placeholder[ $custom_field['type'] ] ) ? $placeholder[ $custom_field['type'] ] : '',
					'supports_custom_value' => true,
					'supports_tokens'       => true,
					'options'               => $options,
				);

				if ( 'listbox' === $custom_field['type'] || 'checkbox' === $custom_field['type'] ) {
					$args['supports_multiple_values'] = true;
				}

				// Add some description if it is datetime.
				if ( 'datetime' === $custom_field['type'] ) {
					$args['description'] = esc_html__( 'ActiveCampaign automatically adjusts your time based your timezone. The timezone in your ActiveCampaign account must match your WordPress site settings.' );
				}

				$fields[] = $args;

			}
		}

		return $fields;
	}

	/**
	 * Get registered fields.
	 */
	public function get_registered_fields( $parsed, $prefix = '' ) {

		$registered_fields = (array) get_transient( 'ua_ac_contact_fields_list_action_fields' );

		$custom_fields = array();

		foreach ( $registered_fields as $registered_field ) {

			if ( ! is_array( $registered_field ) ) {
				continue;
			}

			$postfix      = $registered_field['postfix'];
			$type         = $registered_field['type'];
			$field_pieces = explode( '_', $postfix );

			if ( ! isset( $field_pieces[3] ) ) {
				continue;
			}

			// Get the field id.
			$field_id = $field_pieces[3];

			$value = '';

			if ( isset( $parsed[ $prefix . $postfix ] ) ) {
				if ( 'textarea' === $type ) {
					$value = sanitize_textarea_field( $parsed[ $prefix . $postfix ] );
				} else {
					$value = sanitize_text_field( $parsed[ $prefix . $postfix ] );
				}
			}

			// Format datetime to ISO.
			if ( 'datetime' === $type ) {

				// Set the timezone to user's timezone in WordPress.
				$date_tz = new \DateTime( $value, new \DateTimeZone( Automator()->get_timezone_string() ) );
				$date_tz->setTimezone( new \DateTimeZone( 'UTC' ) );
				$date = $date_tz->format( 'm/d/Y H:i' );

				// ActiveCampaign format is in ISO.
				$value = gmdate( 'c', strtotime( $date ) );

			}

			// For list.
			if ( 'listbox' === $type || 'checkbox' === $type ) {
				$decoded_json = json_decode( $value );
				if ( ! empty( $decoded_json ) ) {
					$value = '||' . implode( '||', $decoded_json ) . '||';
				}
			}

			$custom_fields[] = array(
				'field' => absint( $field_id ),
				'value' => $value,
			);

		}

		return $custom_fields;

	}

	/**
	 * Method api_request
	 *
	 * @param $params
	 *
	 * @return void
	 */
	public function api_request( $body, $action = null ) {

		$body['url']   = get_option( 'uap_active_campaign_api_url', '' );
		$body['token'] = get_option( 'uap_active_campaign_api_key', '' );

		if ( empty( $body['url'] ) || empty( $body['token'] ) ) {
			throw new Exception(
				_x( 'Empty Account URL or API key. Go to Automator &rarr; App integrations &rarr; ActiveCampaign to reconnect your account.', 'ActiveCampaign', 'uncanny_automator' ),
				500
			);
		}
		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => $body,
			'action'   => $action,
			'timeout'  => 60, // Sync can be slow sometimes.
		);

		$response = Api_Server::api_call( $params );

		$this->check_for_errors( $response );

		return $response;

	}

	/**
	 * Throws API related errors as exception.
	 *
	 * @param mixed[] $response The response from the API-Server.
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	public function check_for_errors( $response ) {

		$errors = isset( $response['data']['errors'] ) ? $response['data']['errors'] : array();

		$error_messages = array();

		foreach ( $errors as $error ) {
			$error_messages[] = $error['title'];
		}

		// Throw an exception if status code is included in list of successful response codes.
		if ( ! in_array( absint( $response['statusCode'] ), array( 201, 200 ), true ) ) {

			$message = implode( ', ', $error_messages );

			if ( empty( $message ) ) {
				// Fallback message in-case the error message is empty.
				$message = _x( 'Try reconnecting your ActiveCampaign account and try again', 'ActiveCampaign', 'uncanny-automator' );
			}

			$error_message = sprintf(
				_x( 'ActiveCampaign has responded with status code: %1$d (%3$s) &mdash; %2$s', 'ActiveCampaign', 'uncanny-automator' ),
				$response['statusCode'],
				$message,
				Automator_Http_Response_Code::text( $response['statusCode'] )
			);

			throw new \Exception( $error_message );
		}

	}

	public function complete_with_errors( $user_id, $action_data, $recipe_id, $error_message ) {

		$action_data['complete_with_errors'] = true;

		// Complete the action with error.
		Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_message );

	}

	public function get_tag_id( $contact_id, $tag_id ) {

		$contact_tag_id = 0;

		$body = array(
			'action'    => 'get_contact_tags',
			'contactId' => $contact_id,
		);

		$response = $this->api_request( $body );

		if ( empty( $response['data']['contactTags'] ) ) {
			throw new \Exception( __( 'The contact has no tags.', 'uncanny-automator' ), $response['statusCode'] );
		}

		foreach ( $response['data']['contactTags'] as $contact_tag ) {
			if ( $tag_id === $contact_tag['tag'] ) {
				$contact_tag_id = $contact_tag['id'];
				break;
			}
		}

		if ( 0 === $contact_tag_id ) {
			throw new \Exception( __( "The contact doesn't have the given tag.", 'uncanny-automator' ) );
		}

		return $contact_tag_id;
	}

	/**
	 * validate_trigger
	 *
	 * @return void
	 */
	public function validate_trigger( $trigger_data ) {
		return true;
	}

	/**
	 * settings_updated
	 *
	 * @return void
	 */
	public function settings_updated() {

		$redirect_url = $this->tab_url;

		$result = 1;

		try {
			$this->get_connected_users();
		} catch ( \Exception $e ) {
			delete_option( 'uap_active_campaign_connected_user' );
			$result = $e->getMessage();
		}

		$redirect_url .= '&connect=' . $result;

		$this->maybe_handle_switch();

		wp_safe_redirect( $redirect_url );

		exit;
	}

	/**
	 * maybe_handle_switch
	 *
	 * @return void
	 */
	public function maybe_handle_switch() {

		if ( ! automator_filter_has_var( 'uap_active_campaign_enable_webhook', INPUT_POST ) ) {
			return;
		}

		$switch_value = automator_filter_input( 'uap_active_campaign_enable_webhook', INPUT_POST );

		update_option( 'uap_active_campaign_enable_webhook', $switch_value );

	}

	/**
	 * get_users
	 *
	 * @return void
	 */
	public function get_users() {

		$users_option_exist = get_option( 'uap_active_campaign_connected_user', 'no' );

		if ( 'no' !== $users_option_exist ) {
			return $users_option_exist;
		}

		try {
			$users = $this->get_connected_users();
		} catch ( \Exception $e ) {
			$users = array();
			update_option( 'uap_active_campaign_connected_user', $users );
		}

		return $users;

	}
}
