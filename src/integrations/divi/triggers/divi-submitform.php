<?php

namespace Uncanny_Automator;

/**
 * Divi Submit Form Trigger
 */
class DIVI_SUBMITFORM {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'DIVI';

	/**
	 * Trigger code
	 *
	 * @var string
	 */
	private $trigger_code;
	/**
	 * Trigger meta
	 *
	 * @var string
	 */
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'DIVISUBMITFORM';
		$this->trigger_meta = 'DIVIFORM';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 *
	 * @throws \Exception
	 */
	public function define_trigger() {
		$trigger = array(
			'author'              => Automator()->get_author_name(),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/divi/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - Divi */
			'sentence'            => sprintf( esc_attr__( 'A user submits {{a form:%1$s}}', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - Divi */
			'select_option_name'  => esc_attr__( 'A user submits {{a form}}', 'uncanny-automator' ),
			'action'              => 'et_pb_contact_form_submit',
			'priority'            => 100,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'divi_form_handler' ),
			'options_callback'    => array( $this, 'load_options' ),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * @return array
	 */
	public function load_options() {
		$options = array(
			'options' => array(
				Automator()->helpers->recipe->divi->options->all_divi_forms( null, $this->trigger_meta, array( 'uo_include_any' => true ) ),
			),
		);

		return Automator()->utilities->keep_order_of_options( $options );
	}

	/**
	 * Trigger handler function
	 *
	 * @param $fields_values
	 * @param $et_contact_error
	 * @param $contact_form_info
	 */
	public function divi_form_handler( $fields_values, $et_contact_error, $contact_form_info ) {

		// This is logged in trigger
		if ( ! is_user_logged_in() ) {
			return;
		}
		// If entry has en error, return
		if ( true === $et_contact_error ) {
			return;
		}
		// If the form doesn't have the contact_form_unique_id, return
		if ( ! isset( $contact_form_info['contact_form_unique_id'] ) ) {
			return;
		}
		$unique_id  = $contact_form_info['contact_form_unique_id'];
		$post_id    = $contact_form_info['post_id'];
		$form_id    = "$post_id-$unique_id";
		$user_id    = wp_get_current_user()->ID;
		$recipes    = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$conditions = Divi_Helpers::match_condition( $form_id, $recipes, $this->trigger_meta );

		if ( ! $conditions ) {
			return;
		}
		if ( empty( $conditions ) ) {
			return;
		}

		foreach ( $conditions['recipe_ids'] as $recipe_id ) {
			$args = array(
				'code'            => $this->trigger_code,
				'meta'            => $this->trigger_meta,
				'recipe_to_match' => $recipe_id,
				'ignore_post_id'  => true,
				'user_id'         => $user_id,
			);

			$args = Automator()->process->user->maybe_add_trigger_entry( $args, false );
			if ( empty( $args ) ) {
				continue;
			}
			foreach ( $args as $result ) {
				Divi_Helpers::save_tokens( $result, $fields_values, $form_id, $this->trigger_meta, $user_id );

				Automator()->process->user->maybe_trigger_complete( $result['args'] );
			}
		}
	}
}
