<?php

namespace Uncanny_Automator;

/**
 * Class HF_FORMSUBMIT
 *
 * @package Uncanny_Automator
 */
class HF_FORMSUBMIT {

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
		$this->trigger_code = 'HFSUBMITFORM';
		$this->trigger_meta = 'HFFORM';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name(),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/happyforms/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Anonymous trigger - HappyForms */
			'sentence'            => sprintf( esc_attr__( 'A user submits {{a form:%1$s}}', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Anonymous trigger - HappyForms */
			'select_option_name'  => esc_attr__( 'A user submits {{a form}}', 'uncanny-automator' ),
			'action'              => 'happyforms_submission_success',
			'priority'            => 20,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'hf_form_submitted' ),
			'options'             => array(
				Automator()->helpers->recipe->happyforms->options->all_happyforms_forms(
					null,
					$this->trigger_meta,
					array(
						'include_any' => true,
						'any_label'   => esc_attr__( 'Any form', 'uncanny-automator' ),
					)
				),
			),
		);

		Automator()->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $submission
	 * @param $form
	 * @param $misc
	 */

	public function hf_form_submitted( $submission, $form, $misc ) {

		$user_id = get_current_user_id();

		$args = array(
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => intval( $form['ID'] ),
			'user_id' => intval( $user_id ),
		);

		$result = Automator()->maybe_add_trigger_entry( $args, false );

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
					Automator()->maybe_trigger_complete( $r['args'] );
				}
			}
		}
	}
}
