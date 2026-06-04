<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * @property Wp_Helpers $item_helpers
 */
class WP_GET_USER_META extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WP' );
		$this->set_action_code( 'WP_GET_USER_META' );
		$this->set_action_meta( 'WP_USER_ID' );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				esc_html_x( 'Get the value of {{a meta key:%2$s}} from {{a user:%1$s}}', 'WordPress', 'uncanny-automator' ),
				$this->get_action_meta(),
				'WP_META_KEY:' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Get the value of {{a meta key}} from {{a user}}', 'WordPress', 'uncanny-automator' ) );
	}

	/**
	 * Define action tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			array(
				'tokenId'   => 'USER_ID',
				'tokenName' => esc_html_x( 'User ID', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'USER_EMAIL',
				'tokenName' => esc_html_x( 'User email', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'META_KEY',
				'tokenName' => esc_html_x( 'Meta key', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'META_VALUE',
				'tokenName' => esc_html_x( 'Meta value', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
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
			array(
				'option_code'           => 'WP_META_KEY',
				'label'                 => esc_html_x( 'Meta key', 'WordPress', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'supports_custom_value' => true,
				'options'               => array(),
				'remote_data'           => $this->item_helpers->remote_data_search_config( 'user_meta_keys' ),
				// Suppress the auto-derived "Meta key" token — META_KEY is
				// declared in define_tokens() as the canonical token instead.
				'relevant_tokens'       => array(),
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
		$input    = sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' );
		$meta_key = sanitize_text_field( $parsed['WP_META_KEY'] ?? '' );

		if ( '' === $input ) {
			$this->add_log_error( esc_html_x( 'User identifier cannot be empty.', 'WordPress', 'uncanny-automator' ) );
			return false;
		}

		if ( '' === $meta_key ) {
			$this->add_log_error( esc_html_x( 'Meta key cannot be empty.', 'WordPress', 'uncanny-automator' ) );
			return false;
		}

		$user = $this->resolve_user( $input );

		if ( false === $user ) {
			$this->add_log_error(
				sprintf(
					/* translators: %s: User identifier */
					esc_html_x( 'No user found matching "%s".', 'WordPress', 'uncanny-automator' ),
					$input
				)
			);
			return false;
		}

		if ( false === metadata_exists( 'user', $user->ID, $meta_key ) ) {
			$this->add_log_error(
				sprintf(
					/* translators: %1$s: Meta key, %2$d: User ID */
					esc_html_x( 'Meta key "%1$s" does not exist for user %2$d.', 'WordPress', 'uncanny-automator' ),
					$meta_key,
					$user->ID
				)
			);
			return false;
		}

		$value = get_user_meta( $user->ID, $meta_key, true );

		$this->hydrate_tokens(
			array(
				'USER_ID'    => $user->ID,
				'USER_EMAIL' => $user->user_email,
				'META_KEY'   => $meta_key,
				'META_VALUE' => is_scalar( $value ) ? (string) $value : wp_json_encode( $value ),
			)
		);

		return true;
	}
}
