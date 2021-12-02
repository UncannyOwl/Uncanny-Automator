<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator;

/**
 * Class CF7_SUBFORM
 *
 * @package Uncanny_Automator
 */
class CF7_SUBFORM {

	/**
	 * Integration code
	 *
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

		if ( is_user_logged_in() ) {
			// Add filter if the user is logged in to fetch the ID.
			add_filter( 'wpcf7_verify_nonce', '__return_true' );
		}

		$this->define_trigger();

	}


	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/contact-form-7/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - Contact Form 7 */
			'sentence'            => sprintf( esc_attr__( 'A user submits {{a form:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - Contact Form 7 */
			'select_option_name'  => esc_attr__( 'A user submits {{a form}}', 'uncanny-automator' ),
			'action'              => 'wpcf7_submit',
			'priority'            => 99,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'wpcf7_submit' ),
			'options'             => array(
				Automator()->helpers->recipe->contact_form7->options->list_contact_form7_forms(),
				Automator()->helpers->recipe->options->number_of_times(),
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

		if ( 'validation_failed' !== $result['status'] ) {

			$user_id = wp_get_current_user()->ID;

			$args = array(
				'code'    => $this->trigger_code,
				'meta'    => $this->trigger_meta,
				'post_id' => $form->id(),
				'user_id' => $user_id,
			);

			$args = Automator()->maybe_add_trigger_entry( $args, false );

			//Adding an action to save contact form submission in trigger meta
			$recipes = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
			do_action( 'automator_save_cf7_form', $form, $recipes, $args );

			if ( $args ) {
				foreach ( $args as $result ) {
					if ( true === $result['result'] ) {
						Automator()->maybe_trigger_complete( $result['args'] );
					}
				}
			}
		}

	}
}
