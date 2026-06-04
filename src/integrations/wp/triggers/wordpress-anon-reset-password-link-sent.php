<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * Class ANON_WP_RESET_PASSWORD_LINK_SENT
 *
 * Triggered when a password reset link is sent to a user.
 *
 * @property Wp_Helpers $item_helpers
 */
class ANON_WP_RESET_PASSWORD_LINK_SENT extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'ANON_WP_RESET_PASSWORD_LINK_SENT', 'WP' )
			->trigger_meta( 'ANON_WP_RESET_PASSWORD_LINK_SENT_META' )
			->trigger_type( 'anonymous' )
			->hook( 'retrieve_password_key', 90, 2 );
	}

	/**
	 * Sets up the trigger properties and action hook.
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type are auto-applied from definition().

		// Define the Trigger's info
		$this->set_is_login_required( false );

		// Trigger sentence
		$this->set_sentence(
			esc_html_x( 'A reset password link was sent to a user', 'WordPress', 'uncanny-automator' )
		);
		$this->set_readable_sentence(
			esc_html_x( 'A reset password link was sent to a user', 'WordPress', 'uncanny-automator' )
		);

		// Trigger wp hook
	}

	/**
	 * Validates the trigger conditions.
	 *
	 * @param  array $trigger   The trigger data.
	 * @param  array $hook_args The hook arguments.
	 * @return bool True if validation passes, false otherwise.
	 */
	public function validate( $trigger, $hook_args ) {
		// Basic validation of hook args
		list( $user_login, $key ) = $hook_args;

		// Ensure we have a valid reset key
		if ( empty( $key ) ) {
			return false;
		}

		// Get the user object
		$user = get_user_by( 'login', $user_login );

		// Verify the user has the capability to reset their password
		if ( ! $user || ! $user->has_cap( 'read' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Defines the available tokens for this trigger.
	 *
	 * @param  array $trigger The trigger data.
	 * @param  array $tokens  Existing tokens.
	 * @return array The modified tokens array.
	 */
	public function define_tokens( $trigger, $tokens ) {
		return Wp_Shared_Tokens::user_tokens();
	}

	/**
	 * Hydrates the tokens with their respective values.
	 *
	 * @param  array $trigger   The trigger data.
	 * @param  array $hook_args The hook arguments.
	 * @return array The token values.
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		list( $user_login, $key ) = $hook_args;
		$user                     = get_user_by( 'login', $user_login );
		$user_id                  = ( $user instanceof \WP_User ) ? (int) $user->ID : 0;

		return Wp_Shared_Tokens::hydrate_user_tokens( $user_id );
	}
}
