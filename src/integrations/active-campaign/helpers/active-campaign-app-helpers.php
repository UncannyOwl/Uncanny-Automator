<?php
namespace Uncanny_Automator\Integrations\Active_Campaign;

use Uncanny_Automator\App_Integrations\App_Helpers;
use Exception;

/**
 * Class Active_Campaign_App_Helpers
 *
 * @package Uncanny_Automator
 *
 * @property Active_Campaign_Webhooks $webhooks
 * @property Active_Campaign_Api $api
 */
class Active_Campaign_App_Helpers extends App_Helpers {

	/**
	 * API URL option.
	 *
	 * @var string
	 */
	const API_URL_OPTION = 'uap_active_campaign_api_url';

	/**
	 * API key option.
	 *
	 * @var string
	 */
	const API_KEY_OPTION = 'uap_active_campaign_api_key';

	/**
	 * Enable webhook option.
	 *
	 * @var string
	 */
	const ENABLE_WEBHOOK_OPTION = 'uap_active_campaign_enable_webhook';

	////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////

	/**
	 * Set properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Set legacy option name for account info.
		$this->set_account_option_name( 'uap_active_campaign_connected_user' );
	}

	/**
	 * Get credentials - override for multiple credential option support.
	 *
	 * @return array - Array of credentials
	 * @throws Exception
	 */
	public function get_credentials() {
		$url = $this->get_account_api_url_option();
		$key = $this->get_account_api_key_option();

		if ( empty( $url ) || empty( $key ) ) {
			throw new Exception(
				esc_html_x( 'Empty Account URL or API key. Go to Automator &rarr; App integrations &rarr; ActiveCampaign to reconnect your account.', 'ActiveCampaign settings notice', 'uncanny-automator' )
			);
		}

		return array(
			'url'   => $url,
			'token' => $key,
		);
	}

	/**
	 * Validate account info.
	 * - Called when helper get_account_info() is called.
	 *
	 * @param array $account_info
	 *
	 * @return array
	 */
	public function validate_account_info( $account_info ) {
		// Normalize the account info format.
		return is_array( $account_info ) && ! empty( $account_info )
			? array_shift( $account_info )
			: array();
	}

	////////////////////////////////////////////////////
	// Integration specific methods
	////////////////////////////////////////////////////

	/**
	 * Get the account API URL.
	 *
	 * @return string
	 */
	public function get_account_api_url_option() {
		return automator_get_option( self::API_URL_OPTION, '' );
	}

	/**
	 * Get the account API key.
	 *
	 * @return string
	 */
	public function get_account_api_key_option() {
		return automator_get_option( self::API_KEY_OPTION, '' );
	}

	/**
	 * Get the tags data.
	 *
	 * @return array
	 */
	private function get_tags_data( $force_sync = false ) {
		$tags = get_transient( 'ua_ac_tag_list' );
		if ( false === $tags ) {
			$tags = $this->sync_tags( $force_sync );
		}

		return $tags;
	}

	/**
	 * Get the lists data.
	 *
	 * @return array
	 */
	private function get_lists_data( $force_sync = false ) {
		$lists = get_transient( 'ua_ac_list_group' );
		if ( false === $lists ) {
			$lists = $this->sync_lists( $force_sync );
		}

		return $lists;
	}

	/**
	 * Get Tag ID by Name
	 *
	 * @param string $tag_name
	 *
	 * @return string
	 */
	public function get_tag_id_by_name( $tag_name ) {
		$tag_name = (string) trim( $tag_name );
		$lists    = get_transient( 'ua_ac_tag_list' );
		if ( false === $lists ) {
			$lists = $this->sync_tags( false );
		}

		if ( ! empty( $lists ) && is_array( $lists ) ) {
			foreach ( $lists as $list ) {
				if ( (string) $list['text'] === $tag_name ) {
					return $list['value'];
				}
			}
		}

		return $tag_name;
	}

	////////////////////////////////////////////////////////////
	// Sync methods
	////////////////////////////////////////////////////////////

