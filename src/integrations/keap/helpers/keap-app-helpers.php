<?php // phpcs:ignoreFile PHPCompatibility.Operators.NewOperators.t_coalesceFound
// phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Keap;

use Exception;
use WP_Error;
use Uncanny_Automator\App_Integrations\App_Helpers;

/**
 * Class Keap_App_Helpers
 *
 * Provides system-level functionality for the Keap integration including:
 * - Account management and configuration
 * - AJAX handlers for recipe UI
 * - Data caching for companies, users, tags, and custom fields
 *
 * @package Uncanny_Automator
 * @since 7.0
 */
class Keap_App_Helpers extends App_Helpers {

	/**
	 * Delete key - to signify deletion of a value.
	 *
	 * @var string
	 */
	const DELETE_KEY = '[delete]';

	/**
	 * Refresh throttle in seconds for AJAX dropdown data.
	 *
	 * Prevents excessive API calls when refreshing companies/users dropdowns.
	 *
	 * @var int
	 */
	const REFRESH_THROTTLE_SECONDS = 120;

	////////////////////////////////////////////////////////////
	// Account details methods
	////////////////////////////////////////////////////////////

	/**
	 * Get / Set account details.
	 *
	 * Fetches account details from the API if not cached, then stores them.
	 * Uses framework methods for account info storage.
	 * Returns empty array on failure to ensure consistent return type.
	 *
	 * @param string $app_id The Keap app ID.
	 *
	 * @return array Details of connected account, or empty array on failure.
	 */
	public function get_account_details( $app_id = '' ) {

		$account = $this->get_account_info();

		if ( empty( $account ) ) {
			try {

				$response = $this->api->api_request( 'get_account_details' );
				$data     = $response['data'] ?? array();
				$user     = $data['user'] ?? array();
				$app      = $data['app'] ?? array();

				// Format contact config data.
				$contact = $app['contact'] ?? array();
				$contact = $this->format_contact_config_data( $contact );

				// Set account details.
				$account = array(
					'app_id'    => $app_id,
					'email'     => $user['email'] ?? '',
					'company'   => $app['application']['company'] ?? '',
					'user_id'   => $user['sub'] ?? '',
					'time_zone' => $app['time_zone'] ?? '',
					'contact'   => $contact,
				);

				$this->store_account_info( $account );

			} catch ( Exception $e ) {
				// Return empty array to ensure consistent return type.
				return array();
			}
		}

		return $account;
	}

	/**
	 * Get Account Detail.
	 *
	 * @param  string $key
	 * @param  string $default
	 *
	 * @return mixed
	 */
	public function get_account_detail( $key, $default = '' ) {
		$account = $this->get_account_details();
		return $account[ $key ] ?? $default;
	}

	/**
	 * Get Account Contact Config.
	 *
	 * @param  string $key
	 * @param  string $default_value
	 *
	 * @return mixed
	 */
	public function get_account_contact_config( $key, $default_value = '' ) {

		$contact = $this->get_account_detail( 'contact', array() );
		if ( empty( $contact ) ) {
			return array();
		}
		$value = $contact[ $key ] ?? '';
		return empty( $value ) ? $default_value : $value;
	}

	/**
	 * Format contact config data.
	 *
	 * @param  array $contact
	 *
	 * @return array
	 */
	private function format_contact_config_data( $contact ) {
		if ( ! empty( $contact ) ) {
			foreach ( $contact as $key => $value ) {
				if ( 'disable_contact_edit_in_client_login' === $key || 'default_new_contact_form' === $key ) {
					unset( $contact[ $key ] );
					continue;
				}
				// Convert comma separated values to array.
				$value = is_string( $value ) ? explode( ',', trim( $value ) ) : $value;
				// Remove empty values and reset keys.
				$contact[ $key ] = array_map( 'trim', array_values( array_filter( $value ) ) );
			}
		}
		return $contact;
	}

	////////////////////////////////////////////////////////////
	// Recipe UI helper methods - Companies
	////////////////////////////////////////////////////////////

	/**
	 * Get Companies.
	 *
	 * @param  bool $refresh
	 *
	 * @return array
	 */
	public function get_companies( $refresh = false ) {

		$option_key     = $this->get_option_key( 'companies' );
		$company_data   = $this->get_app_option( $option_key );
		$companies      = $company_data['data'];
		$should_refresh = $refresh || $company_data['refresh'];

		if ( empty( $companies ) || $should_refresh ) {
			try {
				$response  = $this->api->api_request( 'get_companies' );
				$data      = $response['data']['companies'] ?? array();
				$companies = array();
				foreach ( $data as $company ) {
					$companies[ $company['id'] ] = array(
						'value' => $company['id'],
						'text'  => $company['company_name'],
					);
				}
				$this->save_app_option( $option_key, $companies );
			} catch ( Exception $e ) {
				return $companies; // Return previous data if any.
			}
		}

		return $companies;
	}

