<?php

namespace Uncanny_Automator\Integrations\Wp_Activity_Log;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Wp_Activity_Log_Failed_Login
 *
 * Fires when WP Activity Log records a failed login (wrong password or
 * non-existing user).
 *
 * @package Uncanny_Automator\Integrations\Wp_Activity_Log
 *
 * @property Wp_Activity_Log_Helpers $item_helpers
 */
class Wp_Activity_Log_Failed_Login extends Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'WP_ACTIVITY_LOG_FAILED_LOGIN', 'WP_ACTIVITY_LOG' )
			->trigger_meta( 'WPAL_FAILED_LOGIN' )
			->trigger_type( 'anonymous' )
			->hook( 'wsal_logged_alert', 10, 5 );
	}

	/**
	 * Setup trigger configuration.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_is_login_required( false );
		$this->set_sentence( esc_html_x( 'A failed login is logged', 'WP Activity Log', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A failed login is logged', 'WP Activity Log', 'uncanny-automator' ) );
	}

	/**
	 * Define trigger options. This trigger takes no configuration.
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
		return array_merge( $tokens, $this->item_helpers->get_event_tokens() );
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

		if ( ! isset( $hook_args[1] ) ) {
			return false;
		}

		if ( ! in_array( (int) $hook_args[1], $this->item_helpers->failed_login_codes(), true ) ) {
			return false;
		}

		$data = $hook_args[2] ?? array();
		$this->set_user_id( is_array( $data ) && isset( $data['CurrentUserID'] ) ? (int) $data['CurrentUserID'] : 0 );

		return true;
	}

	/**
	 * Hydrate token values from hook arguments.
	 *
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( , $type, $data, $date, $site_id ) = array_pad( $hook_args, 5, null );

		return $this->item_helpers->hydrate_event_tokens( $type, $data, $date, $site_id );
	}
}
