<?php
namespace Uncanny_Automator\Integrations\Gravity_Forms;

/**
 * Class ANON_GF_FORM_ENTRY_UPDATED
 *
 * @package Uncanny_Automator
 */
class ANON_GF_FORM_ENTRY_UPDATED extends \Uncanny_Automator\Recipe\Trigger {

	private $gf;

	/**
	 * setup_trigger
	 *
	 * @return void
	 */
	public function setup_trigger() {

		$this->gf = array_shift( $this->dependencies );

		$this->set_integration( 'GF' );

		$this->set_trigger_code( 'ANON_GF_FORM_ENTRY_UPDATED' );

		$this->set_trigger_meta( 'ANON_GF_FORM_ENTRY_UPDATED_META' );

		$this->set_is_login_required( false );

		$this->set_trigger_type( 'anonymous' );

		// The action hook to attach this trigger into.
		$this->add_action( array( 'gform_after_update_entry', 'gform_post_update_entry' ) );

		// The number of arguments that the action hook accepts.
		$this->set_action_args_count( 3 );

		$this->set_sentence(
			sprintf(
				// translators: %1$s: Form name
				esc_html_x( 'An entry for {{a form:%1$s}} is updated', 'Gravity Forms', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'An entry for {{a form}} is updated', 'Gravity Forms', 'uncanny-automator' )
		);
	}
	/**
	 * Process data from two hooks.
	 *
	 * @param mixed $hook_args The arguments.
	 * @return mixed
	 */
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
	/**
	 * Is form.
	 *
	 * @param mixed $array The array.
	 * @return mixed
	 */
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
				'label'           => esc_attr_x( 'Form', 'Gravity Forms', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => $this->gf->helpers->get_forms_as_options( true ),
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

		$form_tokens = $this->gf->tokens->possible_tokens->form_tokens( $form_id );

		foreach ( $form_tokens as $key => $field_token ) {
			$field_token['tokenId'] = $this->reformat_token_id( $form_id, $field_token['tokenId'] );
			$tokens[ $key ]         = $field_token;
		}

		$entry_tokens = array(
			array(
				'tokenId'   => 'ENTRY_ID',
				'tokenName' => esc_html_x( 'Entry ID', 'Gravity Forms', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'ENTRY_DATE_SUBMITTED',
				'tokenName' => esc_html_x( 'Entry submission date', 'Gravity Forms', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ENTRY_DATE_UPDATED',
				'tokenName' => esc_html_x( 'Entry date updated', 'Gravity Forms', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ENTRY_URL_SOURCE',
				'tokenName' => esc_html_x( 'Entry source URL', 'Gravity Forms', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'USER_IP',
				'tokenName' => esc_html_x( 'User IP', 'Gravity Forms', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);

		return array_merge( $tokens, $entry_tokens );
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
			// Get entry to check created_by
			$entry = \GFAPI::get_entry( $entry_id );

			// Set user ID dynamically
			$user_id = isset( $entry['created_by'] ) && 0 !== absint( $entry['created_by'] ) ? absint( $entry['created_by'] ) : wp_get_current_user()->ID;
			$user_id = apply_filters( 'automator_pro_gravity_forms_user_id', $user_id, $entry, wp_get_current_user() );

			$this->set_user_id( $user_id );

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

		$entry = \GFAPI::get_entry( $entry_id );

		$tokens = array(
			'FORM_ID'              => $form['id'],
			'FORM_TITLE'           => $form['title'],
			'ENTRY_DATE_SUBMITTED' => $entry['date_created'],
			'ENTRY_DATE_UPDATED'   => $entry['date_updated'],
			'ENTRY_URL_SOURCE'     => $entry['source_url'],
			'ENTRY_ID'             => $entry_id,
			'USER_IP'              => $entry['ip'],
		);

		$form_tokens = $this->gf->tokens->parser->parsed_fields_tokens( $form, $entry );

		foreach ( $form_tokens as $token_id => $token_value ) {
			$new_token_id            = $this->reformat_token_id( $form['id'], $token_id );
			$tokens[ $new_token_id ] = $token_value;
		}

		return $tokens;
	}
	/**
	 * Reformat token id.
	 *
	 * @param mixed $form_id The ID.
	 * @param mixed $string The string.
	 * @return mixed
	 */
	public function reformat_token_id( $form_id, $string ) {

		$search = $form_id . '|';

		if ( 0 === strpos( $string, $search ) ) {
			$new_string = 'field_' . substr( $string, strlen( $search ) );
			return $new_string;
		}

		return $string;
	}
}
