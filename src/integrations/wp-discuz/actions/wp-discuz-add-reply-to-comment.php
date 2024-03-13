<?php

namespace Uncanny_Automator\Integrations\Wp_Discuz;

/**
 * Class WP_DISCUZ_ADD_REPLY_TO_COMMENT
 *
 * @package Uncanny_Automator
 */
class WP_DISCUZ_ADD_REPLY_TO_COMMENT extends \Uncanny_Automator\Recipe\Action {

	/**
	 * @return mixed|void
	 */
	protected function setup_action() {
		$this->set_integration( 'WPDISCUZ' );
		$this->set_action_code( 'WPD_ADD_REPLY_TO_COMMENT' );
		$this->set_action_meta( 'WPD_REPLY' );
		$this->set_requires_user( false );
		$this->set_sentence( sprintf( esc_attr_x( 'Add {{a reply:%1$s}} to a comment', 'wpDiscuz', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_attr_x( 'Add {{a reply}} to a comment', 'wpDiscuz', 'uncanny-automator' ) );
	}

	/**
	 * Define the Action's options
	 *
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'input_type'     => 'text',
				'option_code'    => $this->get_action_meta() . '_POST_ID',
				'required'       => true,
				'supports_token' => true,
				'label'          => esc_html__( 'Post ID', 'uncanny-automator' ),
			),
			array(
				'input_type'     => 'text',
				'option_code'    => $this->get_action_meta() . '_PARENT_COMMENT_ID',
				'required'       => true,
				'supports_token' => true,
				'label'          => esc_html__( 'Parent comment ID', 'uncanny-automator' ),
			),
			array(
				'input_type'     => 'email',
				'option_code'    => $this->get_action_meta() . '_EMAIL',
				'required'       => true,
				'supports_token' => true,
				'default_value'  => '{{admin_email}}',
				'label'          => esc_html__( "Commenter's email", 'uncanny-automator' ),
				'description'    => esc_html__( 'If the email matches a WordPress user, the comment will be attributed to that user.', 'uncanny-automator' ),
			),
			array(
				'input_type'     => 'text',
				'option_code'    => $this->get_action_meta() . '_NAME',
				'required'       => true,
				'supports_token' => true,
				'default_value'  => '{{site_name}}',
				'label'          => esc_html__( 'Name', 'uncanny-automator' ),
				'description'    => esc_html__( "If the email provided above matches a WordPress user, that user's name will be used.", 'uncanny-automator' ),
			),
			array(
				'input_type'     => 'textarea',
				'option_code'    => $this->get_action_meta(),
				'required'       => true,
				'supports_token' => true,
				'label'          => esc_html__( 'Reply', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param       $parsed
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$post_id        = isset( $parsed[ $this->get_action_meta() . '_POST_ID' ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() . '_POST_ID' ] ) : 0;
		$author         = isset( $parsed[ $this->get_action_meta() . '_NAME' ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() . '_NAME' ] ) : '';
		$content        = isset( $parsed[ $this->get_action_meta() ] ) ? wp_kses_post( $parsed[ $this->get_action_meta() ] ) : '';
		$email          = isset( $parsed[ $this->get_action_meta() . '_EMAIL' ] ) ? sanitize_email( $parsed[ $this->get_action_meta() . '_EMAIL' ] ) : '';
		$comment_parent = isset( $parsed[ $this->get_action_meta() . '_PARENT_COMMENT_ID' ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() . '_PARENT_COMMENT_ID' ] ) : '';
		$existing_user  = get_user_by( 'email', $email );

		if ( empty( $content ) ) {
			$this->add_log_error( 'Comment content is empty.' );

			return false;
		}

		if ( false !== $existing_user ) {
			// If the email provided above matches a WordPress user, that user's name will be used.
			$author  = $existing_user->data->display_name;
			$user_id = $existing_user->data->ID;

		}

		$comment_data = array(
			'comment_post_ID'      => $post_id,
			'comment_parent'       => $comment_parent,
			'comment_author'       => $author,
			'comment_content'      => $content,
			'comment_author_email' => $email,
			'comment_author_url'   => '', // WordPress throws error notice if this is removed.
			'user_id'              => $user_id,
			'posted_by_automator'  => true, // to avoid infinity loop with other comment triggers
		);

		$comment = wp_new_comment( $comment_data, true );

		if ( is_wp_error( $comment ) ) {
			$this->add_log_error( $comment->get_error_message() );

			return false;
		}

		return true;
	}
}
