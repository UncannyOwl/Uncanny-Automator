<?php
namespace Uncanny_Automator\Integrations\Thrive_Architect;

/**
 * Class FORM_SUBMITTED.
 *
 * @package Uncanny_Automator\Integrations\Thrive_Architect
 */
class FORM_SUBMITTED extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Setups the Trigger.
	 *
	 * @return void
	 */
	public function setup_trigger() {

		$this->set_integration( 'THRIVE_ARCHITECT' );
		$this->set_trigger_code( 'THRIVE_ARCHITECT_FORM_SUBMITTED' );
		$this->set_trigger_meta( 'THRIVE_ARCHITECT_FORM_SUBMITTED_META' );
		$this->set_is_login_required( false );
		$this->set_trigger_type( 'anonymous' );

		// The action hook to attach this trigger into.
		$this->add_action( array( 'tcb_api_form_submit' ) );

		// The number of arguments that the action hook accepts.
		$this->set_action_args_count( 1 );

		$this->set_sentence(
			sprintf(
				/* Translators: Trigger sentence */
				esc_html_x( 'A {{form:%1$s}} is submitted', 'Thrive Architect', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			/* Translators: Trigger sentence */
			esc_html__( 'A {{form}} is submitted', 'Thrive Architect', 'uncanny-automator' )
		);

		$this->set_helper( new Thrive_Architect_Helpers() );
	}

	/**
	 * Retrieves the fields to be used as option fields in the dropdown.
	 *
	 * @return array The dropdown option fields.
	 */
	public function options() {

		$options_value = $this->get_helper()->get_forms();

		$form = array(
			'input_type'      => 'select',
			'option_code'     => $this->get_trigger_meta(),
			'label'           => _x( 'Form', 'Thrive Architect', 'uncanny-automator' ),
			'required'        => true,
			'options'         => $options_value,
			'options_show_id' => false,
			'relevant_tokens' => array(),
		);

		return array( $form );

	}

	/**
	 * Validates the trigger before processing.
	 *
	 * @return boolean False or true.
	 */
	public function validate( $trigger, $hook_args ) {

		if ( ! Thrive_Architect_Helpers::is_dependencies_ready() ) {
			return false;
		}

		$form_data     = $hook_args[0];
		$form_settings = \TCB\inc\helpers\FormSettings::get_one( $form_data['_tcb_id'] );

		if ( 'TCB\\inc\\helpers\\FormSettings' !== get_class( $form_settings ) ) {
			return false;
		}

		$config = (array) json_decode( $form_settings->get_config(), true );

		if ( ! isset( $config['form_identifier'] ) || ! isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ) {
			return false;
		}

		$selected_form = isset( $trigger['meta'][ $this->get_trigger_meta() ] )
			? $trigger['meta'][ $this->get_trigger_meta() ] : '';

		$extracted_form_values = Thrive_Architect_Helpers::extract_form_properties( $selected_form );

		// Any logic.
		if ( -1 === intval( $selected_form ) ) {
			return true;
		}

		return $config['form_identifier'] === $extracted_form_values['form_identifier'];

	}

	/**
	 * Defines the tokens.
	 *
	 * @param mixed $trigger
	 * @param mixed $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ): array {

		$tokens[] = array(
			'tokenId'   => 'FORM_ID',
			'tokenName' => _x( 'Form ID', 'Thrive Architect', 'uncanny-automator' ),
			'tokenType' => 'text', // Token type can be 'text', 'int', 'email', 'url'.
		);

		$tokens[] = array(
			'tokenId'   => 'FORM_TITLE',
			'tokenName' => _x( 'Form title', 'Thrive Apprentice', 'uncanny-automator' ),
			'tokenType' => 'text', // Token type can be 'text', 'int', 'email', 'url'.
		);

		if ( ! Thrive_Architect_Helpers::is_dependencies_ready() ) {
			return $tokens;
		}

		// Get the trigger selected form value.
		$selected_form = $trigger['meta'][ $this->get_trigger_meta() ];

		// Extract to get the form post id.
		$extracted_form_values = Thrive_Architect_Helpers::extract_form_properties( // <-- this function right here returns an error.
			$selected_form
		);

		$form_settings = \TCB\inc\helpers\FormSettings::get_one( $extracted_form_values['form_post_id'] );
		$form_settings = (array) json_decode( $form_settings->get_config(), true );

		// Iterate through the inputs and serve them as tokens.
		foreach ( (array) $form_settings['inputs'] as $id => $props ) {
			$tokens[] = array(
				'tokenId'   => $id,
				'tokenName' => $props['label'],
				'tokenType' => 'text',
			);
		}

		return $tokens;

	}

	/**
	 * Populate the tokens with actual values when a trigger runs.
	 *
	 * @param mixed[] $trigger The Trigger args.
	 * @param mixed[] $hook_args The accepted action hook arguments.
	 *
	 * @return mixed[]
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$form_data = $hook_args[0];

		if ( ! Thrive_Architect_Helpers::is_dependencies_ready() ) {
			return array();
		}

		$form_settings = \TCB\inc\helpers\FormSettings::get_one( $form_data ['_tcb_id'] );
		$config        = (array) json_decode( $form_settings->get_config(), true );

		$token_values = array(
			'FORM_ID'    => $config['form_identifier'],
			'FORM_TITLE' => $config['form_identifier'],
		);

		$field_ids = array_keys( $config['inputs'] );

		foreach ( $field_ids as $id ) {
			if ( isset( $form_data[ $id ] ) ) {
				$token_values[ $id ] = Thrive_Architect_Helpers::handle_as_token( $form_data[ $id ] );
			}
		}

		return $token_values;

	}

}
