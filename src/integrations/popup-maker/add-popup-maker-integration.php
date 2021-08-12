<?php

namespace Uncanny_Automator;


/**
 * Class Add_Popup_Maker_Integration
 * @package Uncanny_Automator
 */
class Add_Popup_Maker_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Popup_Maker_Integration constructor.
	 */
	public function __construct() {
		// filter Popup Maker triggers.
		add_filter( 'pum_registered_triggers', array( $this, 'uap_add_new_popup_trigger' ) );
		add_filter( 'pum_popup_is_loadable', array( $this, 'maybe_disable_pop_up' ), 10, 2 );

		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'PM' );
		$this->set_name( 'Popup Maker' );
		$this->set_icon( 'popup-maker-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'popup-maker/popup-maker.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'Popup_Maker' );
	}

	/**
	 * Add a Automator trigger type to Popup Maker
	 *
	 * @param array $triggers Existing triggers.
	 *
	 * @return array
	 */
	public function uap_add_new_popup_trigger( $triggers ) {

		$triggers['automator'] = array(
			/* translators: 1. Trademarked term */
			'name'            => sprintf( esc_attr__( '%1$s recipe is completed', 'uncanny-automator' ), 'Automator' ),
			'modal_title'     => esc_attr__( 'Settings', 'uncanny-automator' ),
			'settings_column' => sprintf( '<strong>%1$s</strong>: %2$s', esc_attr__( 'Recipes', 'uncanny-automator' ), '{{data.recipe}}' ),
			'fields'          => array(
				'general' => array(
					'recipe' => array(
						'label'     => esc_attr__( 'Recipe', 'uncanny-automator' ),
						'type'      => 'postselect',
						'post_type' => 'uo-recipe',
						'multiple'  => true,
						'as_array'  => true,
						'std'       => array(),
					),
				),
			),
		);

		return $triggers;
	}

	/**
	 * Disable the popup if the trigger is Automator and the action has not been completed
	 *
	 * @param bool $loadable Whether a popup is loadable.
	 * @param int $pop_id Post ID of popup.
	 *
	 * @return bool
	 */
	public function maybe_disable_pop_up( $loadable, $pop_id ) {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$popup_settings = $wpdb->get_results( "SELECT post_id, meta_value as settings FROM $wpdb->postmeta WHERE meta_key = 'popup_settings'" );

		// All recipes that have popup maker triggers
		$recipes_enabled_in_popups = array();

		foreach ( $popup_settings as $popup ) {

			$popup_id       = $popup->post_id;
			$popup_settings = maybe_unserialize( $popup->settings );

			if ( isset( $popup_settings['triggers'] ) ) {
				foreach ( $popup_settings['triggers'] as $trigger ) {
					if ( 'automator' === $trigger['type'] ) {
						if ( isset( $trigger['settings'] ) && isset( $trigger['settings']['recipe'] ) ) {
							foreach ( $trigger['settings']['recipe'] as $recipe_id ) {
								$recipes_enabled_in_popups[ $popup_id ][] = absint( $recipe_id );
							}
						}
					}
				}
			}
		}

		global $wpdb;
		$automator_popups = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT post_parent FROM $wpdb->posts WHERE ID in (SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'POPUPID')"
		);

		// Is the pop up restricted by automator action completion?
		if ( ! isset( $recipes_enabled_in_popups[ $pop_id ] ) || empty( array_intersect( $recipes_enabled_in_popups[ $pop_id ], $automator_popups ) ) ) {
			return $loadable;
		}

		$is_action_popup_ids_enabled = get_user_meta( get_current_user_id(), 'display_pop_up_' . $pop_id, false );

		// if an this action was competed then a meta value was stores for this pop up.
		if ( is_array( $is_action_popup_ids_enabled ) && in_array( (string) $pop_id, $is_action_popup_ids_enabled, true ) ) {
			// delete the user meta so further recipes can run.
			delete_user_meta( get_current_user_id(), 'display_pop_up_' . $pop_id );

			return true;
		}

		return false;
	}

}
