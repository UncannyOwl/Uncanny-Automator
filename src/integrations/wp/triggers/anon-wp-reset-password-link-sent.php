<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class ANON_WP_RESET_PASSWORD_LINK_SENT
 *
 * Triggered when a password reset link is sent to a user.
 */
class ANON_WP_RESET_PASSWORD_LINK_SENT extends \Uncanny_Automator\Recipe\Trigger {


	protected $helpers;

	/**
	 * Sets up the trigger properties and action hook.
	 */
	protected function setup_trigger() {
		// Store a dependency (optional)
		$this->helpers = array_shift( $this->dependencies );

		// Define the Trigger's info
		$this->set_integration( 'WP' );
		$this->set_trigger_code( 'ANON_WP_RESET_PASSWORD_LINK_SENT' );
		$this->set_trigger_meta( 'ANON_WP_RESET_PASSWORD_LINK_SENT_META' );
		$this->set_trigger_type( 'anonymous' );

		// Trigger sentence
		$this->set_sentence(
			esc_attr_x( 'A reset password link was sent to a user', 'WordPress', 'uncanny-automator' )
		);
		$this->set_readable_sentence(
			esc_attr_x( 'A reset password link was sent to a user', 'WordPress', 'uncanny-automator' )
		);

		// Trigger wp hook
		$this->add_action( 'retrieve_password_key', 90, 2 );
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
		return array(
			'USER_ID'    => array(
				'tokenId'   => 'USER_ID',
				'tokenName' => esc_html_x( 'User ID', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			'USER_LOGIN' => array(
				'tokenId'   => 'USER_LOGIN',
				'tokenName' => esc_html_x( 'User login', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			'USER_EMAIL' => array(
				'tokenId'   => 'USER_EMAIL',
				'tokenName' => esc_html_x( 'User email', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
		);
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

		$token_values = array(
			'USER_ID'    => $user ? $user->ID : 0,
			'USER_LOGIN' => $user_login,
			'USER_EMAIL' => $user ? $user->user_email : '',
		);

		return $token_values;
	}
}