	/**
	 * Generic sync helper for tags and lists.
	 *
	 * @param array|WP_Error $result     The API result from fetch method.
	 * @param string         $name_key   The key for the name field ('tag' or 'name').
	 * @param string         $transient_key The transient key to store the data.
	 *
	 * @return array|WP_Error Array of options, or WP_Error on failure.
	 */
	private function sync_generic_items( $result, $name_key, $transient_key ) {
		// Check for WP_Error
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$available_items = array();
		foreach ( $result as $item ) {
			$available_items[ $item['id'] ] = $item[ $name_key ];
		}

		asort( $available_items );

		$items = array();
		foreach ( $available_items as $value => $text ) {
			if ( ! empty( $text ) ) {
				$items[] = array(
					'value' => $value,
					'text'  => $text,
				);
			}
		}

		if ( ! empty( $items ) ) {
			set_transient( $transient_key, $items, HOUR_IN_SECONDS );
		}

		return $items;
	}

	/**
	 * Sync tags using the new fetch method.
	 *
	 * @return array|WP_Error Array of tag options, or WP_Error on failure.
	 */
	public function sync_tags() {
		$result = $this->api->fetch_tags();
		return $this->sync_generic_items( $result, 'tag', 'ua_ac_tag_list' );
	}

	/**
	 * Sync lists using the new fetch method.
	 *
	 * @return array|WP_Error Array of list options, or WP_Error on failure.
	 */
	public function sync_lists() {
		$result = $this->api->fetch_lists();
		return $this->sync_generic_items( $result, 'name', 'ua_ac_list_group' );
	}

