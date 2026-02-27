<?php

namespace Uncanny_Automator\Integrations\Sg_Security;

/**
 * Class Sg_Unblock_Ip
 *
 * @package Uncanny_Automator
 * @method \Uncanny_Automator\Integrations\Sg_Security\Sg_Security_Helpers get_item_helpers()
 */
class Sg_Unblock_Ip extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'SG_SECURITY' );
		$this->set_action_code( 'SG_UNBLOCK_IP' );
		$this->set_action_meta( 'SG_IP_ADDRESS' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		// translators: %1$s is the IP address.
		$this->set_sentence( sprintf( esc_html_x( 'Unblock {{an IP address:%1$s}}', 'SG Security', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Unblock {{an IP address}}', 'SG Security', 'uncanny-automator' ) );
		$this->set_action_tokens(
			array(
				'IP_ADDRESS' => array(
					'name' => esc_html_x( 'IP address', 'SG Security', 'uncanny-automator' ),
					'type' => 'text',
				),
			),
			$this->get_action_code()
		);
	}

	/**
	 * Define action options.
	 *
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => esc_html_x( 'IP address', 'SG Security', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
				'tokens'      => true,
				'description' => esc_html_x( 'Enter a valid IPv4 or IPv6 address.', 'SG Security', 'uncanny-automator' ),
			),
		);
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

		$ip = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : '';

		if ( empty( $ip ) || false === filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$this->add_log_error( esc_html_x( 'Invalid IP address provided.', 'SG Security', 'uncanny-automator' ) );
			return false;
		}

		$visitor = $this->get_item_helpers()->get_visitor_by_ip( $ip );

		if ( null === $visitor ) {
			$this->add_log_error( sprintf( esc_html_x( 'No visitor record found for IP address: %s', 'SG Security', 'uncanny-automator' ), $ip ) );
			return false;
		}

		$this->get_item_helpers()->update_visitor_block( $visitor->id, 0 );

		$this->hydrate_tokens(
			array(
				'IP_ADDRESS' => $ip,
			)
		);

		return true;
	}
}
