<?php

namespace Uncanny_Automator\Integrations\Charitable;

/**
 * Class CHARITABLE_USER_REGISTERED
 *
 * @property \Uncanny_Automator\Integrations\Charitable\Charitable_Helpers $item_helpers
 */
class CHARITABLE_USER_REGISTERED extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 */
	public static function definition() {
		// charitable_after_insert_donor fires from Charitable_User::add_donor() whenever a
		// donor row is created — covers both new-WP-user registrations AND existing WP users
		// who become donors on their first donation. charitable_after_insert_user only covers
		// new-WP-user registrations and misses the existing-user-becomes-donor case.
		return self::new_definition( 'CHARITABLE_USER_REGISTERED', 'CHARITABLE' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'CHARITABLE_USER' )
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
		$this->set_sentence( esc_html_x( 'A user registers as a donor', 'Charitable', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A user registers as a donor', 'Charitable', 'uncanny-automator' ) );
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
		list( $donor_id, $user ) = array_pad( $hook_args, 2, null );

		if ( empty( $donor_id ) ) {
			return false;
		}

		// Donors can exist without a linked WP user (manual admin donations, anonymous flows).
		// The sentence is "A user registers as a donor", so require a real user.
		if ( ! is_object( $user ) || empty( $user->ID ) ) {
			return false;
		}

		$this->set_user_id( (int) $user->ID );
		return true;
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
		$donor_id = isset( $hook_args[0] ) ? (int) $hook_args[0] : 0;
		return $this->item_helpers->hydrate_donor_tokens( $donor_id );
	}
}