	/**
	 * Sync contact fields
	 *
	 * @return array|WP_Error Array of field options, or WP_Error on failure.
	 */
	public function sync_contact_fields() {
		$result = $this->api->fetch_contact_fields();

		// Check for WP_Error
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$available_fields = array();
		$action_fields    = array();
		$field_options    = array();

		// Get all options.
		if ( ! empty( $result['fieldOptions'] ) ) {
			foreach ( $result['fieldOptions'] as $option ) {
				$field_options[ $option['id'] ] = $option;
			}
		}

		// Get all the fields and assign the options.
		if ( ! empty( $result['fields'] ) ) {
			foreach ( $result['fields'] as $field ) {
				$options = array();

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

		// Set available fields.
		set_transient( 'ua_ac_contact_fields_list', $available_fields, HOUR_IN_SECONDS );
		// Set action fields.
		set_transient( 'ua_ac_contact_fields_list_action_fields', $action_fields, HOUR_IN_SECONDS );

		return $available_fields;
	}

	////////////////////////////////////////////////////
	// AJAX handlers
	////////////////////////////////////////////////////

	/**
	 * Lists all available tags.
	 *
	 * Syncs the tag in case the transients has expired.
	 *
	 * @return void
	 */
	public function list_tags_ajax() {
		Automator()->utilities->ajax_auth_check();
		$tags    = $this->get_tags_data( $this->is_ajax_refresh() );
		$options = array();

		// Add any tag option for triggers only.
		if ( ! $this->is_ajax_request_from_action() ) {
			$options[] = array(
				'value' => -1,
				'text'  => esc_html_x( 'Any tag', 'Active Campaign', 'uncanny-automator' ),
			);
		}

		if ( is_array( $tags ) && ! empty( $tags ) ) {
			foreach ( $tags as $tag ) {
				$options[] = array(
					'value' => $tag['value'],
					'text'  => $tag['text'],
				);
			}
		}
		wp_send_json(
			array(
				'success' => true,
				'options' => $options,
			)
		);
	}

	/**
	 * Lists all available lists.
	 *
	 * Syncs the list in case the transients has expired.
	 *
	 * @return void
	 */
	public function list_retrieve_ajax() {
		Automator()->utilities->ajax_auth_check();
		$lists = $this->get_lists_data( $this->is_ajax_refresh() );
		wp_send_json(
			array(
				'success' => true,
				'options' => $lists,
			)
		);
	}

	////////////////////////////////////////////////////////////
	// Recipe helper methods
	////////////////////////////////////////////////////////////

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
			'datetime' => esc_html_x( 'mm/dd/yyyy hh:mm', 'ActiveCampaign', 'uncanny-automator' ),
		);

		// Add the custom fields.
		if ( false !== $custom_fields && ! empty( $custom_fields ) ) {

			foreach ( $custom_fields as $id => $custom_field ) {

				$options = array();

				// Add empty default option for dropdown fields.
				if ( 'dropdown' === $custom_field['type'] ) {
					$options[] = array(
						'value' => '',
						'text'  => esc_attr_x( 'Select an option', 'ActiveCampaign', 'uncanny-automator' ),
					);
				}

				if ( ! empty( $custom_field['options'] ) ) {
					foreach ( $custom_field['options'] as $option ) {
						$options[] = array(
							'value' => $option['value'],
							'text'  => $option['label'],
						);
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
					$args['description'] = esc_html_x( 'ActiveCampaign automatically adjusts your time based your timezone. The timezone in your ActiveCampaign account must match your WordPress site settings.', 'ActiveCampaign', 'uncanny-automator' );
				}

				$fields[] = $args;

			}
		}

		return $fields;
	}

	/**
	 * Get registered fields.
	 *
	 * @param array $parsed The parsed form data.
	 * @param string $prefix The prefix for the field key.
	 *
	 * @return array The registered fields.
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
			$field_key    = $prefix . $postfix;

			if ( ! isset( $field_pieces[3] ) ) {
				continue;
			}

			// Get the field id.
			$field_id = $field_pieces[3];

			// Initialize value.
			$value = '';

			if ( isset( $parsed[ $field_key ] ) ) {
				$value = 'textarea' === $type
					? sanitize_textarea_field( $parsed[ $field_key ] )
					: sanitize_text_field( $parsed[ $field_key ] );
			}

			$is_delete = '[delete]' === trim( $value );

			// Format datetime to ISO.
			if ( 'datetime' === $type && ! empty( $value ) && ! $is_delete ) {

				// Set the timezone to user's timezone in WordPress.
				$date_tz = new \DateTime( $value, new \DateTimeZone( Automator()->get_timezone_string() ) );
				$date_tz->setTimezone( new \DateTimeZone( 'UTC' ) );
				$date = $date_tz->format( 'm/d/Y H:i' );

				// ActiveCampaign format is in ISO.
				$value = gmdate( 'c', strtotime( $date ) );

			}

			// Check for default "Select an option" for dropdown fields only
			if ( ! $is_delete && 'dropdown' === $type ) {
				if ( $this->is_default_option_selected( $parsed, $field_key ) ) {
					continue; // Exclude from API request.
				}
			}

			// Handle multi-select fields (listbox, checkbox)
			if ( ! $is_delete && in_array( $type, array( 'listbox', 'checkbox' ), true ) ) {
				$value = $this->format_multi_select_value( $value, $parsed, $field_key );
				// If null is returned, exclude from API request.
				if ( is_null( $value ) ) {
					continue;
				}
			}

			// Skip adding empty values to avoid clearing existing field values in ActiveCampaign
			if ( ! empty( $value ) ) {
				$custom_fields[] = array(
					'field' => absint( $field_id ),
					'value' => $value,
				);
			}
		}

		return $custom_fields;
	}

	/**
	 * Check if a field has the default "Select an option" value.
	 *
	 * @param array  $parsed    The parsed form data.
	 * @param string $field_key The field key to check.
	 *
	 * @return bool True if it's the default option.
	 */
	private function is_default_option_selected( $parsed, $field_key ) {
		$readable_key = $field_key . '_readable';
		return isset( $parsed[ $readable_key ] ) && esc_attr_x( 'Select an option', 'ActiveCampaign', 'uncanny-automator' ) === $parsed[ $readable_key ];
	}

	/**
	 * Format multi-select field values for ActiveCampaign API.
	 *
	 * @param string $value     The field value.
	 * @param array  $parsed    The parsed form data.
	 * @param string $field_key The field key to check readable value.
	 *
	 * @return string|null The formatted value or null to exclude from API request.
	 */
	private function format_multi_select_value( $value, $parsed, $field_key ) {

		// Try to decode as JSON.
		$decoded_json = json_decode( $value );
		if ( ! empty( $decoded_json ) ) {
			return '||' . implode( '||', $decoded_json ) . '||';
		}

		if ( ! empty( $value ) ) {
			// Check if the value is a custom value.
			$is_custom_value = isset( $parsed[ $field_key . '_readable' ] ) && esc_attr_x( 'Use a token/custom value', 'ActiveCampaign', 'uncanny-automator' ) === $parsed[ $field_key . '_readable' ];
			// Format custom value for API.
			if ( $is_custom_value ) {
				return '||' . $value . '||';
			}
		}

		// No selection - exclude from API request entirely.
		return null;
	}

	/**
	 * Filter Add Contact API Body.
	 *
	 * @param array $body
	 * @param array $args
	 *
	 * @return array
	 */
	public function filter_add_contact_api_body( $body, $args ) {

		$body = apply_filters( 'automator_active_campaign_add_contact_api_body', $body, $args );

		// Build the contact object
		$contact = array(
			'email' => $body['email'],
		);

		// Fields that should be included in contact object
		$contact_fields = array( 'firstName', 'lastName', 'phone' );

		foreach ( $contact_fields as $field ) {
			if ( ! isset( $body[ $field ] ) ) {
				continue;
			}

			$value = $body[ $field ];

			// Handle [DELETE] - add as empty to actively remove
			if ( '[delete]' === trim( strtolower( $value ) ) ) {
				$contact[ $field ] = '';
			} elseif ( ! empty( trim( $value ) ) ) {
				// Handle actual values - only add if not empty
				$contact[ $field ] = $value;
			}
		}

		// Process custom fields
		if ( isset( $body['fields'] ) && is_array( $body['fields'] ) ) {
			$field_values = array();
			foreach ( $body['fields'] as $field ) {
				if ( '[delete]' === trim( $field['value'] ) ) {
					$field['value'] = '';
				}
				$field_values[] = $field;
			}
			if ( ! empty( $field_values ) ) {
				$contact['fieldValues'] = $field_values;
			}
		}

		// Build the final body
		return array(
			'action'         => $body['action'],
			'email'          => $body['email'],
			'contact'        => wp_json_encode(
				array(
					'contact' => $contact,
				)
			),
			'updateIfExists' => $body['updateIfExists'] ?? false,
		);
	}


	/**
	 * Get tag UI select config for triggers or actions.
	 *
	 * @param string $option_code  The option code.
	 * @param bool $is_action      Whether the select is for an action.
	 *
	 * @return array
	 */
	public function get_tag_select_config( $option_code, $is_action = false ) {
		$args = array(
			'option_code'     => $option_code,
			'label'           => esc_attr_x( 'Tag', 'ActiveCampaign', 'uncanny-automator' ),
			'input_type'      => 'select',
			'options'         => array(),
			'required'        => true,
			'relevant_tokens' => array(),
			'ajax'            => array(
				'endpoint' => 'active-campaign-list-tags',
				'event'    => 'on_load',
			),
		);

		// If action allow custom value.
		if ( $is_action ) {
			unset( $args['relevant_tokens'] );
			$args['supports_custom_value']    = true;
			$args['fill_values_in']           = $option_code;
			$args['custom_value_description'] = 'AC_ANNON_ADDTAG_CONTACT_ID' === $option_code
				? esc_html_x(
					"Tag ID or name. If you enter a name that doesn't already exist, the tag will be created automatically.",
					'ActiveCampaign',
					'uncanny-automator'
				)
				: esc_html_x( "Tag ID.", 'ActiveCampaign', 'uncanny-automator' );
		}

		return $args;
	}

	/**
	 * Get list UI select config for actions.
	 *
	 * @param string $option_code  The option code.
	 *
	 * @return array
	 */
	public function get_list_select_config( $option_code ) {
		return array(
			'option_code'              => $option_code,
			'label'                    => esc_attr_x( 'List', 'ActiveCampaign', 'uncanny-automator' ),
			'input_type'               => 'select',
			'options'                  => array(),
			'required'                 => true,
			'fill_values_in'           => $option_code,
			'supports_custom_value'    => true,
			'custom_value_description' => esc_html_x( 'List ID', 'ActiveCampaign', 'uncanny-automator' ),
			'ajax'                     => array(
				'endpoint' => 'active-campaign-list-retrieve',
				'event'    => 'on_load',
			),
		);
	}

	/**
	 * Get email field configuration for ActiveCampaign actions.
	 *
	 * @param string $option_code  The option code for the email field.
	 *
	 * @return array
	 */
	public function get_email_field_config( $option_code ) {
		return array(
			'option_code' => $option_code,
			'label'       => esc_attr_x( 'Email', 'ActiveCampaign', 'uncanny-automator' ),
			'placeholder' => esc_attr_x( 'me@domain.com', 'ActiveCampaign', 'uncanny-automator' ),
			'input_type'  => 'email',
			'required'    => true,
		);
	}

	/**
	 * Check if the AJAX request is from an action
	 *
	 * @return bool
	 */
	private function is_ajax_request_from_action() {
		$item_id = automator_filter_input( 'item_id', INPUT_POST );
		return $item_id && 'uo-action' === get_post_type( $item_id );
	}
}
