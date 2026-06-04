<?php

namespace Uncanny_Automator\Integrations\Popup_Maker;

use Uncanny_Automator\Recipe\Action;

/**
 * Class PM_POPUPSHOW
 *
 * @package Uncanny_Automator\Integrations\Popup_Maker
 *
 * @property Popup_Maker_Helpers $item_helpers
 */
class PM_POPUPSHOW extends Action {

	/**
	 * Set up the action.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'PM' );
		$this->set_action_code( 'POPUPSHOW' );
		$this->set_action_meta( 'POPUPID' );
		$this->set_requires_user( false );
		$this->set_support_link(
			\Automator()->get_author_support_link( 'POPUPSHOW', 'knowledge-base/working-with-popup-maker-actions' )
		);

		$this->set_sentence(
			sprintf(
				/* translators: Action sentence: Show a popup */
				esc_html_x( 'Show {{a popup:%1$s}}', 'Popup Maker', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'Show {{a popup}}', 'Popup Maker', 'uncanny-automator' )
		);

		// Keep the popup_settings.triggers entry in sync whenever the user saves this action.
		add_filter( 'automator_option_updated', array( $this, 'sync_popup_recipe_trigger' ), 10, 4 );
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'option_code'              => $this->get_action_meta(),
				'label'                    => esc_html_x( 'Popup', 'Popup Maker', 'uncanny-automator' ),
				'input_type'               => 'select',
				'required'                 => true,
				'options'                  => array(),
				'supports_custom_value'    => true,
				'custom_value_description' => esc_html_x( 'Popup ID', 'Popup Maker', 'uncanny-automator' ),
				'remote_data'              => $this->item_helpers->remote_data_load_config( 'popups' ),
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
	 * @return bool|null
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$popup_id = absint( $parsed[ $this->get_action_meta() ] ?? 0 );
		$popup    = get_post( $popup_id );

		if ( ! $popup instanceof \WP_Post ) {
			$this->add_log_error(
				sprintf(
					'%s: %d',
					esc_html_x( 'The popup no longer exists. Popup ID', 'Popup Maker', 'uncanny-automator' ),
					$popup_id
				)
			);
			return false;
		}

		$settings = get_post_meta( $popup->ID, 'popup_settings', true );

		if ( empty( $settings ) || ! isset( $settings['triggers'] ) ) {
			$this->add_log_error(
				sprintf(
					'%s: %d',
					esc_html_x( 'No settings found with this popup. Popup ID', 'Popup Maker', 'uncanny-automator' ),
					$popup_id
				)
			);
			return false;
		}

		$found         = false;
		$found_recipes = array();

		foreach ( $settings['triggers'] as $_trigger ) {
			if ( 'auto_open' !== $_trigger['type'] ) {
				continue;
			}
			if ( ! isset( $_trigger['settings']['recipe'] ) ) {
				continue;
			}
			if ( empty( $_trigger['settings']['recipe'] ) ) {
				continue;
			}
			$found         = true;
			$found_recipes = array_merge( $found_recipes, $_trigger['settings']['recipe'] );
		}

		if ( false === $found || empty( $found_recipes ) ) {
			$this->add_log_error(
				sprintf(
					'%s: %d',
					esc_html_x( 'Recipes are not set for this popup. Popup ID', 'Popup Maker', 'uncanny-automator' ),
					$popup_id
				)
			);
			return false;
		}

		if ( 'publish' !== $popup->post_status || 0 === absint( get_post_meta( $popup_id, 'enabled', true ) ) ) {
			$this->add_log_error(
				sprintf(
					'%s: %d',
					esc_html_x( 'The popup is no longer active. Popup ID', 'Popup Maker', 'uncanny-automator' ),
					$popup_id
				)
			);
			return false;
		}

		$popup_recipes = array_map( 'absint', $found_recipes );

		if ( ! in_array( absint( $recipe_id ), $popup_recipes, true ) ) {
			$this->add_log_error(
				sprintf(
					'%s: %d',
					esc_html_x( 'The recipe is not linked with this popup. Popup ID', 'Popup Maker', 'uncanny-automator' ),
					$popup_id
				)
			);
			return false;
		}

		if ( 0 !== (int) $user_id ) {
			update_user_meta( $user_id, 'display_pop_up_' . $popup_id, $popup_id );
			return true;
		}

		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$md5 = md5( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) );
			automator_update_option( 'automator_display_popup_' . $md5, $popup_id );
			return true;
		}

		$this->add_log_error(
			sprintf(
				'%s: %d',
				esc_html_x( 'The popup failed to display. Popup ID', 'Popup Maker', 'uncanny-automator' ),
				$popup_id
			)
		);
		return false;
	}

	/**
	 * Keep the chosen popup's `popup_settings.triggers` in sync when the user
	 * saves this action's options. Adds an `auto_open` trigger entry linking the
	 * popup to the parent recipe, if one isn't already present.
	 *
	 * @param mixed  $return     The pass-through value of the filter.
	 * @param mixed  $item       The post object the option was saved against.
	 * @param string $meta_key   The post meta key being updated.
	 * @param mixed  $meta_value The new meta value.
	 *
	 * @return mixed
	 */
	public function sync_popup_recipe_trigger( $return, $item, $meta_key, $meta_value ) {

		if ( ! isset( $item->post_type ) ) {
			return $return;
		}

		if ( 'uo-action' !== $item->post_type ) {
			return $return;
		}

		if ( is_array( $meta_value ) && ! array_key_exists( 'POPUPID', $meta_value ) ) {
			return $return;
		}

		$pop_id = is_array( $meta_value ) ? $meta_value['POPUPID'] : $meta_value;

		$popup = get_post( $pop_id );
		if ( ! $popup instanceof \WP_Post ) {
			return $return;
		}

		$settings = get_post_meta( $pop_id, 'popup_settings', true );
		$found_it = false;

		if ( isset( $settings['triggers'] ) ) {
			foreach ( $settings['triggers'] as $trigger ) {
				if ( 'auto_open' !== $trigger['type'] ) {
					continue;
				}
				if ( ! isset( $trigger['settings']['recipe'] ) ) {
					continue;
				}
				if ( empty( $trigger['settings']['recipe'] ) ) {
					continue;
				}
				$popup_recipes = array_map( 'absint', $trigger['settings']['recipe'] );
				if ( ! in_array( absint( $item->post_parent ), $popup_recipes, true ) ) {
					continue;
				}
				$found_it = true;
			}
		}

		if ( true === $found_it ) {
			return $return;
		}

		$settings['triggers'][] = array(
			'type'     => 'auto_open',
			'settings' => array(
				'delay'  => '500',
				'recipe' => array( $item->post_parent ),
			),
		);

		update_post_meta( $pop_id, 'popup_settings', $settings );

		return $return;
	}
}