	/**
	 * Get Companies Ajax handler.
	 *
	 * @return array
	 */
	public function get_companies_ajax() {

		Automator()->utilities->verify_nonce();

		$options = array();

		// Check if we should add an empty option.
		$group_id  = automator_filter_input( 'group_id', INPUT_POST );
		if ( 'KEAP_ADD_UPDATE_CONTACT_META' === $group_id ) {
			$options[] = array(
				'value' => '',
				'text'  => esc_html_x( 'Select a company', 'Keap', 'uncanny-automator' ),
			);
		}

		$companies = $this->get_companies( $this->is_ajax_refresh() );
		if ( ! empty( $companies ) ) {
			$options = array_merge( $options, array_values( $companies ) );
		}

		$this->ajax_success( $options );
	}

	/**
	 * Get Company Selection.
	 *
	 * @param  mixed string|int $selected - ID or Name.
	 *
	 * @return mixed object || WP_Error - Company || Error.
	 */
	public function get_valid_company_selection( $selected, $refresh = false ) {

		if ( empty( $selected ) ) {
			return new WP_Error( 'empty', esc_html_x( 'Company required.', 'Keap', 'uncanny-automator' ) );
		}

		$can_refresh = false === $refresh;

		$companies = $this->get_companies( $refresh );
		if ( empty( $companies ) ) {
			if ( ! $can_refresh ) {
				return new WP_Error( 'empty', esc_html_x( 'No companies found.', 'Keap', 'uncanny-automator' ) );
			}
			return $this->get_valid_company_selection( $selected, true );
		}

		// Check by ID.
		$company_id = is_numeric( $selected ) ? absint( $selected ) : 0;
		if ( ! empty( $company_id ) && key_exists( $company_id, $companies ) ) {
			$company = $companies[ $company_id ];
			return (object) array(
				'id'           => $company['value'],
				'company_name' => $company['text'],
			);
		}

		// Check by name.
		$company_name = empty( $company_id ) ? trim( $selected ) : '';
		foreach ( $companies as $company ) {
			if ( strcasecmp( $company['text'], $company_name ) == 0 ) {
				return (object) array(
					'id'           => $company['value'],
					'company_name' => $company['text'],
				);
			}
		}

		// Try to refresh and re-validate.
		if ( $can_refresh ) {
			// Confirm last timestamp is at least REFRESH_THROTTLE_SECONDS old.
			$option_key   = $this->get_option_key( 'companies' );
			$company_data = $this->get_app_option( $option_key, self::REFRESH_THROTTLE_SECONDS );
			if ( $company_data['refresh'] ) {
				return $this->get_valid_company_selection( $selected, true );
			}
		}

		// No match found.
		return new WP_Error( 'invalid', esc_html_x( 'Invalid company.', 'Keap', 'uncanny-automator' ) );
	}

	/**
	 * Add new company to saved options.
	 *
	 * @param  int $id
	 * @param  string $name
	 *
	 * @return void
	 */
	public function add_new_company_to_saved_options( $id, $name ) {
		$option_key = $this->get_option_key( 'companies' );
		$companies  = $this->get_companies();
		$companies[ $id ] = array(
			'value' => $id,
			'text'  => $name,
		);
		$this->save_app_option( $option_key, $companies );
	}

	////////////////////////////////////////////////////////////
	// Recipe UI helper methods - Account Users
	////////////////////////////////////////////////////////////

	/**
	 * Get App Account Users.
	 *
	 * @return array
	 */
	public function get_account_users( $refresh = false ) {

		$option_key     = $this->get_option_key( 'users' );
		$user_data      = $this->get_app_option( $option_key );
		$users          = $user_data['data'];
		$should_refresh = $refresh || $user_data['refresh'];

		if ( empty( $users ) || $should_refresh ) {
			try {
				$response = $this->api->api_request( 'get_app_account_users' );
				$data     = $response['data']['users'] ?? array();
				$users    = array();
				foreach ( $data as $user ) {
					if ( 'Active' !== $user['status'] ) {
						continue;
					}
					$name  = ! empty( $user['preferred_name'] )
						? $user['preferred_name']
						: trim( $user['first_name'] . ' ' . $user['last_name'] );
					$label = $name . ( ! empty( $user['email_address'] ) ? ' (' . $user['email_address'] . ')' : '' );
					$users[ $user['id'] ] = array(
						'value'    => $user['id'],
						'text'     => $label,
						'email'    => $user['email_address'],
					);
				}
				$this->save_app_option( $option_key, $users );
			} catch ( Exception $e ) {
				return $users; // Return previous data if any.
			}
		}

		return $users;
	}

