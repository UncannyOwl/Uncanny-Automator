<?php

namespace Uncanny_Automator\Integrations\Constant_Contact;

use Exception;

/**
 * Class Constant_Contact_App_Helpers
 *
 * @package Uncanny_Automator
 *
 * @property Constant_Contact_Api_Caller $api
 */
class Constant_Contact_App_Helpers extends \Uncanny_Automator\App_Integrations\App_Helpers {

	/**
	 * The transient name for the account info.
	 *
	 * @var string
	 */
	const TRANSIENT_ACCOUNT_INFO = 'automator_constant_contact_account_info';

	/**
	 * The option name for the custom fields repeater.
	 *
	 * @var string
	 */
	const OPTION_CUSTOM_FIELDS_REPEATER = 'automator_constant_contact_custom_fields_repeater';

	/**
	 * Set properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Preserve the typo in the option name for backward compatibility.
		$this->set_credentials_option_name( 'automator_contant_contact_integration_credentials' );
	}

	/**
	 * Validate credentials
	 *
	 * Note: this does seem redundant here as we already validate on initial authorization.
	 * Leaving in place as it's basically a backup check if the ua_option gets corrupted.
	 *
	 * @param mixed $credentials The credentials.
	 * @param array $args Optional arguments.
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function validate_credentials( $credentials, $args = array() ) {
		if ( empty( $credentials['access_token'] ) ) {
			throw new Exception( esc_html_x( 'Missing access token in credentials', 'Constant Contact', 'uncanny-automator' ) );
		}
		return $credentials;
	}

	/**
	 * Prepare credentials for storage.
	 *
	 * @param array $credentials The credentials.
	 *
	 * @return array
	 */
	public function prepare_credentials_for_storage( $credentials ) {
		// Add expires_at timestamp if we have expires_in.
		if ( isset( $credentials['expires_in'] ) ) {
			$credentials['expires_at'] = time() + intval( $credentials['expires_in'] );
		}

		// Clear the account info transient when storing new credentials.
		$this->delete_account_info();

		return $credentials;
	}

	/**
	 * Get account info - Override due to credentials being saved in transient.
	 *
	 * @return array
	 */
	public function get_account_info() {
		// Check transient first.
		$cached_info = get_transient( self::TRANSIENT_ACCOUNT_INFO );

		if ( false !== $cached_info ) {
			return $cached_info;
		}

		try {
			// Fetch user info from API.
			$user_info = $this->api->get_user_info();

			if ( ! isset( $user_info['data'] ) ) {
				throw new Exception( esc_html_x( 'Unable to fetch account information.', 'Constant Contact', 'uncanny-automator' ) );
			}

			// Store account info in transient.
			set_transient( self::TRANSIENT_ACCOUNT_INFO, $user_info['data'], DAY_IN_SECONDS );

			return $user_info['data'];
		} catch ( Exception $e ) {
			return array();
		}
	}

	/**
	 * Delete account info - Override due to credentials being saved in transient.
	 *
	 * @return void
	 */
	public function delete_account_info() {
		delete_transient( self::TRANSIENT_ACCOUNT_INFO );
	}

	////////////////////////////////////////////////////////////
	// AJAX option data methods
	////////////////////////////////////////////////////////////

	/**
	 * Fetches the custom fields list.
	 *
	 * @return void
	 */
	public function contact_contact_fields_get() {

		Automator()->utilities->ajax_auth_check();

		$rows = array();

		try {
			$response      = $this->api->contact_fields_get();
			$custom_fields = $response['data']['custom_fields'] ?? array();

			if ( empty( $custom_fields ) ) {
				throw new Exception( esc_html_x( 'No custom fields found on connected account.', 'Constant Contact', 'uncanny-automator' ) );
			}

			if ( isset( $response['data']['custom_fields'] ) && is_array( $response['data']['custom_fields'] ) ) {
				foreach ( $response['data']['custom_fields'] as $field ) {
					$rows[] = array(
						'CUSTOM_FIELD_ID'    => $field['custom_field_id'],
						'CUSTOM_FIELD_NAME'  => $field['name'],
						'CUSTOM_FIELD_VALUE' => '',
					);
				}
			}

			wp_send_json(
				array(
					'success' => true,
					'rows'    => $rows,
				)
			);

		} catch ( Exception $e ) {
			wp_send_json(
				array(
					'success' => false,
					'error'   => esc_html( $e->getMessage() ),
				)
			);
		}
	}

