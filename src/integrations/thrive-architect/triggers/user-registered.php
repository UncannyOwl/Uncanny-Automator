<?php

namespace Uncanny_Automator\Integrations\Thrive_Architect;

/**
 * Class USER_REGISTERED
 * @package Uncanny_Automator
 */
class USER_REGISTERED extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * @return mixed
	 */
	protected function setup_trigger() {
		$this->set_helper( new Thrive_Architect_Helpers() );
		$this->set_integration( 'THRIVE_ARCHITECT' );
		$this->set_trigger_code( 'THRIVE_ARCHITECT_USER_REGISTERED' );
		$this->set_trigger_meta( 'THRIVE_ARCHITECT_USER_REGISTERED_META' );
		$this->set_is_login_required( false );
		// translators: %1$s. Form meta key
		$this->set_sentence( sprintf( esc_attr_x( 'A user registers via {{a registration form:%1$s}}', 'Thrive Architect', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_attr_x( 'A user registers via {{a registration form}}', 'Thrive Architect', 'uncanny-automator' ) );
		$this->add_action( 'thrive_register_form_through_wordpress_user', 10, 2 );
	}

	/**
	 * options
	 *
	 * method to display a default option group
	 *
	 * @return array
	 */
	public function options() {
		$options_value = $this->get_helper()->get_forms();

		return array(
			array(
				'input_type'      => 'select',
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Form', 'Thrive Architect', 'uncanny-automator' ),
				'required'        => true,
				'options'         => $options_value,
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
		if ( ! Thrive_Architect_Helpers::is_dependencies_ready() ) {
			return false;
		}

		$form_data     = $hook_args[1];
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

		return ( - 1 === intval( $selected_form ) || $config['form_identifier'] === $extracted_form_values['form_identifier'] );
	}

	/**
	 * define_tokens
	 *
	 * Trigger specific tokens
	 *
	 * @param mixed $tokens
	 * @param mixed $args
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ): array {

		$tokens[] = array(
			'tokenId'   => 'FORM_ID',
			'tokenName' => esc_html_x( 'Form ID', 'Thrive Architect', 'uncanny-automator' ),
			'tokenType' => 'text', // Token type can be 'text', 'int', 'email', 'url'.
		);

		$tokens[] = array(
			'tokenId'   => 'FORM_TITLE',
			'tokenName' => esc_html_x( 'Form title', 'Thrive Architect', 'uncanny-automator' ),
			'tokenType' => 'text', // Token type can be 'text', 'int', 'email', 'url'.
		);

		if ( ! Thrive_Architect_Helpers::is_dependencies_ready() ) {
			return $tokens;
		}

		// Get the trigger selected form value.
		$selected_form = $trigger['meta'][ $this->get_trigger_meta() ];

		// Extract to get the form post id.
		$extracted_form_values = Thrive_Architect_Helpers::extract_form_properties(
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
	 * @param mixed[] $completed_trigger The Trigger args.
	 * @param mixed[] $hook_args The accepted action hook arguments.
	 *
	 * @return mixed[]
	 */
	public function hydrate_tokens( $completed_trigger, $hook_args ) {

		$form_data = $hook_args[1];

		if ( ! Thrive_Architect_Helpers::is_dependencies_ready() ) {
			return array();
		}

		$form_settings = \TCB\inc\helpers\FormSettings::get_one( $form_data['_tcb_id'] );
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
