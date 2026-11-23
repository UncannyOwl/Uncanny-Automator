<?php

namespace Uncanny_Automator\Integrations\Asgaros_Forum;

use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;
use Uncanny_Automator\Recipe\Action;

/**
 * Class Asgaros_Forum_Create_Reply
 *
 * Action: Create a reply to {{a topic}}.
 *
 * Inserts the post in the topic's own forum, then sends the subscriber mail
 * the reply form sends. The form's submit hook doesn't fire, so "A user
 * replies to {{a topic}}" doesn't run for these replies.
 *
 * @property Asgaros_Forum_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 * @since 8.1.0
 */
class Asgaros_Forum_Create_Reply extends Action {

	/**
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'ASGAROS_FORUM' );
		$this->set_action_code( 'ASGAROS_FORUM_CREATE_REPLY' );
		$this->set_action_meta( 'ASGAROS_FORUM_TOPIC' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: 1: The selected topic */
				esc_html_x( 'Create a reply to {{a topic:%1$s}}', 'Asgaros Forum', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Create a reply to {{a topic}}', 'Asgaros Forum', 'uncanny-automator' ) );
	}

	/**
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code'           => $this->get_action_meta(),
				'label'                 => esc_html_x( 'Topic', 'Asgaros Forum', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => array(),
				'relevant_tokens'       => array(),
				'supports_custom_value' => true,
				'remote_data'           => $this->item_helpers->remote_data_search_config( 'topics_strict' ),
			),
			array(
				'option_code' => 'REPLY_CONTENT',
				'label'       => esc_html_x( 'Content', 'Asgaros Forum', 'uncanny-automator' ),
				'input_type'  => 'textarea',
				'required'    => true,
			),
			array(
				'option_code' => 'REPLY_AUTHOR',
				'label'       => esc_html_x( 'Author', 'Asgaros Forum', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
				'description' => esc_html_x( 'A user ID or a token. Leave empty to post as the recipe user.', 'Asgaros Forum', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'POST_ID'  => array(
				'name' => esc_html_x( 'Post ID', 'Asgaros Forum', 'uncanny-automator' ),
				'type' => 'int',
			),
			'POST_URL' => array(
				'name' => esc_html_x( 'Post URL', 'Asgaros Forum', 'uncanny-automator' ),
				'type' => 'url',
			),
			'TOPIC_ID' => array(
				'name' => esc_html_x( 'Topic ID', 'Asgaros Forum', 'uncanny-automator' ),
				'type' => 'int',
			),
			'FORUM_ID' => array(
				'name' => esc_html_x( 'Forum ID', 'Asgaros Forum', 'uncanny-automator' ),
				'type' => 'int',
			),
		);
	}

	/**
	 * @param int   $user_id     The recipe user's ID.
	 * @param array $action_data The action configuration.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        Additional arguments.
	 * @param array $parsed      Parsed field values.
	 *
	 * @return bool
	 *
	 * @throws Action_Error_Exception When the action cannot complete.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$forum = $this->item_helpers->get_forum();

		if ( null === $forum ) {
			throw Action_Error_Exception::execution_failed( esc_html_x( 'Asgaros Forum is not available.', 'Asgaros Forum', 'uncanny-automator' ) );
		}

		$topic_value = (string) ( $parsed[ $this->get_action_meta() ] ?? '' );
		$topic_id    = $this->item_helpers->parse_id( $topic_value );
		$content     = wp_kses_post( $parsed['REPLY_CONTENT'] ?? '' );
		$topic       = $this->item_helpers->get_topic_row( $topic_id );

		if ( '' === trim( $topic_value ) ) {
			throw Action_Error_Exception::validation_failed( esc_html_x( 'The Topic field is empty.', 'Asgaros Forum', 'uncanny-automator' ) );
		}

		if ( null === $topic ) {
			throw Action_Error_Exception::not_found(
				sprintf(
					/* translators: %s: The Topic field's value */
					esc_html_x( 'Topic %s does not exist.', 'Asgaros Forum', 'uncanny-automator' ),
					esc_html( sanitize_text_field( $topic_value ) )
				)
			);
		}

		if ( '' === $content ) {
			throw Action_Error_Exception::validation_failed( esc_html_x( 'The reply needs content.', 'Asgaros Forum', 'uncanny-automator' ) );
		}

		$author_id = $this->item_helpers->resolve_author( $parsed['REPLY_AUTHOR'] ?? '', $user_id, $action_data['meta']['REPLY_AUTHOR'] ?? '' );

		if ( is_wp_error( $author_id ) ) {
			throw Action_Error_Exception::validation_failed( esc_html( $author_id->get_error_message() ) );
		}

		$forum_id = absint( $topic->parent_id );
		$post_id  = absint( $forum->content->insert_post( $topic_id, $forum_id, $content, $author_id ) );

		if ( 0 === $post_id ) {
			throw Action_Error_Exception::execution_failed(
				sprintf(
					/* translators: %d: Topic ID */
					esc_html_x( 'Asgaros Forum did not create the reply to topic %d.', 'Asgaros Forum', 'uncanny-automator' ),
					absint( $topic_id )
				)
			);
		}

		$this->item_helpers->notify_new_post( $post_id, $forum_id, $author_id );

		$this->hydrate_tokens(
			array(
				'POST_ID'  => $post_id,
				'POST_URL' => $this->item_helpers->get_post_url( $post_id, $topic_id ),
				'TOPIC_ID' => $topic_id,
				'FORUM_ID' => $forum_id,
			)
		);

		return true;
	}
}