	/**
	 * AJAX handler to get custom fields for transposed repeater.
	 *
	 * @return void
	 */
	public function get_custom_fields_repeater() {

		Automator()->utilities->ajax_auth_check();

		try {
			// Use the dedicated custom fields class.
			$field_properties = Constant_Contact_Custom_Fields::get_fields_for_repeater( $this, $this->api, $this->is_ajax_refresh() );

			wp_send_json(
				array(
					'success'          => true,
					'field_properties' => $field_properties,
				)
			);

		} catch ( Exception $e ) {
			wp_send_json(
				array(
					'success' => false,
					'error'   => esc_html( $e->getMessage() ),
				)
			);
		}
	}

	/**
	 * Fetches the tag list.
	 *
	 * @return void
	 */
	public function tag_list() {

		Automator()->utilities->ajax_auth_check();

		$options = array();

		try {
			$response = $this->api->list_tags();

			if ( isset( $response['data']['tags'] ) && is_array( $response['data']['tags'] ) ) {
				foreach ( $response['data']['tags'] as $tag ) {
					if ( isset( $tag['tag_id'], $tag['name'] ) ) {
						$options[] = array(
							'value' => $tag['tag_id'],
							'text'  => $tag['name'],
						);
					}
				}
			}

			wp_send_json(
				array(
					'success' => true,
					'options' => $options,
				)
			);

		} catch ( Exception $e ) {
			wp_send_json(
				array(
					'success' => false,
					'error'   => esc_html( $e->getMessage() ),
				)
			);
		}
	}

	/**
	 * Fetches the list memberships.
	 *
	 * @return void
	 */
	public function list_memberships_get() {

		Automator()->utilities->ajax_auth_check();

		try {
			$options  = array();
			$response = $this->api->list_memberships_get();

			if ( isset( $response['data']['lists'] ) && is_array( $response['data']['lists'] ) ) {
				foreach ( $response['data']['lists'] as $list ) {
					if ( isset( $list['list_id'], $list['name'] ) ) {
						$options[] = array(
							'value' => $list['list_id'],
							'text'  => $list['name'],
						);
					}
				}
			}

			wp_send_json(
				array(
					'success' => true,
					'options' => $options,
				)
			);

		} catch ( Exception $e ) {
			wp_send_json(
				array(
					'success' => false,
					'error'   => esc_html( $e->getMessage() ),
				)
			);
		}
	}

	////////////////////////////////////////////////////////////
	// Common recipe helper methods
	////////////////////////////////////////////////////////////

	/**
	 * Get the email config.
	 *
	 * @param string $option_code The option code.
	 *
	 * @return array
	 */
	public function get_email_config( $option_code ) {
		return array(
			'option_code' => $option_code,
			'label'       => esc_html_x( 'Email', 'Constant Contact', 'uncanny-automator' ),
			'input_type'  => 'email',
			'required'    => true,
		);
	}

	/**
	 * Get the email from the parsed data.
	 *
	 * @param array $parsed The parsed data.
	 * @param string $option_code The option code.
	 *
	 * @return string
	 * @throws Exception
	 */
	public function get_email_from_parsed( $parsed, $option_code ) {
		$email = sanitize_text_field( $parsed[ $option_code ] ?? '' );

		if ( empty( $email ) ) {
			throw new Exception( esc_html_x( 'Email is required', 'Constant Contact', 'uncanny-automator' ) );
		}

		if ( false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new Exception(
				sprintf(
					// translators: %s: Email address
					esc_html_x( 'Invalid email address: %s', 'Constant Contact', 'uncanny-automator' ),
					esc_html( $email )
				)
			);
		}

		return $email;
	}
}
