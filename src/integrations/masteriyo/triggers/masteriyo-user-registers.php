<?php

namespace Uncanny_Automator\Integrations\Masteriyo;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Masteriyo_User_Registers
 *
 * Fires for every account `masteriyo_create_new_user()` saves, before any email
 * verification. The hook's second and third arguments carry the password,
 * so only the `User` model in the first is read.
 *
 * @package Uncanny_Automator
 * @since 8.1.0
 */
class Masteriyo_User_Registers extends Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'MASTERIYO_USER_REGISTERS', 'MASTERIYO' )
			->trigger_meta( 'MASTERIYO_USER_REGISTERS' )
			->hook( 'masteriyo_created_customer', 10, 3 );
	}

	/**
	 * Setup trigger configuration.
	 *
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_is_pro( false );
		// The new account has no session, and on the CSV import, the Enrollments
		// screen and admin order creation the signed-in user is the admin.
		$this->set_is_login_required( false );

		$this->set_sentence( esc_html_x( 'A user registers', 'Masteriyo LMS', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A user registers', 'Masteriyo LMS', 'uncanny-automator' ) );
	}

	/**
	 * Define trigger options.
	 *
	 * The hook exposes no selectable entity, so the trigger fires on every
	 * Masteriyo registration.
	 *
	 * @return array[]
	 */
	public function options() {
		return array();
	}

	/**
	 * Define available tokens.
	 *
	 * @param array $trigger The trigger settings.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {

		return array(
			array(
				'tokenId'   => 'MASTERIYO_ROLE',
				'tokenName' => esc_html_x( 'Role', 'Masteriyo LMS', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Validate trigger against hook arguments.
	 *
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		$user_id = $this->get_registered_user_id( $hook_args );

		if ( 0 === $user_id || false === get_userdata( $user_id ) ) {
			return false;
		}

		$this->set_user_id( $user_id );

		return true;
	}

	/**
	 * Hydrate token values from hook arguments.
	 *
	 * @param array $trigger   The completed trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$user = get_userdata( $this->get_registered_user_id( $hook_args ) );

		$roles = false !== $user ? (array) $user->roles : array();

		return array( 'MASTERIYO_ROLE' => implode( ', ', $roles ) );
	}

	/**
	 * The account id from the hook's `User` model, which is not an int.
	 *
	 * @param array $hook_args The hook arguments.
	 *
	 * @return int
	 */
	private function get_registered_user_id( $hook_args ) {

		$user = $hook_args[0] ?? null;

		if ( ! is_object( $user ) || ! method_exists( $user, 'get_id' ) ) {
			return 0;
		}

		return absint( $user->get_id() );
	}
}
