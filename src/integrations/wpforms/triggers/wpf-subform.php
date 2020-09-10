<?php

namespace Uncanny_Automator;

/**
 * Class WPF_SUBFORM
 * @package Uncanny_Automator
 */
class WPF_SUBFORM {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'WPF';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'WPFSUBFORM';
		$this->trigger_meta = 'WPFFORMS';
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
			/* translators: Logged-in trigger - WP Forms */
			'sentence'            => sprintf(  esc_attr__( 'A user submits {{a form:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - WP Forms */
			'select_option_name'  =>  esc_attr__( 'A user submits {{a form}}', 'uncanny-automator' ),
			'action'              => 'wpforms_process_complete',
			'priority'            => 20,
			'accepted_args'       => 4,
			'validation_function' => array( $this, 'wpform_submit' ),
			'options'             => [
				$uncanny_automator->helpers->recipe->wpforms->options->list_wp_forms(),
				$uncanny_automator->helpers->recipe->options->number_of_times(),
			],
		);

		$uncanny_automator->register->trigger( $trigger );
	}

	/**
	 * @param $fields
	 * @param $entry
	 * @param $form_data
	 * @param $entry_id
	 */
	public function wpform_submit( $fields, $entry, $form_data, $entry_id ) {

		global $uncanny_automator;

		if ( empty( $form_data ) ) {
			return;
		}

		$user_id = get_current_user_id();
		$args    = [
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => intval( $form_data['id'] ),
			'user_id' => $user_id,
		];

		$args = $uncanny_automator->maybe_add_trigger_entry( $args, false );

		//Adding an action to save form submission in trigger meta
		$recipes = $uncanny_automator->get->recipes_from_trigger_code( $this->trigger_code );
		do_action( 'automator_save_wp_form', $fields, $form_data, $recipes, $args );

		if ( $args ) {
			foreach ( $args as $result ) {
				if ( true === $result['result'] ) {
					$uncanny_automator->maybe_trigger_complete( $result['args'] );
				}
			}
		}
	}
}
