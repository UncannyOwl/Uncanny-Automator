<?php

namespace Uncanny_Automator;

/**
 * Class ANON_HF_SUBMITFORM
 *
 * @package Uncanny_Automator
 */
class ANON_HF_SUBMITFORM {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'HF';

	/**
	 * @var string
	 */
	private $trigger_code;
	/**
	 * @var string
	 */
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'ANONHFSUBMITFORM';
		$this->trigger_meta = 'ANONHFFORM';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {
		$trigger = array(
			'author'              => Automator()->get_author_name(),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/happyforms/' ),
			'is_pro'              => false,
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Anonymous trigger - Formidable */
			'sentence'            => sprintf( __( '{{A form:%1$s}} is submitted', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Anonymous trigger - Formidable */
			'select_option_name'  => __( '{{A form}} is submitted', 'uncanny-automator' ),
			'type'                => 'anonymous',
			'action'              => 'happyforms_submission_success',
			'priority'            => 20,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'hf_submit_form' ),
			'options'             => array(
				Automator()->helpers->recipe->happyforms->options->all_happyforms_forms( null, $this->trigger_meta ),
			),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $entry_id
	 * @param $form_id
	 */
	public function hf_submit_form( $submission, $form, $misc ) {
		$user_id = get_current_user_id();

		$args = array(
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => intval( $form['ID'] ),
			'user_id' => intval( $user_id ),
		);

		$result = Automator()->process->user->maybe_add_trigger_entry( $args, false );

		if ( $result ) {
			foreach ( $result as $r ) {
				if ( true === $r['result'] ) {
					if ( isset( $r['args'] ) && isset( $r['args']['get_trigger_id'] ) ) {
						//Saving form values in trigger log meta for token parsing!
						$hf_args = array(
							'trigger_id'     => (int) $r['args']['trigger_id'],
							'meta_key'       => $this->trigger_meta,
							'user_id'        => $user_id,
							'trigger_log_id' => $r['args']['get_trigger_id'],
							'run_number'     => $r['args']['run_number'],
						);

						Automator()->helpers->recipe->happyforms->extract_save_hf_fields( $submission, $form['ID'], $hf_args );
					}
					Automator()->process->user->maybe_trigger_complete( $r['args'] );
				}
			}
		}
	}
}
