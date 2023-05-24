<?php

namespace Uncanny_Automator\Integrations\Gravity_Forms;

/**
 * Class ANON_GF_SUBFIELD
 *
 * @package Uncanny_Automator
 */
class ANON_GF_SUBFORM extends \Uncanny_Automator\Recipe\Trigger {

	const TRIGGER_CODE = 'ANONGFSUBFORM';

	const TRIGGER_META = 'ANONGFFORMS';

	private $gf;

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function setup_trigger() {

		$this->gf = array_shift( $this->dependencies );

		$this->set_integration( 'GF' );

		$this->set_trigger_code( self::TRIGGER_CODE );

		$this->set_trigger_meta( self::TRIGGER_META );

		$this->set_trigger_type( 'anonymous' );

		$this->set_sentence(
			sprintf(
				/* translators: Anonymous trigger - Gravity Forms */
				esc_html__( '{{A form:%1$s}} is submitted', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			/* translators: Anonymous trigger - Gravity Forms */
			esc_html__( '{{A form}} is submitted', 'uncanny-automator' )
		);

		$this->add_action( 'gform_after_submission' );

		$this->set_action_args_count( 2 );

		$this->set_author( Automator()->get_author_name( $this->trigger_code ) );

		$this->set_support_link( Automator()->get_author_support_link( $this->trigger_code, 'integration/gravity-forms/' ) );

	}

	/**
	 * @return array
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

		$tokens[] = array(
			'tokenId'   => 'ANONGFFORMS',
			'tokenName' => __( 'Form title', 'uncanny-automator' ),
			'tokenType' => 'text',
		);

		$tokens[] = array(
			'tokenId'   => 'ANONGFFORMS_ID',
			'tokenName' => __( 'Form ID', 'uncanny-automator' ),
			'tokenType' => 'int',
		);

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

		$selected_form_id = absint( $trigger['meta'][ $this->get_trigger_meta() ] );

		// If any form is selected
		if ( -1 === $selected_form_id ) {
			return true;
		}

		list( $entry, $form ) = $hook_args;

		if ( absint( $form['id'] ) === $selected_form_id ) {
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

		$this->gf->tokens->save_legacy_trigger_tokens( $this->trigger_records, $entry, $form );

		return array();
	}
}
