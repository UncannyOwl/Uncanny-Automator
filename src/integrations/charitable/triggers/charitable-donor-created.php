<?php

namespace Uncanny_Automator\Integrations\Charitable;

/**
 * Class CHARITABLE_DONOR_CREATED
 *
 * @property \Uncanny_Automator\Integrations\Charitable\Charitable_Helpers $item_helpers
 */
class CHARITABLE_DONOR_CREATED extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 */
	public static function definition() {
		return self::new_definition( 'CHARITABLE_DONOR_CREATED', 'CHARITABLE' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'CHARITABLE_DONOR' )
			->hook( 'charitable_after_insert_donor', 20, 2 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {

		// integration / code / trigger_meta / trigger_type are auto-applied from definition().
		$this->set_is_login_required( false );
		$this->set_sentence( esc_html_x( 'A donor is created', 'Charitable', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A donor is created', 'Charitable', 'uncanny-automator' ) );
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
		list( $donor_id ) = $hook_args;
		return ! empty( $donor_id );
	}

	/**
	 * Define Tokens.
	 *
	 * @param array $trigger
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge( $tokens, $this->item_helpers->get_donor_tokens_config() );
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
		list( $donor_id ) = $hook_args;
		return $this->item_helpers->hydrate_donor_tokens( $donor_id );
	}
}
