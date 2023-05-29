<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Recipe\Action_Tokens;

/**
 * Class WP_ADD_REPLY_TO_COMMENT
 *
 * @package Uncanny_Automator
 */
class WP_ADD_REPLY_TO_COMMENT {
	use Recipe\Actions;
	use Action_Tokens;

	/**
	 * Automator Action Construct
	 */
	public function __construct() {
		$this->setup_action();
	}

	/**
	 * Setup Action.
	 *
	 * @return void.
	 */
	protected function setup_action() {
		$this->set_integration( 'WP' );
		$this->set_action_code( 'WP_REPLY_TO_A_COMMENT' );
		$this->set_action_meta( 'WP_COMMENT_REPLY' );
		$this->set_requires_user( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'integration/wordpress-core/' ) );
		/* translators: Sentence name - WordPress */
		$this->set_sentence( sprintf( esc_attr__( 'Add {{a reply:%1$s}} to a comment', 'uncanny-automator' ), $this->get_action_meta() . '_COMMENT:' . $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_attr__( 'Add {{a reply}} to a comment', 'uncanny-automator' ) );
		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->set_action_tokens(
			array(
				'COMMENT_ID' => array(
					'name' => __( 'Comment ID', 'uncanny-automator-pro' ),
					'type' => 'int',
				),
			),
			$this->action_code
		);
		$this->register_action();
	}

	/**
	 * Method load_options
	 *
	 * @return array
	 */
	public function load_options() {
		return array(
			'options_group' => array(
				$this->get_action_meta() => array(
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
						'option_code'    => $this->get_action_meta() . '_COMMENT',
						'required'       => true,
						'supports_token' => true,
						'label'          => esc_html__( 'Reply', 'uncanny-automator' ),
					),
				),
			),
		);

	}

	/**
	 * Method process_action.
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 * @param $parsed
	 *
	 * @return void.
	 */
	public function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$post_id        = isset( $parsed[ $this->get_action_meta() . '_POST_ID' ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() . '_POST_ID' ] ) : 0;
		$author         = isset( $parsed[ $this->get_action_meta() . '_NAME' ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() . '_NAME' ] ) : '';
		$content        = isset( $parsed[ $this->get_action_meta() . '_COMMENT' ] ) ? wp_kses_post( $parsed[ $this->get_action_meta() . '_COMMENT' ] ) : '';
		$email          = isset( $parsed[ $this->get_action_meta() . '_EMAIL' ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() . '_EMAIL' ] ) : '';
		$comment_parent = isset( $parsed[ $this->get_action_meta() . '_PARENT_COMMENT_ID' ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() . '_PARENT_COMMENT_ID' ] ) : '';
		$existing_user  = get_user_by( 'email', $email );
		if ( empty( $content ) ) {
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, __( 'Comment content is empty.', 'uncanny-automator' ) );

			return;
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
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $comment->get_error_message() );

			return;
		}

		$this->hydrate_tokens(
			array(
				'COMMENT_ID' => $comment,
			)
		);

		Automator()->complete->action( $user_id, $action_data, $recipe_id );

	}
}
