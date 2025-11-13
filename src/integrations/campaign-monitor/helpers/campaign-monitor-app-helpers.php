<?php // phpcs:ignoreFile PHPCompatibility.Operators.NewOperators.t_coalesceFound
// phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Campaign_Monitor;

use Exception;
use WP_Error;
use Uncanny_Automator\App_Integrations\App_Helpers;

/**
 * Class Campaign_Monitor_App_Helpers
 *
 * @package Uncanny_Automator
 * 
 * @property Campaign_Monitor_Api_Caller $api
 */
class Campaign_Monitor_App_Helpers extends App_Helpers {

	/**
	 * Client field action meta key.
	 *
	 * @var string
	 */
	const ACTION_CLIENT_META_KEY = 'CAMPAIGN_MONITOR_CLIENT';

	/**
	 * Set properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Preserve the existing option name for backward compatibility.
		$this->set_credentials_option_name( 'automator_campaign_monitor_credentials' );
		$this->set_account_option_name( 'automator_campaign_monitor_account' );
	}

	/**
	 * Prepare credentials for storage.
	 *
	 * @param array $credentials The credentials.
	 *
	 * @return array
	 */
	public function prepare_credentials_for_storage( $credentials ) {
		// Calculate the expiration timestamp minus a day.
		$expires_in = isset( $credentials['expires_in'] ) ? absint( $credentials['expires_in'] ) - 86400 : 0;
		$credentials['expires_on'] = time() + $expires_in;

		return $credentials;
	}

	/**
	 * Get / Set account details.
	 *
	 * @param  bool $return_error
	 *
	 * @return mixed - Details of connected account || WP_Error
	 */
	public function get_account_details( $return_error = true ) {

		$account = $this->get_account_info();

		if ( empty( $account ) ) {
			try {
				$response = $this->api->get_primary_contact();
				$data     = $response['data'] ?? array();
				$primary  = $data['EmailAddress'] ?? false;

				// Get / set clients.
				$clients = $this->get_clients( true );
				$type    = count( $clients ) > 1 ? 'agency' : 'client';

				$account = array(
					'type'   => $type,
					'email'  => $primary,
					'client' => 'client' === $type ? $clients[0] : null,
				);

				// Save account details.
				$this->store_account_info( $account );

				if ( 'client' === $type ) {
					// Maybe update the hidden client field for existing actions.
					$this->maybe_update_actions_hidden_client_field_meta( $clients[0]['value'] );
				}

			}
			catch ( Exception $e ) {
				if ( $return_error ) {
					return new WP_Error( 'campaign_monitor_get_account_details_error', $e->getMessage() );
				}
				// Return default array for checks.
				return array(
					'type'   => 'client',
					'client' => array( 'value' => '' ),
				);
			}
		}

		return $account;
	}

	/**
	 * Get Clients.
	 *
	 * @return array
	 */
	public function get_clients( $refresh = false ) {

		// Get Clients from transient.
		$transient = "automator_campaign_monitor_clients";
		$clients   = get_transient( $transient );

		if ( empty( $clients ) || $refresh ) {
			$clients  = array();
			try {
				$response = $this->api->get_clients();
				$data     = $response['data'] ?? array();
				foreach ( $data as $client ) {
					$clients[] = array(
						'value'    => $client['ClientID'],
						'text'     => $client['Name'],
					);
				}
				// Set transient.
				set_transient( $transient, $clients, DAY_IN_SECONDS );
			}
			catch ( Exception $e ) {
				return array();
			}

		}

		return $clients;
	}

	/**
	 * Get Clients Ajax handler.
	 *
	 * @return string - JSON response.
	 */
	public function get_clients_ajax() {

		Automator()->utilities->ajax_auth_check();

		wp_send_json( array(
			'success' => true,
			'options' => $this->get_clients( $this->is_ajax_refresh() ),
		) );
	}

	/**
	 * Get Lists.
	 * 
	 * @param string $client_id
	 * @param bool $refresh
	 *
	 * @return array|WP_Error
	 */
	public function get_lists( $client_id = '', $refresh = false ) {
		if ( empty( $client_id ) ) {
			$account   = $this->get_account_details( false );
			$client_id = $account['client']['value'] ?? null;
		}

		if ( empty( $client_id ) ) {
			return array();
		}

		$transient = "automator_campaign_monitor_lists_{$client_id}";
		$lists     = array();

		if ( ! $refresh ) {
			$lists = get_transient( $transient );
			if ( ! empty( $lists ) ) {
				return $lists;
			}
		}

		try {
			$response = $this->api->get_lists( $client_id );
			$data     = $response['data'] ?? array();
			$lists    = array();
			foreach ( $data as $list ) {
				$lists[] = array(
					'value' => $list['ListID'],
					'text'  => $list['Name'],
				);
			}

			// Set transient.
			set_transient( $transient, $lists, DAY_IN_SECONDS );

			return $lists;

		} catch ( Exception $e ) {
			return new WP_Error( 'campaign_monitor_get_lists_error', $e->getMessage() );
		}
	}

