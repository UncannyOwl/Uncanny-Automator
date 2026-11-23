<?php

namespace Uncanny_Automator\Integrations\Asgaros_Forum;

use Uncanny_Automator\Recipe\Trigger;
use Uncanny_Automator\Recipe\Trigger_Definition;

/**
 * Class Asgaros_Forum_Topic_Approved
 *
 * Trigger: A topic is approved.
 *
 * Anonymous: the run stays with whoever is logged in (the approving moderator,
 * a bystander to the topic), and the topic's author is a token. Approving an
 * already approved topic fires again; Asgaros doesn't tell the two apart.
 *
 * @property Asgaros_Forum_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 * @since 8.1.0
 */
class Asgaros_Forum_Topic_Approved extends Trigger {

	/**
	 * @return Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'ASGAROS_FORUM_TOPIC_APPROVED', 'ASGAROS_FORUM' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'ASGAROS_FORUM_TOPIC' )
			->hook( 'asgarosforum_after_topic_approve', 10, 1 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_is_pro( false );

		$this->set_sentence( esc_html_x( 'A topic is approved', 'Asgaros Forum', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A topic is approved', 'Asgaros Forum', 'uncanny-automator' ) );
	}

	/**
	 * The hook carries only the topic; the forum is a token.
	 *
	 * @return array[]
	 */
	public function options() {
		return array();
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
			Asgaros_Forum_Tokens::topic_tokens(),
			Asgaros_Forum_Tokens::author_tokens()
		);
	}

	/**
	 * `approve_topic()` checks only the moderator, never the topic.
	 *
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		$topic = $this->item_helpers->get_topic_row( $this->item_helpers->parse_id( $hook_args[0] ?? 0 ) );

		return null !== $topic && 1 === absint( $topic->approved );
	}

	/**
	 * @param array $trigger   The completed trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$topic_id = $this->item_helpers->parse_id( $hook_args[0] ?? 0 );
		$topic    = $this->item_helpers->get_topic_row( $topic_id );

		return array_merge(
			Asgaros_Forum_Tokens::hydrate_topic_tokens( $this->item_helpers, $topic_id ),
			Asgaros_Forum_Tokens::hydrate_author_tokens( $this->item_helpers, null !== $topic ? absint( $topic->author_id ) : null )
		);
	}
}
