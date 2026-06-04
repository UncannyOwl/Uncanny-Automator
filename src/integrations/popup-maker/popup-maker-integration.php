<?php

namespace Uncanny_Automator\Integrations\Popup_Maker;

use Uncanny_Automator\Integration;

/**
 * Class Pm_Integration
 *
 * @package Uncanny_Automator\Integrations\Popup_Maker
 */
class Pm_Integration extends Integration {

	/**
	 * Helpers instance.
	 *
	 * @var Popup_Maker_Helpers
	 */
	public $helpers;

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->helpers = new Popup_Maker_Helpers();

		$this->set_integration( 'PM' );
		$this->set_name( 'Popup Maker' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/popup-maker-icon.svg' );
		$this->set_plugin_file_path( 'popup-maker/popup-maker.php' );
	}

	/**
	 * Load actions and register the Popup Maker UI integration hooks.
	 *
	 * @return void
	 */
	public function load() {

		// Wire the Popup Maker bridge hooks for full-load mode too (editor/REST) —
		// the parent only calls load_shared_hooks() in targeted mode, never alongside load().
		$this->load_shared_hooks();

		// Free action.
		new PM_POPUPSHOW( $this->helpers );

		// Free triggers.
		new ANON_PM_POPUP_OPENED( $this->helpers );
		new ANON_PM_POPUP_CONVERSION( $this->helpers );
		new USER_PM_FORM_SUBMITTED( $this->helpers );
		new ANON_PM_FORM_SUBMITTED( $this->helpers );
		new USER_PM_SUB_FORM_SUBMITTED( $this->helpers );
		new ANON_PM_SUB_FORM_SUBMITTED( $this->helpers );
	}

	/**
	 * Register the Popup Maker bridge hooks shared across load modes.
	 *
	 * These wire into Popup Maker's own UI and frontend, which run outside
	 * Automator's surfaces — so they must register in targeted mode (frontend /
	 * PM editor) where load_shared_hooks() runs but load() does not. load() calls
	 * this too so full-load mode wires them as well.
	 *
	 * @return void
	 */
	protected function load_shared_hooks() {

		// Expose the "Automator" trigger inside Popup Maker's trigger UI.
		add_filter( 'pum_registered_triggers', array( $this, 'register_popup_maker_trigger' ) );

		// Gate popup auto-open on the Automator action having fired for this user.
		add_filter( 'pum_popup_is_loadable', array( $this, 'maybe_disable_pop_up' ), 10, 2 );

		// One-time migration from the old "automator" trigger key to "auto_open".
		add_action( 'admin_init', array( $this, 'migrate_popup_maker_to_new_methods' ) );
	}

	/**
	 * Check whether Popup Maker is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'Popup_Maker' );
	}

	/**
	 * Register Automator's "auto_open" trigger inside Popup Maker.
	 *
	 * @param array $triggers Existing Popup Maker triggers.
	 *
	 * @return array
	 */
	public function register_popup_maker_trigger( $triggers ) {

		$triggers['auto_open']['settings_column']             = sprintf(
			'<strong>%1$s</strong>: %2$s<br/><strong>%3$s</strong>: %4$s',
			esc_html_x( 'Recipes', 'Popup Maker', 'uncanny-automator' ),
			'{{data.recipe}}',
			esc_html_x( 'Delay', 'Popup Maker', 'uncanny-automator' ),
			'{{data.delay}}'
		);
		$triggers['auto_open']['fields']['general']['recipe'] = array(
			'label'     => esc_html_x( 'Recipe', 'Popup Maker', 'uncanny-automator' ),
			'type'      => 'postselect',
			'post_type' => 'uo-recipe',
			'multiple'  => true,
			'as_array'  => true,
			'std'       => array(),
		);

		return $triggers;
	}

