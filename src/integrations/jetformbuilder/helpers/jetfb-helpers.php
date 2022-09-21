<?php
namespace Uncanny_Automator;

class Jetfb_Helpers {

	public function __construct( $hooks_loaded = true ) {

		if ( $hooks_loaded ) {

			add_action( 'wp_ajax_automator_jetforms_fields_dropdown', array( $this, 'fields_dropdown' ) );

		}

	}

	/**
	 * Get option fields.
	 *
	 * @param object $trigger The trigger trait.
	 * @return array The option fields.
	 */
	public function get_option_fields( $trigger = null ) {

		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					array(
						'option_code'     => $trigger->get_trigger_meta(),
						'label'           => __( 'Form', 'uncanny-automator' ),
						'input_type'      => 'select',
						'required'        => true,
						'options'         => $this->get_forms(),
						'relevant_tokens' => array(),
					),
				),
			)
		);

	}

	/**
	 * Get option field group.
	 *
	 * @param object $trigger The trigger trait.
	 * @return array The option fields.
	 */
	public function get_option_field_group( $trigger = null ) {

		return Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => array(
					$trigger->get_trigger_meta() => array(
						array(
							'option_code'     => $trigger->get_trigger_meta(),
							'label'           => __( 'Form', 'uncanny-automator' ),
							'input_type'      => 'select',
							'required'        => true,
							'is_ajax'         => true,
							'endpoint'        => 'automator_jetforms_fields_dropdown',
							'fill_values_in'  => 'FIELD',
							'options'         => $this->get_forms(),
							'relevant_tokens' => array(),
						),
						array(
							'option_code'     => 'FIELD',
							'label'           => __( 'Field', 'uncanny-automator' ),
							'input_type'      => 'select',
							'required'        => true,
							'options'         => array(),
							'relevant_tokens' => array(),
						),
						array(
							'option_code'     => 'VALUE',
							'label'           => __( 'Value', 'uncanny-automator' ),
							'input_type'      => 'text',
							'required'        => true,
							'relevant_tokens' => array(),
						),
					),
				),
			)
		);

	}

	/**
	 * Return all JetEngine forms that are published.
	 *
	 * @return array The collection of JetEngine forms.
	 */
	public function get_forms( $option_fields = array() ) {

		if ( ! $this->dependencies_loaded() ) {
			return array();
		}

		$forms = \Jet_Form_Builder\Classes\Tools::get_forms_list_for_js();

		foreach ( $forms as $form ) {

			if ( ! empty( $form['value'] ) ) {

				$form_label = ! empty( $form['label'] ) ?
					$form['label'] :
					sprintf(
						esc_html__( 'ID: %d (no title)', 'uncanny-automator' ),
						$form['value']
					);

				$option_fields[ esc_attr( $form['value'] ) ] = esc_html( $form_label );

			}
		}

		return $option_fields;

	}

	public function fields_dropdown() {

		Automator()->utilities->ajax_auth_check();

		$options = array();

		$fields = (array) $this->get_form_fields( automator_filter_input( 'value', INPUT_POST, FILTER_SANITIZE_NUMBER_INT ) );

		foreach ( $fields as $field ) {

			if ( ! empty( $field['name'] ) ) {

				$options[] = array(
					'text'  => isset( $field['label'] ) ? $field['label'] : $field['name'],
					'value' => $field['name'],
				);

			}
		}

		return wp_send_json( $options );

	}

	/**
	 * Get the specific form fields.
	 *
	 * @param integer $form_id
	 *
	 * @return array The form fields.
	 */
	public function get_form_fields( $form_id = 0 ) {

		$form_post = get_post( $form_id );

		if ( ! isset( $form_post->post_content ) ) {
			return array();
		}

		return $this->get_fields_from_post_content( $form_post->post_content );

	}

	/**
	 * Return all valid JetFormBuilder fields from wp editor.
	 *
	 * @return array The fields.
	 */
	public function get_fields_from_post_content( $content = '' ) {

		$fields = preg_match_all( '/<!-- wp:jet-forms\/(.*?) \/-->/', $content, $matches );

		$tokenizable = array();

		if ( ! empty( $matches[1] ) ) {

			foreach ( $matches[1] as $match ) {

				$match = json_decode( substr( $match, strpos( $match, '{' ), strlen( $match ) ), true );

				if ( is_array( $match ) ) {
					$tokenizable[] = $match;
				}
			}
		}

		return $tokenizable;

	}

	public function dependencies_loaded() {

		return function_exists( 'jet_form_builder_init' );

	}

}
