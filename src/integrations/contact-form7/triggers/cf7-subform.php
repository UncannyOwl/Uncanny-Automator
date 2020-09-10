<?php

namespace Uncanny_Automator;

/**
 * Class CF7_SUBFORM
 * @package Uncanny_Automator_Pro
 */
class CF7_SUBFORM {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'CF7';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'CF7SUBFORM';
		$this->trigger_meta = 'CF7FORMS';
		//add_filter( 'wpcf7_verify_nonce', '__return_true' );
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
			/* translators: Logged-in trigger - Contact Form 7 */
			'sentence'            => sprintf(  esc_attr__( 'A user submits {{a form:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - Contact Form 7 */
			'select_option_name'  =>  esc_attr__( 'A user submits {{a form}}', 'uncanny-automator' ),
			'action'              => 'wpcf7_submit',
			'priority'            => 99,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'wpcf7_submit' ),
			'options'             => [
				$uncanny_automator->helpers->recipe->contact_form7->options->list_contact_form7_forms(),
				$uncanny_automator->helpers->recipe->options->number_of_times(),
			],
		);

		$uncanny_automator->register->trigger( $trigger );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $form
	 * @param $result
	 */
	public function wpcf7_submit( $form, $result ) {
		if ( 'validation_failed' !== $result['status'] ) {
			global $uncanny_automator;
			$user_id = wp_get_current_user()->ID;

			$args = [
				'code'    => $this->trigger_code,
				'meta'    => $this->trigger_meta,
				'post_id' => $form->id(),
				'user_id' => $user_id,
			];

			$args = $uncanny_automator->maybe_add_trigger_entry( $args, false );

			//Adding an action to save contact form submission in trigger meta
			$recipes = $uncanny_automator->get->recipes_from_trigger_code( $this->trigger_code );
			do_action( 'automator_save_cf7_form', $form, $recipes, $args );

			if ( $args ) {
				foreach ( $args as $result ) {
					if ( true === $result['result'] ) {
						$uncanny_automator->maybe_trigger_complete( $result['args'] );
					}
				}
			}
		}
	}
}
