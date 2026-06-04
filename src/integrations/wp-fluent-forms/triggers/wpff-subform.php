<?php

namespace Uncanny_Automator\Integrations\Wp_Fluent_Forms;

/**
 * Logged-in trigger: a user submits a Fluent Forms form a number of times.
 *
 * @property Wp_Fluent_Forms_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 */
class WPFF_SUBFORM extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'WPFFSUBFORM', 'WPFF' )
			->trigger_meta( 'WPFFFORMS' )
			->hook( 'fluentform/before_insert_submission', 20, 3 );
	}

	/**
	 * Set up the trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type are auto-applied from definition().

		// translators: %1$s is the form name, %2$s is the number of times
		$this->set_sentence( sprintf( esc_html_x( 'A user submits {{a form:%1$s}} {{a number of:%2$s}} time(s)', 'Fluent Forms', 'uncanny-automator' ), $this->get_trigger_meta(), 'NUMTIMES' ) );
		$this->set_readable_sentence( esc_html_x( 'A user submits {{a form}} {{a number of}} time(s)', 'Fluent Forms', 'uncanny-automator' ) );
	}

	/**
	 * Define the trigger options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->item_helpers->form_select_field( $this->get_trigger_meta(), true ),
			$this->item_helpers->number_of_times_field(),
		);
	}

	/**
	 * Define the trigger tokens.
	 *
	 * @param array $trigger
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return $this->item_helpers->tokens()->define_trigger_tokens( $trigger, $tokens, $this->get_trigger_meta() );
	}

	/**
	 * Validate the trigger.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		return $this->item_helpers->validate_form_id_match( $trigger, $hook_args, $this->get_trigger_meta() );
	}

	/**
	 * Hydrate the trigger tokens.
	 *
	 * @param array $completed_trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $completed_trigger, $hook_args ) {
		return $this->item_helpers->tokens()->hydrate_trigger_tokens( $completed_trigger, $hook_args, $this->get_trigger_meta() );
	}
}
