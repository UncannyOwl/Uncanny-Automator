<?php

namespace Uncanny_Automator\Integrations\Redirection;

/**
 * Class Redirection_404_Logged
 *
 * @property \Uncanny_Automator\Integrations\Redirection\Redirection_Helpers $item_helpers
 */
class Redirection_404_Logged extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 */
	public static function definition() {
		return self::new_definition( 'REDIRECTION_404_LOGGED', 'REDIRECTION' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'REDIRECTION_404' )
			->hook( 'redirection_404', 10, 1 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_is_login_required( false );
		$this->set_sentence( esc_html_x( 'A 404 error is logged', 'Redirection', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A 404 error is logged', 'Redirection', 'uncanny-automator' ) );
	}

	/**
	 * Trigger options.
	 *
	 * @return array
	 */
	public function options() {
		return array();
	}

	/**
	 * Validate Trigger.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		$data = $hook_args[0] ?? null;
		return is_array( $data ) && isset( $data['url'] );
	}

	/**
	 * Define Tokens.
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
					'tokenId'   => 'ERROR_404_URL',
					'tokenName' => esc_html_x( 'Requested URL', 'Redirection', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'ERROR_404_REFERRER',
					'tokenName' => esc_html_x( 'Referrer', 'Redirection', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'ERROR_404_AGENT',
					'tokenName' => esc_html_x( 'User agent', 'Redirection', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'ERROR_404_IP',
					'tokenName' => esc_html_x( 'IP address', 'Redirection', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'ERROR_404_DATE',
					'tokenName' => esc_html_x( 'Date logged', 'Redirection', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
			)
		);
	}

	/**
	 * Hydrate Tokens.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$data = (array) ( $hook_args[0] ?? array() );

		return array(
			'ERROR_404_URL'      => (string) ( $data['url'] ?? '' ),
			'ERROR_404_REFERRER' => (string) ( $data['referrer'] ?? '' ),
			'ERROR_404_AGENT'    => (string) ( $data['agent'] ?? '' ),
			'ERROR_404_IP'       => (string) ( $data['ip'] ?? '' ),
			'ERROR_404_DATE'     => (string) ( $data['created'] ?? '' ),
		);
	}
}
