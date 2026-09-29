<?php

namespace Uncanny_Automator\Integrations\Loginizer;

use Uncanny_Automator\Recipe\Action;

/**
 * Class Loginizer_Add_Ip_To_Blacklist
 *
 * Appends an IP range to `loginizer_blacklist`. Loginizer exposes no API for
 * this — the admin screen POST-handles the option directly
 * (`main/settings/brute-force.php:282`) — so the helper replicates that write,
 * reusing Loginizer's own `loginizer_iprange_validate()` when it is loaded.
 *
 * @package Uncanny_Automator\Integrations\Loginizer
 *
 * @property Loginizer_Helpers $item_helpers
 */
class Loginizer_Add_Ip_To_Blacklist extends Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'LOGINIZER' );
		$this->set_requires_user( false );
		$this->set_action_code( 'LOGINIZER_ADD_IP_TO_BLACKLIST' );
		$this->set_action_meta( 'LOGINIZER_BLACKLIST_START_IP' );
		$this->set_sentence(
			sprintf(
				/* translators: %1$s: the start of the IP range. */
				esc_html_x( 'Add {{an IP range:%1$s}} to the blacklist', 'Loginizer', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Add {{an IP range}} to the blacklist', 'Loginizer', 'uncanny-automator' ) );
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
				'label'           => esc_html_x( 'Start IP', 'Loginizer', 'uncanny-automator' ),
				'input_type'      => 'text',
				'required'        => true,
				'supports_tokens' => true,
			),
			array(
				'option_code'     => 'LOGINIZER_BLACKLIST_END_IP',
				'label'           => esc_html_x( 'End IP', 'Loginizer', 'uncanny-automator' ),
				'input_type'      => 'text',
				'required'        => false,
				'supports_tokens' => true,
				'description'     => esc_html_x( 'Leave empty to blacklist the start IP only.', 'Loginizer', 'uncanny-automator' ),
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
			'LOGINIZER_BLACKLISTED_RANGE' => array(
				'name' => esc_html_x( 'Blacklisted IP range', 'Loginizer', 'uncanny-automator' ),
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

		unset( $user_id, $action_data, $recipe_id, $args );

		$start = trim( (string) ( $parsed[ $this->get_action_meta() ] ?? '' ) );
		$end   = trim( (string) ( $parsed['LOGINIZER_BLACKLIST_END_IP'] ?? '' ) );

		if ( '' === $start ) {
			$this->add_log_error( 'A start IP is required.' );
			return false;
		}

		$result = $this->item_helpers->add_ip_range( Loginizer_Helpers::BLACKLIST, $start, $end );

		if ( false === $result['success'] ) {
			$this->add_log_error( $result['error'] );
			return false;
		}

		$this->hydrate_tokens( array( 'LOGINIZER_BLACKLISTED_RANGE' => $result['range'] ) );

		return true;
	}
}
