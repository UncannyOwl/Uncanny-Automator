<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * Class WP_DELETE_MENU
 *
 * @package Uncanny_Automator
 * @property Wp_Helpers $item_helpers
 */
class WP_DELETE_MENU extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WP' );
		$this->set_action_code( 'WP_DELETE_MENU' );
		$this->set_action_meta( 'WP_MENU' );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				esc_html_x( 'Delete {{a menu:%1$s}}', 'WordPress', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Delete {{a menu}}', 'WordPress', 'uncanny-automator' ) );
	}

	/**
	 * Define action tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
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
				'option_code'           => $this->get_action_meta(),
				'label'                 => esc_html_x( 'Menu', 'WordPress', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'supports_custom_value' => true,
				'options'               => array(),
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'nav_menus' ),
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
		$menu_id = absint( sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' ) );

		if ( 0 === $menu_id ) {
			$this->add_log_error( esc_html_x( 'Invalid menu ID.', 'WordPress', 'uncanny-automator' ) );
			return false;
		}

		$menu = wp_get_nav_menu_object( $menu_id );

		if ( false === $menu ) {
			$this->add_log_error(
				sprintf(
					/* translators: %d: Menu ID */
					esc_html_x( 'Menu with ID %d does not exist.', 'WordPress', 'uncanny-automator' ),
					$menu_id
				)
			);
			return false;
		}

		$menu_name = $menu->name;
		$result    = wp_delete_nav_menu( $menu_id );

		if ( is_wp_error( $result ) ) {
			$this->add_log_error( $result->get_error_message() );
			return false;
		}

		if ( false === $result ) {
			$this->add_log_error( esc_html_x( 'Failed to delete the menu.', 'WordPress', 'uncanny-automator' ) );
			return false;
		}

		$this->hydrate_tokens(
			array(
				'MENU_NAME' => $menu_name,
			)
		);

		return true;
	}
}
