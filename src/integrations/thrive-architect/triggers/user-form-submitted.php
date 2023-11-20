<?php
namespace Uncanny_Automator\Integrations\Thrive_Architect;

/**
 * Class USER_FORM_SUBMITTED.
 *
 * @package Uncanny_Automator\Integrations\Thrive_Architect
 */
class USER_FORM_SUBMITTED extends FORM_SUBMITTED {

	/**
	 * Setups the Trigger.
	 *
	 * @return void
	 */
	public function setup_trigger() {

		$this->set_integration( 'THRIVE_ARCHITECT' );
		$this->set_trigger_code( 'THRIVE_ARCHITECT_USER_FORM_SUBMITTED' );
		$this->set_trigger_meta( 'THRIVE_ARCHITECT_USER_FORM_SUBMITTED_META' );
		$this->set_trigger_type( 'user' );

		// The action hook to attach this trigger into.
		$this->add_action( array( 'tcb_api_form_submit' ) );

		// The number of arguments that the action hook accepts.
		$this->set_action_args_count( 1 );

		$this->set_sentence(
			sprintf(
				/* Translators: Trigger sentence */
				esc_html_x( 'A user submits a {{form:%1$s}}', 'Thrive Architect', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			/* Translators: Trigger sentence */
			esc_html__( 'A user submits a {{form}}', 'Thrive Architect', 'uncanny-automator' )
		);

		$this->set_helper( new Thrive_Architect_Helpers() );
	}

}
