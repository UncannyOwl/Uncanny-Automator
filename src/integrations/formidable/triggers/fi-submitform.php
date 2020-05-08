<?php

namespace Uncanny_Automator;

/**
 * Class FI_SUBMITFORM
 * @package uncanny_automator
 */
class FI_SUBMITFORM {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'FI';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'FISUBMITFORM';
		$this->trigger_meta = 'FIFORM';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		global $uncanny_automator;

		$trigger = array(
			'author'              => $uncanny_automator->get_author_name(),
			'support_link'        => $uncanny_automator->get_author_support_link(),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - Formidable */
			'sentence'            => sprintf( __( 'A user submits {{a form:%1$s}}', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - Formidable */
			'select_option_name'  => __( 'A user submits {{a form}}', 'uncanny-automator' ),
			'action'              => 'frm_process_entry',
			'priority'            => 10,
			'accepted_args'       => 4,
			'validation_function' => array( $this, 'fi_submit_form' ),
			'options'             => [
				$uncanny_automator->helpers->recipe->formidable->options->all_formidable_forms( null, $this->trigger_meta),
			],
		);

		$uncanny_automator->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param object $params params array.
	 * @param object $errors errors array.
	 * @param object $form form object.
	 * @param object $args other settings.
	 */
	public function fi_submit_form( $params, $errors, $form, $args ) {

		global $uncanny_automator;

		$user_id = get_current_user_id();
		if ( empty( $user_id ) ) {
			return;
		}

		$args = [
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => intval( $form->id ),
			'user_id' => intval( $user_id ),
		];

		$uncanny_automator->maybe_add_trigger_entry( $args );

	}
}
