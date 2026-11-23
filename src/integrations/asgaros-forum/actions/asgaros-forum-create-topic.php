<?php

namespace Uncanny_Automator\Integrations\Asgaros_Forum;

use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;
use Uncanny_Automator\Recipe\Action;

/**
 * Class Asgaros_Forum_Create_Topic
 *
 * Action: Create a topic in {{a forum}}.
 *
 * Follows Asgaros's own programmatic path, `create_blog_topic()`: insert the
 * topic and its first post, then send the new-topic mail (or the site-owner
 * mail for a topic held for approval). The form's submit hook doesn't fire,
 * so "A user submits a topic" doesn't run for these topics.
 *
 * @property Asgaros_Forum_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 * @since 8.1.0
 */
class Asgaros_Forum_Create_Topic extends Action {

	/**
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'ASGAROS_FORUM' );
		$this->set_action_code( 'ASGAROS_FORUM_CREATE_TOPIC' );
		$this->set_action_meta( 'ASGAROS_FORUM_FORUM' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: 1: The selected forum */
				esc_html_x( 'Create a topic in {{a forum:%1$s}}', 'Asgaros Forum', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Create a topic in {{a forum}}', 'Asgaros Forum', 'uncanny-automator' ) );
	}

	/**
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code'           => $this->get_action_meta(),
				'label'                 => esc_html_x( 'Forum', 'Asgaros Forum', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => array(),
				'relevant_tokens'       => array(),
				'supports_custom_value' => true,
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'forums_strict' ),
			),
			array(
				'option_code' => 'TOPIC_TITLE',
				'label'       => esc_html_x( 'Title', 'Asgaros Forum', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
			),
			array(
				'option_code' => 'TOPIC_CONTENT',
				'label'       => esc_html_x( 'Content', 'Asgaros Forum', 'uncanny-automator' ),
				'input_type'  => 'textarea',
				'required'    => true,
			),
			array(
				'option_code' => 'TOPIC_AUTHOR',
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
			'TOPIC_ID'    => array(
				'name' => esc_html_x( 'Topic ID', 'Asgaros Forum', 'uncanny-automator' ),
				'type' => 'int',
			),
			'TOPIC_TITLE' => array(
				'name' => esc_html_x( 'Topic title', 'Asgaros Forum', 'uncanny-automator' ),
				'type' => 'text',
			),
			'TOPIC_URL'   => array(
				'name' => esc_html_x( 'Topic URL', 'Asgaros Forum', 'uncanny-automator' ),
				'type' => 'url',
			),
			'POST_ID'     => array(
				'name' => esc_html_x( 'Post ID', 'Asgaros Forum', 'uncanny-automator' ),
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

		$forum_value = (string) ( $parsed[ $this->get_action_meta() ] ?? '' );
		$forum_id    = $this->item_helpers->parse_id( $forum_value );
		$title       = sanitize_text_field( $parsed['TOPIC_TITLE'] ?? '' );
		$content     = wp_kses_post( $parsed['TOPIC_CONTENT'] ?? '' );

		if ( '' === trim( $forum_value ) ) {
			throw Action_Error_Exception::validation_failed( esc_html_x( 'The Forum field is empty.', 'Asgaros Forum', 'uncanny-automator' ) );
		}

		if ( null === $this->item_helpers->get_forum_row( $forum_id ) ) {
			throw Action_Error_Exception::not_found(
				sprintf(
					/* translators: %s: The Forum field's value */
					esc_html_x( 'Forum %s does not exist.', 'Asgaros Forum', 'uncanny-automator' ),
					esc_html( sanitize_text_field( $forum_value ) )
				)
			);
		}

		if ( '' === $title ) {
			throw Action_Error_Exception::validation_failed( esc_html_x( 'The topic needs a title.', 'Asgaros Forum', 'uncanny-automator' ) );
		}

		if ( '' === $content ) {
			throw Action_Error_Exception::validation_failed( esc_html_x( 'The topic needs content.', 'Asgaros Forum', 'uncanny-automator' ) );
		}

		$author_id = $this->item_helpers->resolve_author( $parsed['TOPIC_AUTHOR'] ?? '', $user_id, $action_data['meta']['TOPIC_AUTHOR'] ?? '' );

		if ( is_wp_error( $author_id ) ) {
			throw Action_Error_Exception::validation_failed( esc_html( $author_id->get_error_message() ) );
		}

		$inserted = $forum->content->insert_topic( $forum_id, $title, $content, $author_id );
		$topic_id = ( is_object( $inserted ) && isset( $inserted->topic_id ) ) ? absint( $inserted->topic_id ) : 0;
		$post_id  = ( is_object( $inserted ) && isset( $inserted->post_id ) ) ? absint( $inserted->post_id ) : 0;

		if ( 0 === $topic_id ) {
			throw Action_Error_Exception::execution_failed( esc_html_x( 'Asgaros Forum did not create the topic.', 'Asgaros Forum', 'uncanny-automator' ) );
		}

		// The topic row is written before its first post; never leave a topic with no posts.
		if ( 0 === $post_id ) {
			$forum->delete_topic( $topic_id, true, false );

			throw Action_Error_Exception::execution_failed( esc_html_x( 'Asgaros Forum did not create the first post of the topic, so the empty topic was removed.', 'Asgaros Forum', 'uncanny-automator' ) );
		}

		$this->item_helpers->notify_new_topic( $topic_id, $forum_id, $author_id );

		$this->hydrate_tokens(
			array(
				'TOPIC_ID'    => $topic_id,
				'TOPIC_TITLE' => $title,
				'TOPIC_URL'   => $this->item_helpers->get_topic_url( $topic_id ),
				'POST_ID'     => $post_id,
			)
		);

		return true;
	}
}
