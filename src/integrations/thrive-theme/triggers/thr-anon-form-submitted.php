<?php

namespace Uncanny_Automator\Integrations\Thrive_Theme;

/**
 * Class THR_ANON_FORM_SUBMITTED
 *
 * @package Uncanny_Automator
 */
class THR_ANON_FORM_SUBMITTED extends \Uncanny_Automator\Recipe\Trigger {

	protected $helpers;

	/**
	 * @return mixed
	 */
	protected function setup_trigger() {
		$this->helpers = array_shift( $this->dependencies );
		$this->set_integration( 'THRIVE_THEME_BUILDER' );
		$this->set_trigger_code( 'ANON_FORM_SUBMITTED' );
		$this->set_trigger_meta( 'THR_FORMS' );
		$this->set_trigger_type( 'anonymous' );
		// Trigger sentence - Thrive Theme
		// translators: 1: Form name
		$this->set_sentence( sprintf( esc_attr_x( '{{A form:%1$s}} is submitted', 'Thrive Theme', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_attr_x( '{{A form}} is submitted', 'Thrive Theme', 'uncanny-automator' ) );
		$this->add_action( 'tcb_api_form_submit' );
	}

	/**
	 * options
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'input_type'      => 'select',
				'required'        => true,
				'label'           => esc_attr_x( 'Form', 'Thrive Theme', 'uncanny-automator' ),
				'options'         => $this->helpers->get_thrive_theme_forms(),
				'relevant_tokens' => array(),
			),
		);
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
		if ( ! isset( $trigger['meta'][ $this->get_trigger_meta() ], $hook_args[0]['form_identifier'] ) ) {
			return false;
		}

		$selected_form = $this->helpers->get_extract_form_id_post_id( $trigger['meta'][ $this->get_trigger_meta() ] );

		return ( intval( '-1' ) === intval( $selected_form['form_identifier'] ) || $hook_args[0]['form_identifier'] === $selected_form['form_identifier'] );
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
		$common_tokens = $this->helpers->get_form_common_tokens();
		$field_tokens  = $this->helpers->get_from_field_tokens( $trigger['meta'][ $this->get_trigger_meta() ] );

		return array_merge( $common_tokens, $field_tokens );
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
		$form_data = $hook_args[0];

		$form_settings = \TCB\inc\helpers\FormSettings::get_one( $form_data ['_tcb_id'] );
		$config        = (array) json_decode( $form_settings->get_config(), true );

		$token_values = array(
			'FORM_ID'   => $config['form_identifier'],
			'FORM_NAME' => $config['form_identifier'],
		);

		$field_ids = array_keys( $config['inputs'] );

		foreach ( $field_ids as $id ) {
			if ( isset( $form_data[ $id ] ) ) {
				$token_values[ $id ] = is_array( $form_data[ $id ] ) ? implode( ', ', (array) $form_data[ $id ] ) : $form_data[ $id ];
			}
		}

		return $token_values;
	}

}
