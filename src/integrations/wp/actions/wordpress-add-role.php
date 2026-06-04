<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * Class WP_ADDROLE
 *
 * @package Uncanny_Automator
 * @property Wp_Helpers $item_helpers
 */
class WP_ADDROLE extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WP' );
		$this->set_action_code( 'ADDROLE' );
		$this->set_action_meta( 'WPROLE' );
		$this->set_requires_user( true );
		// translators: %1$s is the role.
		$this->set_sentence( sprintf( esc_html_x( "Add {{a new role:%1\$s}} to the user's roles", 'WordPress', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( "Add {{a new role}} to the user's roles", 'WordPress', 'uncanny-automator' ) );
	}

	/**
	 * Define action tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			array(
				'tokenId'   => 'USER_ROLES',
				'tokenName' => esc_html_x( "List of user's roles", 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Define action options.
	 *
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code'           => $this->get_action_meta(),
				'label'                 => esc_html_x( 'Role', 'WordPress', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'supports_custom_value' => true,
				'options'               => array(),
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'roles_strict' ),
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action configuration.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        Additional arguments.
	 * @param array $parsed      Parsed token values.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$role = sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' );

		$user_obj = new \WP_User( (int) $user_id );

		if ( ! $user_obj instanceof \WP_User || 0 === $user_obj->ID ) {
			$this->add_log_error( esc_html_x( 'User not found.', 'WordPress', 'uncanny-automator' ) );
			return false;
		}

		$user_obj->add_role( $role );

		$this->hydrate_tokens(
			array(
				'USER_ROLES' => ! empty( $user_obj->roles ) ? implode( ', ', array_values( $user_obj->roles ) ) : '',
			)
		);

		return true;
	}
}
