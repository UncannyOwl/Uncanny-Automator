<?php

namespace Uncanny_Automator;

/**
 * Class Ws_Form_Lite_Helpers
 *
 * @package Uncanny_Automator
 */
class Ws_Form_Lite_Helpers {

	/**
	 * Ws_Form_Helpers __construct
	 */
	public function __construct( $load = true ) {
		if ( $load ) {
			add_action( 'wp_ajax_select_form_fields_WSFORMS', array( $this, 'select_form_fields_func' ) );
		}
	}

	/**
	 * @param $label
	 * @param $option_code
	 * @param $is_any
	 * @param $is_all
	 * @param $tokens
	 * @param $supports_custom_value
	 *
	 * @return array|mixed|void
	 */
	public function get_ws_all_forms( $label = null, $option_code = 'WSF_FORMS', $args = array(), $is_any = false, $is_all = false, $tokens = array(), $supports_custom_value = false ) {
		$options = array();

		if ( $is_all ) {
			$options['-1'] = __( 'All forms', 'uncanny-automator' );
		}

		if ( $is_any ) {
			$options['-1'] = __( 'Any form', 'uncanny-automator' );
		}

		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$ws_form_form = new \WS_Form_Form();
		$forms        = $ws_form_form->db_read_all( '', "NOT (status = 'trash')", 'label ASC, id ASC', '', '', false );

		foreach ( $forms as $form ) {
			$title = esc_html( $form['label'] );
			if ( empty( $title ) ) {
				$title = sprintf( esc_attr__( 'ID: %s (no title)', 'uncanny-automator' ), $form['id'] );
			}
			$options[ $form['id'] ] = $title;
		}

		$option = array(
			'input_type'            => 'select',
			'option_code'           => $option_code,
			/* translators: HTTP request method */
			'label'                 => empty( $label ) ? esc_attr__( 'Form', 'uncanny-automator' ) : $label,
			'required'              => true,
			'is_ajax'               => $is_ajax,
			'fill_values_in'        => $target_field,
			'endpoint'              => $end_point,
			'supports_custom_value' => $supports_custom_value,
			'relevant_tokens'       => $tokens,
			'options'               => $options,
		);

		return apply_filters( 'uap_option_get_ws_all_forms', $option );
	}

	/**
	 * Return all the specific fields of a form ID provided in ajax call
	 */
	public function select_form_fields_func() {
		Automator()->utilities->ajax_auth_check();

		$fields = array();
		if ( automator_filter_has_var( 'value', INPUT_POST ) ) {
			$form_id     = automator_filter_input( 'value', INPUT_POST );
			$form_fields = $this->get_form_fields( $form_id );
			if ( is_array( $form_fields ) ) {
				foreach ( $form_fields as $field ) {
					$fields[] = array(
						'value' => "field_{$field->id}",
						'text'  => $field->label,
					);
				}
			}
		}
		echo wp_json_encode( $fields );
		die();
	}

	/**
	 * Get the fields for the form by Form ID.
	 *
	 * @param int $id  - Form ID
	 *
	 * @return array - Array of field objects
	 */
	public function get_form_fields( $id ) {

		static $fields = array();

		if ( isset( $fields[ $id ] ) ) {
			return $fields[ $id ];
		}

		try {

			$form        = new \WS_Form_Form();
			$form->id    = $id;
			$form_object = $form->db_read_published( true );

			// Check form object to prevent fatals.
			if ( ! is_object( $form_object ) || ! property_exists( $form_object, 'id' ) || ! property_exists( $form_object, 'label' ) ) {
				throw new Exception( 'Invalid form object' );
			}

			$fields[ $id ] = \WS_Form_Common::get_fields_from_form( $form_object, true );
			foreach ( $fields[ $id ] as $field_id => $field ) {
				// Remove submit button - Review may be other fields to omit.
				if ( 'submit' === $field->type ) {
					unset( $fields[ $id ][ $field_id ] );
				}
			}
		} catch ( \Exception $e ) {
			$fields[ $id ] = array();
		}

		return apply_filters( 'automator_ws_form_get_form_fields', $fields[ $id ], $id );
	}

}
