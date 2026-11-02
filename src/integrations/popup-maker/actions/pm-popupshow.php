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

		// Link the recipe in the popup when this action is added (a copy's included) or its popup changes.
		add_action( 'automator_event_action_created', array( $this, 'sync_popup_from_saved_action' ) );
		add_action( 'automator_event_action_fields_changed', array( $this, 'sync_popup_from_saved_action' ) );
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
	 * Link the saved action's recipe in its popup's auto_open trigger.
	 *
	 * Listens to `automator_event_action_created` (an added action, a copied recipe's
	 * included) and to `automator_event_action_fields_changed` when `POPUPID` is among
	 * the changed fields, so it runs for builder, MCP and automator_*() saves alike,
	 * on requests that load this integration. It links the payload's recipe, not the
	 * action's parent: an action inside a block has the block as its parent.
	 *
	 * @param object $change The action_created or action_fields_changed payload.
	 *
	 * @return void
	 */
	public function sync_popup_from_saved_action( $change ) {
		$payload = $change->to_array();
		if ( isset( $payload['fields'] ) && ! in_array( 'POPUPID', (array) $payload['fields'], true ) ) {
			return;
		}

		$action_id = (int) $payload['item_id'];
		if ( 'POPUPSHOW' !== get_post_meta( $action_id, 'code', true ) ) {
			return;
		}

		// get_post( 0 ) is the global post, so an empty POPUPID must not reach it.
		$pop_id = absint( get_post_meta( $action_id, 'POPUPID', true ) );
		$popup  = 0 < $pop_id ? get_post( $pop_id ) : null;
		if ( ! $popup instanceof \WP_Post || 'popup' !== $popup->post_type ) {
			return;
		}

		$settings = get_post_meta( $pop_id, 'popup_settings', true );
		if ( ! is_array( $settings ) ) {
			return;
		}

		$recipe_id = (int) $payload['recipe_id'];

		foreach ( (array) ( $settings['triggers'] ?? array() ) as $trigger ) {
			if ( 'auto_open' !== ( $trigger['type'] ?? '' ) || empty( $trigger['settings']['recipe'] ) ) {
				continue;
			}
			if ( in_array( $recipe_id, array_map( 'absint', (array) $trigger['settings']['recipe'] ), true ) ) {
				return;
			}
		}

		$settings['triggers'][] = array(
			'type'     => 'auto_open',
			'settings' => array(
				'delay'  => '500',
				'recipe' => array( $recipe_id ),
			),
		);

		update_post_meta( $pop_id, 'popup_settings', $settings );
	}
}
