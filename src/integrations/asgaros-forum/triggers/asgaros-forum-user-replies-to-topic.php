<?php

namespace Uncanny_Automator\Integrations\Asgaros_Forum;

use Uncanny_Automator\Recipe\Trigger;
use Uncanny_Automator\Recipe\Trigger_Definition;

/**
 * Class Asgaros_Forum_User_Replies_To_Topic
 *
 * Trigger: A user replies to {{a topic}} in {{a forum}}.
 *
 * The topic is picked from the chosen forum, as LearnDash picks a lesson from
 * a course.
 *
 * @property Asgaros_Forum_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 * @since 8.1.0
 */
class Asgaros_Forum_User_Replies_To_Topic extends Trigger {

	/**
	 * @return Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'ASGAROS_FORUM_USER_REPLIES_TO_TOPIC', 'ASGAROS_FORUM' )
			->trigger_meta( 'ASGAROS_FORUM_TOPIC' )
			->hook( 'asgarosforum_after_add_post_submit', 10, 6 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_is_pro( false );

		$this->set_sentence(
			sprintf(
				/* translators: 1: Topic 2: Forum */
				esc_html_x( 'A user replies to {{a topic:%1$s}} in {{a forum:%2$s}}', 'Asgaros Forum', 'uncanny-automator' ),
				$this->get_trigger_meta(),
				'ASGAROS_FORUM_FORUM:' . $this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'A user replies to {{a topic}} in {{a forum}}', 'Asgaros Forum', 'uncanny-automator' ) );
	}

	/**
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code'     => 'ASGAROS_FORUM_FORUM',
				'label'           => esc_html_x( 'Forum', 'Asgaros Forum', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'forums' ),
			),
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Topic', 'Asgaros Forum', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_parent_config( 'topics_from_forum', array( 'ASGAROS_FORUM_FORUM' ) ),
			),
		);
	}

	/**
	 * @param array $trigger The trigger settings.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge(
			$tokens,
			Asgaros_Forum_Tokens::post_tokens(),
			Asgaros_Forum_Tokens::author_tokens(),
			array(
				array(
					'tokenId'   => 'SUBJECT',
					'tokenName' => esc_html_x( 'Subject', 'Asgaros Forum', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
			)
		);
	}

	/**
	 * A filter-cancelled reply still reaches the hook, with `$post_id` false.
	 *
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		if ( ! isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ) {
			return false;
		}

		$post_id   = $this->item_helpers->parse_id( $hook_args[0] ?? 0 );
		$topic_id  = $this->item_helpers->parse_id( $hook_args[1] ?? 0 );
		$author_id = $this->item_helpers->parse_id( $hook_args[5] ?? 0 );

		if ( 0 === $post_id || 0 === $topic_id || 0 === $author_id ) {
			return false;
		}

		$selected_topic = (string) $trigger['meta'][ $this->get_trigger_meta() ];

		if ( Asgaros_Forum_Helpers::ANY_VALUE !== $selected_topic && $this->item_helpers->parse_id( $selected_topic ) !== $topic_id ) {
			return false;
		}

		// A recipe saved before the Forum field existed has no key: "Any" (R15).
		$selected_forum = (string) ( $trigger['meta']['ASGAROS_FORUM_FORUM'] ?? Asgaros_Forum_Helpers::ANY_VALUE );

		if ( Asgaros_Forum_Helpers::ANY_VALUE !== $selected_forum ) {
			$topic = $this->item_helpers->get_topic_row( $topic_id );

			if ( null === $topic || absint( $topic->parent_id ) !== $this->item_helpers->parse_id( $selected_forum ) ) {
				return false;
			}
		}

		$this->set_user_id( $author_id );

		return true;
	}

	/**
	 * @param array $trigger   The completed trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$post_id   = $this->item_helpers->parse_id( $hook_args[0] ?? 0 );
		$content   = isset( $hook_args[3] ) ? (string) $hook_args[3] : null;
		$author_id = $this->item_helpers->parse_id( $hook_args[5] ?? 0 );

		return array_merge(
			Asgaros_Forum_Tokens::hydrate_post_tokens( $this->item_helpers, $post_id, $content ),
			Asgaros_Forum_Tokens::hydrate_author_tokens( $this->item_helpers, $author_id ),
			array( 'SUBJECT' => isset( $hook_args[2] ) ? (string) $hook_args[2] : '' )
		);
	}
}
