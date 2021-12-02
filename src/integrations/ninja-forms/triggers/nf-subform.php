<?php

namespace Uncanny_Automator;

/**
 * Class NF_SUBFORM
 *
 * @package Uncanny_Automator
 */
class NF_SUBFORM {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'NF';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'NFSUBFORM';
		$this->trigger_meta = 'NFFORMS';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/ninja-forms/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - Ninja Forms */
			'sentence'            => sprintf( esc_attr__( 'A user submits {{a form:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - Ninja Forms */
			'select_option_name'  => esc_attr__( 'A user submits {{a form}}', 'uncanny-automator' ),
			'action'              => 'ninja_forms_after_submission',
			'priority'            => 20,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'nform_submit' ),
			'options'             => array(
				Automator()->helpers->recipe->ninja_forms->options->list_ninja_forms(),
				Automator()->helpers->recipe->options->number_of_times(),
			),
		);

		Automator()->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $form
	 */
	public function nform_submit( $form ) {

		if ( empty( $form ) ) {
			return;
		}

		$user_id = get_current_user_id();

		$args = array(
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => intval( $form['form_id'] ),
			'user_id' => $user_id,
		);

		$result = Automator()->maybe_add_trigger_entry( $args, false );

		if ( $result ) {
			foreach ( $result as $r ) {
				if ( true === $r['result'] ) {
					if ( isset( $r['args'] ) && isset( $r['args']['get_trigger_id'] ) ) {
						//Saving form values in trigger log meta for token parsing!
						$ninja_args = array(
							'trigger_id'     => (int) $r['args']['trigger_id'],
							'meta_key'       => $this->trigger_meta,
							'user_id'        => $user_id,
							'trigger_log_id' => $r['args']['get_trigger_id'],
							'run_number'     => $r['args']['run_number'],
						);

						Automator()->helpers->recipe->ninja_forms->extract_save_ninja_fields( $form, $ninja_args );
					}

					Automator()->maybe_trigger_complete( $r['args'] );
				}
			}
		}
	}
}
