<?php

namespace Uncanny_Automator\Integrations\Ontraport;

/**
 * Class Ontraport_Add_Update_Contact
 *
 * @package Uncanny_Automator
 *
 * @property Ontraport_App_Helpers $helpers
 * @property Ontraport_Api_Caller $api
 */
class Ontraport_Add_Update_Contact extends \Uncanny_Automator\Recipe\App_Action {

	use Ontraport_Contact_Fields_Trait;

	/**
	 * The value used to indicate a field should be cleared.
	 *
	 * @var string
	 */
	const DELETE_VALUE = '[DELETE]';

	/**
	 * Errors collected during custom field validation.
	 *
	 * @var array
	 */
	protected $custom_field_errors = array();

	/**
	 * Spins up new action inside "ONTRAPORT" integration.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'ONTRAPORT' );
		$this->set_action_code( 'ONTRAPORT_ADD_UPDATE_CONTACT_CODE' );
		$this->set_action_meta( 'ONTRAPORT_ADD_UPDATE_CONTACT_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/ontraport/' ) );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_readable_sentence( esc_attr_x( 'Create or update {{a contact}}', 'Ontraport', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: Email address
				esc_attr_x( 'Create or update {{a contact:%1$s}}', 'Ontraport', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			// Email field.
			$this->helpers->get_email_field( $this->get_action_meta() ),
			// Contact fields repeater.
			$this->get_contact_field_repeater_config(),
			// Status field.
			$this->get_status_field( true ),
			// Custom fields repeater.
			$this->get_custom_fields_repeater_config(),
		);
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
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$this->custom_field_errors = array();

		$email  = $this->helpers->validate_email( $this->get_parsed_meta_value( $this->get_action_meta(), '' ) );
		$status = $this->get_parsed_meta_value( 'STATUS', '' );

		// Parse repeater fields — only include fields with the update toggle enabled.
		$repeater_data = json_decode( $action_data['meta']['ONTRAPORT_CONTACT_FIELDS'] ?? '[]' );
		$fields        = $this->parse_contact_fields( $repeater_data );

		$fields['email'] = $email;

		if ( self::DELETE_VALUE === $status ) {
			$fields['status'] = '';
		} elseif ( '' !== $status ) {
			$fields['status'] = $status;
		}

		// Parse custom fields — only non-empty or [DELETE] values included.
		$custom_fields = $this->parse_custom_fields( $parsed['ONTRAPORT_CUSTOM_FIELDS'] ?? '{}' );
		$fields        = array_merge( $fields, $custom_fields );

		$body = array(
			'fields' => wp_json_encode( $fields ),
		);

		$this->api->send_request( 'contact_upsert', $body, $action_data );

		// Surface any custom field conversion errors as a notice.
		if ( ! empty( $this->custom_field_errors ) ) {
			$this->set_complete_with_notice( true );
			$this->add_log_error( implode( ' | ', $this->custom_field_errors ) );
		}

		return true;
	}

	////////////////////////////////////////////////////////////
	// Repeater Configs
	////////////////////////////////////////////////////////////

	/**
	 * Get the repeater config for the contact fields.
	 *
	 * @return array
	 */
	private function get_contact_field_repeater_config() {
		return array(
			'option_code'     => 'ONTRAPORT_CONTACT_FIELDS',
			'input_type'      => 'repeater',
			'label'           => esc_html_x( 'Contact information', 'Ontraport', 'uncanny-automator' ),
			'required'        => false,
			'hide_actions'    => true,
			'fields'          => array(
				array(
					'option_code' => 'ONTRAPORT_FIELD_KEY',
					'label'       => esc_html_x( 'Key', 'Ontraport', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => true,
					'read_only'   => true,
					'is_hidden'   => true,
				),
				array(
					'option_code' => 'ONTRAPORT_FIELD_NAME',
					'label'       => esc_html_x( 'Field', 'Ontraport', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => true,
					'read_only'   => true,
				),
				array(
					'option_code' => 'ONTRAPORT_FIELD_VALUE',
					'label'       => esc_html_x( 'Value', 'Ontraport', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => false,
				),
				array(
					'option_code'   => 'ONTRAPORT_UPDATE_FIELD',
					'label'         => esc_html_x( 'Update', 'Ontraport', 'uncanny-automator' ),
					'input_type'    => 'checkbox',
					'is_toggle'     => true,
					'required'      => false,
					'default_value' => true,
					'description'   => esc_html_x( 'Enable to update this field', 'Ontraport', 'uncanny-automator' ),
				),
			),
			'default_value'   => $this->get_contact_field_default_rows(),
			'relevant_tokens' => array(),
		);
	}

	/**
	 * Get the repeater config for the custom fields.
	 *
	 * @return array
	 */
	private function get_custom_fields_repeater_config() {
		return array(
			'option_code'     => 'ONTRAPORT_CUSTOM_FIELDS',
			'input_type'      => 'repeater',
			'hide_actions'    => true,
			'hide_header'     => true,
			'label'           => esc_html_x( 'Custom fields', 'Ontraport', 'uncanny-automator' ),
			'required'        => true,
			'layout'          => 'transposed',
			'fields'          => array(),
			'ajax'            => array(
				'event'    => 'on_load',
				'endpoint' => 'automator_ontraport_get_custom_fields',
			),
			'description'     => esc_html_x( 'Leave empty to skip or use [DELETE] to clear a value', 'Ontraport', 'uncanny-automator' ),
			'relevant_tokens' => array(),
		);
	}

	////////////////////////////////////////////////////////////
	// Custom Field Type Mapping
	////////////////////////////////////////////////////////////

	/**
	 * Generate transposed repeater field configs from custom field metadata.
	 *
	 * @param array $custom_fields The custom field definitions.
	 *
	 * @return array The repeater field configs.
	 */
	public static function generate_custom_field_configs( $custom_fields ) {

		$fields = array();

		foreach ( $custom_fields as $field ) {
			$automator_type = self::map_ontraport_type( $field['type'] );

			$config = array(
				'option_code'     => $field['key'],
				'label'           => $field['alias'],
				'input_type'      => $automator_type,
				'ontraport_type'  => $field['type'],
				'supports_tokens' => true,
				'required'        => false,
			);

			if ( 'select' === $automator_type ) {
				$config = self::apply_select_config( $config, $field );
			}

			if ( in_array( $field['type'], array( 'longtext', 'rich_text' ), true ) ) {
				$config['supports_markdown'] = true;
			}

			$fields[] = $config;
		}

		return $fields;
	}

	/**
	 * Map an Ontraport field type to an Automator input type.
	 *
	 * @param string $type The Ontraport field type.
	 *
	 * @return string The Automator input type.
	 */
	private static function map_ontraport_type( $type ) {
		switch ( $type ) {
			case 'drop':
			case 'list':
			case 'check':
				return 'select';
			case 'longtext':
			case 'rich_text':
				return 'textarea';
			default:
				return 'text';
		}
	}

	/**
	 * Apply select-specific configuration to a field config.
	 *
	 * @param array $config The base field config.
	 * @param array $field  The Ontraport field metadata.
	 *
	 * @return array The updated config.
	 */
	private static function apply_select_config( $config, $field ) {

		$options = self::map_ontraport_options( $field );

		// Prepend empty option for single-select fields.
		if ( 'list' !== $field['type'] ) {
			array_unshift(
				$options,
				array(
					'value' => '',
					'text'  => esc_html_x( 'Select option', 'Ontraport', 'uncanny-automator' ),
				)
			);
			$config['default_value'] = '';
		}

		// Multi-select support for list fields.
		if ( 'list' === $field['type'] ) {
			$config['supports_multiple_values'] = true;
		}

		// Append [DELETE] option.
		$options[] = array(
			'value' => self::DELETE_VALUE,
			'text'  => esc_html_x( 'Delete value', 'Ontraport', 'uncanny-automator' ),
		);

		$config['options'] = $options;

		return $config;
	}

	/**
	 * Map Ontraport field options to Automator select options.
	 *
	 * @param array $field The Ontraport field metadata.
	 *
	 * @return array The select options.
	 */
	private static function map_ontraport_options( $field ) {

		// Checkbox fields get hardcoded options.
		if ( 'check' === $field['type'] ) {
			return array(
				array(
					'value' => '1',
					'text'  => esc_html_x( 'Checked', 'Ontraport', 'uncanny-automator' ),
				),
				array(
					'value' => '0',
					'text'  => esc_html_x( 'Unchecked', 'Ontraport', 'uncanny-automator' ),
				),
			);
		}

		$options = array();

		foreach ( $field['options'] as $value => $label ) {
			$options[] = array(
				'value' => (string) $value,
				'text'  => $label,
			);
		}

		return $options;
	}
}
