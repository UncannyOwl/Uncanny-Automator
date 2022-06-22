<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator;

use Uncanny_Automator\Api_Server;

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
	 * Active_Campaign_helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );

		$this->setting_tab = 'active-campaign';
		$this->tab_url     = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-config&tab=premium-integrations&integration=' . $this->setting_tab;

		// Add the ajax endpoints.
		add_action( 'wp_ajax_active-campaign-list-tags', array( $this, 'list_tags' ) );
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

		$webhook_enabled_option = get_option( 'uap_active_campaign_enable_webhook', false );

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

		$free_license_status = get_option( 'uap_automator_free_license_status' );
		$pro_license_status  = get_option( 'uap_automator_pro_license_status' );

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

		$lists = get_transient( 'ua_ac_list_group' );

		if ( false === $lists ) {
			$lists = $this->sync_lists( false );
		}

		wp_send_json( $lists );

	}


	public function list_tags() {

		$lists = get_transient( 'ua_ac_tag_list' );

		if ( false === $lists ) {
			$lists = $this->sync_tags( false );
		}

		wp_send_json( $lists );

	}

	public function list_contacts() {

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
			throw new \Exception( sprintf( __( 'The contact %s does not exist in ActiveCampaign.', 'uncanny-automator' ), $email ) );
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

		wp_send_json(
			array(
				'success'                  => true,
				'is_tags_synced'           => ( false !== $tags ),
				'is_lists_synced'          => ( false !== $lists ),
				'is_contact_fields_synced' => ( false !== $contact_fields ),
			)
		);
	}

	/**
	 * Get the tags.
	 *
	 * Warning! This function will return tag names as the option values, because the incoming webhooks return names and not ids as well.
	 * It also doesn't cache the results, so it should only be used in postponed options load.
	 *
	 * @return void
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

		$tag_options = $this->get_tags();

		if ( $any ) {

			$any_item = array(
				'value' => - 1,
				'text'  => __( 'Any tag', 'uncanny-automator' ),
			);

			array_unshift( $tag_options, $any_item );
		}

		$tags_dropdown = array(
			'option_code'           => $code,
			'label'                 => __( 'Tag', 'uncanny-automator' ),
			'input_type'            => 'select',
			'supports_custom_value' => true,
			'required'              => true,
			'options'               => $tag_options,
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
				return false;
			}
		}

		$settings_url = get_option( 'uap_active_campaign_api_url', '' );
		$settings_key = get_option( 'uap_active_campaign_api_key', '' );

		if ( empty( $settings_url ) || empty( $settings_key ) ) {
			return false;
		}

		$offset         = 0;
		$limit          = 100;
		$has_items      = true;
		$available_tags = array();

		$api_url = '';

		while ( $has_items ) {

			$response = wp_safe_remote_get(
				$settings_url . '/api/3/tags?limit=' . $limit . '&offset=' . $offset,
				array(
					'headers' => array(
						'Api-token' => $settings_key,
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				automator_log( $response->get_error_message(), 'ActiveCampaign::sync_tags Error' );
				return $response;
			}

			$response = json_decode( wp_remote_retrieve_body( $response ) );

			foreach ( $response->tags as $tag ) {
				$available_tags[ $tag->id ] = $tag->tag;
			}

			if ( empty( $response->tags ) || count( $response->tags ) < $limit ) {
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
	 * @return mixed Boolean false if not successful. Otherwise, array list of the active campaign tags.
	 */
	public function sync_contact_fields( $should_verify_nonce = true ) {

		if ( $should_verify_nonce ) {
			if ( ! wp_verify_nonce( automator_filter_input( 'nonce', INPUT_POST ), 'uncanny_automator' ) ) {
				return false;
			}
		}

		$settings_url = get_option( 'uap_active_campaign_api_url', '' );
		$settings_key = get_option( 'uap_active_campaign_api_key', '' );

		if ( empty( $settings_url ) || empty( $settings_key ) ) {
			return false;
		}

		$offset           = 0;
		$limit            = 100;
		$has_items        = true;
		$available_fields = array();

		$api_url = '';

		while ( $has_items ) {

			$response = wp_safe_remote_get(
				$settings_url . '/api/3/fields?limit=' . $limit . '&offset=' . $offset,
				array(
					'headers' => array(
						'Api-token' => $settings_key,
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				automator_log( $response->get_error_message(), 'ActiveCampaign sync contact fields error.' );
				return $response;
			}

			$response = json_decode( wp_remote_retrieve_body( $response ) );

			$action_fields = array();

			// Bail out if not set.
			$field_options = array();

			// Get all options.
			if ( isset( $response->fieldOptions ) ) { //phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				foreach ( $response->fieldOptions as $option ) { //phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					$field_options[ $option->id ] = $option;
				}
			}

			// Get all the fields and assign the options.
			if ( isset( $response->fields ) ) {

				foreach ( $response->fields as $field ) {

					$options = false;

					if ( ! empty( $field->options ) ) {
						foreach ( $field->options as $field_option ) {
							if ( isset( $field_options[ $field_option ] ) ) {
								$options[] = $field_options[ $field_option ];
							}
						}
					}

					$available_fields[ $field->id ] = array(
						'type'          => $field->type,
						'title'         => $field->title,
						'description'   => $field->descript,
						'is_required'   => $field->isrequired,
						'default_value' => $field->defval,
						'options'       => $options,
					);

					$action_fields[] = array(
						'type'    => $field->type,
						'postfix' => '_CUSTOM_FIELD_' . $field->id,
					);

				}
			}

			if ( empty( $response->fields ) || count( $response->fields ) < $limit ) {
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
	 * @return mixed Boolean false if fail. Otherwise, an array of list from AC.
	 */
	public function sync_lists( $should_verify_nonce = true ) {

		if ( $should_verify_nonce ) {
			if ( ! wp_verify_nonce( automator_filter_input( 'nonce', INPUT_POST ), 'uncanny_automator' ) ) {
				return false;
			}
		}

		$settings_url = get_option( 'uap_active_campaign_api_url', '' );
		$settings_key = get_option( 'uap_active_campaign_api_key', '' );

		if ( empty( $settings_url ) || empty( $settings_key ) ) {
			return false;
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

			if ( is_wp_error( $response ) ) {
				automator_log( $response->get_error_message(), 'ActiveCampaign::sync_lists Error' );
				return false;
			}

			$response = json_decode( wp_remote_retrieve_body( $response ) );

			foreach ( $response->lists as $list ) {
				$available_lists[ $list->id ] = $list->name;
			}

			if ( count( $response->lists ) < $limit ) {
				$has_items = false;
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

		$registered_fields = get_transient( 'ua_ac_contact_fields_list_action_fields' );

		$custom_fields = array();

		foreach ( $registered_fields as $registered_field ) {

			$postfix = $registered_field['postfix'];

			$type = $registered_field['type'];

			$field_pieces = explode( '_', $postfix );

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
				$date_tz = new \DateTime( $value, new \DateTimeZone( wp_timezone_string() ) );
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

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => $body,
			'action'   => $action,
		);

		$response = Api_Server::api_call( $params );

		$this->check_for_errors( $response );

		return $response;

	}

	public function check_for_errors( $response ) {

		if ( 200 !== $response['statusCode'] ) {
			throw new \Exception( 'Request to ActiveCampaign returned with status: ' . $response['statusCode'], $response['statusCode'] );
		}

		$errors = isset( $response['data']['errors'] ) ? $response['data']['errors'] : '';

		if ( empty( $errors ) ) {
			return;
		}

		$error_message = array();

		foreach ( $errors as $error ) {
			$error_message[] = $error['title'];
		}

		throw new \Exception( implode( ', ', $error_message ) );
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
	public function validate_trigger() {
		try {
			return false !== Api_Server::charge_credit();
		} catch ( \Exception $e ) {
			return false;
		}
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
