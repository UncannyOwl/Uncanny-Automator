<?php
namespace Uncanny_Automator\Integrations\Common\Tokens;

use Uncanny_Automator\Tokens\Token;

class User_Role extends Token {

	/**
	 * setup
	 *
	 * @return void
	 */
	public function setup() {
		$this->integration   = 'COMMON';
		$this->id            = 'user_role';
		$this->name          = esc_attr_x( 'User role', 'Token', 'uncanny-automator' );
		$this->requires_user = true;
	}

	/**
	 * display_in_recipe_ui
	 *
	 * @return bool
	 */
	public function display_in_recipe_ui() {

		if ( 'anonymous' === $this->get_recipe_type() ) {
			$this->remove_supported_item( 'trigger' );
			$this->remove_supported_item( 'user-selector' );
		}

		return true;
	}

	/**
	 * parse
	 *
	 * @param  mixed $replaceable
	 * @param  mixed $field_text
	 * @param  mixed $match
	 * @param  mixed $current_user
	 * @return mixed
	 */
	public function parse( $replaceable, $field_text, $match, $current_user ) {

		$user = get_user_by( 'id', $current_user );

		$roles = '';

		if ( ! is_a( $user, 'WP_User' ) ) {
			return $replaceable;
		}

		$roles = $user->roles;

		$rr = array();

		global $wp_roles;

		if ( empty( $roles ) ) {
			return $replaceable;
		}

		foreach ( $roles as $r ) {

			if ( empty( $wp_roles->roles[ $r ]['name'] ) ) {
				continue;
			}

			$rr[] = $wp_roles->roles[ $r ]['name'];
		}

		$roles = join( ', ', $rr );

		return $roles;
	}
}
