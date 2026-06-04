<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * Class WP_USERROLEADDED
 *
 * Fires when a specific role is added to a user.
 *
 * @package Uncanny_Automator\Integrations\Wp
 *
 * @property Wp_Helpers $item_helpers
 */
class WP_USERROLEADDED extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'USERROLEADDED', 'WP' )
			->trigger_meta( 'WPROLE' )
			->hook( 'automator_user_role_changed', 90, 3 );
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
				esc_html_x( '{{A specific:%1$s}} role is added to the user', 'WordPress', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence(
			esc_html_x( '{{A specific}} role is added to the user', 'WordPress', 'uncanny-automator' )
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
			Wp_Shared_Tokens::role_tokens()
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

		// Only fire when the role is truly new (not already held).
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

		// Deduplication transient to prevent double-firing.
		$transient_key = sprintf(
			'automator_role_add_%d_%s_%d_%s',
			$trigger['ID'] ?? 0,
			$trigger['recipe_id'] ?? 0,
			$user_id,
			$role
		);

		if ( get_transient( $transient_key ) ) {
			return false;
		}

		set_transient( $transient_key, true, 10 );

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
			Wp_Shared_Tokens::hydrate_role_tokens( (string) $role )
		);
	}
}
