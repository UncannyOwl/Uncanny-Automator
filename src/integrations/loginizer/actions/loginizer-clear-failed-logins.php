<?php

namespace Uncanny_Automator\Integrations\Loginizer;

use Uncanny_Automator\Recipe\Action;

/**
 * Class Loginizer_Clear_Failed_Logins
 *
 * Purges an IP's rows from `{prefix}loginizer_logs`, mirroring the admin
 * screen's delete at `main/settings/brute-force.php:239`. This is the "unblock"
 * half of the lockout workflow: Loginizer computes lockout state from the
 * attempt counter in that table, so clearing the row resets the IP.
 *
 * @package Uncanny_Automator\Integrations\Loginizer
 *
 * @property Loginizer_Helpers $item_helpers
 */
class Loginizer_Clear_Failed_Logins extends Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'LOGINIZER' );
		$this->set_requires_user( false );
		$this->set_action_code( 'LOGINIZER_CLEAR_FAILED_LOGINS' );
		$this->set_action_meta( 'LOGINIZER_LOGS_IP' );
		$this->set_sentence(
			sprintf(
				/* translators: %1$s: the IP address to purge logs for. */
				esc_html_x( 'Purge the failed login logs for {{an IP address:%1$s}}', 'Loginizer', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Purge the failed login logs for {{an IP address}}', 'Loginizer', 'uncanny-automator' ) );
	}

	/**
	 * Options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_action_meta(),
				'label'           => esc_html_x( 'IP address', 'Loginizer', 'uncanny-automator' ),
				'input_type'      => 'text',
				'required'        => true,
				'supports_tokens' => true,
			),
		);
	}

	/**
	 * Define output tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'LOGINIZER_LOGS_CLEARED_IP'   => array(
				'name' => esc_html_x( 'IP address', 'Loginizer', 'uncanny-automator' ),
				'type' => 'text',
			),
			'LOGINIZER_LOGS_ROWS_DELETED' => array(
				'name' => esc_html_x( 'Rows deleted', 'Loginizer', 'uncanny-automator' ),
				'type' => 'int',
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

		unset( $user_id, $action_data, $recipe_id, $args );

		$ip = trim( (string) ( $parsed[ $this->get_action_meta() ] ?? '' ) );

		if ( ! $this->item_helpers->is_valid_ip( $ip ) ) {
			$this->add_log_error( sprintf( 'Invalid IP address: [%s].', $ip ) );
			return false;
		}

		// No matching row is a no-op, not a failure — the IP has no logs to clear.
		$deleted = $this->item_helpers->delete_failed_logs( $ip );

		$this->hydrate_tokens(
			array(
				'LOGINIZER_LOGS_CLEARED_IP'   => $ip,
				'LOGINIZER_LOGS_ROWS_DELETED' => $deleted,
			)
		);

		return true;
	}
}
