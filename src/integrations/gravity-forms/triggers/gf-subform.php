<?php

namespace Uncanny_Automator;

/**
 * Class GF_SUBFORM
 * @package Uncanny_Automator
 */
class GF_SUBFORM {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'GF';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'GFSUBFORM';
		$this->trigger_meta = 'GFFORMS';
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
			/* translators: Logged-in trigger - Gravity Forms */
			'sentence'            => sprintf(  esc_attr__( 'A user submits {{a form:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - Gravity Forms */
			'select_option_name'  =>  esc_attr__( 'A user submits {{a form}}', 'uncanny-automator' ),
			'action'              => 'gform_after_submission',
			'priority'            => 20,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'gform_submit' ),
			'options'             => [
				$uncanny_automator->helpers->recipe->gravity_forms->options->list_gravity_forms(),
				$uncanny_automator->helpers->recipe->options->number_of_times(),
			],
		);

		$uncanny_automator->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $entry
	 * @param $form
	 */
	public function gform_submit( $entry, $form ) {

		global $uncanny_automator;

		if ( empty( $entry ) ) {
			return;
		}

		$user_id = get_current_user_id();

		$args = [
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => $form['id'],
			'user_id' => $user_id,
		];

		$uncanny_automator->maybe_add_trigger_entry( $args );
	}
}
