<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * Class WP_USERROLEUPDATED
 *
 * Fires when a user's role changes to a specific role.
 *
 * @package Uncanny_Automator\Integrations\Wp
 *
 * @property Wp_Helpers $item_helpers
 */
class WP_USERROLEUPDATED extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'USERROLEUPDATED', 'WP' )
			->trigger_meta( 'WPROLE' )
			->hook( 'set_user_role', 90, 3 );
	}

	/**
	 * Sets up the trigger properties and action hook.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type are auto-applied from definition().

		// translators: %1$s is a role.
		$this->set_sentence(
			sprintf(
				esc_html_x( "A user's role changes to {{a specific role:%1\$s}}", 'WordPress', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence(
			esc_html_x( "A user's role changes to {{a specific role}}", 'WordPress', 'uncanny-automator' )
		);
	}

	/**
	 * Define the trigger options.
	 *
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'option_code' => $this->get_trigger_meta(),
				'label'       => esc_html_x( 'Role', 'WordPress', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => true,
				'options'     => array(),
				'remote_data' => $this->item_helpers->remote_data_load_config( 'roles' ),
			),
		);
	}

	/**
	 * Define trigger tokens.
	 *
	 * @param array $trigger The trigger data.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge(
			Wp_Shared_Tokens::user_tokens(),
			Wp_Shared_Tokens::role_change_tokens()
		);
	}

	/**
	 * Validate the trigger.
	 *
	 * @param array $trigger   The trigger data.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		list( $user_id, $role, $old_roles ) = $hook_args;

		// Bail if the role was already assigned. wp_update_user() re-applies
		// the existing role on every profile save, which would re-fire this trigger.
		if ( in_array( $role, (array) $old_roles, true ) ) {
			return false;
		}

		$selected_role = $trigger['meta'][ $this->get_trigger_meta() ] ?? '';

		// Match "Any role" or specific role.
		if ( intval( '-1' ) !== intval( $selected_role ) ) {
			$user_obj = get_user_by( 'ID', $user_id );
			if ( ! $user_obj instanceof \WP_User || ! user_can( $user_obj, $selected_role ) ) {
				return false;
			}
			if ( (string) $role !== (string) $selected_role ) {
				return false;
			}
		}

		$this->set_user_id( absint( $user_id ) );

		return true;
	}

	/**
	 * Hydrate trigger tokens with runtime values.
	 *
	 * @param array $trigger   The trigger data.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $user_id, $role, $old_roles ) = $hook_args;

		return array_merge(
			Wp_Shared_Tokens::hydrate_user_tokens( absint( $user_id ) ),
			Wp_Shared_Tokens::hydrate_role_change_tokens( (string) $role, (array) $old_roles )
		);
	}
}
