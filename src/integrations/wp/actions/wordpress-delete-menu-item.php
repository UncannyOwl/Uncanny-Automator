<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * Class WP_DELETE_MENU_ITEM
 *
 * @package Uncanny_Automator
 * @property Wp_Helpers $item_helpers
 */
class WP_DELETE_MENU_ITEM extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WP' );
		$this->set_action_code( 'WP_DELETE_MENU_ITEM' );
		$this->set_action_meta( 'WP_MENU_ITEM_ID' );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				esc_html_x( 'Delete {{an item:%1$s}} from a menu', 'WordPress', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Delete {{an item}} from a menu', 'WordPress', 'uncanny-automator' ) );
	}

	/**
	 * Define action tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			array(
				'tokenId'   => 'MENU_ITEM_TITLE',
				'tokenName' => esc_html_x( 'Menu item title', 'WordPress', 'uncanny-automator' ),
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
				'option_code' => $this->get_action_meta(),
				'label'       => esc_html_x( 'Menu item ID', 'WordPress', 'uncanny-automator' ),
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
		$item_id = absint( sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' ) );

		if ( 0 === $item_id ) {
			$this->add_log_error( esc_html_x( 'Invalid menu item ID.', 'WordPress', 'uncanny-automator' ) );
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

		$item_title = $item_post->post_title;
		$result     = wp_delete_post( $item_id, true );

		if ( false === $result || null === $result ) {
			$this->add_log_error( esc_html_x( 'Failed to delete the menu item.', 'WordPress', 'uncanny-automator' ) );
			return false;
		}

		$this->hydrate_tokens(
			array(
				'MENU_ITEM_TITLE' => $item_title,
			)
		);

		return true;
	}
}
