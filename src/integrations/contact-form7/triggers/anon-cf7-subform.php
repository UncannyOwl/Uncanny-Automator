<?php

namespace Uncanny_Automator;

/**
 * Class ANON_CF7_SUBFORM
 *
 * @package Uncanny_Automator
 */
class ANON_CF7_SUBFORM {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'CF7';

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
		$this->trigger_code = 'ANONCF7SUBFORM';
		$this->trigger_meta = 'ANONCF7FORMS';
		$this->define_trigger();
	}


	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {
		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/contact-form-7/' ),
			'is_pro'              => false,
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			'meta'                => $this->trigger_meta,
			/* translators: Anonymous trigger - Contact Form 7 */
			'sentence'            => sprintf( __( '{{A form:%1$s}} is submitted', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Anonymous trigger - Contact Form 7 */
			'select_option_name'  => __( '{{A form}} is submitted', 'uncanny-automator' ),
			'action'              => 'wpcf7_submit',
			'type'                => 'anonymous',
			'priority'            => 99,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'wpcf7_submit' ),
			'options'             => array(
				Automator()->helpers->recipe->contact_form7->options->list_contact_form7_forms( null, $this->trigger_meta ),
			),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $form
	 * @param $result
	 */
	public function wpcf7_submit( $form, $result ) {
		if ( 'validation_failed' === (string) $result['status'] ) {
			return;
		}
		$args = array(
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => $form->id(),
			'user_id' => 0,
		);

		$args = Automator()->process->user->maybe_add_trigger_entry( $args, false );

		//Adding an action to save contact form submission in trigger meta
		$recipes = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		do_action( 'automator_save_anon_cf7_form', $form, $recipes, $args );

		if ( $args ) {
			foreach ( $args as $result ) {
				if ( true === $result['result'] ) {
					Automator()->process->user->maybe_trigger_complete( $result['args'] );
				}
			}
		}
	}
}
