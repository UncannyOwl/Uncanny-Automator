<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * Class WP_RENAME_MENU
 *
 * @package Uncanny_Automator
 * @property Wp_Helpers $item_helpers
 */
class WP_RENAME_MENU extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WP' );
		$this->set_action_code( 'WP_RENAME_MENU' );
		$this->set_action_meta( 'WP_MENU' );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				esc_html_x( 'Rename {{a menu:%1$s}} to {{a new name:%2$s}}', 'WordPress', 'uncanny-automator' ),
				$this->get_action_meta(),
				'WP_NEW_MENU_NAME:' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Rename {{a menu}} to {{a new name}}', 'WordPress', 'uncanny-automator' ) );
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
				'tokenId'   => 'OLD_MENU_NAME',
				'tokenName' => esc_html_x( 'Old menu name', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'NEW_MENU_NAME',
				'tokenName' => esc_html_x( 'New menu name', 'WordPress', 'uncanny-automator' ),
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
			array(
				'option_code' => 'WP_NEW_MENU_NAME',
				'label'       => esc_html_x( 'New name', 'WordPress', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
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
		$menu_id  = absint( sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' ) );
		$new_name = sanitize_text_field( $parsed['WP_NEW_MENU_NAME'] ?? '' );

		if ( 0 === $menu_id ) {
			$this->add_log_error( esc_html_x( 'Invalid menu ID.', 'WordPress', 'uncanny-automator' ) );
			return false;
		}

		if ( '' === $new_name ) {
			$this->add_log_error( esc_html_x( 'New menu name cannot be empty.', 'WordPress', 'uncanny-automator' ) );
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

		$old_name = $menu->name;
		$result   = wp_update_nav_menu_object( $menu_id, array( 'menu-name' => $new_name ) );

		if ( is_wp_error( $result ) ) {
			$this->add_log_error( $result->get_error_message() );
			return false;
		}

		$this->hydrate_tokens(
			array(
				'MENU_ID'       => $menu_id,
				'OLD_MENU_NAME' => $old_name,
				'NEW_MENU_NAME' => $new_name,
			)
		);

		return true;
	}
}