	/**
	 * Disable a popup unless its bound Automator action has fired for this user.
	 *
	 * @param bool $loadable Whether the popup is currently loadable.
	 * @param int  $pop_id   The popup post ID.
	 *
	 * @return bool
	 */
	public function maybe_disable_pop_up( $loadable, $pop_id ) {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$popup_settings = $wpdb->get_results( "SELECT post_id, meta_value as settings FROM $wpdb->postmeta WHERE meta_key = 'popup_settings'" );

		// All recipes that have popup maker triggers.
		$recipes_enabled_in_popups = array();

		foreach ( $popup_settings as $popup ) {

			$popup_id       = $popup->post_id;
			$popup_settings = maybe_unserialize( $popup->settings );

			if ( isset( $popup_settings['triggers'] ) ) {
				foreach ( $popup_settings['triggers'] as $trigger ) {
					if ( 'auto_open' === (string) $trigger['type'] ) {
						if ( isset( $trigger['settings'] ) && isset( $trigger['settings']['recipe'] ) ) {
							foreach ( $trigger['settings']['recipe'] as $recipe_id ) {
								$recipes_enabled_in_popups[ $popup_id ][] = absint( $recipe_id );
							}
						}
					}
				}
			}
		}

		$automator_popups = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT post_parent FROM $wpdb->posts WHERE ID in (SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'POPUPID')"
		);

		// Is the popup restricted by Automator action completion?
		if ( ! isset( $recipes_enabled_in_popups[ $pop_id ] ) || empty( array_intersect( $recipes_enabled_in_popups[ $pop_id ], $automator_popups ) ) ) {
			return $loadable;
		}

		$is_action_popup_ids_enabled = array();
		$user_id                     = get_current_user_id();

		if ( 0 !== $user_id ) {
			$is_action_popup_ids_enabled = get_user_meta( $user_id, 'display_pop_up_' . $pop_id, false );
		} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$md5                         = md5( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) );
			$is_action_popup_ids_enabled = automator_get_option( 'automator_display_popup_' . $md5, array() );
			$is_action_popup_ids_enabled = array( $is_action_popup_ids_enabled );
		}

		// If this action was completed then a meta value was stored for this popup.
		if ( is_array( $is_action_popup_ids_enabled ) && in_array( (string) $pop_id, $is_action_popup_ids_enabled, true ) ) {
			// Delete the user meta so further recipes can run.
			if ( 0 !== $user_id ) {
				delete_user_meta( $user_id, 'display_pop_up_' . $pop_id );
			} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
				$md5 = md5( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) );
				automator_delete_option( 'automator_display_popup_' . $md5 );
			}

			return true;
		}

		return false;
	}

	/**
	 * One-time migration: convert old "automator" trigger entries to "auto_open".
	 *
	 * Idempotent — gated by the `automator_popup_maker_migrated` option.
	 *
	 * @return void
	 */
	public function migrate_popup_maker_to_new_methods() {

		if ( 'yes' === automator_get_option( 'automator_popup_maker_migrated', 'no' ) ) {
			return;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$popup_settings = $wpdb->get_results( "SELECT post_id, meta_value as settings FROM $wpdb->postmeta WHERE meta_key = 'popup_settings'" );

		if ( empty( $popup_settings ) ) {
			automator_update_option( 'automator_popup_maker_migrated', 'yes', true );

			return;
		}

		foreach ( $popup_settings as $popup ) {

			$popup_id = $popup->post_id;
			$settings = maybe_unserialize( $popup->settings );

			if ( ! isset( $settings['triggers'] ) ) {
				continue;
			}

			foreach ( $settings['triggers'] as $k => $trigger ) {
				if ( 'automator' !== $trigger['type'] ) {
					continue;
				}
				if ( ! isset( $trigger['settings']['recipe'] ) ) {
					continue;
				}
				if ( empty( $trigger['settings']['recipe'] ) ) {
					continue;
				}

				$recipes = $trigger['settings']['recipe'];

				foreach ( $recipes as $recipe ) {
					$settings['triggers'][] = array(
						'type'     => 'auto_open',
						'settings' => array(
							'delay'  => '500',
							'recipe' => array( $recipe ),
						),
					);
				}

				unset( $settings['triggers'][ $k ] );
				update_post_meta( $popup_id, 'popup_settings', $settings );
			}
		}

		automator_update_option( 'automator_popup_maker_migrated', 'yes', true );
	}
}
