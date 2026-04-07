<?php

namespace Uncanny_Automator\Integrations\HubSpot;

use Uncanny_Automator\App_Integrations\App_Helpers;
use Exception;

/**
 * Class HubSpot_App_Helpers
 *
 * @package Uncanny_Automator
 *
 * @property HubSpot_Api_Caller $api
 */
class HubSpot_App_Helpers extends App_Helpers {

	////////////////////////////////////////////////////////////
	// Recipe UI helper methods
	////////////////////////////////////////////////////////////

	/**
	 * Get HubSpot static lists.
	 *
	 * @param bool $refresh Whether to force refresh from API.
	 *
	 * @return array
	 */
	private function get_lists( $refresh = false ) {

		$option_key  = $this->get_option_key( 'lists' );
		$cached_data = $this->get_app_option( $option_key );
		$lists       = $cached_data['data'];

		// Fetch from API if refreshing or no cached data.
		if ( $refresh || $cached_data['refresh'] || empty( $lists ) ) {

			try {
				$response = $this->api->api_request( 'get_lists' );
				$lists    = array();

				foreach ( $response['data']['lists'] as $list ) {

					if ( 'STATIC' !== $list['listType'] ) {
						continue;
					}

					$lists[] = array(
						'value' => $list['listId'],
						'text'  => $list['name'],
					);
				}

				// Cache the results (without placeholder).
				if ( ! empty( $lists ) ) {
					$this->save_app_option( $option_key, $lists );
				}
			} catch ( Exception $e ) {
				return array(
					array(
						'value' => '',
						'text'  => $e->getMessage(),
					),
				);
			}
		}

		// Prepend placeholder option.
		$options = $lists;
		array_unshift(
			$options,
			array(
				'value' => '',
				'text'  => esc_html_x( 'Select a segment', 'HubSpot', 'uncanny-automator' ),
			)
		);

		return $options;
	}

	////////////////////////////////////////////////////////////
	// Recipe UI AJAX handlers
	////////////////////////////////////////////////////////////

	/**
	 * Get segment options via AJAX.
	 *
	 * @return void
	 */
	public function get_list_options_ajax() {
		Automator()->utilities->verify_nonce();

		$options = apply_filters(
			'automator_hubspot_options_get_lists',
			$this->get_lists( $this->is_ajax_refresh() )
		);

		$this->ajax_success( $options );
	}

	/**
	 * Unified AJAX handler for field options.
	 *
	 * Determines the field type from $_POST['field_id'] and returns appropriate data:
	 * - CONTACT_FIELDS: Returns transposed repeater fields for contact info
	 * - CUSTOM_FIELDS: Returns transposed repeater fields for custom fields
	 * - FIELD_NAME: Returns dropdown options for additional fields
	 *
	 * @return void
	 */
	public function get_fields_ajax() {
		Automator()->utilities->verify_nonce();

		$field_id   = automator_filter_input( 'field_id', INPUT_POST );
		$group_id   = automator_filter_input( 'group_id', INPUT_POST );
		$all_fields = $this->get_cached_fields( $this->is_ajax_refresh() );
		$fields     = array();

		switch ( $field_id ) {
			case 'CONTACT_FIELDS':
				$fields = HubSpot_Field_Utils::filter_contact_fields( $all_fields );
				// Exclude fields auto-populated by the action.
				if ( 'HUBSPOT_ADD_USER_META' === $group_id ) {
					$fields = HubSpot_Field_Utils::exclude_fields( $fields, array( 'firstname', 'lastname' ) );
				}
				break;

			case 'CUSTOM_FIELDS':
				$fields = HubSpot_Field_Utils::filter_custom_fields( $all_fields );
				break;

			default:
				$this->ajax_error( esc_html_x( 'Invalid field_id', 'HubSpot', 'uncanny-automator' ) );
		}

		$this->ajax_success( array( 'fields' => $fields ), 'field_properties' );
	}

	/**
	 * Get cached fields, refreshing from API if needed.
	 *
	 * Returns UI-formatted field data for repeater configuration.
	 *
	 * @param bool $refresh Whether to force refresh from API.
	 *
	 * @return array The cached field configurations.
	 */
	public function get_cached_fields( $refresh = false ) {

		$option_key  = $this->get_option_key( 'fields' );
		$cached_data = $this->get_app_option( $option_key );
		$fields      = $cached_data['data'];

		// Return cached fields if available and not expired.
		if ( ! $refresh && ! $cached_data['refresh'] && ! empty( $fields ) ) {
			return $fields;
		}

		// Fetch from API and cache.
		try {
			$response = $this->api->api_request( 'get_fields' );
			$fields   = HubSpot_Field_Utils::generate_repeater_fields( $response['data'] );

			// Cache the generated fields (all fields, not filtered).
			if ( ! empty( $fields ) ) {
				$this->save_app_option( $option_key, $fields );
			}

			return $fields;
		} catch ( Exception $e ) {
			return $fields;
		}
	}

	////////////////////////////////////////////////////////////
	// Recipe UI option configs
	////////////////////////////////////////////////////////////

	/**
	 * Get segment select option config.
	 *
	 * @param string $option_code The option code.
	 * @return array
	 */
	public function get_list_option_config( $option_code ) {
		return array(
			'option_code'           => $option_code,
			'label'                 => esc_html_x( 'Segment', 'HubSpot', 'uncanny-automator' ),
			'input_type'            => 'select',
			'supports_tokens'       => false,
			'supports_custom_value' => false,
			'required'              => true,
			'options'               => array(),
			'ajax'                  => array(
				'endpoint' => 'automator_hubspot_get_list_options',
				'event'    => 'on_load',
			),
		);
	}

	/**
	 * Get email text field option config.
	 *
	 * @param string $option_code The option code.
	 * @param string $label       Optional custom label.
	 * @return array
	 */
	public function get_email_option_config( $option_code, $label = null ) {
		return array(
			'option_code' => $option_code,
			'input_type'  => 'text',
			'label'       => $label ?? esc_attr_x( 'Email address', 'HubSpot', 'uncanny-automator' ),
			'description' => '',
			'required'    => true,
			'default'     => '',
		);
	}

	/**
	 * Get update checkbox option config.
	 *
	 * @return array
	 */
	public function get_update_option_config() {
		return array(
			'option_code'   => 'UPDATE',
			'input_type'    => 'checkbox',
			'label'         => esc_html_x( 'If the contact already exists, update their info', 'HubSpot', 'uncanny-automator' ),
			'description'   => '',
			'required'      => false,
			'default_value' => true,
		);
	}
}
