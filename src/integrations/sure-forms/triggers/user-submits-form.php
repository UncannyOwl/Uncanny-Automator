<?php

namespace Uncanny_Automator\Integrations\Sure_Forms;

/**
 * Class USER_SUBMITS_FORM
 *
 * @package Uncanny_Automator
 */
class USER_SUBMITS_FORM extends \Uncanny_Automator\Recipe\Trigger {

	protected $helper;

	/**
	 * @return mixed
	 */
	protected function setup_trigger() {
		$this->helper = array_shift( $this->dependencies );
		$this->set_integration( 'SURE_FORMS' );
		$this->set_trigger_code( 'SURE_USER_SUBMITS_FORM' );
		$this->set_trigger_meta( 'SURE_FORMS' );
		// translators: 1: Form name
		$this->set_sentence( sprintf( esc_attr_x( 'A user submits {{a form:%1$s}}', 'Sure Forms', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_attr_x( 'A user submits {{a form}}', 'Sure Forms', 'uncanny-automator' ) );
		$this->add_action( 'srfm_form_submit', 10, 1 );
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
				'label'           => esc_html_x( 'Form', 'Sure Forms', 'uncanny-automator' ),
				'options'         => $this->helper->get_all_sure_forms(),
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
		$form_info = $this->helper->get_sure_form_tokens();

		$form_id     = isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ? $trigger['meta'][ $this->get_trigger_meta() ] : 0;
		$form_fields = $this->helper->get_sure_form_field_tokens( $form_id );
		return array_merge( $form_info, $form_fields );
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
		if ( ! isset( $trigger['meta'][ $this->get_trigger_meta() ], $hook_args[0]['form_id'] ) ) {
			return false;
		}

		$selected_form = intval( $trigger['meta'][ $this->get_trigger_meta() ] );
		$form_id       = intval( $hook_args[0]['form_id'] );

		return ( $selected_form === $form_id || intval( '-1' ) === $selected_form );
	}

	/**
	 * Hydrate tokens for Sure Forms trigger.
	 *
	 * @param array $completed_trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */

	public function hydrate_tokens( $completed_trigger, $hook_args ) {
		$data = $hook_args[0] ?? array();

		$form_id   = $data['form_id'] ?? null;
		$form_data = $data['data'] ?? array();

		if ( empty( $form_id ) || empty( $form_data ) ) {
			return array();
		}

		$all_fields    = $this->helper->get_all_form_fields( $form_id );
		$parsed_fields = array();

		foreach ( $all_fields as $field_id => $field ) {
			$slug = $field['slug'];
			if ( ! isset( $form_data[ $slug ] ) ) {
				continue;
			}

			$parsed_fields[ $field_id ] = array(
				'type'      => $field['type'],
				'value'     => $form_data[ $slug ],
				'value_raw' => $form_data[ $slug ],
			);
		}

		return $this->helper->parse_token_values( $form_id, $parsed_fields );
	}
}
