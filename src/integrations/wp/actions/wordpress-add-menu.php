<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * Class WP_ADD_MENU
 *
 * @package Uncanny_Automator
 * @property Wp_Helpers $item_helpers
 */
class WP_ADD_MENU extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WP' );
		$this->set_action_code( 'WP_ADD_MENU' );
		$this->set_action_meta( 'WP_MENU_NAME' );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				esc_html_x( 'Add {{a menu:%1$s}}', 'WordPress', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Add {{a menu}}', 'WordPress', 'uncanny-automator' ) );
	}

	/**
	 * Define action tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			array(
				'tokenId'   => 'MENU_ID',
				'tokenName' => esc_html_x( 'Menu ID', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'MENU_NAME',
				'tokenName' => esc_html_x( 'Menu name', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_action_meta(),
				'label'           => esc_html_x( 'Menu name', 'WordPress', 'uncanny-automator' ),
				'input_type'      => 'text',
				'required'        => true,
				// Suppress the auto-derived "Menu name" token — MENU_NAME is
				// declared in define_tokens() as the canonical token instead.
				'relevant_tokens' => array(),
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$menu_name = sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' );

		if ( '' === $menu_name ) {
			$this->add_log_error( esc_html_x( 'Menu name cannot be empty.', 'WordPress', 'uncanny-automator' ) );
			return false;
		}

		$menu_id = wp_create_nav_menu( $menu_name );

		if ( is_wp_error( $menu_id ) ) {
			$this->add_log_error( $menu_id->get_error_message() );
			return false;
		}

		$this->hydrate_tokens(
			array(
				'MENU_ID'   => $menu_id,
				'MENU_NAME' => $menu_name,
			)
		);

		return true;
	}
}
