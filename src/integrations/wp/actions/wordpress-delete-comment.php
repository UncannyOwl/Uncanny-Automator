<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * Class WP_DELETE_COMMENT
 *
 * @package Uncanny_Automator
 * @property Wp_Helpers $item_helpers
 */
class WP_DELETE_COMMENT extends \Uncanny_Automator\Recipe\Action {

	/**
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WP' );
		$this->set_action_code( 'WP_DELETE_COMMENT' );
		$this->set_action_meta( 'WP_COMMENT_ID' );
		$this->set_requires_user( false );
		// translators: %1$s: Comment ID.
		$this->set_sentence( sprintf( esc_html_x( 'Delete {{a comment:%1$s}}', 'WordPress', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Delete {{a comment}}', 'WordPress', 'uncanny-automator' ) );
	}

	/**
	 * Define action tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			array(
				'tokenId'   => 'COMMENT_AUTHOR',
				'tokenName' => esc_html_x( 'Comment author', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'COMMENT_CONTENT',
				'tokenName' => esc_html_x( 'Comment content', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'COMMENT_POST_ID',
				'tokenName' => esc_html_x( 'Comment post ID', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'int',
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
				'label'       => esc_html_x( 'Comment ID', 'WordPress', 'uncanny-automator' ),
				'required'    => true,
			),
			array(
				'option_code'   => 'WP_FORCE_DELETE',
				'input_type'    => 'checkbox',
				'label'         => esc_html_x( 'Force delete', 'WordPress', 'uncanny-automator' ),
				'description'   => esc_html_x( 'When enabled, permanently deletes instead of moving to trash.', 'WordPress', 'uncanny-automator' ),
				'required'      => false,
				'default_value' => false,
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

		$comment_id   = absint( sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' ) );
		$force_delete = ! empty( $parsed['WP_FORCE_DELETE'] ) && 'true' === sanitize_text_field( $parsed['WP_FORCE_DELETE'] );

		if ( 0 === $comment_id ) {
			$this->add_log_error( esc_html_x( 'Invalid comment ID.', 'WordPress', 'uncanny-automator' ) );

			return false;
		}

		$comment = get_comment( $comment_id );

		if ( null === $comment ) {
			// translators: %d: Comment ID.
			$this->add_log_error( sprintf( esc_html_x( 'Comment with ID %d does not exist.', 'WordPress', 'uncanny-automator' ), $comment_id ) );

			return false;
		}

		$comment_author  = $comment->comment_author;
		$comment_content = $comment->comment_content;
		$comment_post_id = (int) $comment->comment_post_ID;

		$result = wp_delete_comment( $comment_id, $force_delete );

		if ( false === $result ) {
			$this->add_log_error( esc_html_x( 'Failed to delete the comment.', 'WordPress', 'uncanny-automator' ) );

			return false;
		}

		$this->hydrate_tokens(
			array(
				'COMMENT_AUTHOR'  => $comment_author,
				'COMMENT_CONTENT' => $comment_content,
				'COMMENT_POST_ID' => $comment_post_id,
			)
		);

		return true;
	}
}
