<?php

namespace Uncanny_Automator\Integrations\Sucuri;

use Uncanny_Automator\Recipe\Action;

/**
 * Class Sucuri_Reset_User_Password
 *
 * Invalidates the recipe user's password and emails them a reset link, via
 * `SucuriScanEvent::setNewPassword( $user_id )` (src/event.lib.php:722). Sucuri
 * generates a random 15-character password, applies it with wp_set_password(),
 * builds a reset key and sends its own branded reset email.
 *
 * Note that Sucuri returns true once the mail has been handed off and discards
 * the send result, so a successful run means the password was rotated — not
 * that the email was delivered. The user is locked out until they use the link.
 *
 * A failed run is not a rollback either: Sucuri calls wp_set_password() at
 * event.lib.php:732, before building the reset key at :737, so a key failure
 * returns false with the password already changed and no email sent. The log
 * message below says so — that user needs a manual reset.
 *
 * @package Uncanny_Automator\Integrations\Sucuri
 */
class Sucuri_Reset_User_Password extends Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'SUCURI' );
		$this->set_requires_user( true );
		$this->set_action_code( 'SUCURI_RESET_USER_PASSWORD' );
		$this->set_action_meta( 'SUCURI_RESET_PASSWORD' );
		$this->set_sentence( esc_html_x( "Reset the user's password", 'Sucuri Security', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( "Reset the user's password", 'Sucuri Security', 'uncanny-automator' ) );
	}

	/**
	 * Define output tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'SUCURI_RESET_USER_ID'    => array(
				'name' => esc_html_x( 'User ID', 'Sucuri Security', 'uncanny-automator' ),
				'type' => 'int',
			),
			'SUCURI_RESET_USER_EMAIL' => array(
				'name' => esc_html_x( 'User email', 'Sucuri Security', 'uncanny-automator' ),
				'type' => 'email',
			),
			'SUCURI_RESET_USER_LOGIN' => array(
				'name' => esc_html_x( 'Username', 'Sucuri Security', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Process action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action data.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        The args.
	 * @param array $parsed      The parsed options.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		if ( ! class_exists( '\SucuriScanEvent' ) ) {
			$this->add_log_error( esc_html_x( 'Sucuri Security is not active.', 'Sucuri Security', 'uncanny-automator' ) );
			return false;
		}

		$user_id = absint( $user_id );
		$user    = get_userdata( $user_id );

		if ( ! $user instanceof \WP_User ) {
			$this->add_log_error(
				sprintf(
					/* translators: %d: The WordPress user ID */
					esc_html_x( 'User not found: [%d].', 'Sucuri Security', 'uncanny-automator' ),
					$user_id
				)
			);
			return false;
		}

		if ( true !== \SucuriScanEvent::setNewPassword( $user_id ) ) {
			$this->add_log_error(
				sprintf(
					/* translators: %d: The WordPress user ID */
					esc_html_x( 'Sucuri could not complete the password reset for user [%d]. The password may already have been changed without the reset email being sent, in which case the user needs a manual reset.', 'Sucuri Security', 'uncanny-automator' ),
					$user_id
				)
			);
			return false;
		}

		$this->hydrate_tokens(
			array(
				'SUCURI_RESET_USER_ID'    => $user_id,
				'SUCURI_RESET_USER_EMAIL' => (string) $user->user_email,
				'SUCURI_RESET_USER_LOGIN' => (string) $user->user_login,
			)
		);

		return true;
	}
}
