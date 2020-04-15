<?php

namespace Uncanny_Automator;

/**
 * Class NF_SUBFORM
 * @package uncanny_automator
 */
class NF_SUBFORM {

	/**
	 * Integration code
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

		global $uncanny_automator;

		$trigger = array(
			'author'              => $uncanny_automator->get_author_name( $this->trigger_code ),
			'support_link'        => $uncanny_automator->get_author_support_link( $this->trigger_code ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: 1:Forms 2:Number of Times */
			'sentence'            => sprintf( __( 'User submits {{a form:%1$s}} {{a number of:%2$s}} times', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			'select_option_name'  => __( 'User submits {{a form}}', 'uncanny-automator' ),
			'action'              => 'ninja_forms_after_submission',
			'priority'            => 20,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'nform_submit' ),
			'options'             => [
				$uncanny_automator->helpers->recipe->ninja_forms->options->list_ninja_forms(),
				$uncanny_automator->helpers->recipe->options->number_of_times(),
			],
		);

		$uncanny_automator->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $form
	 */
	public function nform_submit( $form ) {

		global $uncanny_automator;

		if ( empty( $form ) ) {
			return;
		}

		$user_id = get_current_user_id();

		$args = [
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => intval( $form['form_id'] ),
			'user_id' => $user_id,
		];

		$uncanny_automator->maybe_add_trigger_entry( $args );
	}
}
