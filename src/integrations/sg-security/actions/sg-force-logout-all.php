<?php

namespace Uncanny_Automator\Integrations\Sg_Security;

/**
 * Class Sg_Force_Logout_All
 *
 * @package Uncanny_Automator
 */
class Sg_Force_Logout_All extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'SG_SECURITY' );
		$this->set_action_code( 'SG_FORCE_LOGOUT_ALL' );
		$this->set_action_meta( 'SG_LOGOUT_ALL' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_sentence( esc_html_x( 'Force all users to log out', 'SG Security', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'Force all users to log out', 'SG Security', 'uncanny-automator' ) );
	}

	/**
	 * Define action options.
	 *
	 * @return array[]
	 */
	public function options() {
		return array();
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action configuration.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        Additional arguments.
	 * @param array $parsed      Parsed token values.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$offset = 0;
		$batch  = 200;

		do {
			$users = get_users(
				array(
					'fields' => 'ID',
					'number' => $batch,
					'offset' => $offset,
				)
			);

			foreach ( $users as $uid ) {
				\WP_Session_Tokens::get_instance( $uid )->destroy_all();
			}

			$offset += $batch;
		} while ( count( $users ) === $batch );

		return true;
	}
}