	/**
	 * Get App Account Users Ajax handler.
	 *
	 * @return string - JSON response.
	 */
	public function get_account_users_ajax() {

		Automator()->utilities->verify_nonce();

		$options = array();

		// Check if we should add an empty option.
		$group_id = automator_filter_input( 'group_id', INPUT_POST );
		if ( 'KEAP_ADD_UPDATE_CONTACT_META' === $group_id ) {
			$options[] = array(
				'value' => '',
				'text'  => esc_html_x( 'Select an owner', 'Keap', 'uncanny-automator' ),
			);
		}

		$account_users = $this->get_account_users( $this->is_ajax_refresh() );
		if ( ! empty( $account_users ) ) {
			// Remove email prop and reset keys.
			$account_users = array_values( array_map( function ( $user ) {
				unset( $user['email'] );
				return $user;
			}, $account_users ) );

			$options = array_merge( $options, $account_users );
		}

		$this->ajax_success( $options );
	}

	/**
	 * Get Account User Selection.
	 *
	 * @param  mixed string|int $selected
	 *
	 * @return mixed int || WP_Error - Account User ID || Error.
	 */
	public function get_valid_account_user_selection( $selected, $refresh = false ) {

		$selected = sanitize_text_field( $selected );
		if ( empty( $selected ) ) {
			return new WP_Error( 'empty', esc_html_x( 'Account user is required.', 'Keap', 'uncanny-automator' ) );
		}

		$can_refresh = false === $refresh;

		$users = $this->get_account_users( $refresh );
		if ( empty( $users ) ) {
			if ( ! $can_refresh ) {
				return new WP_Error( 'empty', esc_html_x( 'No account users found.', 'Keap', 'uncanny-automator' ) );
			}
			return $this->get_valid_account_user_selection( $selected, true );
		}

		// Check by ID.
		$user_id = is_numeric( $selected ) ? absint( $selected ) : 0;
		if ( ! empty( $user_id ) && key_exists( $user_id, $users ) ) {
			return $user_id;
		}

		// Check by email.
		$user_email = $selected;
		foreach ( $users as $user ) {
			if ( strcasecmp( $user['email'], $user_email ) == 0 ) {
				return $user['value'];
			}
		}

		// Try to refresh and re-validate.
		if ( $can_refresh ) {
			// Confirm last timestamp is at least REFRESH_THROTTLE_SECONDS old.
			$option_key = $this->get_option_key( 'users' );
			$user_data  = $this->get_app_option( $option_key, self::REFRESH_THROTTLE_SECONDS );
			if ( $user_data['refresh'] ) {
				return $this->get_valid_account_user_selection( $selected, true );
			}
		}

		// No match found.
		return new WP_Error( 'invalid', esc_html_x( 'Invalid account user.', 'Keap', 'uncanny-automator' ) );
	}

	////////////////////////////////////////////////////////////
	// Recipe UI helper methods - Tags
	////////////////////////////////////////////////////////////

	/**
	 * Get Tags.
	 *
	 * @param  bool $refresh
	 *
	 * @return array
	 */
	public function get_tags( $refresh = false ) {

		$option_key     = $this->get_option_key( 'tags' );
		$tag_data       = $this->get_app_option( $option_key );
		$tags           = $tag_data['data'];
		$should_refresh = $refresh || $tag_data['refresh'];

		if ( empty( $tags ) || $should_refresh ) {
			try {
				$response = $this->api->api_request( 'get_tags' );
				$data     = $response['data']['tags'] ?? array();
				$tags     = array();
				foreach ( $data as $tag ) {
					$tags[ $tag['id'] ] = array(
						'value' => $tag['id'],
						'text'  => $tag['name'],
					);
				}
				$this->save_app_option( $option_key, $tags );
			} catch ( Exception $e ) {
				return $tags; // Return previous results or empty.
			}
		}

		return $tags;
	}

	/**
	 * Get Tags Ajax handler.
	 *
	 * @return string - JSON response.
	 */
	public function get_tags_ajax() {

		Automator()->utilities->verify_nonce();
		$tags = $this->get_tags( $this->is_ajax_refresh() );
		$tags = ! empty( $tags ) ? array_values( $tags ) : array();

		$this->ajax_success( $tags );
	}

	////////////////////////////////////////////////////////////
	// Recipe UI helper methods - Custom Fields
	////////////////////////////////////////////////////////////