	/**
	 * Get Lists Ajax handler.
	 *
	 * @return string - JSON response.
	 */
	public function get_lists_ajax() {

		Automator()->utilities->verify_nonce();
		$values    = automator_filter_input_array( 'values', INPUT_POST );
		$client_id = sanitize_text_field( $values[ self::ACTION_CLIENT_META_KEY ] ?? '' );
		$lists     = $this->get_lists( $client_id, $this->is_ajax_refresh() );

		if ( is_wp_error( $lists ) ) {
			wp_send_json( 
				array(
					'success' => false,
					'error'   => $lists->get_error_message(),
				)
			);
		}

		wp_send_json( 
			array(
				'success' => true,
				'options' => $lists,
			)
		);
	}

	/**
	 * Get Custom Fields Ajax Repeater handler.
	 *
	 * @return string - JSON response.
	 */
	public function get_custom_fields_repeater_ajax() {

		Automator()->utilities->ajax_auth_check();

		// Get list from request.
		$values  = automator_filter_input_array( 'values', INPUT_POST );
		$list_id = sanitize_text_field( $values['LIST'] ?? '' );

		// Get custom fields.
		$fields = $this->get_custom_fields( $list_id, $this->is_ajax_refresh() );

		if ( is_wp_error( $fields ) ) {
			wp_send_json( 
				array(
					'success' => false,
					'error'   => $fields->get_error_message(),
				)
			);
		}

		// Format options.
		$options = array();
		foreach ( $fields as $field ) {
			$options[] = array(
				'value' => $field['value'],
				'text'  => $field['text'],
			);
		}

		// Prepare response.
		$response = array(
			'success' => true,
			'field_properties' => array(
				'fields' => $this->get_repeater_fields_config( $options )
			),
		);

		// Check if we need to clear out rows by comparing the current list id with the saved one.
		$item_post_id = automator_filter_input( 'item_id', INPUT_POST );
		$current_list = ! empty( absint( $item_post_id ) ) ? get_post_meta( $item_post_id, 'LIST', true ) : false;
		if ( (string) $current_list !== (string) $list_id ) {
			$response['rows'] = array();
		} else {
			// Get the current rows to avoid accidental data loss.
			$current_rows = ! empty( absint( $item_post_id ) ) ? get_post_meta( $item_post_id, 'CUSTOM_FIELDS', false ) : false;
			if ( ! empty( $current_rows ) ) {
				$response['rows'] = json_decode( $current_rows[0], true );
			}
		}

		wp_send_json( $response );
	}

	/**
	 * Get repeater fields configuration.
	 *
	 * @return array
	 */
	public function get_repeater_fields_config( $options = array() ) {
		return array(
			array(
				'input_type'  => 'select',
				'option_code' => 'FIELD',
				'label'       => _x( 'Field', 'Campaign Monitor', 'uncanny-automator' ),
				'options'     => $options,
				'required'    => true,
			),
			array(
				'input_type'      => 'text',
				'option_code'     => 'FIELD_VALUE',
				'label'           => _x( 'Value', 'Campaign Monitor', 'uncanny-automator' ),
				'supports_tokens' => true,
				'required'        => true,
			),
		);
	}

	/**
	 * Clear all Campaign Monitor transients.
	 *
	 * @return void
	 */
	public function clear_transients() {
		// Query all transients.
		global $wpdb;
		$table = "{$wpdb->prefix}uap_options";
		$transients = $wpdb->get_col( $wpdb->prepare(
			"SELECT option_name FROM {$table} WHERE option_name LIKE %s",
			$wpdb->esc_like( '_transient_automator_campaign_monitor_' ) . '%'
		) );

		// Delete all transients.
		if ( ! empty( $transients ) && is_array( $transients ) ) {
			foreach ( $transients as $transient ) {
				delete_transient( str_replace( '_transient_', '', $transient ) );
			}
		}
	}

	/**
	 * Get Client field for actions.
	 *
	 * @return array
	 */
	public function get_client_field() {
		
		$account = $this->get_account_details( false );
		$field   = array(
			'option_code' => self::ACTION_CLIENT_META_KEY,
			'label'       => esc_html_x( 'Client', 'Campaign Monitor', 'uncanny-automator' ),
		);

		// Client accounts have only one client.
		if ( 'client' === $account['type'] ) {
			$field['input_type'] = 'text';
			$field['default']    = $account['client']['value'];
			$field['read_only']  = true;
			$field['is_hidden']  = true;
			return $field;
		}

		// Agency accounts have multiple clients.
		$field['input_type'] = 'select';
		$field['options']    = array();
		$field['required']   = true;
		$field['ajax']       = array(
			'endpoint' => 'automator_campaign_monitor_get_clients',
			'event'    => 'on_load',
		);

		return $field;
	}

	/**
	 * Get Client List field for actions.
	 *
	 * @return array
	 */
	public function get_client_list_field() {
		$account = $this->get_account_details( false );
		$event   = 'agency' === $account['type'] ? 'parent_fields_change' : 'on_load';

		return array(
			'option_code' => 'LIST',
			'label'       => esc_html_x( 'List', 'Campaign Monitor', 'uncanny-automator' ),
			'input_type'  => 'select',
			'required'    => true,
			'ajax'        => array(
				'endpoint'       => 'automator_campaign_monitor_get_lists',
				'event'          => $event,
				'listen_fields'  => 'agency' === $account['type'] ? array( self::ACTION_CLIENT_META_KEY ) : array(),
			),
		);
	}

