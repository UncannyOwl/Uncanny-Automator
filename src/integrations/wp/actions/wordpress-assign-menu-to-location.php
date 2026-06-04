<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * Class WP_ASSIGN_MENU_TO_LOCATION
 *
 * @package Uncanny_Automator
 * @property Wp_Helpers $item_helpers
 */
class WP_ASSIGN_MENU_TO_LOCATION extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WP' );
		$this->set_action_code( 'WP_ASSIGN_MENU_LOCATION' );
		$this->set_action_meta( 'WP_MENU' );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				esc_html_x( 'Assign {{a menu:%1$s}} to {{a location:%2$s}}', 'WordPress', 'uncanny-automator' ),
				$this->get_action_meta(),
				'WP_MENU_LOCATION:' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Assign {{a menu}} to {{a location}}', 'WordPress', 'uncanny-automator' ) );
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
			array(
				'tokenId'   => 'LOCATION_NAME',
				'tokenName' => esc_html_x( 'Location name', 'WordPress', 'uncanny-automator' ),
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
				'option_code'           => 'WP_MENU_LOCATION',
				'label'                 => esc_html_x( 'Location', 'WordPress', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'supports_custom_value' => true,
				'options'               => array(),
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'menu_locations' ),
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
		$location = sanitize_text_field( $parsed['WP_MENU_LOCATION'] ?? '' );

		if ( 0 === $menu_id ) {
			$this->add_log_error( esc_html_x( 'Invalid menu ID.', 'WordPress', 'uncanny-automator' ) );
			return false;
		}

		if ( '' === $location ) {
			$this->add_log_error( esc_html_x( 'Menu location cannot be empty.', 'WordPress', 'uncanny-automator' ) );
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

		$locations              = get_nav_menu_locations();
		$locations[ $location ] = $menu_id;

		set_theme_mod( 'nav_menu_locations', $locations );

		// Resolve location display name.
		$registered_locations = get_registered_nav_menus();
		$location_name        = isset( $registered_locations[ $location ] ) ? $registered_locations[ $location ] : $location;

		$this->hydrate_tokens(
			array(
				'MENU_NAME'     => $menu->name,
				'LOCATION_NAME' => $location_name,
			)
		);

		return true;
	}
}
