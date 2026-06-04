<?php

namespace Uncanny_Automator\Integrations\Thrive_Ovation;

use Uncanny_Automator\Recipe\Trigger;
use Uncanny_Automator\Recipe\Trigger_Definition;

/**
 * Class THRIVE_OVATION_TESTIMONIAL_CREATED
 *
 * @package Uncanny_Automator\Integrations\Thrive_Ovation
 *
 * @property Thrive_Ovation_Helpers $item_helpers
 */
class THRIVE_OVATION_TESTIMONIAL_CREATED extends Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'TVO_TESTIMONIAL_SUBMITTED', 'THRIVE_OVATION' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'TVO_TESTIMONIALS' )
			->hook( 'thrive_ovation_testimonial_submit', 10, 2 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type / hook are auto-applied from definition().
		$this->set_is_login_required( false );

		$this->set_sentence( esc_html_x( 'A testimonial is submitted', 'Thrive Ovation', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A testimonial is submitted', 'Thrive Ovation', 'uncanny-automator' ) );
	}

	/**
	 * Trigger options — no user-facing fields for this trigger.
	 *
	 * @return array
	 */
	public function options() {
		return array();
	}

	/**
	 * Define available tokens.
	 *
	 * @param array $trigger The trigger settings.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {

		return array_merge( $tokens, $this->item_helpers->tokens()->testimonial_tokens() );
	}

	/**
	 * Validate whether the trigger should fire.
	 *
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		// $hook_args is func_get_args() of `thrive_ovation_testimonial_submit`
		// ( $testimonial_data, $user_data ), so the testimonial array is arg[0].
		$testimonial_data = isset( $hook_args[0] ) && is_array( $hook_args[0] ) ? $hook_args[0] : array();

		return ! empty( $testimonial_data );
	}

	/**
	 * Hydrate token values from the testimonial payload.
	 *
	 * @param array $trigger   The completed trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$testimonial_data = isset( $hook_args[0] ) && is_array( $hook_args[0] ) ? $hook_args[0] : array();

		return $this->item_helpers->tokens()->hydrate_testimonial_tokens( $testimonial_data );
	}
}