	/**
	 * Maybe save action CLIENT meta value.
	 *
	 * @param  array $meta_value
	 * @param  WP_Post $item
	 *
	 * @return array
	 */
	public function maybe_save_action_client_meta( $meta_value, $item ) {

		// Check action post type and CLIENT key.
		if ( 'uo-action' !== $item->post_type || ! isset( $meta_value[ self::ACTION_CLIENT_META_KEY ] ) ) {
			return $meta_value;
		}

		// Action meta keys.
		$action_metas = array(
			'CAMPAIGN_MONITOR_ADD_UPDATE_SUBSCRIBER_META',
			'CAMPAIGN_MONITOR_REMOVE_SUBSCRIBER_META',
		);

		// Check if $meta_value contains a key from $action_metas.
		if ( ! array_intersect_key( $meta_value, array_flip( $action_metas ) ) ) {
			return $meta_value;
		}

		// Check if CLIENT is empty.
		if ( empty( $meta_value[ self::ACTION_CLIENT_META_KEY ] ) ) {
			$account                                    = $this->get_account_details( false );
			$meta_value[ self::ACTION_CLIENT_META_KEY ] = $account['client']['value'] ?? '';
		}

		return $meta_value;
	}

	/**
	 * Maybe update actions hidden client field meta.
	 *
	 * @param string $client_id
	 * @return void
	 */
	public function maybe_update_actions_hidden_client_field_meta( $client_id ) {
		// Query all action IDs with the client meta key.
		global $wpdb;
		$metas = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key LIKE %s AND meta_value != %s",
				self::ACTION_CLIENT_META_KEY,
				$client_id
			)
		);

		// Update the client meta key.
		if ( ! empty( $metas ) ) {
			foreach ( $metas as $meta ) {
				update_post_meta( $meta->post_id, self::ACTION_CLIENT_META_KEY, $client_id );
			}
		}
	}

	/**
	 * Get list ID from parsed data.
	 *
	 * @param array $parsed
	 * @param string $key
	 * @return string
	 * @throws Exception
	 */
	public function get_list_id_from_parsed( $parsed, $key = 'LIST' ) {
		$list_id = isset( $parsed[ $key ] ) ? sanitize_text_field( $parsed[ $key ] ) : '';
		
		if ( empty( $list_id ) ) {
			throw new Exception( esc_html_x( 'List ID is required.', 'Campaign Monitor', 'uncanny-automator' ) );
		}
		
		return $list_id;
	}

	/**
	 * Get email from parsed data.
	 *
	 * @param array $parsed
	 * @param string $key
	 * @return string
	 * @throws Exception
	 */
	public function get_email_from_parsed( $parsed, $key ) {
		$email = isset( $parsed[ $key ] ) ? sanitize_email( $parsed[ $key ] ) : '';
		
		if ( empty( $email ) || ! is_email( $email ) ) {
			throw new Exception( esc_html_x( 'Valid email address is required.', 'Campaign Monitor', 'uncanny-automator' ) );
		}
		
		return $email;
	}

	/**
	 * Get custom fields configuration for a list.
	 *
	 * @param string $list_id
	 * @param bool $refresh
	 *
	 * @return array|WP_Error
	 */
	public function get_custom_fields( $list_id = null, $refresh = false ) {
		
		if ( empty( $list_id ) ) {
			return array();
		}

		$transient = "automator_campaign_monitor_custom_fields_{$list_id}";
		$fields    = array();

		if ( ! $refresh ) {
			$fields = get_transient( $transient );
			if ( ! empty( $fields ) ) {
				return $fields;
			}
		}

		try {
			$response = $this->api->get_custom_fields( $list_id );
			$data     = $response['data'] ?? array();

			if ( empty( $data ) ) {
				return array(
					array(
						'value' => '',
						'text'  => esc_html_x( 'No custom fields found', 'Campaign Monitor', 'uncanny-automator' ),
					),
				);
			}

			$types_map = array(
				'Text'            => 'text',
				'Number'          => 'number',
				'Date'            => 'date',
				'MultiSelectOne'  => 'select',
				'MultiSelectMany' => 'select',
			);

			foreach ( $data as $field ) {
				$fields[ $field['Key'] ] = array(
					'value'                    => $field['Key'],
					'text'                     => $field['FieldName'],
					'type'                     => $types_map[ $field['DataType'] ] ?? 'text',
					'options'                  => $field['FieldOptions'],
					'supports_multiple_values' => 'MultiSelectMany' === $field['DataType'],
				);
			}

			// Set transient.
			set_transient( $transient, $fields, DAY_IN_SECONDS );

			return $fields;

		} catch ( Exception $e ) {
			return new WP_Error( 'campaign_monitor_get_custom_fields_error', $e->getMessage() );
		}
	}
}