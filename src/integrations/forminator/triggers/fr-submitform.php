<?php

namespace Uncanny_Automator;

/**
 * Class FR_SUBMITFORM
 * @package Uncanny_Automator
 */
class FR_SUBMITFORM {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'FR';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'FRSUBMITFORM';
		$this->trigger_meta = 'FRFORM';
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
			/* translators: Logged-in trigger - Forminator */
			'sentence'            => sprintf(  esc_attr__( 'A user submits {{a form:%1$s}}', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - Forminator */
			'select_option_name'  =>  esc_attr__( 'A user submits {{a form}}', 'uncanny-automator' ),
			'action'              => 'forminator_custom_form_after_save_entry',
			'priority'            => 100,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'fr_submit_form' ),
			'options'             => [
				$uncanny_automator->helpers->recipe->forminator->options->all_forminator_forms( null, $this->trigger_meta ),
			],
		);

		$uncanny_automator->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param int $form_id submitted form id.
	 * @param array $response response array.
	 * @param $method
	 */
	public function fr_submit_form( $form_id, $response, $method ) {
		if ( true === $response['success'] ) {
			global $uncanny_automator;

			$user_id = get_current_user_id();
			if ( empty( $user_id ) ) {
				return;
			}

			$args = [
				'code'    => $this->trigger_code,
				'meta'    => $this->trigger_meta,
				'post_id' => intval( $form_id ),
				'user_id' => intval( $user_id ),
			];

			$args = $uncanny_automator->maybe_add_trigger_entry( $args, false );

			//Adding an action to save contact form submission in trigger meta
			$recipes = $uncanny_automator->get->recipes_from_trigger_code( $this->trigger_code );
			do_action( 'automator_save_forminator_form_entry', $form_id, $recipes, $args );

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
