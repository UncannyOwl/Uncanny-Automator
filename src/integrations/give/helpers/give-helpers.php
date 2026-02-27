<?php

namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Give_Pro_Helpers;

/**
 * Class Give_Helpers
 *
 * @package Uncanny_Automator
 */
class Give_Helpers {

	/**
	 * @var Give_Helpers
	 */
	public $options;
	/**
	 * @var Give_Pro_Helpers
	 */
	public $pro;
	/**
	 * @var true
	 */
	public $load_options = true;

	/**
	 * Give_Helpers constructor.
	 */
	public function __construct() {
	}

	/**
	 * @param Give_Helpers $options
	 */
	public function setOptions( Give_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Give_Pro_Helpers $pro
	 */
	public function setPro( Give_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param $label
	 * @param $option_code
	 * @param $args
	 * @param $tokens
	 *
	 * @return mixed|null
	 */
	public function list_all_give_forms( $label = null, $option_code = 'MAKEDONATION', $args = array(), $tokens = array() ) {

		if ( ! $label ) {
			$label = esc_html_x( 'Form', 'Give', 'uncanny-automator' );
		}

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = array();

		$query_args = array(
			'post_type'      => 'give_forms',
			'posts_per_page' => 9999,
			'post_status'    => 'publish',
		);
		$options    = Automator()->helpers->recipe->wp_query( $query_args, true, esc_html_x( 'Any form', 'Give', 'uncanny-automator' ) );
		$type       = 'select';

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => $type,
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $options,
			'relevant_tokens' => $tokens,
		);

		return apply_filters( 'uap_option_list_all_give_forms', $option );
	}

	/**
	 * @param null $form_id
	 *
	 * @return mixed|void
	 */
	public function get_form_fields_and_ffm( $form_id = null ) {

		$fields = array(
			'give_title'    => array(
				'type'     => 'text',
				'required' => true,
				'label'    => esc_html_x( 'Name title prefix', 'Give', 'uncanny-automator' ),
				'key'      => 'title',
			),
			'give_first'    => array(
				'type'     => 'text',
				'required' => true,
				'label'    => esc_html_x( 'First name', 'Give', 'uncanny-automator' ),
				'key'      => 'first_name',
			),
			'give_last'     => array(
				'type'     => 'text',
				'required' => false,
				'label'    => esc_html_x( 'Last name', 'Give', 'uncanny-automator' ),
				'key'      => 'last_name',
			),
			'give_email'    => array(
				'type'     => 'email',
				'required' => true,
				'label'    => esc_html_x( 'Email', 'Give', 'uncanny-automator' ),
				'key'      => 'user_email',
			),
			'give-amount'   => array(
				'type'     => 'tel',
				'required' => true,
				'label'    => esc_html_x( 'Donation amount', 'Give', 'uncanny-automator' ),
				'key'      => 'price',
			),
			'give_currency' => array(
				'type'     => 'text',
				'required' => true,
				'label'    => esc_html_x( 'Currency', 'Give', 'uncanny-automator' ),
				'key'      => 'currency',
			),
			'give_comment'  => array(
				'type'     => 'text',
				'required' => true,
				'label'    => esc_html_x( 'Comment', 'Give', 'uncanny-automator' ),
				'key'      => 'give_comment',
			),
			'address1'      => array(
				'type'     => 'text',
				'required' => true,
				'label'    => esc_html_x( 'Address line 1', 'Give', 'uncanny-automator' ),
				'key'      => 'address1',
			),
			'address2'      => array(
				'type'     => 'text',
				'required' => true,
				'label'    => esc_html_x( 'Address line 2', 'Give', 'uncanny-automator' ),
				'key'      => 'address2',
			),
			'city'          => array(
				'type'     => 'text',
				'required' => true,
				'label'    => esc_html_x( 'City', 'Give', 'uncanny-automator' ),
				'key'      => 'city',
			),
			'state'         => array(
				'type'     => 'text',
				'required' => true,
				'label'    => esc_html_x( 'State', 'Give', 'uncanny-automator' ),
				'key'      => 'state',
			),
			'zip'           => array(
				'type'     => 'text',
				'required' => true,
				'label'    => esc_html_x( 'Zip', 'Give', 'uncanny-automator' ),
				'key'      => 'zip',
			),
			'country'       => array(
				'type'     => 'text',
				'required' => true,
				'label'    => esc_html_x( 'Country', 'Give', 'uncanny-automator' ),
				'key'      => 'country',
			),
		);

		if ( class_exists( '\Give_FFM_Render_Form' ) && null != $form_id && '-1' != $form_id ) {

			$custom_fields = array();
			if ( method_exists( '\Give_FFM_Render_Form', 'get_input_fields' ) ) {
				$custom_fields = \Give_FFM_Render_Form::get_input_fields( $form_id );
			} elseif ( class_exists( '\GiveFormFieldManager\Helpers\Form' ) && method_exists( '\GiveFormFieldManager\Helpers\Form', 'get_input_fields' ) ) {
				$custom_fields = \GiveFormFieldManager\Helpers\Form::get_input_fields( $form_id );
			}

			if ( ! empty( $custom_fields ) && empty( $custom_fields[2] ) ) {
				if ( apply_filters( 'give_telemetry_form_uses_addon_form_field_manager', false, $form_id ) ) {
					$form_builder_fields = give()->form_meta->get_meta( $form_id, 'formBuilderFields', true );
					if ( ! empty( $form_builder_fields ) && strpos( $form_builder_fields, 'givewp-form-field-manager' ) !== false ) {
						$decoded = json_decode( $form_builder_fields, true );
						if ( is_array( $decoded ) ) {
							$custom_fields[2] = $this->get_give_form_fields_from_form_builder( $decoded );
						}
					}
				}
			}

			if ( ! empty( $custom_fields ) ) {
				if ( ! empty( $custom_fields[2] ) && is_array( $custom_fields[2] ) ) {
					foreach ( $custom_fields[2] as $custom_form_field ) {
						$custom_form_field['required']        = ( 'no' === $custom_form_field['required'] ) ? false : true;
						$fields[ $custom_form_field['name'] ] = array(
							'type'     => $custom_form_field['input_type'],
							'required' => $custom_form_field['required'],
							'label'    => $custom_form_field['label'],
							'key'      => $custom_form_field['name'],
							'custom'   => true,
						);
					}
				}
			}
		}

		return apply_filters( 'automator_give_wp_form_field', $fields );
	}

	/**
	 * Extract field labels and types from GiveWP v3 form builder data.
	 *
	 * @param array|string $form_builder_data JSON string or decoded array of form builder blocks.
	 * @return array Array of fields with label, type, fieldName, and other metadata.
	 */
	public function get_give_form_fields_from_form_builder( $form_builder_data ) {
		// Decode JSON if string is provided
		if ( is_string( $form_builder_data ) ) {
			$form_builder_data = json_decode( $form_builder_data, true );
			if ( ! is_array( $form_builder_data ) ) {
				return array();
			}
		}

		if ( ! is_array( $form_builder_data ) ) {
			return array();
		}

		$fields = array();

		// Recursively process blocks
		$process_blocks = function ( $blocks ) use ( &$process_blocks, &$fields ) {
			foreach ( $blocks as $block ) {
				if ( ! isset( $block['name'] ) || ! isset( $block['attributes'] ) ) {
					continue;
				}

				$block_name = $block['name'];
				$attributes = $block['attributes'];

				// Check if this is a Form Field Manager custom field
				if ( strpos( $block_name, 'givewp-form-field-manager/' ) === 0 ) {
					// Extract field type from block name (e.g., "givewp-form-field-manager/dropdown" -> "dropdown")
					$field_type = str_replace( 'givewp-form-field-manager/', '', $block_name );

					// Get field information
					$field_data = array(
						'label'      => isset( $attributes['label'] ) ? sanitize_text_field( $attributes['label'] ) : '',
						'input_type'       => $field_type,
						'name'  => isset( $attributes['fieldName'] ) ? sanitize_key( $attributes['fieldName'] ) : '',
						'required'  => isset( $attributes['isRequired'] ) ? 'yes' : 'no',
					);

					// Add options for fields that have them (dropdown, radio, multi-select, checkbox)
					if ( isset( $attributes['options'] ) && is_array( $attributes['options'] ) ) {
						$field_data['options'] = array_map(
							function ( $option ) {
								return array(
									'label'   => isset( $option['label'] ) ? sanitize_text_field( $option['label'] ) : '',
									'value'   => isset( $option['value'] ) ? sanitize_text_field( $option['value'] ) : '',
									'checked' => isset( $option['checked'] ) ? (bool) $option['checked'] : false,
								);
							},
							$attributes['options']
						);
					}

					$fields[] = $field_data;
				}

				// Recursively process innerBlocks
				if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
					$process_blocks( $block['innerBlocks'] );
				}
			}
		};

		$process_blocks( $form_builder_data );

		return $fields;
	}
}
