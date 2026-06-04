<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * Class WP_RESTORE_POST_FROM_TRASH
 *
 * @package Uncanny_Automator
 * @property Wp_Helpers $item_helpers
 */
class WP_RESTORE_POST_FROM_TRASH extends \Uncanny_Automator\Recipe\Action {

	/**
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WP' );
		$this->set_action_code( 'WP_RESTORE_POST' );
		$this->set_action_meta( 'WP_POST_ID' );
		$this->set_requires_user( false );
		// translators: %1$s: Post ID.
		$this->set_sentence( sprintf( esc_html_x( 'Restore {{a post:%1$s}} from trash', 'WordPress', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Restore {{a post}} from trash', 'WordPress', 'uncanny-automator' ) );
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
			array(
				'tokenId'   => 'POST_URL',
				'tokenName' => esc_html_x( 'Post URL', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'POST_STATUS',
				'tokenName' => esc_html_x( 'Post status', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code' => $this->get_action_meta(),
				'input_type'  => 'text',
				'label'       => esc_html_x( 'Post ID', 'WordPress', 'uncanny-automator' ),
				'required'    => true,
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action data.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        The arguments.
	 * @param array $parsed      The parsed data.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$post_id = absint( sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' ) );

		if ( 0 === $post_id ) {
			$this->add_log_error( esc_html_x( 'Invalid post ID.', 'WordPress', 'uncanny-automator' ) );

			return false;
		}

		$post = get_post( $post_id );

		if ( null === $post ) {
			// translators: %d: Post ID.
			$this->add_log_error( sprintf( esc_html_x( 'Post with ID %d does not exist.', 'WordPress', 'uncanny-automator' ), $post_id ) );

			return false;
		}

		if ( 'trash' !== $post->post_status ) {
			// translators: %d: Post ID.
			$this->add_log_error( sprintf( esc_html_x( 'Post with ID %d is not in the trash.', 'WordPress', 'uncanny-automator' ), $post_id ) );

			return false;
		}

		$result = wp_untrash_post( $post_id );

		if ( false === $result || null === $result ) {
			$this->add_log_error( esc_html_x( 'Failed to restore the post from trash.', 'WordPress', 'uncanny-automator' ) );

			return false;
		}

		$restored_post = get_post( $post_id );

		$this->hydrate_tokens(
			array(
				'POST_ID'     => $post_id,
				'POST_TITLE'  => null !== $restored_post ? $restored_post->post_title : '',
				'POST_URL'    => get_permalink( $post_id ),
				'POST_STATUS' => null !== $restored_post ? $restored_post->post_status : '',
			)
		);

		return true;
	}
}
