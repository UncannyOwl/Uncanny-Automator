<?php

namespace Uncanny_Automator\Integrations\Loginizer;

use Uncanny_Automator\Recipe\Action;

/**
 * Class Loginizer_Remove_Ip_From_Whitelist
 *
 * Drops every whitelist entry whose range contains the given IP, mirroring the
 * admin screen's delete at `main/settings/brute-force.php:188-190`.
 *
 * @package Uncanny_Automator\Integrations\Loginizer
 *
 * @property Loginizer_Helpers $item_helpers
 */
class Loginizer_Remove_Ip_From_Whitelist extends Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'LOGINIZER' );
		$this->set_requires_user( false );
		$this->set_action_code( 'LOGINIZER_REMOVE_IP_FROM_WHITELIST' );
		$this->set_action_meta( 'LOGINIZER_WHITELIST_IP' );
		$this->set_sentence(
			sprintf(
				/* translators: %1$s: the IP address to remove. */
				esc_html_x( 'Remove {{an IP address:%1$s}} from the whitelist', 'Loginizer', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Remove {{an IP address}} from the whitelist', 'Loginizer', 'uncanny-automator' ) );
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
			'LOGINIZER_REMOVED_IP'      => array(
				'name' => esc_html_x( 'IP address', 'Loginizer', 'uncanny-automator' ),
				'type' => 'text',
			),
			'LOGINIZER_ENTRIES_REMOVED' => array(
				'name' => esc_html_x( 'Entries removed', 'Loginizer', 'uncanny-automator' ),
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

		// A no-match is a no-op, not a failure — the desired end state is reached.
		$removed = $this->item_helpers->remove_ip( Loginizer_Helpers::WHITELIST, $ip );

		$this->hydrate_tokens(
			array(
				'LOGINIZER_REMOVED_IP'      => $ip,
				'LOGINIZER_ENTRIES_REMOVED' => $removed,
			)
		);

		return true;
	}
}
