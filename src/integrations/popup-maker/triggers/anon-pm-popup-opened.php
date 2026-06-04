<?php

namespace Uncanny_Automator\Integrations\Popup_Maker;

use Uncanny_Automator\Recipe\Trigger;
use Uncanny_Automator\Recipe\Trigger_Definition;

/**
 * Class ANON_PM_POPUP_OPENED
 *
 * Fires when a popup is opened (anonymous — popups display to logged-out
 * visitors too). Hook: `pum_analytics_open` — fired dynamically as
 * `do_action( 'pum_analytics_' . $event )` where `$event === 'open'`
 * inside `PUM_Analytics::track()`.
 *
 * @property Popup_Maker_Helpers $item_helpers
 *
 * @package Uncanny_Automator\Integrations\Popup_Maker
 */
class ANON_PM_POPUP_OPENED extends Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'ANON_PM_POPUP_OPENED', 'PM' )
			->trigger_meta( 'PM_POPUP' )
			->trigger_type( 'anonymous' )
			->hook( 'pum_analytics_open', 10, 2 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_is_login_required( false );

		// translators: 1: Popup
		$this->set_sentence( sprintf( esc_html_x( '{{A popup:%1$s}} is opened', 'Popup Maker', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_html_x( '{{A popup}} is opened', 'Popup Maker', 'uncanny-automator' ) );
	}

	/**
	 * Trigger options.
	 *
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Popup', 'Popup Maker', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'popups_any' ),
			),
		);
	}

	/**
	 * Define trigger tokens.
	 *
	 * @param array $trigger The trigger settings.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array(
			array(
				'tokenId'   => 'POPUP_ID',
				'tokenName' => esc_html_x( 'Popup ID', 'Popup Maker', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'POPUP_TITLE',
				'tokenName' => esc_html_x( 'Popup title', 'Popup Maker', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POPUP_OPEN_COUNT',
				'tokenName' => esc_html_x( 'Popup open count', 'Popup Maker', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'POPUP_CONVERSION_COUNT',
				'tokenName' => esc_html_x( 'Popup conversion count', 'Popup Maker', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'POPUP_EDIT_URL',
				'tokenName' => esc_html_x( 'Popup edit URL', 'Popup Maker', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'EVENT_METHOD',
				'tokenName' => esc_html_x( 'Event method', 'Popup Maker', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Validate whether the trigger should fire.
	 *
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args The arguments from the WP hook.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		if ( ! isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ) {
			return false;
		}

		if ( ! isset( $hook_args[0] ) ) {
			return false;
		}

		$selected_popup_id = (int) $trigger['meta'][ $this->get_trigger_meta() ];
		$fired_popup_id    = (int) $hook_args[0];

		// "Any popup".
		if ( -1 === $selected_popup_id ) {
			return true;
		}

		return $fired_popup_id === $selected_popup_id;
	}

	/**
	 * Hydrate token values from hook arguments.
	 *
	 * @param array $trigger   The completed trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$popup_id = isset( $hook_args[0] ) ? (int) $hook_args[0] : 0;
		$args     = isset( $hook_args[1] ) && is_array( $hook_args[1] ) ? $hook_args[1] : array();

		$tokens = $this->item_helpers->get_popup_token_values( $popup_id );

		$event_method = isset( $args['method'] ) ? (string) $args['method'] : '';

		return array(
			$this->get_trigger_meta()  => $tokens['POPUP_TITLE'],
			'POPUP_ID'                 => $tokens['POPUP_ID'],
			'POPUP_TITLE'              => $tokens['POPUP_TITLE'],
			'POPUP_OPEN_COUNT'         => $tokens['POPUP_OPEN_COUNT'],
			'POPUP_CONVERSION_COUNT'   => $tokens['POPUP_CONVERSION_COUNT'],
			'POPUP_EDIT_URL'           => $tokens['POPUP_EDIT_URL'],
			'EVENT_METHOD'             => $event_method,
		);
	}
}
