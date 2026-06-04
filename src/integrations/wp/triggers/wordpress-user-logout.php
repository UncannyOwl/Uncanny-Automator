<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * Class WP_LOGOUT
 *
 * Fires when a user logs out of the site.
 *
 * @package Uncanny_Automator\Integrations\Wp
 *
 * @property Wp_Helpers $item_helpers
 */
class WP_LOGOUT extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'WP_LOGOUT_CODE', 'WP' )
			->trigger_meta( 'WP_LOGOUT_META' )
			->hook( 'wp_logout', 99, 1 );
	}

	/**
	 * Sets up the trigger properties and action hook.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type are auto-applied from definition().

		$this->set_sentence(
			esc_html_x( 'A user logs out of a site', 'WordPress', 'uncanny-automator' )
		);
		$this->set_readable_sentence(
			esc_html_x( 'A user logs out of a site', 'WordPress', 'uncanny-automator' )
		);

		// wp_logout hook passes user_id in WP 6.0+. Use 1 arg to be safe.
	}

	/**
	 * No user-selectable options.
	 *
	 * @return array
	 */
	public function options() {
		return array();
	}

	/**
	 * Define trigger tokens.
	 *
	 * @param array $trigger The trigger data.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return Wp_Shared_Tokens::user_tokens();
	}

	/**
	 * Validate the trigger.
	 *
	 * @param array $trigger   The trigger data.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		// WP 6.0+ passes user_id as first arg. Older versions pass nothing.
		$user_id = isset( $hook_args[0] ) ? absint( $hook_args[0] ) : 0;

		if ( empty( $user_id ) ) {
			return false;
		}

		$this->set_user_id( $user_id );

		return true;
	}

	/**
	 * Hydrate trigger tokens with runtime values.
	 *
	 * @param array $trigger   The trigger data.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$user_id = isset( $hook_args[0] ) ? absint( $hook_args[0] ) : 0;

		return Wp_Shared_Tokens::hydrate_user_tokens( $user_id );
	}
}
