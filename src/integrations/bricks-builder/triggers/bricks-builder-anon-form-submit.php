<?php

namespace Uncanny_Automator\Integrations\Bricks_Builder;

/**
 * Class BRICKS_BUILDER_ANON_FORM_SUBMIT
 *
 * @pacakge Uncanny_Automator
 */
class BRICKS_BUILDER_ANON_FORM_SUBMIT extends \Uncanny_Automator\Recipe\Trigger {

	protected $helper;

	/**
	 * @return array[]
	 */
	public function options() {
		return array(
			Automator()->helpers->recipe->field->select(
				array(
					'option_code'     => $this->get_trigger_meta(),
					'label'           => esc_attr_x( 'Form', 'Bricks Builder', 'uncanny-automator' ),
					'required'        => true,
					// Load the options from the helpers file
					'options'         => $this->helpers->get_all_bricks_builder_forms( true ),
					'relevant_tokens' => array(),
				)
			),
		);
	}

	/**
	 * @param $trigger
	 * @param $hook_args
	 *
	 * @return false|void
	 */
	public function validate( $trigger, $hook_args ) {
		if ( ! isset( $trigger['meta'][ $this->get_trigger_meta() ], $hook_args[0] ) ) {
			return false;
		}

		$selected_form_id = $trigger['meta'][ $this->get_trigger_meta() ];
		$form_fields      = $hook_args[0]->get_fields();

		return ( intval( '-1' ) === intval( $selected_form_id ) || $selected_form_id === $form_fields['formId'] );
	}

	/**
	 * @param $trigger
	 * @param $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		$common_tokens = $this->helpers->get_form_common_tokens();
		$field_tokens  = $this->helpers->get_from_field_tokens( $trigger['meta'][ $this->get_trigger_meta() ] );

		return array_merge( $common_tokens, $field_tokens );
	}

	/**
	 * @param $completed_trigger
	 * @param $hook_args
	 *
	 * @return void
	 */
	public function hydrate_tokens( $completed_trigger, $hook_args ) {
		$selected_form_id = $completed_trigger['meta'][ $this->get_trigger_meta() ];
		$form_fields      = $hook_args[0]->get_fields();
		$token_values     = array(
			'FORM_ID'   => $form_fields['formId'],
			'FORM_NAME' => ( intval( '-1' ) !== intval( $selected_form_id ) ) ? $completed_trigger['meta'][ $this->get_trigger_meta() . '_readable' ] : '-',
		);
		if ( intval( '-1' ) !== $selected_form_id ) {
			$form_settings = $hook_args[0]->get_settings();
			$fields        = $form_settings['fields'];
			foreach ( $fields as $field ) {
				$token_values[ 'form-field-' . $field['id'] ] = $form_fields[ 'form-field-' . $field['id'] ];
			}
		}

		return $token_values;
	}

	/**
	 * @return mixed
	 */
	protected function setup_trigger() {
		$this->helpers = array_shift( $this->dependencies );
		$this->set_integration( 'BRICKS_BUILDER' );
		$this->set_trigger_code( 'BB_ANON_SUBMITS_FORM' );
		$this->set_trigger_meta( 'BB_FORMS' );
		$this->set_trigger_type( 'anonymous' );
		// Trigger sentence - Bricks Builder
		// translators: 1: Form name
		$this->set_sentence( sprintf( esc_attr_x( '{{A form:%1$s}} is submitted', 'Bricks Builder', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_attr_x( '{{A form}} is submitted', 'Bricks Builder', 'uncanny-automator' ) );
		$this->add_action( 'bricks/form/custom_action', 20, 1 );
	}

}
