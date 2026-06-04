<?php

namespace Uncanny_Automator\Integrations\Wp_Fluent_Forms;

/**
 * Anonymous trigger: a Fluent Forms form is submitted (logged-out friendly).
 *
 * @property Wp_Fluent_Forms_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 */
class ANON_WPFF_SUBFORM extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'ANONWPFFSUBFORM', 'WPFF' )
			->trigger_meta( 'ANONWPFFFORMS' )
			->trigger_type( 'anonymous' )
			->hook( 'fluentform/before_insert_submission', 20, 3 );
	}

	/**
	 * Set up the trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type are auto-applied from definition().
		$this->set_is_login_required( false );

		// translators: %1$s is the form name
		$this->set_sentence( sprintf( esc_html_x( '{{A form:%1$s}} is submitted', 'Fluent Forms', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_html_x( '{{A form}} is submitted', 'Fluent Forms', 'uncanny-automator' ) );
	}

	/**
	 * Define the trigger options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->item_helpers->form_select_field( $this->get_trigger_meta(), true ),
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
	 * Validate the trigger and associate the run with the current user when present.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		if ( ! $this->item_helpers->validate_form_id_match( $trigger, $hook_args, $this->get_trigger_meta() ) ) {
			return false;
		}

		// Anonymous trigger: associate the run with the current user if any.
		$user_id = get_current_user_id();
		if ( $user_id > 0 ) {
			$this->set_user_id( $user_id );
		}

		return true;
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
