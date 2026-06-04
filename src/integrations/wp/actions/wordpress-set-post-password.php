<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * @property Wp_Helpers $item_helpers
 */
class WP_SET_POST_PASSWORD extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WP' );
		$this->set_action_code( 'WP_SET_POST_PASSWORD' );
		$this->set_action_meta( 'WP_POST_ID' );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				esc_html_x( 'Set the password of {{a post:%1$s}} to {{a password:%2$s}}', 'WordPress', 'uncanny-automator' ),
				$this->get_action_meta(),
				'WP_POST_PASSWORD:' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Set the password of {{a post}} to {{a password}}', 'WordPress', 'uncanny-automator' ) );
	}

	/**
	 * Define action tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			array(
				'tokenId'   => 'POST_ID',
				'tokenName' => esc_html_x( 'Post ID', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'POST_TITLE',
				'tokenName' => esc_html_x( 'Post title', 'WordPress', 'uncanny-automator' ),
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
				'option_code'     => $this->get_action_meta(),
				'label'           => esc_html_x( 'Post ID', 'WordPress', 'uncanny-automator' ),
				'input_type'      => 'text',
				'required'        => true,
				// Suppress the auto-derived "Post ID" token — POST_ID is
				// declared in define_tokens() as the canonical token instead.
				'relevant_tokens' => array(),
			),
			array(
				'option_code' => 'WP_POST_PASSWORD',
				'label'       => esc_html_x( 'Password', 'WordPress', 'uncanny-automator' ),
				'description' => esc_html_x( 'Leave empty to remove password protection.', 'WordPress', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
		);
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
		$post_id  = absint( sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' ) );
		$password = sanitize_text_field( $parsed['WP_POST_PASSWORD'] ?? '' );

		$post = get_post( $post_id );

		if ( null === $post ) {
			$this->add_log_error(
				sprintf(
					esc_html_x( 'Post with ID %d not found.', 'WordPress', 'uncanny-automator' ),
					$post_id
				)
			);

			return false;
		}

		$result = wp_update_post(
			array(
				'ID'            => $post_id,
				'post_password' => $password,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			$this->add_log_error( $result->get_error_message() );

			return false;
		}

		$this->hydrate_tokens(
			array(
				'POST_ID'    => $post_id,
				'POST_TITLE' => get_the_title( $post_id ),
			)
		);

		return true;
	}
}
