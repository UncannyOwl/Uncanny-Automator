<?php
namespace Uncanny_Automator\Integrations\Gravity_Forms;

class ANON_GF_FORM_ENTRY_UPDATED extends \Uncanny_Automator\Recipe\Trigger {

	const TRIGGER_CODE = 'ANON_GF_FORM_ENTRY_UPDATED';

	const TRIGGER_META = 'ANON_GF_FORM_ENTRY_UPDATED_META';

	private $gf;

	/**
	 * setup_trigger
	 *
	 * @return void
	 */
	public function setup_trigger() {

		$this->gf = array_shift( $this->dependencies );

		$this->set_integration( 'GF' );

		$this->set_trigger_code( self::TRIGGER_CODE );

		$this->set_trigger_meta( self::TRIGGER_META );

		$this->set_is_login_required( false );

		$this->set_trigger_type( 'anonymous' );

		// The action hook to attach this trigger into.
		$this->add_action( array( 'gform_after_update_entry', 'gform_post_update_entry' ) );

		// The number of arguments that the action hook accepts.
		$this->set_action_args_count( 3 );

		$this->set_sentence(
			sprintf(
				/* Translators: Trigger sentence */
				esc_html__( 'An entry for {{a form:%1$s}} is updated', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			/* Translators: Trigger sentence */
			esc_html__( 'An entry for {{a form}} is updated', 'uncanny-automator' )
		);
	}

	public function process_data_from_two_hooks( $hook_args ) {

		list( $arg1, $arg2, $arg3 ) = $hook_args;

		$current_action = current_action();

		switch ( $current_action ) {
			case 'gform_after_update_entry':
				$form           = $arg1;
				$entry_id       = $arg2;
				$original_entry = $arg3;
				break;
			case 'gform_post_update_entry':
				$entry          = $arg1;
				$entry_id       = $entry['id'];
				$form_id        = $entry['form_id'];
				$form           = \GFAPI::get_form( $form_id );
				$original_entry = $arg2;
				break;
			default:
				# code...
				break;
		}

		return array( $form, $entry_id, $original_entry );
	}

	public function is_form( $array ) {

		if ( isset( $array['title'] ) && isset( $array['fields'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Retrieves the fields to be used as option fields in the dropdown.
	 *
	 * @return array The dropdown option fields.
	 */
	public function options() {

		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_attr__( 'Form', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => $this->gf->get_forms_options(),
				'relevant_tokens' => array(),
			),
		);

	}

	/**
	 * define_tokens
	 *
	 * @param  array $trigger
	 * @param  array $tokens
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {

		$form_id = $trigger['meta'][ $this->get_trigger_meta() ];

		$tokens = array_merge( $this->gf->tokens->form_specific_tokens( $form_id ), $this->gf->tokens->entry_tokens() );

		return $tokens;
	}

	/**
	 * Validates the trigger before processing.
	 *
	 * @return boolean False or true.
	 */
	public function validate( $trigger, $hook_args ) {

		// If any form is selected
		if ( '-1' === $trigger['meta'][ $this->get_trigger_meta() ] ) {
			return true;
		}

		$hook_args = $this->process_data_from_two_hooks( $hook_args );

		list( $form, $entry_id, $original_entry ) = $hook_args;

		if ( absint( $form['id'] ) === absint( $trigger['meta'][ $this->get_trigger_meta() ] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Method hydrate_tokens.
	 *
	 * @param $parsed
	 * @param $args
	 * @param $trigger
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$hook_args = $this->process_data_from_two_hooks( $hook_args );

		list( $form, $entry_id, $original_entry ) = $hook_args;

		$entry_tokens = $this->gf->tokens->hydrate_entry_tokens( $entry_id, $form );
		$form_tokens  = $this->gf->tokens->hydrate_form_tokens( $form );

		return $entry_tokens + $form_tokens;
	}

}
