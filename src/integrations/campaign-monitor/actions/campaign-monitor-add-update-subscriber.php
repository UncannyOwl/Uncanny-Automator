<?php

namespace Uncanny_Automator\Integrations\Campaign_Monitor;

use Exception;
use Uncanny_Automator\Recipe\Log_Properties;

/**
 * Class CAMPAIGN_MONITOR_ADD_UPDATE_SUBSCRIBER
 *
 * @package Uncanny_Automator
 */
class CAMPAIGN_MONITOR_ADD_UPDATE_SUBSCRIBER extends \Uncanny_Automator\Recipe\Action {

	use Log_Properties;

	/**
	 * Delete key - to signify deletion of a value.
	 *
	 * @var string
	 */
	const DELETE_KEY = '[delete]';

	/**
	 * Prefix for action code / meta.
	 *
	 * @var string
	 */
	public $prefix = 'CAMPAIGN_MONITOR_ADD_UPDATE_SUBSCRIBER';

	/**
	 * Store the complete with notice messages.
	 *
	 * @var array
	 */
	public $complete_with_notice_messages = array();

	/**
	 * Set up action.
	 *
	 * @return void
	 */
	public function setup_action() {

		/** @var \Uncanny_Automator\Integrations\Campaign_Monitor\Campaign_Monitor_Helpers $helper */
		$helper        = array_shift( $this->dependencies );
		$this->helpers = $helper;

		$this->set_integration( 'CAMPAIGN_MONITOR' );
		$this->set_action_code( $this->prefix . '_CODE' );
		$this->set_action_meta( $this->prefix . '_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/campaign-monitor/' ) );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
			/* translators: %1$s Subscriber Email, %2$s List*/
				esc_attr_x( 'Add/Update {{a subscriber:%1$s}} to {{a list:%2$s}}', 'Campaign Monitor', 'uncanny-automator' ),
				$this->get_action_meta(),
				'LIST:' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Add/Update {{a subscriber}} to {{a list}}', 'Campaign Monitor', 'uncanny-automator' ) );
		$this->set_background_processing( true );

	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		$fields = array(
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => _x( 'Email', 'Campaign Monitor', 'uncanny-automator' ),
				'input_type'  => 'email',
				'required'    => true,
			),
		);

		// Hidden field or select list depending on # of clients.
		$fields[] = $this->helpers->get_client_field();

		// List select field based on client.
		$fields[] = $this->helpers->get_client_list_field();

		// Allow user to update existing subscriber.
		$fields[] = array(
			'option_code' => 'UPDATE_EXISTING_SUBSCRIBER',
			'label'       => _x( 'Update existing subscriber', 'Campaign Monitor', 'uncanny-automator' ),
			'input_type'  => 'checkbox',
			'description' => sprintf(
			/* translators: %1$s: [delete] */
				_x( 'To exclude fields from being updated, leave them empty. To delete a value from a field, set its value to %1$s, including the square brackets.', 'Campaign Monitor', 'uncanny-automator' ),
				self::DELETE_KEY
			),
			'is_toggle'   => true,
		);

		// Conditional Fields.
		$fields[] = array(
			'option_code' => 'NAME',
			'label'       => _x( 'Name', 'Campaign Monitor', 'uncanny-automator' ),
			'input_type'  => 'text',
		);

		$fields[] = array(
			'option_code' => 'MOBILE_NUMBER',
			'label'       => _x( 'Mobile', 'Campaign Monitor', 'uncanny-automator' ),
			'input_type'  => 'text',
			'description' => _x( 'Numbers must include the + prefix and country code.', 'Campaign Monitor', 'uncanny-automator' ),
		);

		$fields[] = array(
			'option_code'           => 'CONSENT_TO_SEND_SMS',
			'label'                 => _x( 'Consent to send SMS', 'Campaign Monitor', 'uncanny-automator' ),
			'input_type'            => 'select',
			'options'               => $this->get_consent_options(),
			'description'           => _x( 'Subscribers will be set as having given consent to receive SMS.', 'Campaign Monitor', 'uncanny-automator' ),
			'supports_custom_value' => false,
		);

		// Custom Fields.
		$fields[] = array(
			'option_code'     => 'CUSTOM_FIELDS',
			'input_type'      => 'repeater',
			'relevant_tokens' => array(),
			'label'           => _x( 'Custom fields', 'Campaign Monitor', 'uncanny-automator' ),
			'required'        => false,
			'description'     => sprintf(
			/* translators: %1$s: [delete], %2$s opening anchor tag, %3$s: closing anchor tag */
				_x( "Custom field values must align with how they are defined in your subscriber list. Multiple values may be separated with commas. To delete a value from a field, set its value to %1\$s, including the square brackets. For more details, be sure to check out Campaign Monitor's tutorial on %2\$screating and using custom fields.%3\$s", 'Campaign Monitor', 'uncanny-automator' ),
				self::DELETE_KEY,
				'<a href="https://help.campaignmonitor.com/s/article/subscriber-custom-fields" target="_blank">',
				'</a>'
			),
			'fields'          => $this->helpers->get_repeater_fields_config(),
			'ajax'            => array(
				'event'         => 'parent_fields_change',
				'listen_fields' => array( 'LIST' ),
				'endpoint'      => 'automator_campaign_monitor_get_custom_fields',
			),
		);

		// Consent fields.
		$fields[] = array(
			'option_code'           => 'CONSENT_TO_TRACK',
			'label'                 => _x( 'Consent to track', 'Campaign Monitor', 'uncanny-automator' ),
			'input_type'            => 'select',
			'options'               => $this->get_consent_options(),
			'description'           => _x( 'Subscribers will be set as having given consent to have their email opens and clicks tracked.', 'Campaign Monitor', 'uncanny-automator' ),
			'supports_custom_value' => false,
		);

		// Resubscribe and restart autoresponders.
		$fields[] = array(
			'option_code' => 'RESUBSCRIBE',
			'label'       => _x( 'Resubscribe', 'Campaign Monitor', 'uncanny-automator' ),
			'input_type'  => 'checkbox',
			'description' => _x( 'Subscribers will be re-added to the list even if they are in an inactive state, have previously been unsubscribed, or added to the suppression list.', 'Campaign Monitor', 'uncanny-automator' ),
			'is_toggle'   => true,
		);

		$fields[] = array(
			'option_code'        => 'RESTART_AUTO_RESPONDERS',
			'label'              => _x( 'Restart autoresponders', 'Campaign Monitor', 'uncanny-automator' ),
			'input_type'         => 'checkbox',
			'description'        => _x( 'Subscribers will re-enter automated workflows. By default resubscribed subscribers will not restart any automated workflows, but they will receive any remaining emails.', 'Campaign Monitor', 'uncanny-automator' ),
			'is_toggle'          => true,
			'dynamic_visibility' => array(
				'default_state'    => 'hidden',
				'visibility_rules' => array(
					array(
						'operator'             => 'AND',
						'rule_conditions'      => array(
							array(
								'option_code' => 'RESUBSCRIBE',
								'compare'     => '==',
								'value'       => true,
							),
						),
						'resulting_visibility' => 'show',
					),
				),
			),
		);

		return $fields;
	}

	/**
	 * Process the action.
	 *
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 * @throws Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		// Required fields - throws error if not set and valid.
		$list_id = $this->helpers->get_list_id_from_parsed( $parsed, 'LIST' );
		$email   = $this->helpers->get_email_from_parsed( $parsed, $this->get_action_meta() );

		// Custom fields repeater.
		$repeater = json_decode( Automator()->parse->text( $action_data['meta']['CUSTOM_FIELDS'], $recipe_id, $user_id, $args ), true );

		// Build request body.
		$body = array(
			'email'              => $email,
			'list_id'            => $list_id,
			'update'             => $this->get_bool_value( 'UPDATE_EXISTING_SUBSCRIBER' ),
			'conditional_fields' => wp_json_encode( $this->build_conditional_fields( $parsed ) ),
			'custom_fields'      => wp_json_encode( $this->build_custom_fields( $repeater, $list_id ) ),
		);

		// Send request.
		$response = $this->helpers->api_request(
			'add_update_subscriber',
			$body,
			$action_data
		);

		if ( ! empty( $this->complete_with_notice_messages ) ) {
			$this->set_complete_with_notice( true );
			$this->add_log_error( implode( ', ', $this->complete_with_notice_messages ) );

			return null;
		}

		return true;
	}

	/**
	 * Get consent options.
	 *
	 * @return array
	 */
	private function get_consent_options() {
		return array(
			array(
				'text'  => _x( 'Unchanged', 'Campaign Monitor', 'uncanny-automator' ),
				'value' => 'Unchanged',
			),
			array(
				'text'  => _x( 'Yes', 'Campaign Monitor', 'uncanny-automator' ),
				'value' => 'Yes',
			),
			array(
				'text'  => _x( 'No', 'Campaign Monitor', 'uncanny-automator' ),
				'value' => 'No',
			),
		);
	}

	/**
	 * Build conditional fields data.
	 *
	 * @param array $parsed
	 *
	 * @return array
	 */
	private function build_conditional_fields( $parsed ) {

		// Map fields.
		$map = array(
			'NAME'          => 'Name',
			'MOBILE_NUMBER' => 'MobileNumber',
		);

		// Consent fields.
		$consent = array(
			'CONSENT_TO_SEND_SMS' => 'ConsentToReceiveSMS',
			'CONSENT_TO_TRACK'    => 'ConsentToTrack',
		);

		// Map bools.
		$bools = array(
			'RESUBSCRIBE'             => 'Resubscribe',
			'RESTART_AUTO_RESPONDERS' => 'RestartSubscriptionBasedAutoresponders',
		);

		// Merge fields.
		$map = array_merge( $map, $consent, $bools );

		// Build data.
		$data = array();
		foreach ( $map as $key => $field ) {

			// Exclude if resubscribe is false.
			if ( 'RESTART_AUTO_RESPONDERS' === $key && empty( $data['Resubscribe'] ) ) {
				continue;
			}

			// Get value.
			$value = key_exists( $key, $bools )
				? $this->get_bool_value( $key )
				: $this->get_parsed_meta_value( $key, '', $parsed );

			// Validate mobile number if provided.
			if ( 'MOBILE_NUMBER' === $key && ! empty( $value ) ) {
				$value = apply_filters( 'automator_campaign_monitor_mobile_number', $value );
				if ( ! $this->validate_is_mobile_E164( $value ) ) {
					$__value = sprintf(
						// translators: 1: Mobile number
						_x( 'Invalid mobile number format: "%s". Please ensure it begins with a plus sign (+), followed by the country code and phone number.', 'Campaign Monitor', 'uncanny-automator' ),
						$value
					);

					$this->complete_with_notice_messages[] = $__value;
					continue;
				}
			}

			// Exclude sms consent if empty mobile number.
			if ( 'CONSENT_TO_SEND_SMS' === $key && ! key_exists( 'MobileNumber', $data ) ) {
				continue;
			}

			// Add to data.
			if ( ! empty( $value ) ) {
				// Clear value if delete
				$data[ $field ] = self::DELETE_KEY === trim( $value ) ? '' : $value;
			}
		}

		return $data;
	}

	/**
	 * Build custom fields data.
	 *
	 * @param array $repeater
	 *
	 * @return array
	 */
	private function build_custom_fields( $repeater, $list_id ) {

		$data   = array();
		$errors = array();

		// Bail if no custom fields set.
		if ( empty( $repeater ) ) {
			return $data;
		}

		// Get custom fields config.
		$config = $this->helpers->get_custom_fields( $list_id );

		// Bail if no custom fields config.
		if ( empty( $config ) || is_wp_error( $config ) ) {

			$this->complete_with_notice_messages[] = _x( 'Unable to validate Custom Field(s).', 'Campaign Monitor', 'uncanny-automator' );

			return $data;
		}

		foreach ( $repeater as $field ) {

			$key   = isset( $field['FIELD'] ) ? sanitize_text_field( $field['FIELD'] ) : '';
			$value = isset( $field['FIELD_VALUE'] ) ? sanitize_text_field( trim( $field['FIELD_VALUE'] ) ) : '';
			if ( empty( $key ) || empty( $value ) ) {
				continue;
			}

			// Bail if no config for key.
			if ( ! key_exists( $key, $config ) ) {
				$errors[] = sprintf(
				/* translators: %s: custom field key */
					_x( 'Invalid custom field key: %s', 'Campaign Monitor', 'uncanny-automator' ),
					$key
				);
				continue;
			}

			// If [delete] is set, remove the field.
			if ( self::DELETE_KEY === $value ) {
				$data[] = (object) array(
					'Key'   => $key,
					// set date to 0000-00-00 to clear.
					'Value' => 'date' === $config[ $key ]['type'] ? '0000-00-00' : '',
				);
				continue;
			}

			// Validate custom field value.
			$validated_value = $this->validate_custom_field_value( $key, $value, $config[ $key ] );
			if ( is_wp_error( $validated_value ) ) {
				$errors[] = $key . ': ' . $validated_value->get_error_message();
				continue;
			}

			// Handle multi-select.
			if ( is_array( $validated_value ) ) {
				if ( ! empty( $validated_value['errors'] ) ) {
					$errors[] = $key . ': ' . implode( ', ', $validated_value['errors'] );
				}
				if ( ! empty( $validated_value['selected'] ) ) {
					// Add multiple values with same key.
					foreach ( $validated_value['selected'] as $selected ) {
						$data[] = (object) array(
							'Key'   => $key,
							'Value' => $selected,
						);
					}
				}
				continue;
			}

			// Add validated field.
			$data[] = (object) array(
				'Key'   => $key,
				'Value' => $validated_value,
			);
		}

		// Log errors.
		if ( ! empty( $errors ) ) {
			$this->complete_with_notice_messages[] = _x( 'Invalid Custom Field(s).:', 'Campaign Monitor', 'uncanny-automator' ) . ' ' . implode( ', ', $errors );
		}

		return $data;
	}

	/**
	 * Validate custom field value.
	 *
	 * @param string $key
	 * @param string $value
	 * @param array $config
	 *
	 * @return mixed|\WP_Error
	 */
	private function validate_custom_field_value( $key, $value, $config ) {

		// Stash original value for filters.
		$original_value = $value;

		// Sanitize value by type.
		$type  = 'select' === $config['type'] && $config['supports_multiple_values'] ? 'multi-select' : $config['type'];
		$value = $this->sanitize_custom_field_value_by_type( $value, $type );

		// Validate value by options.
		if ( ! is_wp_error( $value ) && ! empty( $config['options'] ) ) {
			$value = $this->validate_custom_field_value_by_options( $value, $config['options'] );
		}

		/**
		 * Filter custom field value.
		 *
		 * @param mixed $value - The custom field value, WP_Error or array if multi-select.
		 * @param string $key - The custom field key.
		 * @param string $original_value - The original custom field value.
		 * @param array $config - The custom field config.
		 *
		 * @return mixed
		 */
		$value = apply_filters( 'automator_campaign_monitor_validate_custom_field_value', $value, $key, $original_value, $config );

		return $value;
	}

	/**
	 * Sanitize / format custom field value by type.
	 *
	 * @param string $value
	 * @param string $type
	 *
	 * @return mixed|\WP_Error
	 */
	private function sanitize_custom_field_value_by_type( $value, $type ) {

		$error     = false;
		$validated = '';

		// Sanitize / Validate by type.
		switch ( $type ) {
			case 'text':
				$validated = sanitize_text_field( $value );
				break;
			case 'number':
				$validated = absint( $value );
				break;
			case 'date':
				// Validate date.
				$date      = date_create( $value );
				$error     = ! $date ? _x( 'Invalid date format', 'Campaign Monitor', 'uncanny-automator' ) : false;
				$validated = $date ? date_format( $date, 'Y-m-d' ) : '';
				break;
			case 'select':
				$validated = sanitize_text_field( $value );
				break;
			case 'multi-select':
				$validated = array_map( 'sanitize_text_field', explode( ',', $value ) );
				break;
			default:
				$error = sprintf(
				/* translators: %s: custom field type */
					_x( 'Invalid custom field type: %s', 'Campaign Monitor', 'uncanny-automator' ),
					$type
				);
				break;
		}

		if ( $error ) {
			return new \WP_Error( 'invalid_field_' . $type, $error );
		}

		return $validated;
	}

	/**
	 * Validate custom field value by options.
	 *
	 * @param string|array $value
	 * @param array $options
	 *
	 * @return mixed - Array of selected and errors if multi-select || String or WP_Error if single.
	 */
	private function validate_custom_field_value_by_options( $value, $options ) {

		$errors   = array();
		$is_multi = is_array( $value );
		$values   = $is_multi ? $value : array( $value );
		$selected = array();

		// Lowercase and trim all options.
		$test_options = array_map( 'strtolower', array_map( 'trim', $options ) );

		// Validate each value exists in options.
		foreach ( $values as $value ) {
			if ( in_array( strtolower( trim( $value ) ), $test_options, true ) ) {
				$selected[] = $value;
			} else {
				$errors[] = sprintf(
				/* translators: %s: custom field value */
					_x( 'Invalid custom field value: %s', 'Campaign Monitor', 'uncanny-automator' ),
					$value
				);
			}
		}

		// Return errors if not multi-select.
		if ( $is_multi ) {
			return array(
				'selected' => $selected,
				'errors'   => $errors,
			);
		}

		// Return error if single select.
		if ( ! empty( $errors ) ) {
			return new \WP_Error( 'invalid_custom_field_value', implode( ', ', $errors ) );
		}

		return $selected[0];
	}

	/**
	 * Get parsed boolean value.
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	private function get_bool_value( $key ) {
		$value = $this->get_parsed_meta_value( $key, false );

		return filter_var( strtolower( $value ), FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Validate if a phone number is in E.164 format.
	 *
	 * @param string $number
	 *
	 * @return bool
	 */
	private function validate_is_mobile_E164( $number ) {
		$pattern = '/^\+[1-9]\d{1,14}$/';

		return preg_match( $pattern, $number );
	}
}
