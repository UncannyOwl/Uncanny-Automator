<?php

namespace Uncanny_Automator\Integrations\Everest_Forms;

/**
 * Class ANON_FORM_SUBMITTED
 *
 * @package Uncanny_Automator
 */
class ANON_FORM_SUBMITTED extends \Uncanny_Automator\Recipe\Trigger {

	protected $helper;

	/**
	 * @return mixed
	 */
	protected function setup_trigger() {
		$this->helper = array_shift( $this->dependencies );
		$this->set_integration( 'EVEREST_FORMS' );
		$this->set_trigger_code( 'EVF_ANON_SUBMITS_FORM' );
		$this->set_trigger_meta( 'EVF_FORMS' );
		$this->set_trigger_type( 'anonymous' );
		// translators: 1: Form name
		$this->set_sentence( sprintf( esc_attr_x( '{{A form:%1$s}} is submitted', 'Everest Forms', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_attr_x( '{{A form}} is submitted', 'Everest Forms', 'uncanny-automator' ) );
		$this->add_action( 'everest_forms_process_complete', 10, 4 );
	}

	/**
	 * options
	 *
	 * Override this method to display a default option group
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'required'        => true,
				'input_type'      => 'select',
				'label'           => _x( 'Form', 'Everest Forms', 'uncanny-automator' ),
				'options'         => $this->helper->get_all_everest_forms(),
				'relevant_tokens' => array(),
			),
		);
	}

	/**
	 * define_tokens
	 *
	 * Override this method if you want to add recipe-specific tokens such as form fields etc.
	 *
	 * @param mixed $tokens
	 * @param mixed $args
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge( $this->helper->get_evf_form_tokens(), $this->helper->get_evf_form_field_tokens( $trigger['meta'][ $this->get_trigger_meta() ] ) );
	}

	/**
	 * validate
	 *
	 * @param mixed $trigger
	 * @param mixed $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		if ( ! isset( $trigger['meta'][ $this->get_trigger_meta() ], $hook_args ) ) {
			return false;
		}

		$selected_form = $trigger['meta'][ $this->get_trigger_meta() ];
		$form_data     = $hook_args[2];

		return ( absint( $selected_form ) === absint( $form_data['id'] ) || intval( '-1' ) === intval( $selected_form ) );
	}

	/**
	 * hydrate_tokens
	 *
	 * @param mixed $completed_trigger
	 * @param mixed $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $completed_trigger, $hook_args ) {
		return $this->helper->parse_token_values( $hook_args[2]['id'], $hook_args[0] );
	}

}
