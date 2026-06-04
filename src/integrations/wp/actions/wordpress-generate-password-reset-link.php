<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * @property Wp_Helpers $item_helpers
 */
class WP_GENERATE_PASSWORD_RESET_LINK extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WP' );
		$this->set_action_code( 'WP_GENERATE_RESET_LINK' );
		$this->set_action_meta( 'WP_USER_EMAIL' );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				esc_html_x( 'Generate a password reset link for {{a user:%1$s}}', 'WordPress', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Generate a password reset link for {{a user}}', 'WordPress', 'uncanny-automator' ) );
	}

	/**
	 * Define action tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			array(
				'tokenId'   => 'RESET_LINK',
				'tokenName' => esc_html_x( 'Password reset link', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'USER_EMAIL',
				'tokenName' => esc_html_x( 'User email', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'USER_LOGIN',
				'tokenName' => esc_html_x( 'User login', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'USER_ID',
				'tokenName' => esc_html_x( 'User ID', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
		);
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => esc_html_x( 'User', 'WordPress', 'uncanny-automator' ),
				'description' => esc_html_x( 'Enter a user email, username, or user ID.', 'WordPress', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
			),
		);
	}

	/**
	 * Resolve a user from an email, login, or ID input.
	 *
	 * @param string $input The user identifier.
	 *
	 * @return \WP_User|false
	 */
	private function resolve_user( $input ) {
		$user = get_user_by( 'email', $input );

		if ( false === $user ) {
			$user = get_user_by( 'login', $input );
		}

		if ( false === $user && is_numeric( $input ) ) {
			$user = get_user_by( 'ID', absint( $input ) );
		}

		return $user;
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$input = sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' );

		$user = $this->resolve_user( $input );

		if ( false === $user ) {
			$this->add_log_error(
				sprintf(
					esc_html_x( 'No user found matching "%s".', 'WordPress', 'uncanny-automator' ),
					$input
				)
			);

			return false;
		}

		$key = get_password_reset_key( $user );

		if ( is_wp_error( $key ) ) {
			$this->add_log_error( $key->get_error_message() );

			return false;
		}

		$reset_link = network_site_url( 'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user->user_login ), 'login' );

		$this->hydrate_tokens(
			array(
				'RESET_LINK' => $reset_link,
				'USER_EMAIL' => $user->user_email,
				'USER_LOGIN' => $user->user_login,
				'USER_ID'    => $user->ID,
			)
		);

		return true;
	}
}
