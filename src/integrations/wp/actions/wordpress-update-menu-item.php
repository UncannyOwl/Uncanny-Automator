<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * Class WP_UPDATE_MENU_ITEM
 *
 * @package Uncanny_Automator
 * @property Wp_Helpers $item_helpers
 */
class WP_UPDATE_MENU_ITEM extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WP' );
		$this->set_action_code( 'WP_UPDATE_MENU_ITEM' );
		$this->set_action_meta( 'WP_MENU' );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				esc_html_x( 'Update {{an item:%2$s}} in {{a menu:%1$s}}', 'WordPress', 'uncanny-automator' ),
				$this->get_action_meta(),
				'WP_MENU_ITEM_ID:' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Update {{an item}} in {{a menu}}', 'WordPress', 'uncanny-automator' ) );
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
				'option_code'     => 'WP_MENU_ITEM_ID',
				'label'           => esc_html_x( 'Menu item ID', 'WordPress', 'uncanny-automator' ),
				'input_type'      => 'text',
				'required'        => true,
				// Suppress the auto-derived "Menu item ID" token —
				// MENU_ITEM_ID is declared in define_tokens() as the
				// canonical token instead.
				'relevant_tokens' => array(),
			),
			array(
				'option_code' => 'WP_MENU_ITEM_TITLE',
				'label'       => esc_html_x( 'New title', 'WordPress', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
			array(
				'option_code' => 'WP_MENU_ITEM_URL',
				'label'       => esc_html_x( 'New URL', 'WordPress', 'uncanny-automator' ),
				'input_type'  => 'url',
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
		$menu_id = absint( sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' ) );
		$item_id = absint( sanitize_text_field( $parsed['WP_MENU_ITEM_ID'] ?? '' ) );
		$title   = sanitize_text_field( $parsed['WP_MENU_ITEM_TITLE'] ?? '' );
		$url     = sanitize_text_field( $parsed['WP_MENU_ITEM_URL'] ?? '' );

		if ( 0 === $menu_id ) {
			$this->add_log_error( esc_html_x( 'Invalid menu ID.', 'WordPress', 'uncanny-automator' ) );
			return false;
		}

		if ( 0 === $item_id ) {
			$this->add_log_error( esc_html_x( 'Invalid menu item ID.', 'WordPress', 'uncanny-automator' ) );
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

		$item_post = get_post( $item_id );

		if ( null === $item_post || 'nav_menu_item' !== $item_post->post_type ) {
			$this->add_log_error(
				sprintf(
					/* translators: %d: Menu item ID */
					esc_html_x( 'Menu item with ID %d does not exist.', 'WordPress', 'uncanny-automator' ),
					$item_id
				)
			);
			return false;
		}

		$update_args = array(
			'menu-item-status' => 'publish',
		);

		if ( '' !== $title ) {
			$update_args['menu-item-title'] = $title;
		}

		if ( '' !== $url ) {
			$update_args['menu-item-url'] = $url;
		}

		$result = wp_update_nav_menu_item( $menu_id, $item_id, $update_args );

		if ( is_wp_error( $result ) ) {
			$this->add_log_error( $result->get_error_message() );
			return false;
		}

		// Refresh item data after update.
		$updated_post = get_post( $item_id );

		$this->hydrate_tokens(
			array(
				'MENU_ITEM_ID'    => $item_id,
				'MENU_ITEM_TITLE' => '' !== $title ? $title : ( null !== $updated_post ? $updated_post->post_title : '' ),
				'MENU_ITEM_URL'   => '' !== $url ? $url : get_post_meta( $item_id, '_menu_item_url', true ),
			)
		);

		return true;
	}
}
