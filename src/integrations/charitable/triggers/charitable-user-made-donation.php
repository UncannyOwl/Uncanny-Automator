<?php

namespace Uncanny_Automator\Integrations\Charitable;

/**
 * Class CHARITABLE_USER_MADE_DONATION
 *
 * @property \Uncanny_Automator\Integrations\Charitable\Charitable_Helpers $item_helpers
 */
class CHARITABLE_USER_MADE_DONATION extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 */
	public static function definition() {
		return self::new_definition( 'USER_MADE_DONATION', 'CHARITABLE' )
			->trigger_meta( 'POST' )
			->hook( 'automator_charitable_donation_made', 10, 1 );
	}

	/**
	 * Logged-In trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {

		// integration / code / trigger_meta / trigger_type are auto-applied from definition().
		// translators: Trigger sentence - Charitable
		$this->set_sentence( esc_html_x( 'A user makes a donation', 'Charitable', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A user makes a donation', 'Charitable', 'uncanny-automator' ) );
	}

	/**
	 * Validate Trigger.
	 *
	 * @param  array $trigger
	 * @param  array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		// The sentence is "A user makes a donation" — fire as soon as a donation is created,
		// regardless of payment status. Off-site gateways (Stripe/PayPal) write the donation
		// row in pending state on the thank-you page; an approved-status check here would
		// silently swallow those.
		$donation = $this->item_helpers->get_donation( $hook_args[0] );
		if ( ! $donation ) {
			return false;
		}

		if ( ! class_exists( 'Charitable_Donor' ) ) {
			return false;
		}

		$donor_id = (int) $donation->get_donor_id();
		if ( empty( $donor_id ) ) {
			return false;
		}

		$donor = new \Charitable_Donor( $donor_id );
		$user  = $donor->get_user();

		if ( ! $user || empty( $user->ID ) ) {
			// No linked WP user — user-context trigger should defer to the anonymous version.
			return false;
		}

		$this->set_user_id( (int) $user->ID );

		return true;
	}

	/**
	 * Define Tokens.
	 *
	 * @param  array $tokens
	 * @param  array $trigger - options selected in the current recipe/trigger
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge( $tokens, $this->item_helpers->get_donation_tokens_config() );
	}

	/**
	 * Hydrate Tokens.
	 *
	 * @param  array $trigger
	 * @param  array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		return $this->item_helpers->hydrate_donation_tokens( $hook_args[0] );
	}
}
