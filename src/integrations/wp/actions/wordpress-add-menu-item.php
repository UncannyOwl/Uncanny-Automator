<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * Class WP_ADD_MENU_ITEM
 *
 * @package Uncanny_Automator
 * @property Wp_Helpers $item_helpers
 */
class WP_ADD_MENU_ITEM extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WP' );
		$this->set_action_code( 'WP_ADD_MENU_ITEM' );
		$this->set_action_meta( 'WP_MENU' );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				esc_html_x( 'Add {{an item:%2$s}} to {{a menu:%1$s}}', 'WordPress', 'uncanny-automator' ),
				$this->get_action_meta(),
				'WP_MENU_ITEM_TITLE:' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Add {{an item}} to {{a menu}}', 'WordPress', 'uncanny-automator' ) );
	}

	/**
	 * Define action tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			array(
				'tokenId'   => 'MENU_ITEM_ID',
				'tokenName' => esc_html_x( 'Menu item ID', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'MENU_ITEM_TITLE',
				'tokenName' => esc_html_x( 'Menu item title', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MENU_ITEM_URL',
				'tokenName' => esc_html_x( 'Menu item URL', 'WordPress', 'uncanny-automator' ),
				'tokenType' => 'url',
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
				'option_code' => 'WP_MENU_ITEM_TITLE',
				'label'       => esc_html_x( 'Title', 'WordPress', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
			),
			array(
				'option_code' => 'WP_MENU_ITEM_URL',
				'label'       => esc_html_x( 'URL', 'WordPress', 'uncanny-automator' ),
				'input_type'  => 'url',
				'required'    => true,
			),
			array(
				'option_code' => 'WP_MENU_ITEM_POSITION',
				'label'       => esc_html_x( 'Position', 'WordPress', 'uncanny-automator' ),
				'input_type'  => 'int',
				'required'    => false,
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
		$title    = sanitize_text_field( $parsed['WP_MENU_ITEM_TITLE'] ?? '' );
		$url      = sanitize_text_field( $parsed['WP_MENU_ITEM_URL'] ?? '' );
		$position = absint( sanitize_text_field( $parsed['WP_MENU_ITEM_POSITION'] ?? 0 ) );

		if ( 0 === $menu_id ) {
			$this->add_log_error( esc_html_x( 'Invalid menu ID.', 'WordPress', 'uncanny-automator' ) );
			return false;
		}

		if ( '' === $title ) {
			$this->add_log_error( esc_html_x( 'Menu item title cannot be empty.', 'WordPress', 'uncanny-automator' ) );
			return false;
		}

		if ( '' === $url ) {
			$this->add_log_error( esc_html_x( 'Menu item URL cannot be empty.', 'WordPress', 'uncanny-automator' ) );
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

		$item_data = array(
			'menu-item-title'    => $title,
			'menu-item-url'      => $url,
			'menu-item-status'   => 'publish',
			'menu-item-type'     => 'custom',
			'menu-item-position' => $position,
		);

		$item_id = wp_update_nav_menu_item( $menu_id, 0, $item_data );

		if ( is_wp_error( $item_id ) ) {
			$this->add_log_error( $item_id->get_error_message() );
			return false;
		}

		$this->hydrate_tokens(
			array(
				'MENU_ITEM_ID'    => $item_id,
				'MENU_ITEM_TITLE' => $title,
				'MENU_ITEM_URL'   => $url,
			)
		);

		return true;
	}
}
