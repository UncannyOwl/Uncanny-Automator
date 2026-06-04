<?php

namespace Uncanny_Automator\Integrations\Charitable;

/**
 * Class ANON_CHARITABLE_MADE_DONATION
 *
 * @property \Uncanny_Automator\Integrations\Charitable\Charitable_Helpers $item_helpers
 */
class ANON_CHARITABLE_MADE_DONATION extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 */
	public static function definition() {
		return self::new_definition( 'ANON_MADE_DONATION', 'CHARITABLE' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'POST' )
			->hook( 'automator_charitable_donation_made', 10, 1 );
	}

	/**
	 * Anonymous trigger that will fire even if no user is logged in.
	 *
	 * @return void
	 */
	protected function setup_trigger() {

		// integration / code / trigger_meta / trigger_type are auto-applied from definition().
		// translators: Trigger sentence - Charitable
		$this->set_sentence( esc_html_x( 'A donation is made', 'Charitable', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A donation is made', 'Charitable', 'uncanny-automator' ) );

	}

	/**
	 * Validate Trigger.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		// Sentence is "A donation is made" — fire on creation, do not require approved status.
		return $this->item_helpers->get_donation( $hook_args[0] ) ? true : false;
	}

	/**
	 * Define Tokens.
	 *
	 * @param array $tokens
	 * @param array $trigger - options selected in the current recipe/trigger
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge( $tokens, $this->item_helpers->get_donation_tokens_config() );
	}

	/**
	 * Hydrate Tokens.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		return $this->item_helpers->hydrate_donation_tokens( $hook_args[0] );
	}

}
