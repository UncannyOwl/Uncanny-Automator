<?php

namespace Uncanny_Automator;

/**
 * Class ANON_GF_SUBFIELD
 * @package Uncanny_Automator
 */
class ANON_GF_SUBFORM {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'GF';

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
		$this->trigger_code = 'ANONGFSUBFORM';
		$this->trigger_meta = 'ANONGFFORMS';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {
		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/gravity-forms/' ),
			'is_pro'              => false,
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Anonymous trigger - Gravity Forms */
			'sentence'            => sprintf( __( '{{A form:%1$s}} is submitted', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Anonymous trigger - Gravity Forms */
			'select_option_name'  => __( '{{A form}} is submitted', 'uncanny-automator' ),
			'action'              => 'gform_after_submission',
			'type'                => 'anonymous',
			'priority'            => 20,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'gform_submit' ),
			'options'             => [
				Automator()->helpers->recipe->gravity_forms->options->list_gravity_forms( null, $this->trigger_meta ),
			],
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $entry
	 * @param $form
	 */
	public function gform_submit( $entry, $form ) {
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
		Automator()->process->user->maybe_add_trigger_entry( $args );
	}
}
