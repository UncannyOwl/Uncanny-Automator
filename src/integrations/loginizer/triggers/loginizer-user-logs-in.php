<?php

namespace Uncanny_Automator\Integrations\Loginizer;

use Uncanny_Automator\Recipe\Trigger;
use WP_User;

/**
 * Class Loginizer_User_Logs_In
 *
 * Fires on a successful authentication. `wp_login` is a WP core hook, so this
 * covers standard, social (`social-base.php:36` fires it explicitly) and SSO
 * logins alike. Loginizer's contribution is the surrounding context — the
 * resolved client IP and which route the login came in on.
 *
 * User trigger: the COMMON user tokens already cover ID, email, display name and
 * roles, so only the Loginizer-specific values are declared here.
 *
 * @package Uncanny_Automator\Integrations\Loginizer
 *
 * @property Loginizer_Helpers $item_helpers
 */
class Loginizer_User_Logs_In extends Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'LOGINIZER_USER_LOGS_IN', 'LOGINIZER' )
			->trigger_meta( 'LOGINIZER_USER_ROLE' )
			->hook( 'wp_login', 10, 2 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_is_pro( false );
		// `wp_login` fires inside wp_signon() after wp_set_auth_cookie() but before
		// any wp_set_current_user() call, so is_user_logged_in() is still false at
		// hook time. Leaving the gate on (abstract-trigger.php:597) would reject the
		// hook before validate() ever runs. Trigger type stays 'user' — validate()
		// binds the real user, the role filter needs one, and the COMMON user tokens
		// are only supplied to user triggers.
		$this->set_is_login_required( false );
		$this->set_sentence(
			sprintf(
				/* translators: %1$s: the user role selector. */
				esc_html_x( 'A user with {{a role:%1$s}} logs in', 'Loginizer', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'A user logs in', 'Loginizer', 'uncanny-automator' ) );
	}

	/**
	 * Trigger options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Role', 'Loginizer', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'user_roles' ),
			),
		);
	}

	/**
	 * Validate — the logged-in user holds the selected role.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		$user = isset( $hook_args[1] ) ? $hook_args[1] : null;

		if ( ! $user instanceof WP_User || 0 === (int) $user->ID ) {
			return false;
		}

		$selected = (string) ( $trigger['meta'][ $this->get_trigger_meta() ] ?? Loginizer_Helpers::ANY );

		if ( Loginizer_Helpers::ANY !== $selected && ! in_array( $selected, (array) $user->roles, true ) ) {
			return false;
		}

		// wp_login fires before the current user is established on some routes
		// (social, SSO), so bind the run explicitly rather than relying on it.
		$this->set_user_id( $user->ID );

		return true;
	}

	/**
	 * Define tokens. COMMON user tokens are supplied by the framework for a user
	 * trigger and must not be redeclared here.
	 *
	 * @param array $trigger
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge(
			$tokens,
			array(
				array(
					'tokenId'   => 'LOGINIZER_LOGIN_IP',
					'tokenName' => esc_html_x( 'IP address', 'Loginizer', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'LOGINIZER_LOGIN_METHOD',
					'tokenName' => esc_html_x( 'Login method', 'Loginizer', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
			)
		);
	}

	/**
	 * Hydrate tokens.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		unset( $trigger, $hook_args );

		return array(
			'LOGINIZER_LOGIN_IP' => $this->item_helpers->current_ip(),
			// Raw, non-localized value: social | sso | standard.
			'LOGINIZER_LOGIN_METHOD' => $this->item_helpers->detect_login_method(),
		);
	}
}