	/**
	 * Get custom fields by option key.
	 *
	 * @param  string $type - 'contact' || 'company'
	 * @param  bool $refresh
	 *
	 * @return array
	 */
	public function get_custom_field_options( $type, $refresh = false ) {

		$option_key     = $this->get_option_key( "{$type}_custom_fields" );
		$fields_data    = $this->get_app_option( $option_key );
		$fields         = $fields_data['data'];
		$should_refresh = $refresh || $fields_data['refresh'];
		$not_found      = array(
			array(
				'value' => '',
				'text'  => esc_html_x( 'No custom fields found', 'Keap', 'uncanny-automator' ),
			),
		);

		if ( ! $should_refresh ) {
			if ( is_array( $fields ) && ! empty( $fields ) ) {
				return $fields;
			}
			if ( 'no_custom_fields' === $fields ) {
				return $not_found;
			}
		}

		try {
			$method   = 'contact' === $type ? 'get_contact_model' : 'get_company_model';
			$response = $this->api->api_request( $method );
			$data     = $response['data'] ?? array();
			$data     = $data['custom_fields'] ?? array();

			if ( empty( $data ) ) {
				$this->save_app_option( $option_key, 'no_custom_fields' );
				return $not_found;
			}

			// Keap to Automator UI - mapping.
			$types_map = array(
				'WEBSITE'       => 'url',
				'DATE'          => 'date',
				'EMAIL'         => 'email',
				'LISTBOX'       => 'select',
				'DAYOFWEEK'     => 'select',
				'DROPDOWN'      => 'select',
				'MONTH'         => 'select',
				'RADIO'         => 'select',
				'STATE'         => 'select',
				'YESNO'         => 'select',
				'CURRENCY'      => 'number',
				'DECIMALNUMBER' => 'number',
				'PERCENT'       => 'number',
				'WHOLENUMBER'   => 'number',
				'YEAR'          => 'number',
				'PHONENUMBER'   => 'text',
				'TEXT'          => 'text',
				'TEXTAREA'      => 'textarea',
			);
			$fields = array();
			foreach ( $data as $field ) {

				// Normalize Keap Type ( Differences between Contact and Company models ).
				$keap_type = strtoupper( str_replace( '_', '', $field['field_type'] ) );

				if ( 'YESNO' === $keap_type ) {
					$field['options'] = array(
						array(
							'id'    => 'Yes',
							'label' => 'Yes',
						),
						array(
							'id'    => 'No',
							'label' => 'No',
						),
					);
				}

				$options = null;
				if ( is_array( $field['options'] ) ) {
					foreach ( $field['options'] as $option ) {
						$options[ $option['id'] ] = array(
							'value' => $option['id'],
							'text'  => $option['label'],
						);
					}
				}

				$fields[ $field['id'] ] = array(
					'value'     => $field['id'],
					'text'      => $field['label'],
					'type'      => $types_map[ $keap_type ] ?? 'text',
					'keap_type' => $keap_type,
					'options'   => $options,
				);
			}

			// Set option.
			$this->save_app_option( $option_key, $fields );
			return $fields;

		} catch ( Exception $e ) {
			// Return previous results or not found.
			return ! empty( $fields ) && is_array( $fields ) ? $fields : $not_found;
		}
	}

	/**
	 * Get common custom fields repeater config.
	 *
	 * @param  string $type - 'contact' || 'company'
	 * @param  bool $refresh
	 *
	 * @return array
	 */
	public function get_custom_fields_repeater_fields_config( $type, $refresh = false ) {

		// Get field map.
		$fields  = $this->get_custom_field_options( $type, $refresh );
		$options = array_map( function( $item ) {
			return array(
				'value' => $item['value'],
				'text'  => $item['text'],
			);
		}, $fields );

		return array(
			array(
				'input_type'            => 'select',
				'option_code'           => 'FIELD',
				'label'                 => esc_html_x( 'Field', 'Keap', 'uncanny-automator' ),
				'options'               => array_values( $options ),
				'options_show_id'       => false,
				'required'              => true,
				'supports_custom_value' => false
			),
			array(
				'input_type'      => 'text',
				'option_code'     => 'FIELD_VALUE',
				'label'           => esc_html_x( 'Value', 'Keap', 'uncanny-automator' ),
				'supports_tokens' => true,
				'required'        => true,
			),
		);
	}

	/**
	 * Get Contact Custom Fields Ajax handler.
	 *
	 * @return string - JSON response.
	 */
	public function get_contact_custom_fields_repeater_ajax() {

		Automator()->utilities->verify_nonce();

		$this->ajax_success(
			array( 
				'fields' => $this->get_custom_fields_repeater_fields_config( 'contact', $this->is_ajax_refresh() )
			),
			'field_properties'
		);
	}

	/**
	 * Get Company Custom Fields Ajax handler.
	 *
	 * @return string - JSON response.
	 */
	public function get_company_custom_fields_repeater_ajax() {

		Automator()->utilities->verify_nonce();

		$this->ajax_success(
			array( 
				'fields' => $this->get_custom_fields_repeater_fields_config( 'company', $this->is_ajax_refresh() )
			),
			'field_properties'
		);
	}
}
