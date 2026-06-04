<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * Class WP_LOGOUT_USER
 *
 * @package Uncanny_Automator
 * @property Wp_Helpers $item_helpers
 */
class WP_LOGOUT_USER extends \Uncanny_Automator\Recipe\Action {

	/**
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WP' );
		$this->set_action_code( 'WP_LOGOUT_USER' );
		$this->set_action_meta( 'WP_LOGOUT' );
		$this->set_requires_user( true );
		$this->set_sentence( esc_html_x( 'Log the user out', 'WordPress', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'Log the user out', 'WordPress', 'uncanny-automator' ) );
	}

	/**
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action data.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        The arguments.
	 * @param array $parsed      The parsed data.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		// Invalidate all sessions for the user
		$user_sessions = \WP_Session_Tokens::get_instance( $user_id );
		$user_sessions->destroy_all();

		// If the current user is the one being logged out, clear their cookies and destroy the session
		if ( get_current_user_id() === $user_id ) {
			wp_clear_auth_cookie();
			wp_destroy_current_session();
			wp_set_auth_cookie( 0 );
		}

		return true;
	}
}
