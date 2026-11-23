<?php

namespace Uncanny_Automator\Integrations\Asgaros_Forum;

use Uncanny_Automator\Recipe\Trigger;
use Uncanny_Automator\Recipe\Trigger_Definition;

/**
 * Class Asgaros_Forum_User_Reacts_To_Post
 *
 * Trigger: A user reacts to {{a post}} with {{a reaction}}.
 *
 * A new reaction only; switching thumbs is "A user changes their reaction"
 * (Pro). The reactor is the recipe user; the AUTHOR_* tokens describe the
 * post's author.
 *
 * @property Asgaros_Forum_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 * @since 8.1.0
 */
class Asgaros_Forum_User_Reacts_To_Post extends Trigger {

	/**
	 * @return Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'ASGAROS_FORUM_USER_REACTS_TO_POST', 'ASGAROS_FORUM' )
			->trigger_meta( 'ASGAROS_FORUM_POST' )
			->hook( 'asgarosforum_after_add_reaction', 10, 3 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_is_pro( false );

		$this->set_sentence(
			sprintf(
				/* translators: 1: Post 2: Reaction */
				esc_html_x( 'A user reacts to {{a post:%1$s}} with {{a reaction:%2$s}}', 'Asgaros Forum', 'uncanny-automator' ),
				$this->get_trigger_meta(),
				'ASGAROS_FORUM_REACTION:' . $this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'A user reacts to {{a post}} with {{a reaction}}', 'Asgaros Forum', 'uncanny-automator' ) );
	}

	/**
	 * @return array[]
	 */
	public function options() {
		return array_merge(
			$this->item_helpers->post_drilldown_fields(),
			array(
				array(
					'option_code'           => 'ASGAROS_FORUM_REACTION',
					'label'                 => esc_html_x( 'Reaction', 'Asgaros Forum', 'uncanny-automator' ),
					'input_type'            => 'select',
					'required'              => true,
					'options'               => $this->item_helpers->build_reaction_options( true ),
					'relevant_tokens'       => array(),
					'supports_custom_value' => false,
					'options_show_id'       => false,
				),
			)
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
			Asgaros_Forum_Tokens::reaction_tokens()
		);
	}

	/**
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		if ( ! isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ) {
			return false;
		}

		$post_id  = $this->item_helpers->parse_id( $hook_args[0] ?? 0 );
		$user_id  = $this->item_helpers->parse_id( $hook_args[1] ?? 0 );
		$reaction = isset( $hook_args[2] ) ? (string) $hook_args[2] : '';

		if ( 0 === $post_id || 0 === $user_id || '' === $reaction ) {
			return false;
		}

		if ( ! $this->item_helpers->post_matches_drilldown( (array) $trigger['meta'], $post_id ) ) {
			return false;
		}

		$selected_reaction = (string) ( $trigger['meta']['ASGAROS_FORUM_REACTION'] ?? Asgaros_Forum_Helpers::ANY_VALUE );

		if ( Asgaros_Forum_Helpers::ANY_VALUE !== $selected_reaction && $selected_reaction !== $reaction ) {
			return false;
		}

		$this->set_user_id( $user_id );

		return true;
	}

	/**
	 * @param array $trigger   The completed trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$post_id  = $this->item_helpers->parse_id( $hook_args[0] ?? 0 );
		$user_id  = $this->item_helpers->parse_id( $hook_args[1] ?? 0 );
		$reaction = isset( $hook_args[2] ) ? (string) $hook_args[2] : '';
		$post     = $this->item_helpers->get_post_row( $post_id );

		return array_merge(
			Asgaros_Forum_Tokens::hydrate_post_tokens( $this->item_helpers, $post_id ),
			Asgaros_Forum_Tokens::hydrate_author_tokens( $this->item_helpers, null !== $post ? absint( $post->author_id ) : null ),
			Asgaros_Forum_Tokens::hydrate_reaction_tokens( $user_id, $reaction )
		);
	}
}
