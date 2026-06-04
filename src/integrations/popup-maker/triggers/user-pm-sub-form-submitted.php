<?php

namespace Uncanny_Automator\Integrations\Popup_Maker;

use Uncanny_Automator\Recipe\Trigger;
use Uncanny_Automator\Recipe\Trigger_Definition;

/**
 * Class USER_PM_SUB_FORM_SUBMITTED
 *
 * Fires when a logged-in user submits the Popup Maker built-in subscription
 * form inside a popup. Hook: `pum_sub_form_success`. `$values['user_id']` is
 * unreliable (scope Gotcha #4) — resolve the submitter via
 * `get_current_user_id()`.
 *
 * @property Popup_Maker_Helpers $item_helpers
 *
 * @package Uncanny_Automator\Integrations\Popup_Maker
 */
class USER_PM_SUB_FORM_SUBMITTED extends Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'USER_PM_SUB_FORM_SUBMITTED', 'PM' )
			->trigger_meta( 'PM_POPUP_SUB' )
			->hook( 'pum_sub_form_success', 10, 1 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_is_login_required( false );

		// translators: 1: Popup
		$this->set_sentence( sprintf( esc_html_x( 'A user submits a newsletter form in {{a popup:%1$s}}', 'Popup Maker', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'A user submits a newsletter form in {{a popup}}', 'Popup Maker', 'uncanny-automator' ) );
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
			array(
				'option_code'     => 'PM_SUB_PROVIDER',
				'label'           => esc_html_x( 'Newsletter provider', 'Popup Maker', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'newsletter_providers' ),
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
				'tokenId'   => 'POPUP_EDIT_URL',
				'tokenName' => esc_html_x( 'Popup edit URL', 'Popup Maker', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'SUBSCRIBER_EMAIL',
				'tokenName' => esc_html_x( 'Subscriber email', 'Popup Maker', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'SUBSCRIBER_FIRST_NAME',
				'tokenName' => esc_html_x( 'Subscriber first name', 'Popup Maker', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'SUBSCRIBER_LAST_NAME',
				'tokenName' => esc_html_x( 'Subscriber last name', 'Popup Maker', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'SUBSCRIBER_NAME',
				'tokenName' => esc_html_x( 'Subscriber full name', 'Popup Maker', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'SUBSCRIBER_PROVIDER',
				'tokenName' => esc_html_x( 'Subscriber provider key', 'Popup Maker', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'SUBSCRIBER_PROVIDER_LABEL',
				'tokenName' => esc_html_x( 'Subscriber provider label', 'Popup Maker', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'SUBSCRIBER_CONSENT',
				'tokenName' => esc_html_x( 'Subscriber consent', 'Popup Maker', 'uncanny-automator' ),
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

		if ( ! isset( $hook_args[0] ) || ! is_array( $hook_args[0] ) ) {
			return false;
		}

		// User-context half: bail when no logged-in user (4b handles anon).
		if ( 0 === get_current_user_id() ) {
			return false;
		}

		$values = $hook_args[0];

		$selected_popup    = isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ? (int) $trigger['meta'][ $this->get_trigger_meta() ] : 0;
		$selected_provider = isset( $trigger['meta']['PM_SUB_PROVIDER'] ) ? (string) $trigger['meta']['PM_SUB_PROVIDER'] : '-1';

		$fired_popup_id = isset( $values['popup_id'] ) ? (int) $values['popup_id'] : 0;
		$fired_provider = isset( $values['provider'] ) ? (string) $values['provider'] : '';

		if ( 0 === $fired_popup_id ) {
			return false;
		}

		if ( -1 !== $selected_popup && $fired_popup_id !== $selected_popup ) {
			return false;
		}

		if ( '-1' !== $selected_provider && $fired_provider !== $selected_provider ) {
			return false;
		}

		$this->set_user_id( get_current_user_id() );

		return true;
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

		$values = isset( $hook_args[0] ) && is_array( $hook_args[0] ) ? $hook_args[0] : array();

		$popup_id     = isset( $values['popup_id'] ) ? (int) $values['popup_id'] : 0;
		$provider_key = isset( $values['provider'] ) ? (string) $values['provider'] : '';

		$popup_tokens = $this->item_helpers->get_popup_token_values( $popup_id );

		$fname = isset( $values['fname'] ) ? (string) $values['fname'] : '';
		$lname = isset( $values['lname'] ) ? (string) $values['lname'] : '';
		$full  = isset( $values['name'] ) ? (string) $values['name'] : trim( $fname . ' ' . $lname );

		return array(
			$this->get_trigger_meta()    => $popup_tokens['POPUP_TITLE'],
			'PM_SUB_PROVIDER'            => $this->item_helpers->get_newsletter_provider_label( $provider_key ),
			'POPUP_ID'                   => $popup_tokens['POPUP_ID'],
			'POPUP_TITLE'                => $popup_tokens['POPUP_TITLE'],
			'POPUP_EDIT_URL'             => $popup_tokens['POPUP_EDIT_URL'],
			'SUBSCRIBER_EMAIL'           => isset( $values['email'] ) ? sanitize_email( (string) $values['email'] ) : '',
			'SUBSCRIBER_FIRST_NAME'      => $fname,
			'SUBSCRIBER_LAST_NAME'       => $lname,
			'SUBSCRIBER_NAME'            => $full,
			'SUBSCRIBER_PROVIDER'        => $provider_key,
			'SUBSCRIBER_PROVIDER_LABEL'  => $this->item_helpers->get_newsletter_provider_label( $provider_key ),
			'SUBSCRIBER_CONSENT'         => isset( $values['consent'] ) ? (string) $values['consent'] : '',
		);
	}
}
