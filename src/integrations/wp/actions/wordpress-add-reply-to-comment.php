<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * Class WP_ADD_REPLY_TO_COMMENT
 *
 * @package Uncanny_Automator
 * @property Wp_Helpers $item_helpers
 */
class WP_ADD_REPLY_TO_COMMENT extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WP' );
		$this->set_action_code( 'WP_REPLY_TO_A_COMMENT' );
		$this->set_action_meta( 'WP_COMMENT_REPLY' );
		$this->set_requires_user( true );
		// translators: %1$s is the comment reply.
		$this->set_sentence( sprintf( esc_html_x( 'Add {{a reply:%1$s}} to a comment', 'WordPress', 'uncanny-automator' ), $this->get_action_meta() . '_COMMENT:' . $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Add {{a reply}} to a comment', 'WordPress', 'uncanny-automator' ) );
	}

	/**
	 * Define action tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			array(
				'tokenId'   => 'COMMENT_ID',
				'tokenName' => esc_html_x( 'Comment ID', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
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
				'input_type'  => 'text',
				'option_code' => $this->get_action_meta() . '_POST_ID',
				'required'    => true,
				'label'       => esc_html_x( 'Post ID', 'WordPress', 'uncanny-automator' ),
				'description' => esc_html_x( 'A numerical value is required.', 'WordPress', 'uncanny-automator' ),
			),
			array(
				'input_type'  => 'text',
				'option_code' => $this->get_action_meta() . '_PARENT_COMMENT_ID',
				'required'    => true,
				'label'       => esc_html_x( 'Parent comment ID', 'WordPress', 'uncanny-automator' ),
				'description' => esc_html_x( 'A numerical value is required.', 'WordPress', 'uncanny-automator' ),
			),
			array(
				'input_type'    => 'email',
				'option_code'   => $this->get_action_meta() . '_EMAIL',
				'required'      => true,
				'default_value' => '{{admin_email}}',
				'label'         => esc_html_x( "Commenter's email", 'WordPress', 'uncanny-automator' ),
				'description'   => esc_html_x( 'If the email matches a WordPress user, the comment will be attributed to that user.', 'WordPress', 'uncanny-automator' ),
			),
			array(
				'input_type'    => 'text',
				'option_code'   => $this->get_action_meta() . '_NAME',
				'required'      => true,
				'default_value' => '{{site_name}}',
				'label'         => esc_html_x( 'Name', 'WordPress', 'uncanny-automator' ),
				'description'   => esc_html_x( "If the email provided above matches a WordPress user, that user's name will be used.", 'WordPress', 'uncanny-automator' ),
			),
			array(
				'input_type'  => 'textarea',
				'option_code' => $this->get_action_meta() . '_COMMENT',
				'required'    => true,
				'label'       => esc_html_x( 'Reply', 'WordPress', 'uncanny-automator' ),
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

		$post_id        = sanitize_text_field( $parsed[ $this->get_action_meta() . '_POST_ID' ] ?? 0 );
		$author         = sanitize_text_field( $parsed[ $this->get_action_meta() . '_NAME' ] ?? '' );
		$content        = wp_kses_post( $parsed[ $this->get_action_meta() . '_COMMENT' ] ?? '' );
		$email          = sanitize_text_field( $parsed[ $this->get_action_meta() . '_EMAIL' ] ?? '' );
		$comment_parent = sanitize_text_field( $parsed[ $this->get_action_meta() . '_PARENT_COMMENT_ID' ] ?? '' );

		if ( empty( $content ) ) {
			$this->add_log_error( esc_html_x( 'Comment content is empty.', 'WordPress', 'uncanny-automator' ) );
			return false;
		}

		$existing_user = get_user_by( 'email', $email );

		if ( false !== $existing_user ) {
			$author  = $existing_user->data->display_name;
			$user_id = $existing_user->data->ID;
		}

		$comment_data = array(
			'comment_post_ID'      => $post_id,
			'comment_parent'       => $comment_parent,
			'comment_author'       => $author,
			'comment_content'      => $content,
			'comment_author_email' => $email,
			'comment_author_url'   => '',
			'user_id'              => $user_id,
			'posted_by_automator'  => true,
		);

		$comment = wp_new_comment( $comment_data, true );

		if ( is_wp_error( $comment ) ) {
			$this->add_log_error( $comment->get_error_message() );
			return false;
		}

		$this->hydrate_tokens(
			array(
				'COMMENT_ID' => $comment,
			)
		);

		return true;
	}
}
