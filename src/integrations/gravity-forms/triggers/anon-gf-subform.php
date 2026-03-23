<?php

namespace Uncanny_Automator\Integrations\Gravity_Forms;

/**
 * Class ANON_GF_SUBFIELD
 *
 * @package Uncanny_Automator
 */
class ANON_GF_SUBFORM extends \Uncanny_Automator\Recipe\Trigger {

	private $gf;

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function setup_trigger() {

		$this->gf = array_shift( $this->dependencies );

		$this->set_integration( 'GF' );

		$this->set_trigger_code( 'ANONGFSUBFORM' );

		$this->set_trigger_meta( 'ANONGFFORMS' );

		$this->set_trigger_type( 'anonymous' );

		$this->set_sentence(
			sprintf(
				/* translators: Anonymous trigger - Gravity Forms */
				esc_html_x( '{{A form:%1$s}} is submitted', 'Gravity Forms', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			/* translators: Anonymous trigger - Gravity Forms */
			esc_html_x( '{{A form}} is submitted', 'Gravity Forms', 'uncanny-automator' )
		);

		$this->add_action( 'gform_after_submission', 10, 2 );

		$this->set_author( Automator()->get_author_name( $this->trigger_code ) );

		$this->set_support_link( Automator()->get_author_support_link( $this->trigger_code, 'integration/gravity-forms/' ) );

		$this->set_can_log_in_new_user( true );
	}

	/**
	 * @return array
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

		$tokens[] = array(
			'tokenId'   => 'ANONGFFORMS',
			'tokenName' => esc_html_x( 'Form title', 'Gravity Forms', 'uncanny-automator' ),
			'tokenType' => 'text',
		);

		$tokens[] = array(
			'tokenId'   => 'ANONGFFORMS_ID',
			'tokenName' => esc_html_x( 'Form ID', 'Gravity Forms', 'uncanny-automator' ),
			'tokenType' => 'int',
		);

		$form_tokens = $this->gf->tokens->possible_tokens->form_tokens( $form_id, $this->trigger_meta );

		// Remove duplicate Form title and Form ID tokens from $form_tokens since we already added them manually
		$form_tokens = array_filter(
			$form_tokens,
			function ( $token ) {
				return ! in_array( $token['tokenId'], array( 'FORM_TITLE', 'FORM_ID' ) );
			}
		);

		$entry_tokens = $this->gf->tokens->possible_tokens->entry_tokens( 'GFENTRYTOKENS' );

		$tokens = array_merge( $tokens, $form_tokens, $entry_tokens );

		return $tokens;
	}

	/**
	 * validate
	 *
	 * @param  array $trigger
	 * @param  array $hook_args
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		$selected_form_id = $trigger['meta'][ $this->get_trigger_meta() ];

		// If any form is selected
		if ( intval( '-1' ) === intval( $selected_form_id ) ) {
			return true;
		}

		list( $entry, $form ) = $hook_args;

		if ( absint( $form['id'] ) === absint( $selected_form_id ) ) {
			// Set user ID dynamically
			$user_id = isset( $entry['created_by'] ) && 0 !== absint( $entry['created_by'] ) ? absint( $entry['created_by'] ) : wp_get_current_user()->ID;
			$user_id = apply_filters( 'automator_pro_gravity_forms_user_id', $user_id, $entry, wp_get_current_user() );

			$this->set_user_id( $user_id );

			return true;
		}

		return false;
	}

	/**
	 * hydrate_tokens
	 *
	 * @param  array $trigger
	 * @param  array $hook_args
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $entry, $form ) = $hook_args;

		$fields_tokens = $this->gf->tokens->parser->parsed_fields_tokens( $form, $entry );
		$entry_tokens  = $this->gf->tokens->parser->parsed_entry_tokens( $entry );

		$this->save_tokens( $this->trigger_meta, $fields_tokens );
		$this->save_tokens( 'GFENTRYTOKENS', $entry_tokens );

		$trigger_tokens = array(
			'ANONGFFORMS'    => $form['title'],
			'ANONGFFORMS_ID' => $form['id'],
		);

		return $trigger_tokens;
	}
}
