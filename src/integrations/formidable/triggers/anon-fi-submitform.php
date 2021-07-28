<?php

namespace Uncanny_Automator;

/**
 * Class ANON_FI_SUBMITFORM
 * @package Uncanny_Automator
 */
class ANON_FI_SUBMITFORM {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'FI';

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
		$this->trigger_code = 'ANONFISUBMITFORM';
		$this->trigger_meta = 'ANONFIFORM';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {
		$trigger = array(
			'author'              => Automator()->get_author_name(),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/formidable-forms/' ),
			'is_pro'              => false,
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Anonymous trigger - Formidable */
			'sentence'            => sprintf( __( '{{A form:%1$s}} is submitted', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Anonymous trigger - Formidable */
			'select_option_name'  => __( '{{A form}} is submitted', 'uncanny-automator' ),
			'type'                => 'anonymous',
			'action'              => 'frm_after_create_entry',
			'priority'            => 20,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'fi_submit_form' ),
			'options'             => [
				Automator()->helpers->recipe->formidable->options->all_formidable_forms( null, $this->trigger_meta ),
			],
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $entry_id
	 * @param $form_id
	 */
	public function fi_submit_form( $entry_id, $form_id ) {

		$user_id = get_current_user_id();

		$args = [
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => intval( $form_id ),
			'user_id' => intval( $user_id ),
		];

		$result = Automator()->process->user->maybe_add_trigger_entry( $args, false );

		if ( $result ) {
			foreach ( $result as $r ) {
				if ( true === $r['result'] ) {
					if ( isset( $r['args'] ) && isset( $r['args']['get_trigger_id'] ) ) {
						//Saving form values in trigger log meta for token parsing!
						$fi_args = [
							'trigger_id'     => (int) $r['args']['trigger_id'],
							'meta_key'       => $this->trigger_meta,
							'user_id'        => $user_id,
							'trigger_log_id' => $r['args']['get_trigger_id'],
							'run_number'     => $r['args']['run_number'],
						];

						Automator()->helpers->recipe->formidable->extract_save_fi_fields( $entry_id, $form_id, $fi_args );
					}
					Automator()->process->user->maybe_trigger_complete( $r['args'] );
				}
			}
		}
	}
}
