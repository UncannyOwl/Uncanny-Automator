<?php

namespace Uncanny_Automator;

/**
 * Class WP_LOGOUT
 *
 * @package Uncanny_Automator
 */
class WP_LOGOUT {

	use Recipe\Triggers;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->setup_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function setup_trigger() {
		$this->set_integration( 'WP' );
		$this->set_trigger_code( 'WP_LOGOUT_CODE' );
		$this->set_trigger_meta( 'WP_LOGOUT_META' );
		$this->set_is_login_required( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->trigger_code, 'integration/wordpress-core/' ) );
		$this->set_sentence(
		/* Translators: Trigger sentence */
			esc_attr__( 'A user logs out of a site', 'uncanny-automator' )
		);
		// Non-active state sentence to show
		$this->set_readable_sentence( esc_attr__( 'A user logs out of a site', 'uncanny-automator' ) );
		// Which do_action() fires this trigger.
		$this->set_action_hook( 'wp_logout' );
		$this->register_trigger();

	}

	/**
	 * Validate the trigger.
	 *
	 * @param $args
	 *
	 * @return bool
	 */
	protected function validate_trigger( ...$args ) {
		$user_id = array_shift( $args );

		if ( empty( $user_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * @param $args
	 *
	 * @return void
	 */
	protected function prepare_to_run( $args ) {
		$user_id = is_array( $args ) ? array_pop( $args ) : 0;
		$this->set_user_id( $user_id );
		$this->set_is_signed_in( true );
	}
}
