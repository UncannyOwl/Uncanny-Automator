<?php

namespace Uncanny_Automator\Integrations\Asgaros_Forum;

use Uncanny_Automator\Recipe\Trigger;
use Uncanny_Automator\Recipe\Trigger_Definition;

/**
 * Class Asgaros_Forum_User_Added_To_Usergroup
 *
 * Trigger: A user is added to {{a usergroup}}.
 *
 * Asgaros's own hook is named after the group, so the usergroup dispatcher
 * listens on it and re-fires this trigger's literal hook. The member is bound
 * from the hook: an admin's profile save, the Users-screen bulk action and
 * auto-add on registration run without the member's session.
 *
 * @property Asgaros_Forum_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 * @since 8.1.0
 */
class Asgaros_Forum_User_Added_To_Usergroup extends Trigger {

	/**
	 * @return Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'ASGAROS_FORUM_USER_ADDED_TO_USERGROUP', 'ASGAROS_FORUM' )
			->trigger_meta( 'ASGAROS_FORUM_USERGROUP' )
			->hook( 'automator_asgaros_forum_user_added_to_usergroup', 10, 2 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_is_pro( false );
		$this->set_is_login_required( false );

		$this->set_sentence(
			sprintf(
				/* translators: 1: Usergroup */
				esc_html_x( 'A user is added to {{a usergroup:%1$s}}', 'Asgaros Forum', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'A user is added to {{a usergroup}}', 'Asgaros Forum', 'uncanny-automator' ) );
	}

	/**
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Usergroup', 'Asgaros Forum', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'usergroups' ),
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
		return array_merge( $tokens, Asgaros_Forum_Tokens::usergroup_tokens() );
	}

	/**
	 * Asgaros fires its hook even when the term write failed, so membership is
	 * read back.
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

		$user_id      = $this->item_helpers->parse_id( $hook_args[0] ?? 0 );
		$usergroup_id = $this->item_helpers->parse_id( $hook_args[1] ?? 0 );

		if ( 0 === $user_id || 0 === $usergroup_id ) {
			return false;
		}

		$selected = (string) $trigger['meta'][ $this->get_trigger_meta() ];

		if ( Asgaros_Forum_Helpers::ANY_VALUE !== $selected && $this->item_helpers->parse_id( $selected ) !== $usergroup_id ) {
			return false;
		}

		if ( ! $this->item_helpers->is_user_in_usergroup( $user_id, $usergroup_id ) ) {
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
		return Asgaros_Forum_Tokens::hydrate_usergroup_tokens(
			$this->item_helpers,
			$this->item_helpers->parse_id( $hook_args[0] ?? 0 ),
			$this->item_helpers->parse_id( $hook_args[1] ?? 0 )
		);
	}
}
