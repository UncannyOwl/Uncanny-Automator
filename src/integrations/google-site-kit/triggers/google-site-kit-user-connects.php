<?php

namespace Uncanny_Automator\Integrations\Google_Site_Kit;

/**
 * Class Google_Site_Kit_User_Connects
 *
 * Fires when the current user completes the Google OAuth flow for Site Kit.
 * Also fires when an already-connected user grants additional scopes — the
 * "Is initial connection" token distinguishes the two (empty previous scopes).
 *
 * Site Kit fires:
 *   do_action( 'googlesitekit_authorize_user', $token_response, $scopes, $previous_scopes )
 *   $hook_args[0] = token response (array, unused here)
 *   $hook_args[1] = newly-granted scopes (string[])
 *   $hook_args[2] = previously-held scopes (string[], empty on first connection)
 *
 * @package Uncanny_Automator\Integrations\Google_Site_Kit
 *
 * @property Google_Site_Kit_Helpers $item_helpers
 */
class Google_Site_Kit_User_Connects extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 */
	public static function definition() {
		return self::new_definition( 'GOOGLE_SITE_KIT_USER_CONNECTS', 'GOOGLE_SITE_KIT' )
			->trigger_meta( 'GOOGLE_SITE_KIT_CONNECT' )
			->hook( 'googlesitekit_authorize_user', 10, 3 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_is_login_required( false );
		$this->set_sentence( esc_html_x( 'A user connects their Google account', 'Site Kit by Google', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A user connects their Google account', 'Site Kit by Google', 'uncanny-automator' ) );
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
	 * Validate trigger.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		// User-only: the OAuth flow is completed by the logged-in user who started
		// it. Gate explicitly (the login-required flag is deprecated) and bind the
		// run to that user.
		$user_id = get_current_user_id();
		if ( 0 === $user_id ) {
			return false;
		}
		$this->set_user_id( $user_id );
		return true;
	}

	/**
	 * Define tokens.
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
					'tokenId'   => 'GOOGLE_SITE_KIT_SCOPES',
					'tokenName' => esc_html_x( 'Scopes granted', 'Site Kit by Google', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'GOOGLE_SITE_KIT_PREVIOUS_SCOPES',
					'tokenName' => esc_html_x( 'Previous scopes', 'Site Kit by Google', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'GOOGLE_SITE_KIT_IS_INITIAL_CONNECTION',
					'tokenName' => esc_html_x( 'Is initial connection', 'Site Kit by Google', 'uncanny-automator' ),
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

		$scopes          = isset( $hook_args[1] ) && is_array( $hook_args[1] ) ? $hook_args[1] : array();
		$previous_scopes = isset( $hook_args[2] ) && is_array( $hook_args[2] ) ? $hook_args[2] : array();

		return array(
			'GOOGLE_SITE_KIT_SCOPES'                => implode( ', ', $scopes ),
			'GOOGLE_SITE_KIT_PREVIOUS_SCOPES'       => implode( ', ', $previous_scopes ),
			'GOOGLE_SITE_KIT_IS_INITIAL_CONNECTION' => empty( $previous_scopes )
				? esc_html_x( 'Yes', 'Site Kit by Google', 'uncanny-automator' )
				: esc_html_x( 'No', 'Site Kit by Google', 'uncanny-automator' ),
		);
	}
}
