<?php

namespace Uncanny_Automator\Integrations\Asgaros_Forum;

use Uncanny_Automator\Recipe\Trigger;
use Uncanny_Automator\Recipe\Trigger_Definition;

/**
 * Class Asgaros_Forum_User_Publishes_Topic
 *
 * Trigger: A user submits a topic in {{a forum}}.
 *
 * Fires for a topic held for approval too; the Topic status token tells the
 * two apart. The new topic is a token, not a pick. The code keeps its
 * original name: it is stored in recipes.
 *
 * @property Asgaros_Forum_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 * @since 8.1.0
 */
class Asgaros_Forum_User_Publishes_Topic extends Trigger {

	/**
	 * @return Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'ASGAROS_FORUM_USER_PUBLISHES_TOPIC', 'ASGAROS_FORUM' )
			->trigger_meta( 'ASGAROS_FORUM_FORUM' )
			->hook( 'asgarosforum_after_add_topic_submit', 10, 6 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_is_pro( false );

		$this->set_sentence(
			sprintf(
				/* translators: 1: The selected forum */
				esc_html_x( 'A user submits a topic in {{a forum:%1$s}}', 'Asgaros Forum', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'A user submits a topic in {{a forum}}', 'Asgaros Forum', 'uncanny-automator' ) );
	}

	/**
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Forum', 'Asgaros Forum', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'forums' ),
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
					'tokenId'   => 'TOPIC_STATUS',
					'tokenName' => esc_html_x( 'Topic status', 'Asgaros Forum', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
			)
		);
	}

	/**
	 * A filter-cancelled submit still reaches the hook with false IDs; a guest
	 * topic has author 0 and no user to run for.
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

		$topic = $this->item_helpers->get_topic_row( $topic_id );

		if ( null === $topic ) {
			return false;
		}

		$selected_forum = (string) $trigger['meta'][ $this->get_trigger_meta() ];

		if ( Asgaros_Forum_Helpers::ANY_VALUE !== $selected_forum && $this->item_helpers->parse_id( $selected_forum ) !== absint( $topic->parent_id ) ) {
			return false;
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
		$topic_id  = $this->item_helpers->parse_id( $hook_args[1] ?? 0 );
		$content   = isset( $hook_args[3] ) ? (string) $hook_args[3] : null;
		$author_id = $this->item_helpers->parse_id( $hook_args[5] ?? 0 );

		$topic  = $this->item_helpers->get_topic_row( $topic_id );
		$status = '';

		if ( null !== $topic ) {
			$status = 1 === absint( $topic->approved )
				? esc_html_x( 'Approved', 'Asgaros Forum', 'uncanny-automator' )
				: esc_html_x( 'Awaiting approval', 'Asgaros Forum', 'uncanny-automator' );
		}

		return array_merge(
			Asgaros_Forum_Tokens::hydrate_post_tokens( $this->item_helpers, $post_id, $content ),
			Asgaros_Forum_Tokens::hydrate_author_tokens( $this->item_helpers, $author_id ),
			array( 'TOPIC_STATUS' => $status )
		);
	}
}
