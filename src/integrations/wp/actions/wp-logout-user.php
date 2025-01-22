<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Recipe\Action;

/**
 * Class WP_LOGOUT_USER
 * @package Uncanny_Automator
 */
class WP_LOGOUT_USER extends Action {

	/**
	 * @return mixed
	 */
	protected function setup_action() {
		$this->set_integration( 'WP' );
		$this->set_action_code( 'WP_LOGOUT_USER' );
		$this->set_action_meta( 'WP_LOGOUT' );
		$this->set_requires_user( true );
		$this->set_sentence( sprintf( esc_attr_x( 'Log the user out', 'WordPress', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_attr_x( 'Log the user out', 'WordPress', 'uncanny-automator' ) );
	}

	/**
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 * @param $parsed
	 *
	 * @return mixed
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
	}
}
