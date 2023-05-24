<?php

namespace Uncanny_Automator\Integrations\Gravity_Forms;

/**
 * Class GF_SUBFORM
 *
 * @package Uncanny_Automator
 */
class GF_SUBFORM extends \Uncanny_Automator\Recipe\Trigger {

	const TRIGGER_CODE = 'GFSUBFORM';

	const TRIGGER_META = 'GFFORMS';

	private $gf;

	/**
	 *
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function setup_trigger() {

		$this->gf = array_shift( $this->dependencies );

		$this->set_integration( 'GF' );

		$this->set_trigger_code( self::TRIGGER_CODE );

		$this->set_trigger_meta( self::TRIGGER_META );

		$this->set_sentence(
			sprintf(
				/* translators: Anonymous trigger - Gravity Forms */
				esc_html__( 'A user submits {{a form:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ),
				$this->get_trigger_meta(),
				'NUMTIMES'
			)
		);

		$this->set_readable_sentence(
			/* translators: Anonymous trigger - Gravity Forms */
			esc_html__( 'A user submits {{a form}}', 'uncanny-automator' )
		);

		$this->add_action( 'gform_after_submission' );

		$this->set_action_args_count( 2 );

		$this->set_author( Automator()->get_author_name( $this->trigger_code ) );

		$this->set_support_link( Automator()->get_author_support_link( $this->trigger_code, 'integration/gravity-forms/' ) );

	}

	/**
	 * load_options
	 *
	 * @return array
	 */
	public function load_options() {
		return array(
			'options' => array(
				array(
					'option_code'     => $this->get_trigger_meta(),
					'label'           => esc_attr__( 'Form', 'uncanny-automator' ),
					'input_type'      => 'select',
					'required'        => true,
					'options'         => $this->gf->get_forms_options(),
					'relevant_tokens' => array(),
				),
				'NUMTIMES' => Automator()->helpers->recipe->options->number_of_times(),
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
			'tokenId'   => self::TRIGGER_META,
			'tokenName' => __( 'Form title', 'uncanny-automator' ),
			'tokenType' => 'text',
		);

		$tokens[] = array(
			'tokenId'   => self::TRIGGER_META . '_ID',
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
